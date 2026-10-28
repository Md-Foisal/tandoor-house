import { Link } from '@inertiajs/react';
import { SectionHeading } from '@/components/brand/section-heading';
import { AllergenList } from '@/components/brand/allergen-list';
import { EmptyState } from '@/components/brand/states';
import { DishCard } from '@/components/menu/dish-card';
import { Course, Plate } from '@/components/motion/served';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { useAddToBasket } from '@/lib/basket';
import { cn } from '@/lib/utils';
import type { MenuItem } from '@/types';

/*
 * One large dish and two stepped smaller ones on desktop; a snap-scrolling
 * row on mobile so the next plate always peeks in from the right.
 */
const placement = [
    'lg:row-span-2',
    'lg:col-start-2 lg:row-start-1',
    'lg:col-start-3 lg:row-start-1 lg:mt-20',
] as const;

function SpecialAction({
    item,
    pending,
    onAdd,
}: {
    item: MenuItem;
    pending: boolean;
    onAdd: (item: MenuItem, origin: Element) => void;
}) {
    const t = useT();

    if (!item.is_available) {
        return (
            <Button variant="secondary" disabled className="w-full">
                {t('home.specials.sold_out')}
            </Button>
        );
    }

    if (item.has_options) {
        return (
            <Button asChild variant="outline" className="w-full">
                <Link
                    href={`/menu/${item.slug}`}
                    aria-label={t('home.specials.choose_item', {
                        item: item.name,
                    })}
                >
                    {t('home.specials.choose')}
                </Link>
            </Button>
        );
    }

    return (
        <Button
            className="w-full"
            disabled={pending}
            aria-label={t('home.specials.add_item', { item: item.name })}
            onClick={(event) => onAdd(item, event.currentTarget)}
        >
            {t('home.specials.add')}
        </Button>
    );
}

export function Specials({ items }: { items: MenuItem[] }) {
    const t = useT();
    const { add, pending } = useAddToBasket();

    const onAdd = (item: MenuItem, origin: Element) =>
        add({ menu_item_id: item.id }, origin);

    return (
        <section
            aria-labelledby="specials-title"
            className="container-page pt-section"
        >
            <div className="flex flex-wrap items-end justify-between gap-4">
                <SectionHeading
                    id="specials-title"
                    eyebrow={t('home.specials.eyebrow')}
                    title={t('home.specials.title')}
                    description={t('home.specials.description')}
                />
                <Button asChild variant="link">
                    <Link href="/menu" prefetch>
                        {t('home.specials.whole_menu')}
                    </Link>
                </Button>
            </div>

            {items.length === 0 ? (
                <EmptyState
                    title={t('home.specials.empty_title')}
                    description={t('home.specials.empty_body')}
                />
            ) : (
                <Course
                    role="list"
                    aria-label={t('home.specials.list')}
                    className="-mx-gutter mt-10 flex snap-x snap-mandatory gap-5 overflow-x-auto px-gutter pb-4 lg:mx-0 lg:grid lg:grid-cols-[1.25fr_1fr_1fr] lg:gap-x-8 lg:gap-y-0 lg:overflow-visible lg:px-0"
                >
                    {items.map((item, index) => (
                        <Plate
                            key={item.id}
                            role="listitem"
                            className={cn(
                                'flex w-[78vw] max-w-sm shrink-0 snap-center flex-col gap-4 lg:w-auto lg:max-w-none',
                                placement[index],
                            )}
                        >
                            <DishCard item={item} href={`/menu/${item.slug}`} />
                            <AllergenList
                                allergens={item.allergens}
                                optional={item.option_allergens}
                                compact
                            />
                            <SpecialAction
                                item={item}
                                pending={pending === item.id}
                                onAdd={onAdd}
                            />
                        </Plate>
                    ))}
                </Course>
            )}
        </section>
    );
}
