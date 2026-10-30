import { Deferred, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/brand/states';
import {
    InventoryShell,
    RowsSkeleton,
} from '@/components/staff-inventory/shell';
import type {
    IngredientOption,
    MovementRow,
    MovementType,
} from '@/components/staff-inventory/types';
import { formatQuantity } from '@/components/staff-inventory/units';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Filters = {
    type: MovementType | null;
    ingredient: number | null;
    from: string | null;
    to: string | null;
};
type Page = {
    data: MovementRow[];
    current_page: number;
    last_page: number;
    total: number;
};

const types: MovementType[] = ['in', 'sale', 'waste', 'adjust'];
const badge = {
    in: 'leaf',
    sale: 'secondary',
    waste: 'destructive',
    adjust: 'turmeric',
} as const;

export default function Movements({
    movements,
    filters,
    ingredients,
    lowCount,
}: {
    movements?: Page;
    filters: Filters;
    ingredients: IngredientOption[];
    lowCount: number;
}) {
    const t = useT();
    const [loading, setLoading] = useState(false);

    const go = (next: Partial<Filters> & { page?: number }) => {
        const merged = { ...filters, ...next };
        const params: Record<string, string> = {};

        if (merged.type) params.type = merged.type;
        if (merged.ingredient !== null)
            params.ingredient = String(merged.ingredient);
        if (merged.from) params.from = merged.from;
        if (merged.to) params.to = merged.to;
        if (next.page && next.page > 1) params.page = String(next.page);

        router.get('/staff/inventory/movements', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['movements', 'filters'],
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    const filtered = Boolean(
        filters.type ||
        filters.ingredient !== null ||
        filters.from ||
        filters.to,
    );

    return (
        <InventoryShell
            active="movements"
            lowCount={lowCount}
            description={t('staff_inventory.movements.description')}
            toolbar={
                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-1.5">
                        <Label htmlFor="mv-type">
                            {t('staff_inventory.movements.type')}
                        </Label>
                        <Select
                            value={filters.type ?? 'all'}
                            onValueChange={(value) =>
                                go({
                                    type:
                                        value === 'all'
                                            ? null
                                            : (value as MovementType),
                                })
                            }
                        >
                            <SelectTrigger
                                id="mv-type"
                                className="min-h-11 w-40"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    {t('staff_inventory.movements.all_types')}
                                </SelectItem>
                                {types.map((type) => (
                                    <SelectItem key={type} value={type}>
                                        {t(`staff_inventory.type.${type}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="mv-ingredient">
                            {t('staff_inventory.movements.ingredient')}
                        </Label>
                        <Select
                            value={
                                filters.ingredient === null
                                    ? 'all'
                                    : String(filters.ingredient)
                            }
                            onValueChange={(value) =>
                                go({
                                    ingredient:
                                        value === 'all' ? null : Number(value),
                                })
                            }
                        >
                            <SelectTrigger
                                id="mv-ingredient"
                                className="min-h-11 w-52"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    {t(
                                        'staff_inventory.movements.all_ingredients',
                                    )}
                                </SelectItem>
                                {ingredients.map((i) => (
                                    <SelectItem key={i.id} value={String(i.id)}>
                                        {i.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="mv-from">
                            {t('staff_inventory.movements.from')}
                        </Label>
                        <Input
                            id="mv-from"
                            type="date"
                            value={filters.from ?? ''}
                            max={filters.to ?? undefined}
                            className="min-h-11 w-40"
                            onChange={(event) =>
                                go({ from: event.target.value || null })
                            }
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="mv-to">
                            {t('staff_inventory.movements.to')}
                        </Label>
                        <Input
                            id="mv-to"
                            type="date"
                            value={filters.to ?? ''}
                            min={filters.from ?? undefined}
                            className="min-h-11 w-40"
                            onChange={(event) =>
                                go({ to: event.target.value || null })
                            }
                        />
                    </div>
                    {filtered && (
                        <Button
                            variant="ghost"
                            onClick={() =>
                                go({
                                    type: null,
                                    ingredient: null,
                                    from: null,
                                    to: null,
                                })
                            }
                        >
                            <X /> {t('staff_inventory.filters.clear')}
                        </Button>
                    )}
                </div>
            }
        >
            <Deferred data="movements" fallback={<RowsSkeleton rows={10} />}>
                {movements && movements.data.length === 0 ? (
                    <div className="rounded-lg border border-line bg-surface">
                        <EmptyState
                            title={t('staff_inventory.movements.empty_title')}
                            description={t(
                                filtered
                                    ? 'staff_inventory.movements.empty_filtered'
                                    : 'staff_inventory.movements.empty',
                            )}
                        />
                    </div>
                ) : (
                    movements && (
                        <div
                            className={cn(
                                'grid gap-3 transition-opacity',
                                loading && 'opacity-60',
                            )}
                            aria-busy={loading}
                        >
                            <p className="text-sm text-ink-muted">
                                {t('staff_inventory.movements.total', {
                                    count: movements.total.toLocaleString(
                                        'en-GB',
                                    ),
                                })}
                            </p>
                            <div className="overflow-hidden rounded-lg border border-line bg-surface">
                                <div
                                    className="sticky top-0 hidden grid-cols-[9rem_minmax(0,1.5fr)_6rem_8rem_minmax(0,2fr)_8rem] gap-4 border-b border-line bg-clay px-4 py-2 text-label text-ink-muted uppercase md:grid"
                                    aria-hidden="true"
                                >
                                    <span>
                                        {t('staff_inventory.movements.when')}
                                    </span>
                                    <span>
                                        {t(
                                            'staff_inventory.columns.ingredient',
                                        )}
                                    </span>
                                    <span>
                                        {t('staff_inventory.movements.type')}
                                    </span>
                                    <span className="text-right">
                                        {t('staff_inventory.movements.change')}
                                    </span>
                                    <span>
                                        {t('staff_inventory.movements.reason')}
                                    </span>
                                    <span>
                                        {t('staff_inventory.movements.by')}
                                    </span>
                                </div>
                                <ul className="divide-y divide-line">
                                    {movements.data.map((row) => (
                                        <li
                                            key={row.id}
                                            className="grid gap-1 px-4 py-3 text-sm md:grid-cols-[9rem_minmax(0,1.5fr)_6rem_8rem_minmax(0,2fr)_8rem] md:items-center md:gap-4"
                                        >
                                            <time
                                                dateTime={row.at}
                                                className="text-ink-muted tabular-nums"
                                            >
                                                {new Date(
                                                    row.at,
                                                ).toLocaleString('en-GB', {
                                                    day: 'numeric',
                                                    month: 'short',
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                })}
                                            </time>
                                            <p className="font-semibold">
                                                {row.ingredient.name}
                                            </p>
                                            <p>
                                                <Badge
                                                    variant={badge[row.type]}
                                                >
                                                    {t(
                                                        `staff_inventory.type.${row.type}`,
                                                    )}
                                                </Badge>
                                            </p>
                                            <p
                                                className={cn(
                                                    'font-semibold tabular-nums md:text-right',
                                                    row.quantity < 0
                                                        ? 'text-chilli'
                                                        : 'text-leaf',
                                                )}
                                            >
                                                {row.quantity > 0 ? '+' : ''}
                                                {formatQuantity(
                                                    row.quantity,
                                                    row.ingredient.unit,
                                                )}
                                            </p>
                                            <p className="text-ink-muted">
                                                {row.order_number
                                                    ? t(
                                                          'staff_inventory.movements.order',
                                                          {
                                                              number: row.order_number,
                                                          },
                                                      )
                                                    : (row.reason ?? '–')}
                                            </p>
                                            <p className="text-ink-muted">
                                                {row.user ??
                                                    t('staff_inventory.system')}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                            {movements.last_page > 1 && (
                                <nav
                                    aria-label={t(
                                        'staff_inventory.movements.pages',
                                    )}
                                    className="flex items-center justify-between gap-3"
                                >
                                    <Button
                                        variant="outline"
                                        disabled={movements.current_page <= 1}
                                        onClick={() =>
                                            go({
                                                page:
                                                    movements.current_page - 1,
                                            })
                                        }
                                    >
                                        <ChevronLeft />{' '}
                                        {t('staff_inventory.movements.newer')}
                                    </Button>
                                    <p
                                        className="text-sm text-ink-muted"
                                        aria-live="polite"
                                    >
                                        {t('staff_inventory.movements.page', {
                                            page: movements.current_page,
                                            pages: movements.last_page,
                                        })}
                                    </p>
                                    <Button
                                        variant="outline"
                                        disabled={
                                            movements.current_page >=
                                            movements.last_page
                                        }
                                        onClick={() =>
                                            go({
                                                page:
                                                    movements.current_page + 1,
                                            })
                                        }
                                    >
                                        {t('staff_inventory.movements.older')}{' '}
                                        <ChevronRight />
                                    </Button>
                                </nav>
                            )}
                        </div>
                    )
                )}
            </Deferred>
        </InventoryShell>
    );
}
