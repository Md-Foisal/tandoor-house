import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useOnline } from '@/components/brand/states';
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
import type { ReportFilters } from './types';

const ALL = 'all';

function visit(params: Record<string, string | null>) {
    const query = Object.fromEntries(
        Object.entries(params).filter(([, value]) => value),
    ) as Record<string, string>;

    router.get('/staff/reports', query, {
        preserveScroll: true,
        replace: true,
    });
}

/**
 * Period and channel live in the URL, so a report can be bookmarked or sent
 * to the accountant. Fixed periods apply at once; a custom range waits for
 * the button.
 */
export function PeriodBar({
    filters,
    periods,
    channels,
    maxDays,
    errors,
}: {
    filters: ReportFilters;
    periods: string[];
    channels: string[];
    maxDays: number;
    errors: Record<string, string>;
}) {
    const t = useT();
    const online = useOnline();
    const [period, setPeriod] = useState(filters.period);
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    useEffect(() => {
        setPeriod(filters.period);
        setFrom(filters.from);
        setTo(filters.to);
    }, [filters.period, filters.from, filters.to]);

    const channel = filters.channel ?? ALL;
    const channelParam = (value: string) => (value === ALL ? null : value);

    const choosePeriod = (value: string) => {
        setPeriod(value);

        if (value !== 'custom') {
            visit({ period: value, channel: filters.channel });
        }
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        visit({ period, from, to, channel: filters.channel });
    };

    const custom = period === 'custom';
    const dateError = errors.from ?? errors.to ?? errors.period;

    return (
        <form
            onSubmit={submit}
            aria-label={t('staff_reports.filters.label')}
            className="flex flex-wrap items-end gap-3"
        >
            <div className="flex flex-col gap-1.5">
                <Label htmlFor="report-period">
                    {t('staff_reports.filters.period')}
                </Label>
                <Select value={period} onValueChange={choosePeriod}>
                    <SelectTrigger id="report-period" className="min-w-44">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {periods.map((key) => (
                            <SelectItem key={key} value={key}>
                                {t(`staff_reports.filters.periods.${key}`)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            {custom && (
                <>
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="report-from">
                            {t('staff_reports.filters.from')}
                        </Label>
                        <Input
                            id="report-from"
                            type="date"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                            aria-invalid={Boolean(errors.from)}
                            aria-describedby={
                                dateError ? 'report-date-error' : undefined
                            }
                            className="w-40"
                        />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="report-to">
                            {t('staff_reports.filters.to')}
                        </Label>
                        <Input
                            id="report-to"
                            type="date"
                            value={to}
                            min={from}
                            onChange={(e) => setTo(e.target.value)}
                            aria-invalid={Boolean(errors.to)}
                            aria-describedby={
                                dateError ? 'report-date-error' : undefined
                            }
                            className="w-40"
                        />
                    </div>
                    <Button
                        type="submit"
                        disabled={!online || !from || !to}
                        title={
                            online
                                ? undefined
                                : t('staff_reports.export.offline')
                        }
                    >
                        {t('staff_reports.filters.apply')}
                    </Button>
                </>
            )}

            <div className="flex flex-col gap-1.5">
                <Label htmlFor="report-channel">
                    {t('staff_reports.filters.channel')}
                </Label>
                <Select
                    value={channel}
                    onValueChange={(value) =>
                        visit({
                            period: filters.period,
                            from:
                                filters.period === 'custom'
                                    ? filters.from
                                    : null,
                            to: filters.period === 'custom' ? filters.to : null,
                            channel: channelParam(value),
                        })
                    }
                >
                    <SelectTrigger id="report-channel" className="min-w-44">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>
                            {t('staff_reports.all_channels')}
                        </SelectItem>
                        {channels.map((key) => (
                            <SelectItem key={key} value={key}>
                                {t(`staff.channel.${key}`)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            {custom && (
                <p className="basis-full text-sm text-ink-muted">
                    {t('staff_reports.filters.max_hint', { days: maxDays })}
                </p>
            )}
            {dateError && (
                <p
                    id="report-date-error"
                    role="alert"
                    className="basis-full text-sm font-semibold text-chilli"
                >
                    {dateError}
                </p>
            )}
        </form>
    );
}
