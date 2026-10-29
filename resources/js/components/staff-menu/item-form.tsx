import { Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Lock } from 'lucide-react';
import { useEffect, useMemo, useRef } from 'react';
import { toast } from 'sonner';
import { useOnline } from '@/components/brand/states';
import { Field } from '@/components/form/field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { AllergenPicker } from './allergen-picker';
import { initialData, toPayload } from './form-state';
import type { ItemFormData } from './form-state';
import { GroupsEditor, PoundsInput, VariantsEditor } from './list-editors';
import { PhotoField } from './photo-field';
import { PriceHistory } from './price-history';
import { toPence } from './pounds';
import type { EditProps, Period } from './types';

function Section({
    id,
    title,
    children,
    className,
}: {
    id: string;
    title: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <section
            aria-labelledby={id}
            className={cn(
                'grid gap-4 rounded-lg border border-line bg-surface p-4 sm:p-5',
                className,
            )}
        >
            <h2 id={id} className="font-display text-xl">
                {title}
            </h2>
            {children}
        </section>
    );
}

function Toggle({
    name,
    label,
    hint,
    checked,
    disabled,
    onChange,
}: {
    name: string;
    label: string;
    hint?: string;
    checked: boolean;
    disabled?: boolean;
    onChange: (next: boolean) => void;
}) {
    return (
        <div className="flex min-h-11 items-center justify-between gap-4">
            <div>
                <Label htmlFor={`field-${name}`}>{label}</Label>
                {hint && <p className="text-sm text-ink-muted">{hint}</p>}
            </div>
            <Switch
                id={`field-${name}`}
                checked={checked}
                disabled={disabled}
                onCheckedChange={onChange}
            />
        </div>
    );
}

export function ItemForm({
    props,
    onSaved,
}: {
    props: EditProps;
    onSaved: () => void;
}) {
    const {
        item,
        history,
        categories,
        stations,
        periods,
        default_vat: defaultVat,
        can_change_vat: canChangeVat,
    } = props;
    const t = useT();
    const online = useOnline();
    const form = useForm<ItemFormData>(
        initialData(item, {
            vat: defaultVat,
            category: props.default_category,
            firstStation: stations[0]?.value ?? 'curry',
        }),
    );
    const { data, setData, errors, processing, isDirty } = form;
    const slugTouched = useRef(item !== null);
    const submitting = useRef(false);
    const shownErrors = errors as Record<string, string | undefined>;
    const categoryName = useMemo(
        () => categories.find((c) => String(c.id) === data.category_id)?.name,
        [categories, data.category_id],
    );

    useEffect(() => {
        const off = router.on('before', (event) => {
            if (
                isDirty &&
                !submitting.current &&
                !window.confirm(t('staff_menu.editor.leave'))
            ) {
                event.preventDefault();
            }
        });
        const warn = (event: BeforeUnloadEvent) => {
            if (isDirty && !submitting.current) {
                event.preventDefault();
            }
        };
        window.addEventListener('beforeunload', warn);

        return () => {
            off();
            window.removeEventListener('beforeunload', warn);
        };
    }, [isDirty, t]);

    const rename = (name: string) => {
        setData((current) => ({
            ...current,
            name,
            slug: slugTouched.current ? current.slug : slugify(name),
        }));
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (toPence(data.price) === null) {
            form.setError('price', t('staff_menu.editor.price_error'));
            document.getElementById('field-price')?.focus();

            return;
        }

        form.transform(
            () =>
                (item
                    ? { ...toPayload(data), _method: 'put' }
                    : toPayload(data)) as never,
        );
        submitting.current = true;

        const options = {
            preserveScroll: true,
            forceFormData: data.photo !== null,
            onSuccess: onSaved,
            onError: (errs: Record<string, string>) => {
                toast.error(t('staff_menu.errors.failed'));
                const first = Object.keys(errs)[0];
                window.setTimeout(
                    () =>
                        document
                            .getElementById(
                                `field-${first.replaceAll('.', '-')}`,
                            )
                            ?.focus(),
                    50,
                );
            },
            onFinish: () => {
                submitting.current = false;
            },
        };

        form.post(
            item ? `/staff/menu/items/${item.id}` : '/staff/menu/items',
            options,
        );
    };

    const togglePeriod = (period: Period, on: boolean) =>
        setData(
            'service_periods',
            on
                ? [...data.service_periods, period]
                : data.service_periods.filter((p) => p !== period),
        );

    const offlineReason = online ? undefined : t('staff_menu.editor.offline');
    const e = (key: string) => t(`staff_menu.editor.${key}`);

    return (
        <form onSubmit={submit} noValidate className="pb-4">
            <div className="grid gap-5 px-4 py-5 md:px-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="grid gap-5">
                    <Section id="s-basics" title={e('sections.basics')}>
                        <Field
                            name="name"
                            label={e('name')}
                            error={errors.name}
                            required
                        >
                            {(p) => (
                                <Input
                                    {...p}
                                    className="h-11"
                                    value={data.name}
                                    maxLength={120}
                                    onChange={(ev) => rename(ev.target.value)}
                                />
                            )}
                        </Field>
                        <Field
                            name="slug"
                            label={e('slug')}
                            hint={e('slug_hint')}
                            error={errors.slug}
                        >
                            {(p) => (
                                <Input
                                    {...p}
                                    className="h-11 font-mono text-sm"
                                    value={data.slug}
                                    onChange={(ev) => {
                                        slugTouched.current = true;
                                        setData('slug', ev.target.value);
                                    }}
                                />
                            )}
                        </Field>
                        <Field
                            name="description"
                            label={e('description')}
                            hint={e('description_hint')}
                            error={errors.description}
                            required
                        >
                            {(p) => (
                                <Textarea
                                    {...p}
                                    rows={3}
                                    value={data.description}
                                    maxLength={1000}
                                    onChange={(ev) =>
                                        setData('description', ev.target.value)
                                    }
                                />
                            )}
                        </Field>
                        <Field
                            name="category_id"
                            label={e('category')}
                            error={errors.category_id}
                            required
                        >
                            {(p) => (
                                <Select
                                    value={data.category_id}
                                    onValueChange={(v) =>
                                        setData('category_id', v)
                                    }
                                >
                                    <SelectTrigger
                                        {...p}
                                        className="h-11 w-full sm:w-72"
                                    >
                                        <SelectValue
                                            placeholder={e('category_pick')}
                                        >
                                            {categoryName}
                                        </SelectValue>
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((c) => (
                                            <SelectItem
                                                key={c.id}
                                                value={String(c.id)}
                                            >
                                                {c.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </Field>
                    </Section>

                    <Section id="s-price" title={e('sections.price')}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                name="price"
                                label={e('price')}
                                hint={e('price_hint')}
                                error={errors.price}
                                required
                            >
                                {(p) => (
                                    <PoundsInput
                                        {...p}
                                        value={data.price}
                                        onChange={(v) => setData('price', v)}
                                    />
                                )}
                            </Field>
                            <Field
                                name="vat_rate"
                                label={e('vat')}
                                hint={
                                    canChangeVat
                                        ? undefined
                                        : e('vat_owner_only')
                                }
                                error={errors.vat_rate}
                            >
                                {(p) => (
                                    <Select
                                        value={String(data.vat_rate)}
                                        disabled={!canChangeVat}
                                        onValueChange={(v) =>
                                            setData('vat_rate', Number(v))
                                        }
                                    >
                                        <SelectTrigger
                                            {...p}
                                            className="h-11 w-full"
                                        >
                                            {!canChangeVat && (
                                                <Lock
                                                    aria-hidden="true"
                                                    className="size-4 text-ink-muted"
                                                />
                                            )}
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="2000">
                                                {e('vat_standard')}
                                            </SelectItem>
                                            <SelectItem value="0">
                                                {e('vat_zero')}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                )}
                            </Field>
                        </div>
                    </Section>

                    <Section id="s-diet" title={e('sections.diet')}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                name="kcal"
                                label={e('kcal')}
                                error={errors.kcal}
                                required
                            >
                                {(p) => (
                                    <Input
                                        {...p}
                                        className="h-11"
                                        inputMode="numeric"
                                        value={data.kcal}
                                        onChange={(ev) =>
                                            setData(
                                                'kcal',
                                                ev.target.value.replace(
                                                    /\D/g,
                                                    '',
                                                ),
                                            )
                                        }
                                    />
                                )}
                            </Field>
                            <fieldset>
                                <legend className="mb-1.5 text-sm font-medium">
                                    {e('spice')}
                                </legend>
                                <RadioGroup
                                    className="grid grid-cols-2 gap-2 sm:grid-cols-4"
                                    value={String(data.spice_level)}
                                    onValueChange={(v) =>
                                        setData('spice_level', Number(v))
                                    }
                                >
                                    {[0, 1, 2, 3].map((level) => (
                                        <Label
                                            key={level}
                                            htmlFor={`spice-${level}`}
                                            className={cn(
                                                'flex min-h-11 cursor-pointer items-center gap-2 rounded-md border px-3 text-sm',
                                                data.spice_level === level
                                                    ? 'border-ember bg-ember-soft'
                                                    : 'border-line bg-surface',
                                            )}
                                        >
                                            <RadioGroupItem
                                                id={`spice-${level}`}
                                                value={String(level)}
                                            />
                                            {t(
                                                `staff_menu.editor.spice_levels.${level}`,
                                            )}
                                        </Label>
                                    ))}
                                </RadioGroup>
                            </fieldset>
                        </div>
                        <div className="grid gap-1">
                            <Toggle
                                name="is_vegetarian"
                                label={e('vegetarian')}
                                checked={data.is_vegetarian || data.is_vegan}
                                disabled={data.is_vegan}
                                onChange={(v) => setData('is_vegetarian', v)}
                            />
                            <Toggle
                                name="is_vegan"
                                label={e('vegan')}
                                hint={e('vegan_hint')}
                                checked={data.is_vegan}
                                onChange={(v) =>
                                    setData((c) => ({
                                        ...c,
                                        is_vegan: v,
                                        is_vegetarian: v
                                            ? true
                                            : c.is_vegetarian,
                                    }))
                                }
                            />
                        </div>
                        <fieldset className="grid gap-2">
                            <legend className="text-sm font-medium">
                                {e('allergens')}
                            </legend>
                            <p className="text-sm text-ink-muted">
                                {e('allergens_hint')}
                            </p>
                            <AllergenPicker
                                idPrefix="allergen"
                                value={data.allergens}
                                onChange={(v) => setData('allergens', v)}
                            />
                            {errors.allergens && (
                                <p className="text-sm font-medium text-chilli">
                                    {errors.allergens}
                                </p>
                            )}
                        </fieldset>
                        <div className="rounded-md border border-turmeric bg-turmeric-soft p-3">
                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id="field-allergens_checked"
                                    checked={data.allergens_checked}
                                    aria-invalid={
                                        errors.allergens_checked
                                            ? true
                                            : undefined
                                    }
                                    aria-describedby={
                                        errors.allergens_checked
                                            ? 'field-allergens_checked-error'
                                            : undefined
                                    }
                                    onCheckedChange={(on) =>
                                        setData(
                                            'allergens_checked',
                                            on === true,
                                        )
                                    }
                                />
                                <Label
                                    htmlFor="field-allergens_checked"
                                    className="min-h-6 leading-snug font-semibold"
                                >
                                    {e('ack')}
                                </Label>
                            </div>
                            {errors.allergens_checked && (
                                <p
                                    id="field-allergens_checked-error"
                                    role="alert"
                                    className="mt-2 text-sm font-medium text-chilli"
                                >
                                    {errors.allergens_checked}
                                </p>
                            )}
                        </div>
                    </Section>

                    <Section id="s-kitchen" title={e('sections.kitchen')}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                name="station"
                                label={e('station')}
                                error={errors.station}
                                required
                            >
                                {(p) => (
                                    <Select
                                        value={data.station}
                                        onValueChange={(v) =>
                                            setData('station', v)
                                        }
                                    >
                                        <SelectTrigger
                                            {...p}
                                            className="h-11 w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {stations.map((s) => (
                                                <SelectItem
                                                    key={s.value}
                                                    value={s.value}
                                                >
                                                    {s.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                            </Field>
                            <Field
                                name="prep_minutes"
                                label={e('prep')}
                                error={errors.prep_minutes}
                                required
                            >
                                {(p) => (
                                    <Input
                                        {...p}
                                        className="h-11"
                                        inputMode="numeric"
                                        value={data.prep_minutes}
                                        onChange={(ev) =>
                                            setData(
                                                'prep_minutes',
                                                ev.target.value.replace(
                                                    /\D/g,
                                                    '',
                                                ),
                                            )
                                        }
                                    />
                                )}
                            </Field>
                        </div>
                        <div className="grid gap-1">
                            <Toggle
                                name="is_hot"
                                label={e('hot')}
                                checked={data.is_hot}
                                onChange={(v) => setData('is_hot', v)}
                            />
                            <Toggle
                                name="is_alcoholic"
                                label={e('alcoholic')}
                                hint={e('alcoholic_hint')}
                                checked={data.is_alcoholic}
                                onChange={(v) => setData('is_alcoholic', v)}
                            />
                            <Toggle
                                name="is_available"
                                label={e('available')}
                                checked={data.is_available}
                                onChange={(v) => setData('is_available', v)}
                            />
                            <Toggle
                                name="is_special"
                                label={e('special')}
                                checked={data.is_special}
                                onChange={(v) => setData('is_special', v)}
                            />
                        </div>
                        <fieldset className="grid gap-2">
                            <legend className="text-sm font-medium">
                                {e('periods')}
                            </legend>
                            <div className="flex flex-wrap gap-4">
                                {periods.map((period) => (
                                    <Label
                                        key={period}
                                        className="flex min-h-11 items-center gap-2"
                                    >
                                        <Checkbox
                                            checked={data.service_periods.includes(
                                                period,
                                            )}
                                            onCheckedChange={(on) =>
                                                togglePeriod(
                                                    period,
                                                    on === true,
                                                )
                                            }
                                        />
                                        {t(`staff_menu.periods.${period}`)}
                                    </Label>
                                ))}
                            </div>
                            <p className="text-sm text-ink-muted">
                                {e('periods_hint')}
                            </p>
                        </fieldset>
                    </Section>

                    <Section id="s-variants" title={e('sections.variants')}>
                        <VariantsEditor
                            value={data.variants}
                            errors={shownErrors}
                            onChange={(v) => setData('variants', v)}
                        />
                    </Section>

                    <Section id="s-modifiers" title={e('sections.modifiers')}>
                        <GroupsEditor
                            value={data.modifier_groups}
                            errors={shownErrors}
                            onChange={(v) => setData('modifier_groups', v)}
                        />
                    </Section>
                </div>

                <aside className="grid content-start gap-5">
                    <Section id="s-photo" title={e('sections.photo')}>
                        <PhotoField
                            file={data.photo}
                            photoUrl={item?.photo_url ?? null}
                            mediaKey={item?.image ?? null}
                            error={errors.photo}
                            onChange={(file) => setData('photo', file)}
                        />
                    </Section>
                    {item && (
                        <Section id="s-history" title={e('sections.history')}>
                            <PriceHistory rows={history} />
                        </Section>
                    )}
                </aside>
            </div>

            <div className="sticky bottom-0 z-20 flex flex-wrap items-center justify-between gap-3 border-t border-line bg-paper px-4 py-3 md:px-6">
                <Button asChild variant="ghost" size="lg">
                    <Link href="/staff/menu">
                        <ArrowLeft aria-hidden="true" />
                        {e('back')}
                    </Link>
                </Button>
                <div className="flex items-center gap-3">
                    {isDirty && !processing && (
                        <span className="text-sm text-ink-muted">
                            {e('unsaved')}
                        </span>
                    )}
                    <Button
                        type="submit"
                        size="lg"
                        disabled={processing || !online}
                        title={offlineReason}
                    >
                        {processing
                            ? e('saving')
                            : item
                              ? e('save')
                              : e('create')}
                    </Button>
                </div>
            </div>
            {!online && (
                <p
                    role="status"
                    className="px-4 pb-3 text-sm text-ink-muted md:px-6"
                >
                    {e('offline')}
                </p>
            )}
        </form>
    );
}

function slugify(name: string): string {
    return name
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}
