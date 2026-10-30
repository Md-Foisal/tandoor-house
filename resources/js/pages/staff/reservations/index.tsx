import { Head, router } from '@inertiajs/react';
import { CalendarPlus, ChevronLeft, ChevronRight } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { EmptyState, ErrorState, useOnline } from '@/components/brand/states';
import { Agenda } from '@/components/staff-reservations/agenda';
import { BookingSheet } from '@/components/staff-reservations/booking-sheet';
import { DayTotals } from '@/components/staff-reservations/day-totals';
import { NewBookingDialog } from '@/components/staff-reservations/new-booking-dialog';
import { Timeline } from '@/components/staff-reservations/timeline';
import type { ReservationsProps } from '@/components/staff-reservations/types';
import {
    useBoardPoll,
    useNewBookingIds,
    useServerMinute,
} from '@/components/staff-reservations/use-board';
import { WeekView } from '@/components/staff-reservations/week-view';
import { StaffPageHeader } from '@/components/staff/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const URL = '/staff/reservations';

function isTyping(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    return (
        target.isContentEditable ||
        ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) ||
        target.closest('[role="dialog"], [role="listbox"]') !== null
    );
}

function BoardSkeleton() {
    const t = useT();

    return (
        <div
            role="status"
            aria-label={t('staff_reservations.states.loading')}
            className="flex flex-col gap-3"
        >
            <div className="grid gap-3 sm:grid-cols-2">
                <Skeleton className="h-28" />
                <Skeleton className="h-28" />
            </div>
            {Array.from({ length: 5 }, (_, i) => (
                <Skeleton key={i} className="h-14" />
            ))}
        </div>
    );
}

export default function Reservations(props: ReservationsProps) {
    const t = useT();
    const online = useOnline();
    const {
        view,
        date,
        today,
        weekday,
        date_label: dateLabel,
        prev,
        next,
        is_today: isToday,
        now,
        rules,
        occasions,
        tables,
        periods,
        bookings,
        services,
        week,
    } = props;

    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [creating, setCreating] = useState(false);
    const [navigating, setNavigating] = useState(false);
    const nowMinute = useServerMinute(now.minute, now.iso);
    const highlighted = useNewBookingIds(bookings);
    const poll = useBoardPoll(view === 'day' && isToday);
    const selected = bookings.find((b) => b.id === selectedId) ?? null;

    const go = useCallback(
        (target: { date?: string; view?: 'day' | 'week' }) => {
            const nextView = target.view ?? view;
            const nextDate = target.date ?? date;

            setSelectedId(null);
            router.get(
                URL,
                {
                    ...(nextDate === today ? {} : { date: nextDate }),
                    ...(nextView === 'week' ? { view: 'week' } : {}),
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    onStart: () => setNavigating(true),
                    onFinish: () => setNavigating(false),
                },
            );
        },
        [view, date, today],
    );

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (
                event.metaKey ||
                event.ctrlKey ||
                event.altKey ||
                isTyping(event.target)
            ) {
                return;
            }

            const actions: Record<string, () => void> = {
                n: () => setCreating(true),
                t: () => go({ date: today }),
                d: () => go({ view: 'day' }),
                w: () => go({ view: 'week' }),
                ArrowLeft: () => go({ date: prev }),
                ArrowRight: () => go({ date: next }),
            };
            const key =
                event.key.length === 1 ? event.key.toLowerCase() : event.key;
            const action = actions[key];

            if (action) {
                event.preventDefault();
                action();
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [go, today, prev, next]);

    const step = view === 'week' ? 'week' : 'day';

    return (
        <>
            <Head title={t('staff_reservations.title')} />
            <StaffPageHeader
                title={t('staff_reservations.title')}
                description={dateLabel}
                actions={
                    <Button
                        size="lg"
                        onClick={() => setCreating(true)}
                        disabled={!online}
                    >
                        <CalendarPlus aria-hidden="true" />
                        {t('staff_reservations.new_booking')}
                    </Button>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <div className="flex items-center gap-1">
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label={t(
                                `staff_reservations.nav.prev_${step}`,
                            )}
                            onClick={() => go({ date: prev })}
                        >
                            <ChevronLeft aria-hidden="true" />
                        </Button>
                        <Button
                            variant="outline"
                            disabled={isToday && view === 'day'}
                            onClick={() => go({ date: today })}
                        >
                            {t('staff_reservations.nav.today')}
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label={t(
                                `staff_reservations.nav.next_${step}`,
                            )}
                            onClick={() => go({ date: next })}
                        >
                            <ChevronRight aria-hidden="true" />
                        </Button>
                    </div>
                    <Input
                        type="date"
                        value={date}
                        aria-label={t('staff_reservations.nav.pick')}
                        className="w-auto"
                        onChange={(e) =>
                            e.target.value && go({ date: e.target.value })
                        }
                    />
                    <div
                        role="group"
                        aria-label={t('staff_reservations.view.label')}
                        className="ml-auto flex rounded-md border-[1.5px] border-ink/80 p-0.5"
                    >
                        {(['day', 'week'] as const).map((mode) => (
                            <button
                                key={mode}
                                type="button"
                                aria-pressed={view === mode}
                                onClick={() => go({ view: mode })}
                                className={cn(
                                    'h-10 min-w-16 rounded-sm px-3 text-sm font-semibold outline-none focus-visible:outline-2 focus-visible:outline-ember',
                                    view === mode
                                        ? 'bg-ink text-paper'
                                        : 'hover:bg-clay',
                                )}
                            >
                                {t(`staff_reservations.view.${mode}`)}
                            </button>
                        ))}
                    </div>
                </div>
            </StaffPageHeader>

            <div
                className="flex flex-col gap-4 p-4 md:p-6"
                aria-busy={navigating}
            >
                {!online && (
                    <p role="status" className="text-sm text-chilli">
                        {t('staff_reservations.states.offline')}
                    </p>
                )}
                {poll.failed && (
                    <ErrorState
                        title={t('staff_reservations.states.error')}
                        onRetry={poll.retry}
                    />
                )}

                {navigating ? (
                    <BoardSkeleton />
                ) : view === 'week' ? (
                    <WeekView
                        days={week}
                        selected={date}
                        onOpen={(day) => go({ date: day, view: 'day' })}
                    />
                ) : (
                    <>
                        {services.length > 0 && (
                            <DayTotals services={services} />
                        )}

                        {bookings.length === 0 ? (
                            <EmptyState
                                title={t(
                                    periods.length === 0
                                        ? 'staff_reservations.empty.closed_title'
                                        : 'staff_reservations.empty.day_title',
                                    { day: weekday },
                                )}
                                description={t(
                                    periods.length === 0
                                        ? 'staff_reservations.empty.closed_body'
                                        : 'staff_reservations.empty.day_body',
                                )}
                                action={
                                    periods.length > 0 ? (
                                        <Button
                                            onClick={() => setCreating(true)}
                                            disabled={!online}
                                        >
                                            {t(
                                                'staff_reservations.new_booking',
                                            )}
                                        </Button>
                                    ) : undefined
                                }
                            />
                        ) : (
                            <>
                                <div className="md:hidden">
                                    <Agenda
                                        bookings={bookings}
                                        highlighted={highlighted}
                                        onSelect={setSelectedId}
                                    />
                                </div>
                                <div className="hidden md:block">
                                    <Timeline
                                        tables={tables}
                                        periods={periods}
                                        bookings={bookings}
                                        nowMinute={nowMinute}
                                        isToday={isToday}
                                        highlighted={highlighted}
                                        onSelect={setSelectedId}
                                    />
                                </div>
                            </>
                        )}

                        {isToday && (
                            <p
                                className="text-xs text-ink-muted"
                                aria-live="polite"
                            >
                                {poll.refreshing
                                    ? t('staff_reservations.states.refreshing')
                                    : t('staff_reservations.states.updated', {
                                          time: poll.updatedAt.toLocaleTimeString(
                                              'en-GB',
                                              {
                                                  hour: '2-digit',
                                                  minute: '2-digit',
                                              },
                                          ),
                                      })}
                            </p>
                        )}
                    </>
                )}

                <p className="hidden text-xs text-ink-muted md:block">
                    {t('staff_reservations.shortcuts.line')}
                </p>
            </div>

            <BookingSheet
                booking={selected}
                tables={tables}
                online={online}
                nowMinute={nowMinute}
                isToday={isToday}
                isPast={date < today}
                dateLabel={dateLabel}
                canConfirmDeposit={props.can_confirm_deposit}
                onClose={() => setSelectedId(null)}
            />
            <NewBookingDialog
                open={creating}
                onOpenChange={setCreating}
                defaultDate={date < today ? today : date}
                rules={rules}
                occasions={occasions}
                canWaive={props.can_waive_deposit}
                online={online}
            />
        </>
    );
}
