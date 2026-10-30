import { Deferred, router } from '@inertiajs/react';
import { PackagePlus, Pencil, Plus, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { EmptyState, useOnline } from '@/components/brand/states';
import { StatTile } from '@/components/staff/stat-tile';
import { IngredientDialog } from '@/components/staff-inventory/ingredient-dialog';
import {
    InventoryShell,
    RowsSkeleton,
    useWhen,
} from '@/components/staff-inventory/shell';
import { StockBar } from '@/components/staff-inventory/stock-bar';
import {
    StockDialog,
    type StockMode,
} from '@/components/staff-inventory/stock-dialog';
import type {
    Abilities,
    IngredientRow,
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
import { Switch } from '@/components/ui/switch';
import { useT } from '@/lib/i18n';
import { channels, events, useLive } from '@/lib/realtime';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

type Filters = { q: string; low: boolean; supplier: number | null };

const POLL_MS = 15_000;

export default function Ingredients({
    ingredients,
    filters,
    abilities,
    lowCount,
    suppliers,
    summary,
}: {
    ingredients?: IngredientRow[];
    filters: Filters;
    abilities: Abilities;
    lowCount: number;
    suppliers: { id: number; name: string }[];
    summary: { count: number; low: number; value?: number };
}) {
    const t = useT();
    const showValue = summary.value !== undefined;
    const gridCols = showValue
        ? 'md:grid-cols-[minmax(0,2fr)_minmax(0,1.6fr)_7rem_7rem_minmax(0,12rem)]'
        : 'md:grid-cols-[minmax(0,2fr)_minmax(0,1.6fr)_7rem_minmax(0,12rem)]';
    const when = useWhen();
    const online = useOnline();
    const searchRef = useRef<HTMLInputElement>(null);
    const [query, setQuery] = useState(filters.q);
    const [loading, setLoading] = useState(false);
    const [editing, setEditing] = useState<IngredientRow | null>(null);
    const [creating, setCreating] = useState(false);
    const [stock, setStock] = useState<{
        mode: StockMode;
        ingredient: IngredientRow;
    } | null>(null);

    // Stock is drawn down as orders are accepted, so order changes are the
    // only signal there is; manual adjustments reload through the poll.
    useLive(['ingredients', 'summary', 'lowCount'], {
        channel: channels.staffOrders,
        events: [events.orderStatusChanged],
        fallbackMs: POLL_MS,
        debounceMs: 2000,
    });

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;

            if (
                event.key === '/' &&
                !['INPUT', 'TEXTAREA', 'SELECT'].includes(target?.tagName ?? '')
            ) {
                event.preventDefault();
                searchRef.current?.focus();
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, []);

    const apply = (next: Partial<Filters>) => {
        const merged = { ...filters, q: query, ...next };
        const params: Record<string, string> = {};

        if (merged.q) params.q = merged.q;
        if (merged.low) params.low = '1';
        if (merged.supplier !== null) params.supplier = String(merged.supplier);

        router.get('/staff/inventory', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['ingredients', 'filters'],
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    useEffect(() => {
        if (query === filters.q) {
            return;
        }

        const timer = window.setTimeout(() => apply({ q: query }), 300);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query]);

    const filtered = Boolean(
        filters.q || filters.low || filters.supplier !== null,
    );

    return (
        <InventoryShell
            active="ingredients"
            lowCount={lowCount}
            actions={
                abilities.manage && (
                    <Button size="lg" onClick={() => setCreating(true)}>
                        <Plus /> {t('staff_inventory.ingredient.add')}
                    </Button>
                )
            }
            toolbar={
                <div className="flex flex-wrap items-end gap-3">
                    <div className="relative min-w-56 flex-1 md:max-w-sm">
                        <Label htmlFor="ing-search" className="sr-only">
                            {t('staff_inventory.search')}
                        </Label>
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-muted"
                            aria-hidden="true"
                        />
                        <Input
                            id="ing-search"
                            ref={searchRef}
                            type="search"
                            value={query}
                            placeholder={t(
                                'staff_inventory.search_placeholder',
                            )}
                            aria-keyshortcuts="/"
                            className="min-h-11 pl-9"
                            onChange={(event) => setQuery(event.target.value)}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label
                            htmlFor="ing-supplier-filter"
                            className="sr-only"
                        >
                            {t('staff_inventory.filters.supplier')}
                        </Label>
                        <Select
                            value={
                                filters.supplier === null
                                    ? 'all'
                                    : String(filters.supplier)
                            }
                            onValueChange={(value) =>
                                apply({
                                    supplier:
                                        value === 'all' ? null : Number(value),
                                })
                            }
                        >
                            <SelectTrigger
                                id="ing-supplier-filter"
                                className="min-h-11 w-52"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    {t('staff_inventory.filters.all_suppliers')}
                                </SelectItem>
                                {suppliers.map((supplier) => (
                                    <SelectItem
                                        key={supplier.id}
                                        value={String(supplier.id)}
                                    >
                                        {supplier.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <Label
                        htmlFor="ing-low"
                        className="flex min-h-11 cursor-pointer items-center gap-2 text-sm"
                    >
                        <Switch
                            id="ing-low"
                            checked={filters.low}
                            onCheckedChange={(on) => apply({ low: on })}
                        />
                        {t('staff_inventory.filters.low_only')}
                    </Label>
                    <p className="hidden text-sm text-ink-muted lg:block">
                        {t('staff_inventory.shortcut_search')}
                    </p>
                </div>
            }
        >
            <div
                className={cn(
                    'grid gap-3',
                    summary.value === undefined ? 'grid-cols-2' : 'grid-cols-3',
                )}
            >
                <StatTile
                    label={t('staff_inventory.summary.ingredients')}
                    value={summary.count}
                />
                <StatTile
                    label={t('staff_inventory.summary.low')}
                    value={summary.low}
                    tone={summary.low > 0 ? 'bad' : 'good'}
                    hint={
                        summary.low > 0
                            ? t('staff_inventory.summary.low_hint')
                            : t('staff_inventory.summary.low_none')
                    }
                />
                {summary.value !== undefined && (
                    <StatTile
                        label={t('staff_inventory.summary.value')}
                        value={formatMoney(summary.value, { compact: true })}
                    />
                )}
            </div>

            <Deferred data="ingredients" fallback={<RowsSkeleton />}>
                {ingredients && ingredients.length === 0 ? (
                    <div className="rounded-lg border border-line bg-surface">
                        <EmptyState
                            title={
                                filtered
                                    ? t('staff_inventory.empty.filtered_title')
                                    : t('staff_inventory.empty.title')
                            }
                            description={
                                filtered
                                    ? t('staff_inventory.empty.filtered')
                                    : t('staff_inventory.empty.body')
                            }
                            action={
                                filtered ? (
                                    <Button
                                        variant="outline"
                                        onClick={() => {
                                            setQuery('');
                                            apply({
                                                q: '',
                                                low: false,
                                                supplier: null,
                                            });
                                        }}
                                    >
                                        <X />{' '}
                                        {t('staff_inventory.filters.clear')}
                                    </Button>
                                ) : undefined
                            }
                        />
                    </div>
                ) : (
                    <div
                        className={cn(
                            'overflow-hidden rounded-lg border border-line bg-surface transition-opacity',
                            loading && 'opacity-60',
                        )}
                        aria-busy={loading}
                    >
                        <div
                            className={cn(
                                'sticky top-0 z-10 hidden gap-4 border-b border-line bg-clay px-4 py-2 text-label text-ink-muted uppercase md:grid',
                                gridCols,
                            )}
                            aria-hidden="true"
                        >
                            <span>
                                {t('staff_inventory.columns.ingredient')}
                            </span>
                            <span>{t('staff_inventory.columns.stock')}</span>
                            {showValue && (
                                <span className="text-right">
                                    {t('staff_inventory.columns.value')}
                                </span>
                            )}
                            <span>
                                {t('staff_inventory.columns.last_movement')}
                            </span>
                            <span className="text-right">
                                {t('staff_inventory.columns.actions')}
                            </span>
                        </div>
                        <ul className="divide-y divide-line">
                            {(ingredients ?? []).map((row) => (
                                <li
                                    key={row.id}
                                    className={cn(
                                        'grid gap-3 px-4 py-3 md:items-center md:gap-4',
                                        gridCols,
                                        row.is_low && 'bg-chilli-soft/50',
                                    )}
                                >
                                    <div className="min-w-0">
                                        <p className="flex flex-wrap items-center gap-2 font-semibold">
                                            {row.name}
                                            {row.is_low && (
                                                <Badge variant="destructive">
                                                    {t('staff_inventory.low')}
                                                </Badge>
                                            )}
                                        </p>
                                        <p className="text-sm text-ink-muted">
                                            {row.supplier?.name ??
                                                t(
                                                    'staff_inventory.no_supplier',
                                                )}
                                        </p>
                                    </div>
                                    <div className="grid gap-1.5">
                                        <p className="flex items-baseline justify-between gap-2 text-sm tabular-nums">
                                            <span
                                                className={cn(
                                                    'font-semibold',
                                                    row.is_low && 'text-chilli',
                                                )}
                                            >
                                                {formatQuantity(
                                                    row.stock,
                                                    row.unit,
                                                )}
                                            </span>
                                            <span className="text-ink-muted">
                                                {t('staff_inventory.par', {
                                                    quantity: formatQuantity(
                                                        row.par_level,
                                                        row.unit,
                                                    ),
                                                })}
                                            </span>
                                        </p>
                                        <StockBar ingredient={row} />
                                    </div>
                                    {row.value !== undefined && (
                                        <p className="flex justify-between text-sm tabular-nums md:block md:text-right">
                                            <span className="text-ink-muted md:hidden">
                                                {t(
                                                    'staff_inventory.columns.value',
                                                )}
                                            </span>
                                            {formatMoney(row.value)}
                                        </p>
                                    )}
                                    <p className="flex justify-between text-sm text-ink-muted md:block">
                                        <span className="md:hidden">
                                            {t(
                                                'staff_inventory.columns.last_movement',
                                            )}
                                        </span>
                                        <span>
                                            {row.last_movement_at ? (
                                                <>
                                                    {row.last_movement_type && (
                                                        <span className="mr-1">
                                                            {t(
                                                                `staff_inventory.type.${row.last_movement_type}`,
                                                            )}
                                                        </span>
                                                    )}
                                                    {when(row.last_movement_at)}
                                                </>
                                            ) : (
                                                t('staff_inventory.never')
                                            )}
                                        </span>
                                    </p>
                                    <div className="flex flex-wrap justify-end gap-2">
                                        <Button
                                            size="sm"
                                            variant={
                                                row.is_low
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            className="min-h-11"
                                            disabled={!online}
                                            onClick={() =>
                                                setStock({
                                                    mode: 'in',
                                                    ingredient: row,
                                                })
                                            }
                                        >
                                            <PackagePlus />{' '}
                                            {t(
                                                'staff_inventory.actions.stock_in',
                                            )}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            className="min-h-11"
                                            disabled={!online}
                                            onClick={() =>
                                                setStock({
                                                    mode: 'adjust',
                                                    ingredient: row,
                                                })
                                            }
                                        >
                                            {t('staff_inventory.actions.count')}
                                        </Button>
                                        {abilities.manage && (
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                aria-label={t(
                                                    'staff_inventory.actions.edit',
                                                    { name: row.name },
                                                )}
                                                onClick={() => setEditing(row)}
                                            >
                                                <Pencil />
                                            </Button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </Deferred>

            <IngredientDialog
                open={creating || editing !== null}
                ingredient={editing}
                suppliers={suppliers}
                onClose={() => {
                    setCreating(false);
                    setEditing(null);
                }}
            />
            <StockDialog
                mode={stock?.mode ?? null}
                ingredient={stock?.ingredient ?? null}
                onClose={() => setStock(null)}
            />
        </InventoryShell>
    );
}
