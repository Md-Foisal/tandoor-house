import { Deferred, router } from '@inertiajs/react';
import { Pencil, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { EmptyState } from '@/components/brand/states';
import { StatTile } from '@/components/staff/stat-tile';
import { RecipeDialog } from '@/components/staff-inventory/recipe-dialog';
import {
    InventoryShell,
    RowsSkeleton,
} from '@/components/staff-inventory/shell';
import type {
    Abilities,
    DishRow,
    IngredientOption,
} from '@/components/staff-inventory/types';
import {
    formatMargin,
    formatQuantity,
} from '@/components/staff-inventory/units';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { choice, useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

type Filters = { q: string; flagged: boolean };

export default function Recipes({
    dishes,
    filters,
    abilities,
    lowCount,
    ingredients,
    target,
}: {
    dishes?: DishRow[];
    filters: Filters;
    abilities: Abilities;
    lowCount: number;
    ingredients: IngredientOption[];
    target: number;
}) {
    const t = useT();
    const [query, setQuery] = useState(filters.q);
    const [loading, setLoading] = useState(false);
    const [editing, setEditing] = useState<DishRow | null>(null);
    const first = useRef(true);

    const apply = (next: Partial<Filters>) => {
        const merged = { ...filters, q: query, ...next };
        const params: Record<string, string> = {};

        if (merged.q) params.q = merged.q;
        if (merged.flagged) params.flagged = '1';

        router.get('/staff/inventory/recipes', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['dishes', 'filters'],
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const timer = window.setTimeout(() => apply({ q: query }), 300);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query]);

    const costed = (dishes ?? []).filter((dish) => dish.margin !== null);
    const under = costed.filter((dish) => dish.flagged).length;
    const average = costed.length
        ? costed.reduce((sum, dish) => sum + (dish.margin ?? 0), 0) /
          costed.length
        : null;

    return (
        <InventoryShell
            active="recipes"
            lowCount={lowCount}
            description={t('staff_inventory.recipe.description', { target })}
            toolbar={
                <div className="flex flex-wrap items-center gap-3">
                    <div className="relative min-w-56 flex-1 md:max-w-sm">
                        <Label htmlFor="recipe-search" className="sr-only">
                            {t('staff_inventory.recipe.search')}
                        </Label>
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-muted"
                            aria-hidden="true"
                        />
                        <Input
                            id="recipe-search"
                            type="search"
                            value={query}
                            placeholder={t(
                                'staff_inventory.recipe.search_placeholder',
                            )}
                            className="min-h-11 pl-9"
                            onChange={(event) => setQuery(event.target.value)}
                        />
                    </div>
                    <Label
                        htmlFor="recipe-flagged"
                        className="flex min-h-11 cursor-pointer items-center gap-2 text-sm"
                    >
                        <Switch
                            id="recipe-flagged"
                            checked={filters.flagged}
                            onCheckedChange={(on) => apply({ flagged: on })}
                        />
                        {t('staff_inventory.recipe.flagged_only', { target })}
                    </Label>
                </div>
            }
        >
            {dishes && (
                <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
                    <StatTile
                        label={t('staff_inventory.recipe.tile_costed')}
                        value={costed.length}
                    />
                    <StatTile
                        label={t('staff_inventory.recipe.tile_under', {
                            target,
                        })}
                        value={under}
                        tone={under > 0 ? 'bad' : 'good'}
                        hint={
                            under > 0
                                ? t('staff_inventory.recipe.tile_under_hint')
                                : t('staff_inventory.recipe.tile_under_none')
                        }
                    />
                    <StatTile
                        className="col-span-2 md:col-span-1"
                        label={t('staff_inventory.recipe.tile_average')}
                        value={formatMargin(
                            average === null
                                ? null
                                : Math.round(average * 10) / 10,
                        )}
                    />
                </div>
            )}
            <Deferred data="dishes" fallback={<RowsSkeleton rows={10} />}>
                {dishes && dishes.length === 0 ? (
                    <div className="rounded-lg border border-line bg-surface">
                        <EmptyState
                            title={t('staff_inventory.recipe.empty_title')}
                            description={t('staff_inventory.recipe.empty')}
                            action={
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        setQuery('');
                                        apply({ q: '', flagged: false });
                                    }}
                                >
                                    <X /> {t('staff_inventory.filters.clear')}
                                </Button>
                            }
                        />
                    </div>
                ) : (
                    <ul
                        className={cn(
                            'divide-y divide-line overflow-hidden rounded-lg border border-line bg-surface transition-opacity',
                            loading && 'opacity-60',
                        )}
                        aria-busy={loading}
                    >
                        {(dishes ?? []).map((dish) => (
                            <li
                                key={dish.id}
                                className={cn(
                                    dish.flagged && 'bg-chilli-soft/50',
                                )}
                            >
                                <details className="group">
                                    <summary className="grid cursor-pointer list-none gap-2 px-4 py-3 md:grid-cols-[minmax(0,2fr)_6rem_6rem_7rem] md:items-center md:gap-4 [&::-webkit-details-marker]:hidden">
                                        <div className="min-w-0">
                                            <p className="font-semibold">
                                                {dish.name}
                                            </p>
                                            <p className="text-sm text-ink-muted">
                                                {dish.category} ·{' '}
                                                {dish.lines.length > 0
                                                    ? choice(
                                                          t(
                                                              'staff_inventory.recipe.lines',
                                                              {
                                                                  count: dish
                                                                      .lines
                                                                      .length,
                                                              },
                                                          ),
                                                          dish.lines.length,
                                                      )
                                                    : t(
                                                          'staff_inventory.recipe.no_recipe',
                                                      )}
                                            </p>
                                        </div>
                                        <p className="flex justify-between text-sm tabular-nums md:block md:text-right">
                                            <span className="text-ink-muted md:hidden">
                                                {t(
                                                    'staff_inventory.recipe.cost',
                                                )}
                                            </span>
                                            {dish.cost === null
                                                ? '–'
                                                : formatMoney(dish.cost)}
                                        </p>
                                        <p className="flex justify-between text-sm tabular-nums md:block md:text-right">
                                            <span className="text-ink-muted md:hidden">
                                                {t(
                                                    'staff_inventory.recipe.net_price',
                                                )}
                                            </span>
                                            {formatMoney(dish.net_price)}
                                        </p>
                                        <p className="flex items-center justify-between gap-2 md:justify-end">
                                            <span className="text-sm text-ink-muted md:hidden">
                                                {t(
                                                    'staff_inventory.recipe.margin',
                                                )}
                                            </span>
                                            {dish.flagged ? (
                                                <Badge variant="destructive">
                                                    {formatMargin(dish.margin)}
                                                </Badge>
                                            ) : (
                                                <span className="text-sm font-semibold tabular-nums">
                                                    {formatMargin(dish.margin)}
                                                </span>
                                            )}
                                        </p>
                                    </summary>
                                    <div className="border-t border-line bg-paper px-4 py-3">
                                        {dish.lines.length === 0 ? (
                                            <p className="text-sm text-ink-muted">
                                                {t(
                                                    'staff_inventory.recipe.no_lines',
                                                )}
                                            </p>
                                        ) : (
                                            <table className="w-full text-sm">
                                                <caption className="sr-only">
                                                    {t(
                                                        'staff_inventory.recipe.table_caption',
                                                        { name: dish.name },
                                                    )}
                                                </caption>
                                                <tbody className="divide-y divide-line">
                                                    {dish.lines.map((line) => (
                                                        <tr
                                                            key={
                                                                line.ingredient_id
                                                            }
                                                        >
                                                            <th
                                                                scope="row"
                                                                className="py-2 text-left font-normal"
                                                            >
                                                                {line.name}
                                                            </th>
                                                            <td className="py-2 text-right tabular-nums">
                                                                {formatQuantity(
                                                                    line.quantity,
                                                                    line.unit,
                                                                )}
                                                            </td>
                                                            <td className="w-20 py-2 text-right text-ink-muted tabular-nums">
                                                                {formatMoney(
                                                                    line.cost,
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        )}
                                        <p className="mt-2 text-sm text-ink-muted">
                                            {t(
                                                'staff_inventory.recipe.price_line',
                                                {
                                                    price: formatMoney(
                                                        dish.price,
                                                    ),
                                                    net: formatMoney(
                                                        dish.net_price,
                                                    ),
                                                },
                                            )}
                                        </p>
                                        {abilities.manage && (
                                            <Button
                                                className="mt-3"
                                                variant="outline"
                                                onClick={() => setEditing(dish)}
                                            >
                                                <Pencil />{' '}
                                                {t(
                                                    'staff_inventory.recipe.edit',
                                                )}
                                            </Button>
                                        )}
                                    </div>
                                </details>
                            </li>
                        ))}
                    </ul>
                )}
            </Deferred>
            <RecipeDialog
                dish={editing}
                ingredients={ingredients}
                target={target}
                onClose={() => setEditing(null)}
            />
        </InventoryShell>
    );
}
