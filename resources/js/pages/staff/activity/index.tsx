import { Head, InfiniteScroll, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { EmptyState, ErrorState, useOnline } from '@/components/brand/states';
import { EntryRow } from '@/components/staff-activity/entry-row';
import { FeedSkeleton } from '@/components/staff-activity/feed-skeleton';
import { Filters } from '@/components/staff-activity/filters';
import type {
    ActivityEntry,
    ActivityFilters,
    Option,
} from '@/components/staff-activity/types';
import { dayLabel, groupByDay } from '@/components/staff-activity/types';
import { StaffPageHeader } from '@/components/staff/page-header';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const BASE = '/staff/activity';

function query(filters: ActivityFilters): Record<string, string> {
    const params: Record<string, string> = {};

    for (const [key, value] of Object.entries(filters)) {
        if (value) params[key] = value;
    }

    return params;
}

export default function ActivityIndex({
    feed,
    filters,
    families,
    people,
    today,
    retention_months,
}: {
    feed: { data: ActivityEntry[] };
    filters: ActivityFilters;
    families: Option[];
    people: Option[];
    today: string;
    retention_months: number;
}) {
    const t = useT();
    const online = useOnline();
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const filtered = Object.values(filters).some(Boolean);
    const groups = groupByDay(feed.data);

    useEffect(() => {
        const off = [
            router.on('httpException', () => setFailed(true)),
            router.on('networkError', () => setFailed(true)),
            router.on('success', () => setFailed(false)),
        ];

        return () => off.forEach((stop) => stop());
    }, []);

    const go = useCallback(
        (next: Partial<ActivityFilters>) => {
            router.get(BASE, query({ ...filters, ...next }), {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['feed', 'filters'],
                reset: ['feed'],
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            });
        },
        [filters],
    );

    const exportHref = `${BASE}/export${
        Object.keys(query(filters)).length
            ? `?${new URLSearchParams(query(filters)).toString()}`
            : ''
    }`;

    return (
        <>
            <Head title={t('staff_activity.title')} />
            <StaffPageHeader
                title={t('staff_activity.title')}
                description={t('staff_activity.description')}
                actions={
                    online ? (
                        <Button variant="outline" asChild>
                            <a href={exportHref} download>
                                <Download /> {t('staff_activity.export')}
                            </a>
                        </Button>
                    ) : (
                        <Button
                            variant="outline"
                            disabled
                            title={t('staff_activity.export_offline')}
                        >
                            <Download /> {t('staff_activity.export')}
                        </Button>
                    )
                }
            >
                <Filters
                    filters={filters}
                    families={families}
                    people={people}
                    onChange={go}
                />
            </StaffPageHeader>

            <div className="grid gap-4 px-4 py-5 md:px-6">
                {!online && (
                    <p
                        role="status"
                        className="rounded-md bg-turmeric-soft px-3 py-2 text-sm text-turmeric"
                    >
                        {t('staff_activity.states.offline')}
                    </p>
                )}
                {failed && (
                    <ErrorState
                        title={t('staff_activity.states.error')}
                        onRetry={() => go({})}
                    />
                )}

                {loading && groups.length === 0 ? (
                    <FeedSkeleton />
                ) : groups.length === 0 ? (
                    <div className="rounded-lg border border-line bg-surface">
                        <EmptyState
                            title={t(
                                filtered
                                    ? 'staff_activity.states.empty_filtered_title'
                                    : 'staff_activity.states.empty_title',
                            )}
                            description={t(
                                filtered
                                    ? 'staff_activity.states.empty_filtered'
                                    : 'staff_activity.states.empty',
                            )}
                        />
                    </div>
                ) : (
                    <div
                        className={cn(
                            'transition-opacity',
                            loading && 'opacity-60',
                        )}
                        aria-busy={loading}
                        aria-label={t('staff_activity.feed_label')}
                    >
                        <InfiniteScroll
                            data="feed"
                            manual
                            preserveUrl
                            onlyNext
                            next={({ fetch, loadingNext, hasNext }) =>
                                hasNext ? (
                                    <div className="flex justify-center pt-4">
                                        <Button
                                            variant="outline"
                                            disabled={loadingNext || !online}
                                            onClick={fetch}
                                        >
                                            {loadingNext
                                                ? t(
                                                      'staff_activity.loading_older',
                                                  )
                                                : t(
                                                      'staff_activity.load_older',
                                                  )}
                                        </Button>
                                    </div>
                                ) : (
                                    <p className="pt-4 text-center text-sm text-ink-muted">
                                        {t('staff_activity.end')}
                                    </p>
                                )
                            }
                        >
                            <div className="grid gap-5">
                                {groups.map((group) => (
                                    <section key={group.day}>
                                        <h2 className="sticky top-0 z-10 bg-paper/95 py-2 text-label text-ink-muted uppercase">
                                            {dayLabel(group.day, today, t)}
                                        </h2>
                                        <ul className="divide-y divide-line overflow-hidden rounded-lg border border-line bg-surface">
                                            {group.entries.map((entry) => (
                                                <EntryRow
                                                    key={entry.id}
                                                    entry={entry}
                                                    families={families}
                                                />
                                            ))}
                                        </ul>
                                    </section>
                                ))}
                            </div>
                        </InfiniteScroll>
                    </div>
                )}

                <p className="text-sm text-ink-muted">
                    {t('staff_activity.retention', {
                        months: retention_months,
                    })}
                </p>
            </div>
        </>
    );
}
