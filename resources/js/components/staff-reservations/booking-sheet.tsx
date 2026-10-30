import { router } from '@inertiajs/react';
import { Mail, Phone } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { useT, choice } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { OccasionIcon, StatusBadge, useDepositLabel } from './booking-badges';
import type { BoardTable, Booking } from './types';

const BASE = '/staff/reservations';

type Action = 'confirm' | 'seat' | 'complete' | 'no_show' | 'cancel';

function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-1">
            <dt className="text-label font-semibold tracking-wide text-ink-muted uppercase">
                {label}
            </dt>
            <dd>{children}</dd>
        </div>
    );
}

/**
 * Everything about one booking, and the buttons that move it along: take
 * the deposit, seat, finish, no-show, cancel, or change table.
 */
export function BookingSheet({
    booking,
    tables,
    online,
    nowMinute,
    isToday,
    isPast,
    dateLabel,
    canConfirmDeposit,
    onClose,
}: {
    booking: Booking | null;
    tables: BoardTable[];
    online: boolean;
    nowMinute: number;
    isToday: boolean;
    isPast: boolean;
    dateLabel: string;
    canConfirmDeposit: boolean;
    onClose: () => void;
}) {
    const t = useT();
    const depositLabel = useDepositLabel();
    const [busy, setBusy] = useState<Action | 'move' | null>(null);
    const [dialog, setDialog] = useState<
        'no_show' | 'cancel' | 'deposit' | null
    >(null);
    const [reason, setReason] = useState('');
    const [reasonError, setReasonError] = useState<string | null>(null);
    const [target, setTarget] = useState('');

    const run = (action: Action, extra: { reason?: string } = {}) => {
        if (!booking) {
            return;
        }

        setBusy(action);
        router.post(
            `${BASE}/${booking.id}/status`,
            { action, ...extra },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setDialog(null);
                    setReason('');
                },
                onError: (errors) => {
                    if (errors.reason) {
                        setReasonError(errors.reason);

                        return;
                    }
                    toast.error(
                        errors.action ?? t('staff_reservations.states.error'),
                    );
                    setDialog(null);
                },
                onFinish: () => setBusy(null),
            },
        );
    };

    const move = () => {
        if (!booking || target === '') {
            return;
        }

        setBusy('move');
        router.patch(
            `${BASE}/${booking.id}/table`,
            { dining_table_id: Number(target) },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setTarget(''),
                onError: (errors) =>
                    toast.error(
                        errors.dining_table_id ??
                            t('staff_reservations.states.error'),
                    ),
                onFinish: () => setBusy(null),
            },
        );
    };

    const status = booking?.status;
    const canNoShow =
        status === 'confirmed' || status === 'pending_deposit'
            ? isPast || (isToday && nowMinute >= (booking?.start_minute ?? 0))
            : false;
    const live =
        status === 'pending_deposit' ||
        status === 'confirmed' ||
        status === 'seated';
    const moveOptions = booking
        ? tables.filter(
              (table) =>
                  table.id !== booking.dining_table_id &&
                  booking.free_table_ids.includes(table.id),
          )
        : [];
    const offlineNote = online ? null : t('staff_reservations.actions.offline');
    const deposit = booking ? depositLabel(booking) : null;

    return (
        <>
            <Sheet
                open={booking !== null}
                onOpenChange={(open) => !open && onClose()}
            >
                <SheetContent
                    side="right"
                    className="flex w-full flex-col gap-0 overflow-y-auto sm:max-w-md"
                >
                    {booking && (
                        <>
                            <SheetHeader className="border-b border-line">
                                <SheetTitle className="font-display text-display-md">
                                    {booking.name}
                                </SheetTitle>
                                <SheetDescription asChild>
                                    <div className="flex flex-col gap-2">
                                        <span>
                                            {t(
                                                'staff_reservations.detail.when',
                                                {
                                                    date: dateLabel,
                                                    start: booking.start,
                                                    end: booking.end,
                                                },
                                            )}
                                        </span>
                                        <span className="flex flex-wrap items-center gap-2">
                                            <StatusBadge
                                                status={booking.status}
                                            />
                                            <span>
                                                {t(
                                                    'staff_reservations.detail.party',
                                                    {
                                                        count: booking.party_size,
                                                    },
                                                )}
                                            </span>
                                            <span className="text-ink-muted">
                                                {booking.reference}
                                            </span>
                                        </span>
                                    </div>
                                </SheetDescription>
                            </SheetHeader>

                            <div className="flex flex-col gap-5 p-4">
                                {live && (
                                    <div className="flex flex-col gap-2">
                                        {status === 'pending_deposit' &&
                                            (canConfirmDeposit ? (
                                                <Button
                                                    size="lg"
                                                    disabled={
                                                        !online || busy !== null
                                                    }
                                                    onClick={() =>
                                                        setDialog('deposit')
                                                    }
                                                >
                                                    {t(
                                                        'staff_reservations.actions.confirm',
                                                    )}
                                                </Button>
                                            ) : (
                                                <p className="text-sm text-ink-muted">
                                                    {t(
                                                        'staff_reservations.actions.confirm_managers',
                                                    )}
                                                </p>
                                            ))}
                                        {status === 'confirmed' && (
                                            <Button
                                                size="lg"
                                                disabled={
                                                    !online ||
                                                    busy !== null ||
                                                    booking.dining_table_id ===
                                                        null
                                                }
                                                onClick={() => run('seat')}
                                            >
                                                {busy === 'seat'
                                                    ? t(
                                                          'staff_reservations.actions.working',
                                                      )
                                                    : t(
                                                          'staff_reservations.actions.seat',
                                                      )}
                                            </Button>
                                        )}
                                        {status === 'seated' && (
                                            <Button
                                                size="lg"
                                                disabled={
                                                    !online || busy !== null
                                                }
                                                onClick={() => run('complete')}
                                            >
                                                {busy === 'complete'
                                                    ? t(
                                                          'staff_reservations.actions.working',
                                                      )
                                                    : t(
                                                          'staff_reservations.actions.complete',
                                                      )}
                                            </Button>
                                        )}
                                        {status === 'pending_deposit' && (
                                            <p className="text-sm text-ink-muted">
                                                {t(
                                                    'staff_reservations.actions.confirm_hint',
                                                )}
                                            </p>
                                        )}
                                        {status === 'confirmed' &&
                                            booking.dining_table_id ===
                                                null && (
                                                <p className="text-sm text-ink-muted">
                                                    {t(
                                                        'staff_reservations.actions.no_table_wait',
                                                    )}
                                                </p>
                                            )}
                                        {status !== 'seated' && (
                                            <div className="flex flex-wrap gap-2">
                                                <Button
                                                    variant="outline"
                                                    disabled={
                                                        !online ||
                                                        busy !== null ||
                                                        !canNoShow
                                                    }
                                                    onClick={() =>
                                                        setDialog('no_show')
                                                    }
                                                >
                                                    {t(
                                                        'staff_reservations.actions.no_show',
                                                    )}
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    disabled={
                                                        !online || busy !== null
                                                    }
                                                    onClick={() =>
                                                        setDialog('cancel')
                                                    }
                                                >
                                                    {t(
                                                        'staff_reservations.actions.cancel',
                                                    )}
                                                </Button>
                                            </div>
                                        )}
                                        {status !== 'seated' && !canNoShow && (
                                            <p className="text-sm text-ink-muted">
                                                {t(
                                                    'staff_reservations.actions.no_show_wait',
                                                    {
                                                        time: booking.start,
                                                    },
                                                )}
                                            </p>
                                        )}
                                        {offlineNote && (
                                            <p
                                                role="status"
                                                className="text-sm text-chilli"
                                            >
                                                {t(
                                                    'staff_reservations.states.offline',
                                                )}
                                            </p>
                                        )}
                                    </div>
                                )}

                                <dl className="flex flex-col gap-4">
                                    <Field
                                        label={t(
                                            'staff_reservations.detail.contact',
                                        )}
                                    >
                                        <div className="flex flex-col gap-1">
                                            <a
                                                className="inline-flex min-h-11 items-center gap-2 text-ember underline underline-offset-4"
                                                href={`tel:${booking.phone.replaceAll(' ', '')}`}
                                            >
                                                <Phone
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                {booking.phone}
                                            </a>
                                            {booking.email ? (
                                                <a
                                                    className="inline-flex min-h-11 items-center gap-2 break-all text-ember underline underline-offset-4"
                                                    href={`mailto:${booking.email}`}
                                                >
                                                    <Mail
                                                        className="size-4 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                    {booking.email}
                                                </a>
                                            ) : (
                                                <span className="text-ink-muted">
                                                    {t(
                                                        'staff_reservations.detail.no_email',
                                                    )}
                                                </span>
                                            )}
                                        </div>
                                    </Field>

                                    <Field
                                        label={t(
                                            'staff_reservations.detail.notes',
                                        )}
                                    >
                                        {booking.notes ? (
                                            <p className="rounded-md bg-turmeric-soft p-3">
                                                {booking.notes}
                                            </p>
                                        ) : (
                                            <span className="text-ink-muted">
                                                {t(
                                                    'staff_reservations.detail.no_notes',
                                                )}
                                            </span>
                                        )}
                                    </Field>

                                    {booking.occasion && (
                                        <Field
                                            label={t(
                                                'staff_reservations.detail.occasion',
                                            )}
                                        >
                                            <span className="inline-flex items-center gap-2">
                                                <OccasionIcon
                                                    occasion={booking.occasion}
                                                />
                                                {t(
                                                    `staff_reservations.occasion.${booking.occasion}`,
                                                )}
                                            </span>
                                        </Field>
                                    )}

                                    <Field
                                        label={t(
                                            'staff_reservations.detail.table',
                                        )}
                                    >
                                        {booking.table_label ?? (
                                            <span className="text-ink-muted">
                                                {t(
                                                    'staff_reservations.detail.table_none',
                                                )}
                                            </span>
                                        )}
                                        {booking.seated_at && (
                                            <span className="ml-2 text-ink-muted">
                                                {t(
                                                    'staff_reservations.detail.seated_at',
                                                    {
                                                        time: booking.seated_at,
                                                    },
                                                )}
                                            </span>
                                        )}
                                    </Field>

                                    <Field
                                        label={t(
                                            'staff_reservations.detail.visits',
                                        )}
                                    >
                                        {booking.previous_visits > 0
                                            ? choice(
                                                  t(
                                                      'staff_reservations.detail.visits_count',
                                                      {
                                                          count: booking.previous_visits,
                                                      },
                                                  ),
                                                  booking.previous_visits,
                                              )
                                            : t(
                                                  'staff_reservations.detail.first_visit',
                                              )}
                                    </Field>

                                    <Field
                                        label={t(
                                            'staff_reservations.detail.deposit',
                                        )}
                                    >
                                        {deposit ??
                                            t(
                                                'staff_reservations.deposit.none',
                                            )}
                                    </Field>
                                </dl>

                                {live && (
                                    <div className="flex flex-col gap-2 border-t border-line pt-4">
                                        <Label
                                            htmlFor="move-table"
                                            className="font-semibold"
                                        >
                                            {t(
                                                'staff_reservations.detail.move',
                                            )}
                                        </Label>
                                        {moveOptions.length === 0 ? (
                                            <p className="text-sm text-ink-muted">
                                                {t(
                                                    'staff_reservations.detail.move_none',
                                                )}
                                            </p>
                                        ) : (
                                            <>
                                                <p className="text-sm text-ink-muted">
                                                    {t(
                                                        'staff_reservations.detail.move_hint',
                                                    )}
                                                </p>
                                                <div className="flex gap-2">
                                                    <Select
                                                        value={target}
                                                        onValueChange={
                                                            setTarget
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            id="move-table"
                                                            className="min-w-0 flex-1"
                                                        >
                                                            <SelectValue
                                                                placeholder={t(
                                                                    'staff_reservations.detail.table',
                                                                )}
                                                            />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {moveOptions.map(
                                                                (table) => (
                                                                    <SelectItem
                                                                        key={
                                                                            table.id
                                                                        }
                                                                        value={String(
                                                                            table.id,
                                                                        )}
                                                                    >
                                                                        {t(
                                                                            'staff_reservations.detail.move_seats',
                                                                            {
                                                                                label: table.label,
                                                                                seats: table.seats,
                                                                            },
                                                                        )}
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                    <Button
                                                        variant="secondary"
                                                        disabled={
                                                            !online ||
                                                            target === '' ||
                                                            busy !== null
                                                        }
                                                        onClick={move}
                                                    >
                                                        {busy === 'move'
                                                            ? t(
                                                                  'staff_reservations.detail.moving',
                                                              )
                                                            : t(
                                                                  'staff_reservations.detail.move_action',
                                                              )}
                                                    </Button>
                                                </div>
                                            </>
                                        )}
                                    </div>
                                )}

                                <div className="flex flex-col gap-2 border-t border-line pt-4">
                                    <h3 className="font-semibold">
                                        {t('staff_reservations.detail.history')}
                                    </h3>
                                    {booking.log.length === 0 ? (
                                        <p className="text-sm text-ink-muted">
                                            {t(
                                                'staff_reservations.detail.history_empty',
                                            )}
                                        </p>
                                    ) : (
                                        <ol className="flex flex-col gap-2 text-sm">
                                            {booking.log.map((entry, index) => (
                                                <li
                                                    key={index}
                                                    className="flex gap-3"
                                                >
                                                    <span className="w-11 shrink-0 text-ink-muted tabular-nums">
                                                        {entry.at}
                                                    </span>
                                                    <span>
                                                        {entry.description}
                                                        {entry.by && (
                                                            <span className="text-ink-muted">
                                                                {' '}
                                                                {entry.by}
                                                            </span>
                                                        )}
                                                    </span>
                                                </li>
                                            ))}
                                        </ol>
                                    )}
                                </div>
                            </div>
                        </>
                    )}
                </SheetContent>
            </Sheet>

            <Dialog
                open={dialog !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDialog(null);
                        setReason('');
                        setReasonError(null);
                    }
                }}
            >
                <DialogContent>
                    {booking && dialog === 'deposit' && (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {t(
                                        'staff_reservations.dialog.deposit_title',
                                        { name: booking.name },
                                    )}
                                </DialogTitle>
                                <DialogDescription>
                                    {t(
                                        'staff_reservations.dialog.deposit_body',
                                        {
                                            amount: formatMoney(
                                                booking.deposit,
                                            ),
                                        },
                                    )}
                                </DialogDescription>
                            </DialogHeader>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="deposit-reference">
                                    {t(
                                        'staff_reservations.dialog.deposit_reference',
                                    )}
                                </Label>
                                <Textarea
                                    id="deposit-reference"
                                    value={reason}
                                    maxLength={300}
                                    placeholder={t(
                                        'staff_reservations.dialog.deposit_reference_hint',
                                    )}
                                    aria-invalid={reasonError !== null}
                                    aria-describedby={
                                        reasonError
                                            ? 'deposit-reference-error'
                                            : undefined
                                    }
                                    onChange={(e) => {
                                        setReason(e.target.value);
                                        setReasonError(null);
                                    }}
                                />
                                {reasonError && (
                                    <p
                                        id="deposit-reference-error"
                                        role="alert"
                                        className="text-sm text-chilli"
                                    >
                                        {reasonError}
                                    </p>
                                )}
                            </div>
                            <DialogFooter>
                                <Button
                                    variant="outline"
                                    onClick={() => setDialog(null)}
                                >
                                    {t('staff_reservations.dialog.keep')}
                                </Button>
                                <Button
                                    disabled={
                                        !online ||
                                        busy !== null ||
                                        reason.trim() === ''
                                    }
                                    onClick={() => run('confirm', { reason })}
                                >
                                    {busy === 'confirm'
                                        ? t(
                                              'staff_reservations.actions.working',
                                          )
                                        : t(
                                              'staff_reservations.dialog.deposit_confirm',
                                          )}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                    {booking && dialog === 'no_show' && (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {t(
                                        'staff_reservations.dialog.no_show_title',
                                        { name: booking.name },
                                    )}
                                </DialogTitle>
                                <DialogDescription>
                                    {booking.deposit > 0
                                        ? t(
                                              'staff_reservations.dialog.no_show_body',
                                              {
                                                  amount: formatMoney(
                                                      booking.deposit,
                                                  ),
                                              },
                                          )
                                        : t(
                                              'staff_reservations.dialog.no_show_body_plain',
                                          )}
                                </DialogDescription>
                            </DialogHeader>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="no-show-note">
                                    {t(
                                        'staff_reservations.dialog.no_show_note',
                                    )}
                                </Label>
                                <Textarea
                                    id="no-show-note"
                                    value={reason}
                                    maxLength={300}
                                    onChange={(e) => setReason(e.target.value)}
                                />
                            </div>
                            <DialogFooter>
                                <Button
                                    variant="outline"
                                    onClick={() => setDialog(null)}
                                >
                                    {t('staff_reservations.dialog.keep')}
                                </Button>
                                <Button
                                    variant="destructive"
                                    disabled={!online || busy !== null}
                                    onClick={() => run('no_show', { reason })}
                                >
                                    {t(
                                        'staff_reservations.dialog.no_show_confirm',
                                    )}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                    {booking && dialog === 'cancel' && (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {t(
                                        'staff_reservations.dialog.cancel_title',
                                        { name: booking.name },
                                    )}
                                </DialogTitle>
                                <DialogDescription>
                                    {t('staff_reservations.dialog.cancel_body')}
                                    {booking.deposit > 0 &&
                                        ` ${t(
                                            'staff_reservations.dialog.cancel_deposit',
                                            {
                                                amount: formatMoney(
                                                    booking.deposit,
                                                ),
                                                state: t(
                                                    booking.deposit_status ===
                                                        'paid'
                                                        ? 'staff_reservations.dialog.paid'
                                                        : 'staff_reservations.dialog.unpaid',
                                                ),
                                            },
                                        )}`}
                                </DialogDescription>
                            </DialogHeader>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="cancel-reason">
                                    {t(
                                        'staff_reservations.dialog.cancel_reason',
                                    )}
                                </Label>
                                <Textarea
                                    id="cancel-reason"
                                    value={reason}
                                    maxLength={300}
                                    placeholder={t(
                                        'staff_reservations.dialog.cancel_reason_hint',
                                    )}
                                    aria-invalid={reasonError !== null}
                                    aria-describedby={
                                        reasonError
                                            ? 'cancel-reason-error'
                                            : undefined
                                    }
                                    onChange={(e) => {
                                        setReason(e.target.value);
                                        setReasonError(null);
                                    }}
                                />
                                {reasonError && (
                                    <p
                                        id="cancel-reason-error"
                                        role="alert"
                                        className="text-sm text-chilli"
                                    >
                                        {reasonError}
                                    </p>
                                )}
                            </div>
                            <DialogFooter>
                                <Button
                                    variant="outline"
                                    onClick={() => setDialog(null)}
                                >
                                    {t('staff_reservations.dialog.keep')}
                                </Button>
                                <Button
                                    variant="destructive"
                                    disabled={
                                        !online ||
                                        busy !== null ||
                                        reason.trim() === ''
                                    }
                                    onClick={() => run('cancel', { reason })}
                                >
                                    {t(
                                        'staff_reservations.dialog.cancel_confirm',
                                    )}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
