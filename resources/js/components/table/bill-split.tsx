import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { BillProps } from './types';

type Options = BillProps['options'];

const PEOPLE = Array.from({ length: 11 }, (_, index) => index + 2);

/**
 * Splits are worked out on the server so the shares always add up to the
 * bill to the penny; this component only asks and shows.
 */
export function BillSplit({
    token,
    options,
    units,
    split,
}: {
    token: string;
    options: Options;
    units: BillProps['units'];
    split: BillProps['split'];
}) {
    const t = useT();
    const [guest, setGuest] = useState(0);
    const [loading, setLoading] = useState(false);

    const go = (next: Partial<Options>) => {
        const merged = { ...options, ...next };
        const query: Record<string, unknown> = {
            service: merged.service ? 1 : 0,
            mode: merged.mode === 'none' ? undefined : merged.mode,
        };

        if (merged.mode === 'even') {
            query.people = merged.people;
        }

        if (merged.mode === 'items') {
            query.guests = merged.guests;
            query.claims = merged.claims;
        }

        router.get(`/t/${token}/bill`, query as Record<string, never>, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['options', 'split', 'totals'],
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    const claim = (key: string, checked: boolean) => {
        const claims = { ...options.claims };

        if (checked) {
            claims[key] = guest;
        } else {
            delete claims[key];
        }

        go({ claims });
    };

    return (
        <section aria-labelledby="split-title" className="flex flex-col gap-4">
            <h2
                id="split-title"
                className="font-display text-display-md text-ink"
            >
                {t('table.bill.split_title')}
            </h2>
            <Tabs
                value={options.mode}
                onValueChange={(mode) => go({ mode: mode as Options['mode'] })}
            >
                <TabsList>
                    <TabsTrigger value="none">
                        {t('table.bill.split_none')}
                    </TabsTrigger>
                    <TabsTrigger value="even">
                        {t('table.bill.split_even')}
                    </TabsTrigger>
                    <TabsTrigger value="items">
                        {t('table.bill.split_items')}
                    </TabsTrigger>
                </TabsList>
            </Tabs>

            {options.mode === 'even' && (
                <div className="flex items-center gap-3">
                    <Label htmlFor="people">{t('table.bill.people')}</Label>
                    <Select
                        value={String(options.people)}
                        onValueChange={(value) => go({ people: Number(value) })}
                    >
                        <SelectTrigger id="people" className="w-24">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {PEOPLE.map((count) => (
                                <SelectItem key={count} value={String(count)}>
                                    {count}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            )}

            {options.mode === 'items' && (
                <div className="flex flex-col gap-4">
                    <div className="flex items-center gap-3">
                        <Label htmlFor="guests">{t('table.bill.people')}</Label>
                        <Select
                            value={String(options.guests)}
                            onValueChange={(value) => {
                                setGuest(0);
                                go({ guests: Number(value), claims: {} });
                            }}
                        >
                            <SelectTrigger id="guests" className="w-24">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {PEOPLE.map((count) => (
                                    <SelectItem
                                        key={count}
                                        value={String(count)}
                                    >
                                        {count}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div
                        role="group"
                        aria-label={t('table.bill.guest_pick')}
                        className="flex flex-wrap gap-2"
                    >
                        {Array.from({ length: options.guests }, (_, index) => (
                            <button
                                key={index}
                                type="button"
                                aria-pressed={guest === index}
                                onClick={() => setGuest(index)}
                                className={cn(
                                    'min-h-11 rounded-full border-[1.5px] px-4 font-semibold outline-hidden focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember focus-visible:outline-solid',
                                    guest === index
                                        ? 'border-ember bg-ember-soft text-ember'
                                        : 'border-line-strong text-ink hover:bg-clay',
                                )}
                            >
                                {t('table.bill.guest_tab', { n: index + 1 })}
                            </button>
                        ))}
                    </div>
                    <ul className="divide-y divide-line border-y border-line">
                        {units.map((unit) => {
                            const owner = options.claims[unit.key];
                            const taken =
                                owner !== undefined && owner !== guest;
                            const id = `unit-${unit.key}`;

                            return (
                                <li
                                    key={unit.key}
                                    className="flex items-center gap-3 py-2"
                                >
                                    <Checkbox
                                        id={id}
                                        checked={owner === guest}
                                        disabled={taken || loading}
                                        onCheckedChange={(checked) =>
                                            claim(unit.key, checked === true)
                                        }
                                    />
                                    <Label
                                        htmlFor={id}
                                        className="flex min-h-11 flex-1 flex-col justify-center font-normal"
                                    >
                                        <span className="text-ink">
                                            {unit.count > 1
                                                ? t('table.bill.unit', {
                                                      name: unit.name,
                                                      n: unit.number,
                                                      count: unit.count,
                                                  })
                                                : unit.name}
                                        </span>
                                        {taken && (
                                            <span className="text-sm text-ink-muted">
                                                {t('table.bill.taken_by', {
                                                    n: owner + 1,
                                                })}
                                            </span>
                                        )}
                                    </Label>
                                    <span className="text-ink tabular-nums">
                                        {formatMoney(unit.price)}
                                    </span>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            )}

            {split && (
                <div aria-live="polite" aria-busy={loading}>
                    <h3 className="text-title font-semibold text-ink">
                        {t('table.bill.shares_title')}
                    </h3>
                    <ul
                        className={cn(
                            'mt-2 divide-y divide-line border-y border-line',
                            loading && 'opacity-60',
                        )}
                    >
                        {split.shares.map((share, index) => (
                            <li
                                key={index}
                                className="flex items-baseline justify-between gap-4 py-3"
                            >
                                <span className="text-ink">
                                    {t('table.bill.share', { n: index + 1 })}
                                </span>
                                <span className="font-semibold text-ink tabular-nums">
                                    {formatMoney(share)}
                                </span>
                            </li>
                        ))}
                        {split.unclaimed > 0 && (
                            <li className="flex items-baseline justify-between gap-4 py-3 text-ink-muted">
                                <span>{t('table.bill.unclaimed')}</span>
                                <span className="tabular-nums">
                                    {formatMoney(split.unclaimed)}
                                </span>
                            </li>
                        )}
                    </ul>
                    <p className="mt-2 text-sm text-ink-muted">
                        {t('table.bill.rounding')}
                    </p>
                </div>
            )}
        </section>
    );
}
