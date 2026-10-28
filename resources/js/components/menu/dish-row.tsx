import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { AllergenList } from '@/components/brand/allergen-list';
import { ArchFrame } from '@/components/brand/arch-frame';
import { DietMarks, SpiceMeter } from '@/components/brand/diet';
import { Picture } from '@/components/brand/picture';
import { Price } from '@/components/brand/price';
import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { MenuItem } from '@/types';

type Props = {
    item: MenuItem;
    href: string;
    onAdd?: (item: MenuItem, origin: HTMLElement) => void;
    className?: string;
};

/**
 * Compact menu row built to scan on a phone: name, a short line, kcal, diet
 * and allergens always visible, photo on the right.
 */
export function DishRow({ item, href, onAdd, className }: Props) {
    const t = useT();
    const soldOut = !item.is_available;
    const needsChoice = item.has_options;

    return (
        <article
            className={cn(
                'relative flex gap-4 py-5',
                soldOut && 'opacity-70',
                className,
            )}
            aria-labelledby={`dish-${item.id}`}
        >
            <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                <h3
                    id={`dish-${item.id}`}
                    className="text-title font-semibold text-ink"
                >
                    <Link
                        href={href}
                        prefetch
                        className="outline-none after:absolute after:inset-0 after:content-[''] focus-visible:underline focus-visible:decoration-ember focus-visible:decoration-2 focus-visible:underline-offset-4"
                    >
                        {item.name}
                    </Link>
                </h3>
                <p className="line-clamp-2 text-sm text-ink-muted">
                    {item.description}
                </p>
                <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                    {(item.variants?.length ?? 0) > 1 ? (
                        <span className="font-semibold text-ink tabular-nums">
                            {t('ui.from_price', {
                                price: formatMoney(item.price, {
                                    compact: true,
                                }),
                            })}
                        </span>
                    ) : (
                        <Price pence={item.price} className="text-ink" />
                    )}
                    <span className="text-sm text-ink-muted tabular-nums">
                        {t('ui.kcal', { kcal: item.kcal })}
                    </span>
                    <DietMarks
                        vegetarian={item.is_vegetarian}
                        vegan={item.is_vegan}
                    />
                    <SpiceMeter level={item.spice_level} />
                    {soldOut && (
                        <span className="text-sm font-semibold text-chilli">
                            {t('ui.sold_out')}
                        </span>
                    )}
                </div>
                <AllergenList
                    allergens={item.allergens}
                    optional={item.option_allergens}
                    compact
                    className="mt-1"
                />
            </div>
            <div className="relative flex w-24 shrink-0 flex-col items-center sm:w-28">
                <ArchFrame ratio="portrait" className="w-full">
                    <Picture mediaKey={item.image} alt="" sizes="112px" steam />
                </ArchFrame>
                {onAdd && (
                    <button
                        type="button"
                        disabled={soldOut}
                        onClick={(event) => onAdd(item, event.currentTarget)}
                        aria-label={
                            needsChoice
                                ? t('ui.choose_item', { item: item.name })
                                : t('ui.add_item', { item: item.name })
                        }
                        className="relative z-10 -mt-5 grid size-11 place-items-center rounded-full bg-ember text-ember-ink shadow-plate transition-transform duration-150 outline-none hover:scale-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember active:scale-95 disabled:bg-clay disabled:text-ink-muted disabled:shadow-none"
                    >
                        <Plus
                            className="size-5"
                            strokeWidth={2.5}
                            aria-hidden="true"
                        />
                    </button>
                )}
            </div>
        </article>
    );
}

export function DishRowSkeleton() {
    return (
        <div className="flex gap-4 py-5" aria-hidden="true">
            <div className="flex flex-1 flex-col gap-2">
                <Skeleton className="h-6 w-2/3" />
                <Skeleton className="h-4 w-full" />
                <Skeleton className="h-4 w-4/5" />
                <div className="mt-1 flex gap-3">
                    <Skeleton className="h-5 w-12" />
                    <Skeleton className="h-5 w-16" />
                </div>
                <Skeleton className="h-4 w-1/2" />
            </div>
            <Skeleton className="aspect-[4/5] w-24 rounded-arch sm:w-28" />
        </div>
    );
}
