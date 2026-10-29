import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import type { TeamTab, WeekInfo } from './types';

export function visitWeek(tab: TeamTab, week: string | null, only: string[]) {
    router.get('/staff/team', week === null ? { tab } : { tab, week }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        only,
    });
}

export function WeekNav({
    tab,
    week,
    only,
}: {
    tab: TeamTab;
    week: WeekInfo;
    only: string[];
}) {
    const t = useT();

    return (
        <div className="flex items-center gap-2">
            <Button
                type="button"
                variant="outline"
                size="icon"
                aria-label={t('staff_team.week.previous')}
                onClick={() => visitWeek(tab, week.previous, [...only, 'week'])}
            >
                <ChevronLeft aria-hidden="true" />
            </Button>
            <p
                className="min-w-40 text-center font-semibold tabular-nums"
                aria-live="polite"
            >
                {week.label}
            </p>
            <Button
                type="button"
                variant="outline"
                size="icon"
                aria-label={t('staff_team.week.next')}
                onClick={() => visitWeek(tab, week.next, [...only, 'week'])}
            >
                <ChevronRight aria-hidden="true" />
            </Button>
            {!week.is_current && (
                <Button
                    type="button"
                    variant="ghost"
                    onClick={() => visitWeek(tab, null, [...only, 'week'])}
                >
                    {t('staff_team.week.this_week')}
                </Button>
            )}
        </div>
    );
}
