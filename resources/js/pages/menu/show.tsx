import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Loader2 } from 'lucide-react';
import { motion } from 'motion/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { AllergenList } from '@/components/brand/allergen-list';
import { ArchFrame } from '@/components/brand/arch-frame';
import { DietMarks, SpiceMeter } from '@/components/brand/diet';
import { Picture } from '@/components/brand/picture';
import { Price } from '@/components/brand/price';
import { QuantityStepper } from '@/components/brand/quantity-stepper';
import { StickyActionBar } from '@/components/brand/sticky-action-bar';
import { useOnline } from '@/components/brand/states';
import {
    FieldError,
    ModifierGroupField,
    NotesField,
    VariantChooser,
} from '@/components/menu-page/dish-options';
import { MENU_QUERY_STORAGE_KEY } from '@/components/menu-page/filters';
import { DishRow } from '@/components/menu/dish-row';
import { SteamWisp } from '@/components/motion/served';
import { Button } from '@/components/ui/button';
import { useAddToBasket } from '@/lib/basket';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import type { MenuItem } from '@/types';

type Props = {
    item: MenuItem;
    category: { name: string; slug: string };
    related: MenuItem[];
};

const MAX_QUANTITY = 20;

function useMenuBackHref(): string {
    const [href, setHref] = useState('/menu');

    useEffect(() => {
        try {
            const stored =
                window.sessionStorage.getItem(MENU_QUERY_STORAGE_KEY) ?? '';

            setHref(`/menu${stored}`);
        } catch {
            setHref('/menu');
        }
    }, []);

    return href;
}

export default function MenuShow({ item, category, related }: Props) {
    const t = useT();
    const online = useOnline();
    const { add, pending } = useAddToBasket();
    const { errors } = usePage().props as { errors?: Record<string, string> };
    const backHref = useMenuBackHref();
    const button = useRef<HTMLButtonElement>(null);

    const variants = item.variants ?? [];
    const groups = item.modifier_groups ?? [];
    const soldOut = !item.is_available;

    const [variantId, setVariantId] = useState<number | null>(
        variants.length === 1 ? (variants[0]?.id ?? null) : null,
    );
    const [selected, setSelected] = useState<number[]>([]);
    const [notes, setNotes] = useState('');
    const [quantity, setQuantity] = useState(1);
    const [attempted, setAttempted] = useState(false);

    const variant = variants.find((candidate) => candidate.id === variantId);
    const unit = useMemo(() => {
        const base = variant?.price ?? item.price;
        const extras = groups
            .flatMap((group) => group.modifiers)
            .filter((modifier) => selected.includes(modifier.id))
            .reduce((sum, modifier) => sum + modifier.price, 0);

        return base + extras;
    }, [variants.length, variant, item.price, groups, selected]);
    const total = unit * quantity;
    const totalText = formatMoney(total, { compact: false });

    const groupErrors = useMemo(() => {
        const found: Record<number, string> = {};

        for (const group of groups) {
            const chosen = group.modifiers.filter((modifier) =>
                selected.includes(modifier.id),
            ).length;

            if (chosen < group.min || chosen > group.max) {
                found[group.id] = t('menu.dish.group_limits', {
                    group: group.name,
                    min: group.min,
                    max: group.max,
                });
            }
        }

        return found;
    }, [groups, selected, t]);

    const variantMissing = variants.length > 0 && variantId === null;
    const hasClientErrors =
        variantMissing || Object.keys(groupErrors).length > 0;
    const showClientErrors = attempted && hasClientErrors;
    const serverMessages = Object.values(errors ?? {});

    const toggle = (modifierId: number, checked: boolean) =>
        setSelected((current) =>
            checked
                ? [...current, modifierId]
                : current.filter((id) => id !== modifierId),
        );

    const pick = (groupId: number, modifierId: number) =>
        setSelected((current) => {
            const group = groups.find((candidate) => candidate.id === groupId);
            const others = current.filter(
                (id) =>
                    !group?.modifiers.some((modifier) => modifier.id === id),
            );

            return [...others, modifierId];
        });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (soldOut || !online) {
            return;
        }

        setAttempted(true);

        if (hasClientErrors) {
            const firstId = variantMissing
                ? 'field-variant'
                : `field-group-${Object.keys(groupErrors)[0]}`;

            document
                .getElementById(firstId)
                ?.scrollIntoView({ block: 'center', behavior: 'smooth' });

            return;
        }

        add(
            {
                menu_item_id: item.id,
                item_variant_id: variantId,
                modifier_ids: selected,
                quantity,
                notes: notes.trim() === '' ? null : notes.trim(),
            },
            button.current,
        );
    };

    const busy = pending === item.id;
    const blocked = soldOut || !online;

    return (
        <>
            <Head title={item.name}>
                <meta
                    head-key="description"
                    name="description"
                    content={item.description}
                />
            </Head>

            <div className="container-page pt-4 pb-40 md:pt-8 md:pb-16">
                <Link
                    href={backHref}
                    className="-ml-2 inline-flex min-h-11 items-center gap-2 rounded-md px-2 font-semibold text-ink-muted outline-none hover:text-ink focus-visible:outline-2 focus-visible:outline-ember"
                >
                    <ArrowLeft className="size-4" aria-hidden="true" />
                    {t('menu.dish.back')}
                </Link>

                <div className="mt-4 grid gap-8 md:grid-cols-[minmax(0,20rem)_1fr] md:gap-12 lg:grid-cols-[minmax(0,28rem)_1fr] lg:gap-20">
                    <div className="mx-auto w-4/5 max-w-xs self-start md:sticky md:top-28 md:w-full md:max-w-none">
                        <motion.div
                            layoutId={`dish-photo-${item.slug}`}
                            className="relative"
                        >
                            <ArchFrame ratio="portrait">
                                <Picture
                                    mediaKey={item.image}
                                    alt={t('menu.dish.photo_alt', {
                                        item: item.name,
                                    })}
                                    sizes="(min-width: 1024px) 28rem, (min-width: 768px) 20rem, 80vw"
                                    priority
                                />
                            </ArchFrame>
                            {item.is_hot && !soldOut && (
                                <SteamWisp className="absolute top-6 left-1/2 -translate-x-1/2" />
                            )}
                            {item.is_special && (
                                <span className="absolute top-[18%] left-3 rounded-sm bg-turmeric-soft px-2 py-1 text-label text-turmeric">
                                    {t('ui.special')}
                                </span>
                            )}
                        </motion.div>
                    </div>

                    <form
                        onSubmit={submit}
                        noValidate
                        className="flex min-w-0 flex-col gap-8"
                        aria-labelledby="dish-name"
                    >
                        <header className="flex flex-col gap-4">
                            <h1
                                id="dish-name"
                                className="font-display text-display-lg text-ink"
                            >
                                {item.name}
                            </h1>
                            <p className="max-w-[60ch] text-body-lg text-ink-muted">
                                {item.description}
                            </p>
                            <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                                {variants.length > 1 ? (
                                    <span className="text-title font-semibold tabular-nums">
                                        {t('ui.from_price', {
                                            price: formatMoney(item.price, {
                                                compact: true,
                                            }),
                                        })}
                                    </span>
                                ) : (
                                    <Price
                                        pence={variants[0]?.price ?? item.price}
                                        className="text-title"
                                    />
                                )}
                                <span className="text-ink-muted tabular-nums">
                                    {t('ui.kcal', { kcal: item.kcal })}
                                </span>
                                <DietMarks
                                    vegetarian={item.is_vegetarian}
                                    vegan={item.is_vegan}
                                />
                                <SpiceMeter
                                    level={item.spice_level}
                                    showLabel
                                />
                            </div>
                            <p className="text-sm text-ink-muted">
                                {t('menu.dish.vat_included')}
                            </p>
                        </header>

                        <section
                            aria-labelledby="dish-allergens"
                            className="flex flex-col gap-3 rounded-lg border border-line bg-surface p-4"
                        >
                            <h2
                                id="dish-allergens"
                                className="text-label text-ink-muted"
                            >
                                {t('menu.dish.allergens')}
                            </h2>
                            <AllergenList
                                allergens={item.allergens}
                                optional={item.option_allergens}
                            />
                            <p className="text-sm text-ink-muted">
                                {t('menu.dish.allergen_note')}
                            </p>
                        </section>

                        {soldOut && (
                            <div
                                role="status"
                                className="flex flex-col gap-1 rounded-lg bg-chilli-soft p-4"
                            >
                                <p className="font-semibold text-chilli">
                                    {t('menu.dish.sold_out')}
                                </p>
                                <p className="text-sm text-ink">
                                    {t('menu.dish.sold_out_note')}
                                </p>
                            </div>
                        )}

                        <fieldset
                            disabled={soldOut}
                            className="flex min-w-0 flex-col gap-8 disabled:opacity-60"
                        >
                            {variants.length > 0 && (
                                <VariantChooser
                                    variants={variants}
                                    value={variantId}
                                    onChange={setVariantId}
                                    error={
                                        (showClientErrors && variantMissing
                                            ? t('cart.choose_size')
                                            : undefined) ?? errors?.variant
                                    }
                                />
                            )}

                            {groups.map((group) => (
                                <ModifierGroupField
                                    key={group.id}
                                    group={group}
                                    selected={selected}
                                    onToggle={toggle}
                                    onPick={(id) => pick(group.id, id)}
                                    error={
                                        attempted
                                            ? groupErrors[group.id]
                                            : undefined
                                    }
                                />
                            ))}

                            <NotesField
                                value={notes}
                                onChange={setNotes}
                                error={errors?.notes}
                            />
                        </fieldset>

                        {serverMessages.length > 0 && (
                            <div
                                role="alert"
                                className="flex flex-col gap-1 rounded-lg border border-chilli/40 bg-chilli-soft p-4"
                            >
                                <p className="font-semibold">
                                    {t('menu.dish.error')}
                                </p>
                                {serverMessages.map((message) => (
                                    <FieldError
                                        key={message}
                                        id={`server-${message.length}`}
                                        message={message}
                                    />
                                ))}
                            </div>
                        )}

                        {!online && !soldOut && (
                            <p role="status" className="text-sm font-medium">
                                {t('menu.dish.offline')}
                            </p>
                        )}

                        <StickyActionBar>
                            <div className="mx-auto flex max-w-xl items-center gap-3 md:mx-0">
                                <QuantityStepper
                                    value={quantity}
                                    onChange={setQuantity}
                                    max={MAX_QUANTITY}
                                    itemName={item.name}
                                    disabled={blocked}
                                />
                                <Button
                                    ref={button}
                                    type="submit"
                                    size="lg"
                                    disabled={blocked || busy || variantMissing}
                                    className="h-12 flex-1"
                                >
                                    {busy && (
                                        <Loader2
                                            className="animate-spin"
                                            aria-hidden="true"
                                        />
                                    )}
                                    {soldOut
                                        ? t('menu.dish.sold_out')
                                        : variantMissing
                                          ? t('menu.dish.choose_size')
                                          : busy
                                            ? t('menu.dish.adding')
                                            : t('menu.dish.add_total', {
                                                  price: totalText,
                                              })}
                                </Button>
                            </div>
                            <p className="sr-only" aria-live="polite">
                                {variantMissing
                                    ? t('menu.dish.choose_size')
                                    : t('menu.dish.total', {
                                          price: totalText,
                                      })}
                            </p>
                        </StickyActionBar>
                    </form>
                </div>

                {related.length > 0 && (
                    <section
                        aria-labelledby="related-heading"
                        className="mt-16 max-w-3xl"
                    >
                        <h2
                            id="related-heading"
                            className="font-display text-display-md text-ink"
                        >
                            {t('menu.dish.more_from', {
                                category: category.name,
                            })}
                        </h2>
                        <ul className="mt-2 divide-y divide-line">
                            {related.map((other) => (
                                <li key={other.id}>
                                    <DishRow
                                        item={other}
                                        href={`/menu/${other.slug}`}
                                    />
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}
