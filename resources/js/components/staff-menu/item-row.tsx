import { Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { DietMarks } from '@/components/brand/diet';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { useT, choice } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { Thumb } from './thumb';
import type { ItemRow as Item, StationOption } from './types';

/** Shared by the header row and every dish row so the columns line up. */
export const rowGrid =
    'md:grid md:grid-cols-[minmax(0,1fr)_7rem_4rem_3.5rem_6.5rem_8rem_5.5rem_5.5rem] md:items-center md:gap-3';

export function ItemTableHeader() {
    const t = useT();
    const col = (key: string) => t(`staff_menu.list.columns.${key}`);

    return (
        <div
            className={cn(
                rowGrid,
                'sticky top-0 z-10 hidden border-b border-line bg-paper px-4 py-2 text-xs font-semibold tracking-wide text-ink-muted uppercase',
            )}
        >
            <span>{col('dish')}</span>
            <span className="text-right">{col('price')}</span>
            <span className="text-right">{col('kcal')}</span>
            <span>{col('diet')}</span>
            <span>{col('allergens')}</span>
            <span>{col('station')}</span>
            <span>{col('available')}</span>
            <span>{col('special')}</span>
        </div>
    );
}

export function ItemRow({
    item,
    stations,
    online,
    pending,
    onFlag,
}: {
    item: Item;
    stations: StationOption[];
    online: boolean;
    pending: boolean;
    onFlag: (
        item: Item,
        flag: 'availability' | 'special',
        value: boolean,
    ) => void;
}) {
    const t = useT();
    const station = stations.find((s) => s.value === item.station)?.label;
    const disabled = !online || pending;
    const reason = !online ? t('staff_menu.list.offline') : undefined;

    return (
        <li
            className={cn(
                rowGrid,
                'flex flex-col gap-3 border-b border-line px-4 py-3 md:py-2',
                !item.is_available && 'bg-clay/40',
            )}
        >
            <div className="flex min-w-0 items-center gap-3">
                <Thumb photoUrl={item.photo_url} mediaKey={item.image} />
                <div className="min-w-0 flex-1">
                    <Link
                        href={`/staff/menu/items/${item.id}/edit`}
                        className="inline-flex min-h-11 items-center gap-1.5 font-semibold text-ink underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember md:min-h-0"
                        aria-label={t('staff_menu.list.edit', {
                            name: item.name,
                        })}
                    >
                        <span className="truncate">{item.name}</span>
                        <Pencil
                            aria-hidden="true"
                            className="size-3.5 shrink-0 text-ink-muted"
                        />
                    </Link>
                    <p className="text-sm text-ink-muted md:hidden">
                        {item.has_variants
                            ? t('staff_menu.list.from', {
                                  price: formatMoney(item.price),
                              })
                            : formatMoney(item.price)}
                        {' · '}
                        {item.kcal} kcal
                    </p>
                </div>
            </div>

            <span className="hidden text-right text-sm tabular-nums md:block">
                {item.has_variants
                    ? t('staff_menu.list.from', {
                          price: formatMoney(item.price),
                      })
                    : formatMoney(item.price)}
            </span>
            <span className="hidden text-right text-sm tabular-nums md:block">
                {item.kcal}
            </span>

            <div className="flex flex-wrap items-center gap-2 text-sm md:contents">
                <span className="md:block">
                    <DietMarks
                        vegetarian={item.is_vegetarian}
                        vegan={item.is_vegan}
                    />
                </span>
                <span className="text-ink-muted md:text-ink">
                    {item.allergen_count === 0
                        ? t('staff_menu.list.no_allergens')
                        : choice(
                              t('staff_menu.list.allergen_count'),
                              item.allergen_count,
                          ).replace(':count', String(item.allergen_count))}
                </span>
                <Badge variant="secondary" className="md:justify-self-start">
                    {station}
                </Badge>
            </div>

            <div className="flex items-center gap-5 md:contents">
                <label className="flex min-h-11 items-center gap-2 text-sm md:min-h-0">
                    <Switch
                        checked={item.is_available}
                        disabled={disabled}
                        title={reason}
                        aria-label={t('staff_menu.list.available_switch', {
                            name: item.name,
                        })}
                        onCheckedChange={(value) =>
                            onFlag(item, 'availability', value)
                        }
                    />
                    <span className="md:sr-only">
                        {t('staff_menu.list.columns.available')}
                    </span>
                </label>
                <label className="flex min-h-11 items-center gap-2 text-sm md:min-h-0">
                    <Switch
                        checked={item.is_special}
                        disabled={disabled}
                        title={reason}
                        aria-label={t('staff_menu.list.special_switch', {
                            name: item.name,
                        })}
                        onCheckedChange={(value) =>
                            onFlag(item, 'special', value)
                        }
                    />
                    <span className="md:sr-only">
                        {t('staff_menu.list.columns.special')}
                    </span>
                </label>
            </div>
        </li>
    );
}

export function ItemRowSkeleton() {
    return (
        <li className="flex items-center gap-3 border-b border-line px-4 py-3">
            <div className="size-14 animate-pulse rounded-md bg-clay" />
            <div className="h-4 w-48 animate-pulse rounded bg-clay" />
        </li>
    );
}
