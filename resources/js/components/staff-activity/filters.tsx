import { Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
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
import type { ActivityFilters, Option } from './types';

const ALL = 'all';

export function Filters({
    filters,
    families,
    people,
    onChange,
}: {
    filters: ActivityFilters;
    families: Option[];
    people: Option[];
    onChange: (next: Partial<ActivityFilters>) => void;
}) {
    const t = useT();
    const [search, setSearch] = useState(filters.q ?? '');
    const lastSent = useRef(filters.q ?? '');
    const filtered = Object.values(filters).some(Boolean);

    useEffect(() => {
        setSearch(filters.q ?? '');
        lastSent.current = filters.q ?? '';
    }, [filters.q]);

    useEffect(() => {
        if (search === lastSent.current) return;

        const timer = setTimeout(() => {
            lastSent.current = search;
            onChange({ q: search.trim() || null });
        }, 350);

        return () => clearTimeout(timer);
    }, [search, onChange]);

    return (
        <form
            role="search"
            aria-label={t('staff_activity.filters.label')}
            className="flex flex-wrap items-end gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                lastSent.current = search;
                onChange({ q: search.trim() || null });
            }}
        >
            <div className="grid min-w-0 grow gap-1.5 md:max-w-xs">
                <Label htmlFor="act-search">
                    {t('staff_activity.filters.search')}
                </Label>
                <div className="relative">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-muted"
                        aria-hidden="true"
                    />
                    <Input
                        id="act-search"
                        type="search"
                        value={search}
                        placeholder={t(
                            'staff_activity.filters.search_placeholder',
                        )}
                        className="min-h-11 pl-9"
                        onChange={(event) => setSearch(event.target.value)}
                    />
                </div>
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="act-family">
                    {t('staff_activity.filters.family')}
                </Label>
                <Select
                    value={filters.family ?? ALL}
                    onValueChange={(value) =>
                        onChange({ family: value === ALL ? null : value })
                    }
                >
                    <SelectTrigger id="act-family" className="min-h-11 w-40">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>
                            {t('staff_activity.filters.all_families')}
                        </SelectItem>
                        {families.map((family) => (
                            <SelectItem key={family.value} value={family.value}>
                                {family.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="act-person">
                    {t('staff_activity.filters.person')}
                </Label>
                <Select
                    value={filters.person ?? ALL}
                    onValueChange={(value) =>
                        onChange({ person: value === ALL ? null : value })
                    }
                >
                    <SelectTrigger id="act-person" className="min-h-11 w-44">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>
                            {t('staff_activity.filters.all_people')}
                        </SelectItem>
                        {people.map((person) => (
                            <SelectItem key={person.value} value={person.value}>
                                {person.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="act-from">
                    {t('staff_activity.filters.from')}
                </Label>
                <Input
                    id="act-from"
                    type="date"
                    value={filters.from ?? ''}
                    max={filters.to ?? undefined}
                    className="min-h-11 w-40"
                    onChange={(event) =>
                        onChange({ from: event.target.value || null })
                    }
                />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="act-to">{t('staff_activity.filters.to')}</Label>
                <Input
                    id="act-to"
                    type="date"
                    value={filters.to ?? ''}
                    min={filters.from ?? undefined}
                    className="min-h-11 w-40"
                    onChange={(event) =>
                        onChange({ to: event.target.value || null })
                    }
                />
            </div>
            {filtered && (
                <Button
                    type="button"
                    variant="ghost"
                    onClick={() => {
                        setSearch('');
                        onChange({
                            family: null,
                            person: null,
                            from: null,
                            to: null,
                            q: null,
                        });
                    }}
                >
                    <X /> {t('staff_activity.filters.clear')}
                </Button>
            )}
        </form>
    );
}
