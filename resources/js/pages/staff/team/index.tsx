import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ErrorState, useOnline } from '@/components/brand/states';
import { StaffPageHeader } from '@/components/staff/page-header';
import { AttendanceTab } from '@/components/staff-team/attendance-tab';
import { PeopleTab } from '@/components/staff-team/people-tab';
import { RotaTab } from '@/components/staff-team/rota-tab';
import { TeamSkeleton } from '@/components/staff-team/team-skeleton';
import { TipsTab } from '@/components/staff-team/tips-tab';
import type {
    AttendancePerson,
    Person,
    Rota,
    TeamContext,
    TeamTab,
    Tips,
    WeekInfo,
} from '@/components/staff-team/types';
import { WeekNav } from '@/components/staff-team/week-nav';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Role } from '@/types';

const tabs: TeamTab[] = ['people', 'rota', 'attendance', 'tips'];

type Props = {
    tab: TeamTab;
    week: WeekInfo;
    me: { id: number; role: Role };
    assignable_roles: Role[];
    people: Person[] | null;
    rota: Rota | null;
    attendance: AttendancePerson[] | null;
    tips: Tips | null;
};

export default function Team(props: Props) {
    const t = useT();
    const online = useOnline();
    const [tab, setTab] = useState<TeamTab>(props.tab);
    const [failed, setFailed] = useState(false);

    useEffect(() => setTab(props.tab), [props.tab]);

    useEffect(() => {
        const off = [
            router.on('httpException', () => setFailed(true)),
            router.on('networkError', () => setFailed(true)),
            router.on('success', () => setFailed(false)),
        ];

        return () => off.forEach((fn) => fn());
    }, []);

    const choose = (next: TeamTab) => {
        setTab(next);
        setFailed(false);
        router.get(
            '/staff/team',
            next === 'people' ? {} : { tab: next, week: props.week.start },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ['tab', 'week', next],
            },
        );
    };

    const context: TeamContext = {
        me: props.me,
        assignableRoles: props.assignable_roles,
        week: props.week,
        online,
        reload: () => router.reload({ only: [tab] }),
    };
    const data = props[tab];
    const weekly = tab !== 'people';

    return (
        <>
            <Head title={t('staff_team.title')} />
            <StaffPageHeader
                title={t('staff_team.title')}
                description={t('staff_team.description')}
                actions={
                    weekly ? (
                        <WeekNav tab={tab} week={props.week} only={[tab]} />
                    ) : undefined
                }
            />

            {!online && (
                <p
                    role="status"
                    className="border-b border-line bg-clay px-4 py-2 text-sm md:px-6"
                >
                    {t('staff_team.states.offline')}
                </p>
            )}

            <div className="sticky top-0 z-20 border-b border-line bg-paper px-4 py-2 md:px-6">
                <div
                    role="tablist"
                    aria-label={t('staff_team.title')}
                    className="flex gap-2 overflow-x-auto pb-1"
                >
                    {tabs.map((key) => (
                        <button
                            key={key}
                            id={`team-tab-${key}`}
                            role="tab"
                            type="button"
                            aria-selected={tab === key}
                            aria-controls="team-panel"
                            onClick={() => choose(key)}
                            className={cn(
                                'inline-flex min-h-12 shrink-0 items-center rounded-full border-2 px-5 text-sm font-bold',
                                tab === key
                                    ? 'border-ink bg-ink text-paper'
                                    : 'border-line-strong bg-surface text-ink',
                            )}
                        >
                            {t(`staff_team.tabs.${key}`)}
                        </button>
                    ))}
                </div>
            </div>

            <div
                id="team-panel"
                role="tabpanel"
                aria-labelledby={`team-tab-${tab}`}
                className="mx-auto grid max-w-6xl gap-4 p-4 md:p-6"
            >
                {failed ? (
                    <ErrorState
                        title={t('staff_team.states.error_title')}
                        onRetry={context.reload}
                    />
                ) : data === null || data === undefined ? (
                    <TeamSkeleton />
                ) : tab === 'people' ? (
                    <PeopleTab
                        people={props.people as Person[]}
                        context={context}
                    />
                ) : tab === 'rota' ? (
                    <RotaTab
                        rota={props.rota as Rota}
                        weekStart={props.week.start}
                        context={context}
                    />
                ) : tab === 'attendance' ? (
                    <AttendanceTab
                        rows={props.attendance as AttendancePerson[]}
                        context={context}
                    />
                ) : (
                    <TipsTab tips={props.tips as Tips} context={context} />
                )}
            </div>
        </>
    );
}
