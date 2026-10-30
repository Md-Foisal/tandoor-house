import { Head, router } from '@inertiajs/react';
import type { RequestPayload } from '@inertiajs/core';
import { useEffect, useRef, useState } from 'react';
import { EmptyState, ErrorState, useOnline } from '@/components/brand/states';
import { StaffPageHeader } from '@/components/staff/page-header';
import { HideDialog } from '@/components/staff-reviews/hide-dialog';
import {
    RemoveReplyDialog,
    ReplyDialog,
} from '@/components/staff-reviews/reply-dialog';
import { ReviewCard } from '@/components/staff-reviews/review-card';
import {
    ReviewSkeleton,
    SummarySkeleton,
} from '@/components/staff-reviews/review-skeleton';
import { SummaryStrip } from '@/components/staff-reviews/summary-strip';
import type {
    Counts,
    HideReason,
    ReviewPage,
    ReviewRow,
    ReviewSummary,
    ReviewTab,
} from '@/components/staff-reviews/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useT } from '@/lib/i18n';
import { useLive } from '@/lib/realtime';
import { cn } from '@/lib/utils';

const POLL_MS = 15000;
const tabs: ReviewTab[] = ['waiting', 'published', 'hidden'];

type Props = {
    tab: ReviewTab;
    counts: Counts;
    reply_max: number;
    hide_reasons: HideReason[];
    reviews?: ReviewPage;
    summary?: ReviewSummary;
};

export default function Reviews(props: Props) {
    const t = useT();
    const online = useOnline();
    const { tab, counts } = props;

    // Keep the last data on screen while a reload is in flight, so an action
    // never flashes the page back to skeletons.
    const lastReviews = useRef<ReviewPage | undefined>(undefined);
    const lastSummary = useRef<ReviewSummary | undefined>(undefined);

    if (props.reviews) {
        lastReviews.current = props.reviews;
    }

    if (props.summary) {
        lastSummary.current = props.summary;
    }

    const reviews = props.reviews ?? lastReviews.current;
    const summary = props.summary ?? lastSummary.current;

    const [switching, setSwitching] = useState(false);
    const [failed, setFailed] = useState(false);
    const [busy, setBusy] = useState(false);
    const [selected, setSelected] = useState<number[]>([]);
    const [hiding, setHiding] = useState<ReviewRow | null>(null);
    const [replying, setReplying] = useState<ReviewRow | null>(null);
    const [removing, setRemoving] = useState<ReviewRow | null>(null);
    const [error, setError] = useState<string | null>(null);

    // No event is broadcast for reviews, so this stays on the quiet poll.
    useLive(['counts', 'reviews'], {
        channel: null,
        events: [],
        fallbackMs: POLL_MS,
    });

    useEffect(() => {
        const offs = [
            router.on('success', () => setFailed(false)),
            router.on('networkError', () => setFailed(true)),
            router.on('httpException', () => setFailed(true)),
        ];

        return () => offs.forEach((off) => off());
    }, []);

    // Drop selections for reviews that have left this page.
    useEffect(() => {
        if (!reviews) {
            return;
        }

        const ids = new Set(reviews.data.map((row) => row.id));
        setSelected((current) => current.filter((id) => ids.has(id)));
    }, [reviews]);

    const rows = reviews?.data ?? [];
    const canAct = online;
    const reload = () =>
        router.reload({ only: ['counts', 'reviews', 'summary'] });

    const visit = (next: { tab: ReviewTab; page?: number }) => {
        setSelected([]);
        setSwitching(true);
        router.get(
            '/staff/reviews',
            {
                ...(next.tab === 'waiting' ? {} : { tab: next.tab }),
                ...(next.page && next.page > 1 ? { page: next.page } : {}),
            },
            {
                only: ['tab', 'counts', 'reviews'],
                preserveState: true,
                replace: true,
                onFinish: () => setSwitching(false),
            },
        );
    };

    const send = (
        method: 'post' | 'put' | 'delete',
        url: string,
        data: Record<string, unknown>,
        done: () => void,
    ) => {
        setBusy(true);
        setError(null);
        router.visit(url, {
            method,
            data: data as RequestPayload,
            preserveScroll: true,
            preserveState: true,
            only: ['counts', 'reviews', 'summary'],
            onSuccess: done,
            onError: (errors) => setError(Object.values(errors)[0] ?? null),
            onFinish: () => setBusy(false),
        });
    };

    const publish = (row: ReviewRow) =>
        send('post', `/staff/reviews/${row.id}/publish`, {}, () => undefined);

    const bulkPublish = () =>
        send('post', '/staff/reviews/publish', { ids: selected }, () =>
            setSelected([]),
        );

    const allSelected = rows.length > 0 && selected.length === rows.length;
    const empty = !switching && reviews && rows.length === 0;
    const loading = switching || !reviews;

    return (
        <>
            <Head title={t('staff_reviews.title')} />
            <StaffPageHeader
                title={t('staff_reviews.title')}
                description={t('staff_reviews.description')}
            >
                <div
                    role="tablist"
                    aria-label={t('staff_reviews.tabs_label')}
                    className="flex gap-2 overflow-x-auto pb-1"
                >
                    {tabs.map((key) => (
                        <button
                            key={key}
                            id={`reviews-tab-${key}`}
                            role="tab"
                            type="button"
                            aria-selected={tab === key}
                            aria-controls="reviews-panel"
                            onClick={() => key !== tab && visit({ tab: key })}
                            className={cn(
                                'inline-flex min-h-12 shrink-0 items-center gap-2 rounded-full border-2 px-5 text-sm font-bold',
                                tab === key
                                    ? 'border-ink bg-ink text-paper'
                                    : 'border-line-strong bg-surface text-ink',
                            )}
                        >
                            {t(`staff_reviews.tabs.${key}`)}
                            <Badge
                                variant={
                                    key === 'waiting' && counts.waiting > 0
                                        ? 'turmeric'
                                        : 'outline'
                                }
                                className="tabular-nums"
                            >
                                {counts[key]}
                            </Badge>
                        </button>
                    ))}
                </div>
            </StaffPageHeader>

            {!online && (
                <p
                    role="status"
                    className="border-b border-line bg-clay px-4 py-2 text-sm md:px-6"
                >
                    {t('staff_reviews.states.offline')}
                </p>
            )}

            <div className="mx-auto grid max-w-5xl gap-4 p-4 pb-28 md:p-6 md:pb-28">
                {summary ? (
                    <SummaryStrip summary={summary} />
                ) : (
                    <SummarySkeleton />
                )}

                <div
                    id="reviews-panel"
                    role="tabpanel"
                    aria-labelledby={`reviews-tab-${tab}`}
                    aria-busy={loading}
                    className="grid gap-3"
                >
                    {tab === 'waiting' && rows.length > 0 && !loading && (
                        <div className="flex min-h-11 items-center gap-3 px-1">
                            <Checkbox
                                id="select-all"
                                checked={allSelected}
                                disabled={!canAct}
                                onCheckedChange={(value) =>
                                    setSelected(
                                        value === true
                                            ? rows.map((row) => row.id)
                                            : [],
                                    )
                                }
                                className="size-6"
                            />
                            <Label htmlFor="select-all">
                                {t('staff_reviews.actions.select_all')}
                            </Label>
                        </div>
                    )}

                    {failed ? (
                        <ErrorState
                            title={t('staff_reviews.states.error_title')}
                            onRetry={reload}
                        />
                    ) : loading ? (
                        <ReviewSkeleton />
                    ) : empty ? (
                        <div className="rounded-lg border border-line bg-surface">
                            <EmptyState
                                title={t(`staff_reviews.empty.${tab}_title`)}
                                description={t(`staff_reviews.empty.${tab}`)}
                            />
                        </div>
                    ) : (
                        <ul className="grid gap-3">
                            {rows.map((row) => (
                                <ReviewCard
                                    key={row.id}
                                    review={row}
                                    tab={tab}
                                    selected={selected.includes(row.id)}
                                    canAct={canAct}
                                    busy={busy}
                                    onSelect={(checked) =>
                                        setSelected((current) =>
                                            checked
                                                ? [...current, row.id]
                                                : current.filter(
                                                      (id) => id !== row.id,
                                                  ),
                                        )
                                    }
                                    onPublish={() => publish(row)}
                                    onHide={() => {
                                        setError(null);
                                        setHiding(row);
                                    }}
                                    onReply={() => {
                                        setError(null);
                                        setReplying(row);
                                    }}
                                    onRemoveReply={() => setRemoving(row)}
                                />
                            ))}
                        </ul>
                    )}

                    {reviews && reviews.last_page > 1 && !loading && (
                        <nav
                            aria-label={t('staff_reviews.pagination.label')}
                            className="flex items-center justify-between gap-3"
                        >
                            <Button
                                variant="outline"
                                size="lg"
                                disabled={reviews.page <= 1}
                                onClick={() =>
                                    visit({ tab, page: reviews.page - 1 })
                                }
                            >
                                {t('staff_reviews.pagination.previous')}
                            </Button>
                            <span className="text-sm text-ink-muted tabular-nums">
                                {t('staff_reviews.pagination.page', {
                                    page: reviews.page,
                                    last: reviews.last_page,
                                })}
                            </span>
                            <Button
                                variant="outline"
                                size="lg"
                                disabled={reviews.page >= reviews.last_page}
                                onClick={() =>
                                    visit({ tab, page: reviews.page + 1 })
                                }
                            >
                                {t('staff_reviews.pagination.next')}
                            </Button>
                        </nav>
                    )}
                </div>
            </div>

            {selected.length > 0 && (
                <div
                    role="region"
                    aria-label={t('staff_reviews.actions.selected', {
                        count: selected.length,
                    })}
                    className="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-paper px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] md:left-(--sidebar-width,0)"
                >
                    <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3">
                        <p className="font-semibold" aria-live="polite">
                            {t('staff_reviews.actions.selected', {
                                count: selected.length,
                            })}
                        </p>
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                variant="ghost"
                                size="lg"
                                onClick={() => setSelected([])}
                            >
                                {t('staff_reviews.actions.clear')}
                            </Button>
                            <Button
                                size="lg"
                                disabled={!canAct || busy}
                                onClick={bulkPublish}
                            >
                                {t('staff_reviews.actions.publish_selected', {
                                    count: selected.length,
                                })}
                            </Button>
                        </div>
                        {!canAct && (
                            <p className="w-full text-sm text-ink-muted">
                                {t('staff_reviews.actions.offline')}
                            </p>
                        )}
                    </div>
                </div>
            )}

            <HideDialog
                review={hiding}
                reasons={props.hide_reasons}
                busy={busy}
                error={error}
                onClose={() => setHiding(null)}
                onConfirm={(reason, note) =>
                    hiding &&
                    send(
                        'post',
                        `/staff/reviews/${hiding.id}/hide`,
                        { reason, note },
                        () => setHiding(null),
                    )
                }
            />
            <ReplyDialog
                review={replying}
                max={props.reply_max}
                busy={busy}
                error={error}
                onClose={() => setReplying(null)}
                onSave={(reply) =>
                    replying &&
                    send(
                        'put',
                        `/staff/reviews/${replying.id}/reply`,
                        { reply },
                        () => setReplying(null),
                    )
                }
            />
            <RemoveReplyDialog
                open={removing !== null}
                busy={busy}
                onClose={() => setRemoving(null)}
                onConfirm={() =>
                    removing &&
                    send(
                        'delete',
                        `/staff/reviews/${removing.id}/reply`,
                        {},
                        () => setRemoving(null),
                    )
                }
            />
        </>
    );
}
