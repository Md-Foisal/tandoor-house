import { Deferred, Head, usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useOnline } from '@/components/brand/states';
import { BreakdownSection } from '@/components/staff-reports/breakdown-section';
import { ItemsSection } from '@/components/staff-reports/items-section';
import { KitchenSection } from '@/components/staff-reports/kitchen-section';
import { OverviewSection } from '@/components/staff-reports/overview-section';
import { PeriodBar } from '@/components/staff-reports/period-bar';
import { ReportCardSkeleton } from '@/components/staff-reports/report-card';
import { TipsSection } from '@/components/staff-reports/tips-section';
import type {
    BreakdownData,
    ItemsData,
    KitchenData,
    OverviewData,
    ReportFilters,
    ReportLinks,
    TipsData,
    VatData,
} from '@/components/staff-reports/types';
import { VatSection } from '@/components/staff-reports/vat-section';
import { StaffPageHeader } from '@/components/staff/page-header';
import { Button } from '@/components/ui/button';
import { choice, useT } from '@/lib/i18n';

type Props = {
    filters: ReportFilters;
    range: {
        label: string;
        days: number;
        previous_label: string;
        max_days: number;
    };
    periods: string[];
    channels: string[];
    links: ReportLinks;
    overview?: OverviewData;
    breakdown?: BreakdownData;
    items?: ItemsData;
    kitchen?: KitchenData;
    vat?: VatData;
    tips?: TipsData;
};

export default function Reports({
    filters,
    range,
    periods,
    channels,
    links,
    overview,
    breakdown,
    items,
    kitchen,
    vat,
    tips,
}: Props) {
    const t = useT();
    const online = useOnline();
    const { errors } = usePage().props as { errors?: Record<string, string> };

    return (
        <>
            <Head title={t('staff_reports.title')} />
            <StaffPageHeader
                title={t('staff_reports.title')}
                description={t('staff_reports.description')}
                actions={
                    online ? (
                        <Button asChild variant="outline">
                            <a href={links.pdf}>
                                <Download />
                                {t('staff_reports.export.pdf')}
                            </a>
                        </Button>
                    ) : (
                        <Button
                            variant="outline"
                            disabled
                            title={t('staff_reports.export.offline')}
                        >
                            <Download />
                            {t('staff_reports.export.pdf')}
                        </Button>
                    )
                }
            >
                <PeriodBar
                    filters={filters}
                    periods={periods}
                    channels={channels}
                    maxDays={range.max_days}
                    errors={errors ?? {}}
                />
            </StaffPageHeader>

            <div className="mx-auto flex w-full max-w-[84rem] min-w-0 flex-col gap-6 px-4 py-6 md:px-6">
                <p className="text-sm text-ink-muted" aria-live="polite">
                    <span className="font-semibold text-ink">
                        {t('staff_reports.filters.showing', {
                            period: range.label,
                        })}
                    </span>{' '}
                    ·{' '}
                    {choice(
                        t('staff_reports.filters.days', { count: range.days }),
                        range.days,
                    )}{' '}
                    ·{' '}
                    {t('staff_reports.filters.compared', {
                        period: range.previous_label,
                    })}
                </p>

                <Deferred data="overview" fallback={<ReportCardSkeleton />}>
                    {overview && (
                        <OverviewSection
                            data={overview}
                            exportHref={links.export.overview}
                        />
                    )}
                </Deferred>
                <Deferred
                    data="breakdown"
                    fallback={<ReportCardSkeleton tall />}
                >
                    {breakdown && (
                        <BreakdownSection
                            data={breakdown}
                            exportHref={links.export.breakdown}
                        />
                    )}
                </Deferred>
                <Deferred data="items" fallback={<ReportCardSkeleton tall />}>
                    {items && (
                        <ItemsSection
                            data={items}
                            exportHref={links.export.items}
                        />
                    )}
                </Deferred>
                <Deferred data="kitchen" fallback={<ReportCardSkeleton />}>
                    {kitchen && (
                        <KitchenSection
                            data={kitchen}
                            exportHref={links.export.kitchen}
                        />
                    )}
                </Deferred>
                <Deferred data="vat" fallback={<ReportCardSkeleton />}>
                    {vat && (
                        <VatSection data={vat} exportHref={links.export.vat} />
                    )}
                </Deferred>
                <Deferred data="tips" fallback={<ReportCardSkeleton tall />}>
                    {tips && (
                        <TipsSection
                            data={tips}
                            exportHref={links.export.tips}
                        />
                    )}
                </Deferred>
            </div>
        </>
    );
}
