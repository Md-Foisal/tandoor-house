import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ErrorState, useOnline } from '@/components/brand/states';
import { StaffPageHeader } from '@/components/staff/page-header';
import { AutomationsSection } from '@/components/staff-settings/automations-section';
import { HolidaysSection } from '@/components/staff-settings/holidays-section';
import { HoursSection } from '@/components/staff-settings/hours-section';
import { HygieneSection } from '@/components/staff-settings/hygiene-section';
import { NotificationsSection } from '@/components/staff-settings/notifications-section';
import { ReceiptsSection } from '@/components/staff-settings/receipts-section';
import { SettingsSkeleton } from '@/components/staff-settings/settings-skeleton';
import { TaxSection } from '@/components/staff-settings/tax-section';
import type {
    DayHours,
    Hygiene,
    Notifications,
    OpenNow,
    Receipts,
    SectionKey,
    SettingsContext,
    Tax,
    Zone,
} from '@/components/staff-settings/types';
import { sectionKeys } from '@/components/staff-settings/types';
import { ZonesSection } from '@/components/staff-settings/zones-section';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Props = {
    section: SectionKey | null;
    can_edit_money: boolean;
    hours: DayHours[];
    open_now: OpenNow;
    holidays: string[];
    next_year: { year: number; dates: string[] };
    zones: Zone[];
    tax: Tax;
    receipts: Receipts;
    notifications: Notifications;
    hygiene: Hygiene;
    automations: Record<string, boolean>;
};

export default function Settings(props: Props) {
    const t = useT();
    const online = useOnline();
    const [failed, setFailed] = useState(false);
    const [loading, setLoading] = useState(false);
    // Phones show the list until a section is chosen; wider screens show hours first.
    const active: SectionKey = props.section ?? 'hours';
    const context: SettingsContext = {
        online,
        canEditMoney: props.can_edit_money,
    };

    useEffect(() => {
        const off = [
            router.on('httpException', () => setFailed(true)),
            router.on('networkError', () => setFailed(true)),
            router.on('success', () => setFailed(false)),
        ];

        return () => off.forEach((fn) => fn());
    }, []);

    const choose = (key: SectionKey | null) => {
        setLoading(true);
        router.get('/staff/settings', key ? { section: key } : {}, {
            only: ['section'],
            preserveState: true,
            replace: false,
            onFinish: () => setLoading(false),
        });
    };

    const body = () => {
        switch (active) {
            case 'hours':
                return (
                    <HoursSection
                        hours={props.hours}
                        openNow={props.open_now}
                        context={context}
                    />
                );
            case 'holidays':
                return (
                    <HolidaysSection
                        dates={props.holidays}
                        nextYear={props.next_year}
                        context={context}
                    />
                );
            case 'zones':
                return <ZonesSection zones={props.zones} context={context} />;
            case 'tax':
                return <TaxSection tax={props.tax} context={context} />;
            case 'receipts':
                return (
                    <ReceiptsSection
                        receipts={props.receipts}
                        context={context}
                    />
                );
            case 'notifications':
                return (
                    <NotificationsSection
                        notifications={props.notifications}
                        context={context}
                    />
                );
            case 'hygiene':
                return (
                    <HygieneSection hygiene={props.hygiene} context={context} />
                );
            case 'automations':
                return (
                    <AutomationsSection
                        automations={props.automations}
                        context={context}
                    />
                );
        }
    };

    return (
        <>
            <Head title={t('staff_settings.title')} />
            <StaffPageHeader
                title={t('staff_settings.title')}
                description={t('staff_settings.description')}
            />

            {!online && (
                <p
                    role="status"
                    className="border-b border-line bg-clay px-4 py-2 text-sm md:px-6"
                >
                    {t('staff_settings.states.offline')}
                </p>
            )}

            <div className="mx-auto grid w-full max-w-6xl grid-cols-1 gap-6 p-4 md:grid-cols-[16rem_1fr] md:p-6">
                <nav
                    aria-label={t('staff_settings.nav_label')}
                    className={cn(
                        'min-w-0 md:block md:self-start',
                        props.section !== null && 'hidden',
                    )}
                >
                    <ul className="grid gap-1">
                        {sectionKeys.map((key) => (
                            <li key={key}>
                                <a
                                    href={`/staff/settings?section=${key}`}
                                    aria-current={
                                        props.section === key ||
                                        (props.section === null &&
                                            key === 'hours')
                                            ? 'page'
                                            : undefined
                                    }
                                    onClick={(event) => {
                                        event.preventDefault();
                                        choose(key);
                                    }}
                                    className={cn(
                                        'flex min-h-14 items-center justify-between gap-3 rounded-md border-2 px-4 py-2',
                                        active === key
                                            ? 'border-line-strong bg-surface md:border-ink md:bg-ink md:text-paper'
                                            : 'border-line-strong bg-surface hover:bg-clay md:border-transparent',
                                    )}
                                >
                                    <span>
                                        <span className="block font-semibold">
                                            {t(
                                                `staff_settings.sections.${key}.title`,
                                            )}
                                        </span>
                                        <span
                                            className={cn(
                                                'block text-sm',
                                                active === key
                                                    ? 'text-ink-muted md:text-paper/80'
                                                    : 'text-ink-muted',
                                            )}
                                        >
                                            {t(
                                                `staff_settings.sections.${key}.summary`,
                                            )}
                                        </span>
                                    </span>
                                    <ChevronRight
                                        aria-hidden="true"
                                        className="size-5 shrink-0 md:hidden"
                                    />
                                </a>
                            </li>
                        ))}
                    </ul>
                </nav>

                <div
                    className={cn(
                        'min-w-0',
                        props.section === null && 'hidden md:block',
                    )}
                >
                    {props.section !== null && (
                        <button
                            type="button"
                            onClick={() => choose(null)}
                            className="mb-3 inline-flex min-h-11 items-center gap-1 font-semibold md:hidden"
                        >
                            <ChevronLeft
                                aria-hidden="true"
                                className="size-5"
                            />
                            {t('staff_settings.back')}
                        </button>
                    )}
                    {failed ? (
                        <ErrorState
                            title={t('staff_settings.states.error_title')}
                            onRetry={() => router.reload()}
                        />
                    ) : loading ? (
                        <SettingsSkeleton />
                    ) : (
                        <div key={active}>{body()}</div>
                    )}
                </div>
            </div>
        </>
    );
}
