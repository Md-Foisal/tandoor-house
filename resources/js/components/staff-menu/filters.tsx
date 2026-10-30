import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useT } from '@/lib/i18n';
import type { CategoryRow, Filters, StationOption } from './types';

const ANY = 'any';

export function visit(next: Filters, onFinish?: () => void) {
    const query: Record<string, string | number> = {};

    if (next.q !== '') query.q = next.q;
    if (next.category) query.category = next.category;
    if (next.availability) query.availability = next.availability;
    if (next.diet) query.diet = next.diet;
    if (next.station) query.station = next.station;

    router.get('/staff/menu', query, {
        only: ['items', 'filters'],
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onFinish,
    });
}

export function MenuFilters({
    filters,
    categories,
    stations,
    searchRef,
    onLoading,
}: {
    filters: Filters;
    categories: CategoryRow[];
    stations: StationOption[];
    searchRef: React.RefObject<HTMLInputElement | null>;
    onLoading: (loading: boolean) => void;
}) {
    const t = useT();
    const [q, setQ] = useState(filters.q);
    const timer = useRef<number | undefined>(undefined);
    const active =
        filters.q !== '' ||
        filters.category !== null ||
        filters.availability !== null ||
        filters.diet !== null ||
        filters.station !== null;

    useEffect(() => () => window.clearTimeout(timer.current), []);

    const apply = (patch: Partial<Filters>) => {
        onLoading(true);
        visit({ ...filters, q, ...patch }, () => onLoading(false));
    };

    const onSearch = (value: string) => {
        setQ(value);
        window.clearTimeout(timer.current);
        timer.current = window.setTimeout(() => apply({ q: value }), 300);
    };

    const pick = (value: string) => (value === ANY ? null : value);

    return (
        <form
            role="search"
            className="grid gap-2 sm:grid-cols-2 lg:flex lg:flex-wrap lg:items-center"
            onSubmit={(event) => {
                event.preventDefault();
                window.clearTimeout(timer.current);
                apply({ q });
            }}
        >
            <div className="relative sm:col-span-2 lg:w-64">
                <Search
                    aria-hidden="true"
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-muted"
                />
                <Input
                    ref={searchRef}
                    type="search"
                    value={q}
                    placeholder={t('staff_menu.filters.search_placeholder')}
                    aria-label={t('staff_menu.filters.search')}
                    className="h-11 pl-9"
                    onChange={(event) => onSearch(event.target.value)}
                />
            </div>

            <Select
                value={filters.category ? String(filters.category) : ANY}
                onValueChange={(v) =>
                    apply({ category: v === ANY ? null : Number(v) })
                }
            >
                <SelectTrigger
                    className="h-11 w-full lg:w-48"
                    aria-label={t('staff_menu.filters.category')}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ANY}>
                        {t('staff_menu.filters.all_categories')}
                    </SelectItem>
                    {categories.map((c) => (
                        <SelectItem key={c.id} value={String(c.id)}>
                            {c.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select
                value={filters.availability ?? ANY}
                onValueChange={(v) =>
                    apply({ availability: pick(v) as Filters['availability'] })
                }
            >
                <SelectTrigger
                    className="h-11 w-full lg:w-40"
                    aria-label={t('staff_menu.filters.availability')}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ANY}>
                        {t('staff_menu.filters.any')}
                    </SelectItem>
                    <SelectItem value="available">
                        {t('staff_menu.filters.available')}
                    </SelectItem>
                    <SelectItem value="sold_out">
                        {t('staff_menu.filters.sold_out')}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select
                value={filters.diet ?? ANY}
                onValueChange={(v) =>
                    apply({ diet: pick(v) as Filters['diet'] })
                }
            >
                <SelectTrigger
                    className="h-11 w-full lg:w-40"
                    aria-label={t('staff_menu.filters.diet')}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ANY}>
                        {t('staff_menu.filters.any_diet')}
                    </SelectItem>
                    <SelectItem value="vegetarian">
                        {t('ui.vegetarian')}
                    </SelectItem>
                    <SelectItem value="vegan">{t('ui.vegan')}</SelectItem>
                </SelectContent>
            </Select>

            <Select
                value={filters.station ?? ANY}
                onValueChange={(v) => apply({ station: pick(v) })}
            >
                <SelectTrigger
                    className="h-11 w-full lg:w-44"
                    aria-label={t('staff_menu.filters.station')}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ANY}>
                        {t('staff_menu.filters.all_stations')}
                    </SelectItem>
                    {stations.map((s) => (
                        <SelectItem key={s.value} value={s.value}>
                            {s.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {active && (
                <Button
                    type="button"
                    variant="ghost"
                    className="h-11 justify-self-start"
                    onClick={() => {
                        setQ('');
                        apply({
                            q: '',
                            category: null,
                            availability: null,
                            diet: null,
                            station: null,
                        });
                    }}
                >
                    <X aria-hidden="true" />
                    {t('staff_menu.filters.clear')}
                </Button>
            )}
        </form>
    );
}
