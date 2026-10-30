import {
    ArrowRightLeft,
    BellRing,
    ExternalLink,
    Link2,
    Receipt,
    Unlink,
    WifiOff,
} from 'lucide-react';
import { useState } from 'react';
import { OrderStatusBadge } from '@/components/staff/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { RequestBadge, StatusLabel, isOccupied } from './status';
import type { FloorTable } from './types';
import type { FloorActions } from './use-floor-actions';
import { formatClock, minutesSince, useNow } from './use-now';

const selectClass =
    'h-12 w-full rounded-md border border-line-strong bg-surface px-3 text-base text-ink outline-hidden focus-visible:outline-2 focus-visible:outline-ember disabled:opacity-50';

export function TableSheet({
    table,
    tables,
    actions,
    online,
    onClose,
    onOpenTable,
}: {
    table: FloorTable | null;
    tables: FloorTable[];
    actions: FloorActions;
    online: boolean;
    onClose: () => void;
    onOpenTable: (id: number) => void;
}) {
    const isMobile = useIsMobile();

    return (
        <Sheet
            open={table !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <SheetContent
                side={isMobile ? 'bottom' : 'right'}
                className={cn(
                    'gap-0 p-0',
                    isMobile
                        ? 'max-h-[90dvh] rounded-t-xl'
                        : 'w-full sm:max-w-md',
                )}
            >
                {table && (
                    <SheetBody
                        key={table.id}
                        table={table}
                        tables={tables}
                        actions={actions}
                        online={online}
                        onOpenTable={onOpenTable}
                    />
                )}
            </SheetContent>
        </Sheet>
    );
}

function SheetBody({
    table,
    tables,
    actions,
    online,
    onOpenTable,
}: {
    table: FloorTable;
    tables: FloorTable[];
    actions: FloorActions;
    online: boolean;
    onOpenTable: (id: number) => void;
}) {
    const t = useT();
    const now = useNow();
    const disabled = actions.busy || !online;
    const joined = table.merged_into_id !== null;
    const isLead = table.merged_labels.length > 0;
    const occupied = isOccupied(table);
    const [party, setParty] = useState(String(Math.min(2, table.group_seats)));
    const [moveTo, setMoveTo] = useState('');

    const freeTables = tables.filter(
        (other) =>
            other.id !== table.id &&
            other.status === 'free' &&
            other.merged_into_id === null &&
            other.merged_labels.length === 0,
    );

    const partyNumber = Number.parseInt(party, 10);
    const partyValid =
        Number.isInteger(partyNumber) &&
        partyNumber >= 1 &&
        partyNumber <= table.group_seats;

    return (
        <>
            <SheetHeader className="gap-1 border-b border-line pr-16">
                <SheetTitle>{table.label}</SheetTitle>
                <SheetDescription className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <StatusLabel status={table.status} className="text-base" />
                    <span>
                        {t('staff_floor.panel.seats_line', {
                            seats: table.group_seats,
                            area: table.area,
                        })}
                    </span>
                </SheetDescription>
                {table.party && (
                    <p className="text-sm font-semibold">
                        {table.party.name ?? t('staff_floor.tile.walk_in')}
                        {table.party.size
                            ? ` · ${t('staff_floor.tile.party_of', { count: table.party.size })}`
                            : ''}
                        {table.seated_at && (
                            <span className="font-normal text-ink-muted">
                                {' · '}
                                {t('staff_floor.tile.seated_for', {
                                    minutes: minutesSince(table.seated_at, now),
                                })}
                            </span>
                        )}
                    </p>
                )}
                {!occupied && table.booking && (
                    <p className="text-sm font-semibold">
                        {t('staff_floor.panel.booking_next', {
                            name: table.booking.name,
                            size: table.booking.party_size,
                            time: formatClock(table.booking.starts_at),
                        })}
                    </p>
                )}
            </SheetHeader>

            <div className="flex flex-1 flex-col gap-6 overflow-y-auto p-4 pb-8">
                {!online && (
                    <p
                        role="status"
                        className="flex items-center gap-2 rounded-md bg-turmeric-soft p-3 text-sm font-semibold text-turmeric"
                    >
                        <WifiOff className="size-4 shrink-0" aria-hidden />
                        {t('staff_floor.panel.offline')}
                    </p>
                )}

                <section
                    aria-labelledby="sheet-requests"
                    className="flex flex-col gap-2"
                >
                    <h3
                        id="sheet-requests"
                        className="text-sm font-semibold text-ink-muted"
                    >
                        {t('staff_floor.panel.requests')}
                    </h3>
                    {table.requests.length === 0 ? (
                        <p className="text-sm text-ink-muted">
                            {t('staff_floor.panel.no_requests')}
                        </p>
                    ) : (
                        <ul className="flex flex-col gap-2">
                            {table.requests.map((request) => (
                                <li
                                    key={request.id}
                                    className="flex items-center justify-between gap-3 rounded-md border border-line-strong bg-surface p-2 pl-3"
                                >
                                    <span className="flex flex-col gap-1">
                                        <RequestBadge type={request.type} />
                                        <span className="text-xs text-ink-muted">
                                            {t('staff_floor.request.since', {
                                                minutes: minutesSince(
                                                    request.created_at,
                                                    now,
                                                ),
                                            })}
                                        </span>
                                    </span>
                                    <Button
                                        size="lg"
                                        disabled={disabled}
                                        onClick={() =>
                                            actions.handle(request.id)
                                        }
                                    >
                                        {t('staff_floor.request.on_my_way')}
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {joined ? (
                    <section className="flex flex-col gap-3 rounded-md border border-dashed border-line-strong p-3">
                        <p className="flex items-start gap-2 text-sm">
                            <Link2
                                className="mt-0.5 size-4 shrink-0"
                                aria-hidden
                            />
                            {t('staff_floor.panel.merge_child', {
                                lead: table.merged_into_label ?? '',
                            })}
                        </p>
                        <Button
                            variant="outline"
                            size="lg"
                            onClick={() =>
                                onOpenTable(table.merged_into_id as number)
                            }
                        >
                            {t('staff_floor.panel.open_lead', {
                                lead: table.merged_into_label ?? '',
                            })}
                        </Button>
                        <Button
                            variant="outline"
                            size="lg"
                            disabled={disabled}
                            onClick={() => actions.unmerge(table.id)}
                        >
                            <Unlink aria-hidden />
                            {t('staff_floor.panel.unmerge_one')}
                        </Button>
                    </section>
                ) : (
                    <>
                        <section
                            aria-labelledby="sheet-status"
                            className="flex flex-col gap-2"
                        >
                            <h3
                                id="sheet-status"
                                className="text-sm font-semibold text-ink-muted"
                            >
                                {t('staff_floor.panel.status_heading')}
                            </h3>

                            {table.status === 'free' && (
                                <form
                                    className="flex flex-col gap-2"
                                    onSubmit={(event) => {
                                        event.preventDefault();

                                        if (partyValid) {
                                            actions.setStatus(
                                                table.id,
                                                'seated',
                                                partyNumber,
                                            );
                                        }
                                    }}
                                >
                                    <Label htmlFor="party-size">
                                        {t('staff_floor.panel.party_label')}
                                    </Label>
                                    <div className="flex items-center gap-2">
                                        <Input
                                            id="party-size"
                                            type="number"
                                            inputMode="numeric"
                                            min={1}
                                            max={table.group_seats}
                                            value={party}
                                            onChange={(event) =>
                                                setParty(event.target.value)
                                            }
                                            aria-describedby="party-hint"
                                            className="h-12 w-24 text-lg tabular-nums"
                                        />
                                        <span
                                            id="party-hint"
                                            className="text-sm text-ink-muted"
                                        >
                                            {t('staff_floor.panel.party_hint', {
                                                seats: table.group_seats,
                                            })}
                                        </span>
                                    </div>
                                    <Button
                                        type="submit"
                                        size="lg"
                                        disabled={disabled || !partyValid}
                                    >
                                        {t('staff_floor.panel.seat_submit')}
                                    </Button>
                                </form>
                            )}

                            {table.status === 'seated' && (
                                <>
                                    <Button
                                        size="lg"
                                        disabled={disabled}
                                        onClick={() =>
                                            actions.setStatus(
                                                table.id,
                                                'bill_requested',
                                            )
                                        }
                                    >
                                        <Receipt aria-hidden />
                                        {t('staff_floor.panel.actions.bill')}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="lg"
                                        disabled={disabled}
                                        onClick={() =>
                                            actions.setStatus(
                                                table.id,
                                                'needs_cleaning',
                                            )
                                        }
                                    >
                                        {t(
                                            'staff_floor.panel.actions.cleaning',
                                        )}
                                    </Button>
                                </>
                            )}

                            {table.status === 'bill_requested' && (
                                <>
                                    <Button
                                        size="lg"
                                        disabled={disabled}
                                        onClick={() =>
                                            actions.setStatus(
                                                table.id,
                                                'needs_cleaning',
                                            )
                                        }
                                    >
                                        {t(
                                            'staff_floor.panel.actions.cleaning',
                                        )}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="lg"
                                        disabled={disabled}
                                        onClick={() =>
                                            actions.setStatus(
                                                table.id,
                                                'seated',
                                            )
                                        }
                                    >
                                        {t(
                                            'staff_floor.panel.actions.back_to_seated',
                                        )}
                                    </Button>
                                </>
                            )}

                            {(table.status === 'needs_cleaning' ||
                                table.status === 'reserved') && (
                                <Button
                                    size="lg"
                                    disabled={disabled}
                                    onClick={() =>
                                        actions.setStatus(table.id, 'free')
                                    }
                                >
                                    {t('staff_floor.panel.actions.free')}
                                </Button>
                            )}
                        </section>

                        {isLead && (
                            <section className="flex flex-col gap-2">
                                <h3 className="text-sm font-semibold text-ink-muted">
                                    {t('staff_floor.panel.merge_heading')}
                                </h3>
                                <p className="text-sm">
                                    {t('staff_floor.panel.merge_lead', {
                                        tables: table.merged_labels.join(', '),
                                    })}
                                </p>
                                <Button
                                    variant="outline"
                                    size="lg"
                                    disabled={disabled}
                                    onClick={() => actions.unmerge(table.id)}
                                >
                                    <Unlink aria-hidden />
                                    {t('staff_floor.panel.unmerge_all')}
                                </Button>
                            </section>
                        )}

                        {occupied && !isLead && (
                            <section className="flex flex-col gap-2">
                                <h3 className="text-sm font-semibold text-ink-muted">
                                    {t('staff_floor.panel.transfer_heading')}
                                </h3>
                                {freeTables.length === 0 ? (
                                    <p className="text-sm text-ink-muted">
                                        {t('staff_floor.panel.transfer_none')}
                                    </p>
                                ) : (
                                    <>
                                        <Label htmlFor="move-to">
                                            {t(
                                                'staff_floor.panel.transfer_label',
                                            )}
                                        </Label>
                                        <select
                                            id="move-to"
                                            className={selectClass}
                                            value={moveTo}
                                            onChange={(event) =>
                                                setMoveTo(event.target.value)
                                            }
                                        >
                                            <option value="">
                                                {t(
                                                    'staff_floor.panel.transfer_placeholder',
                                                )}
                                            </option>
                                            {freeTables.map((other) => (
                                                <option
                                                    key={other.id}
                                                    value={other.id}
                                                >
                                                    {other.label} ·{' '}
                                                    {other.seats} · {other.area}
                                                </option>
                                            ))}
                                        </select>
                                        <p className="text-xs text-ink-muted">
                                            {t(
                                                'staff_floor.panel.transfer_note',
                                                {
                                                    table: table.label,
                                                },
                                            )}
                                        </p>
                                        <Button
                                            variant="outline"
                                            size="lg"
                                            disabled={disabled || moveTo === ''}
                                            onClick={() =>
                                                actions.transfer(
                                                    table.id,
                                                    Number(moveTo),
                                                    () =>
                                                        onOpenTable(
                                                            Number(moveTo),
                                                        ),
                                                )
                                            }
                                        >
                                            <ArrowRightLeft aria-hidden />
                                            {t(
                                                'staff_floor.panel.transfer_submit',
                                            )}
                                        </Button>
                                    </>
                                )}
                            </section>
                        )}
                    </>
                )}

                {!joined && (
                    <section
                        aria-labelledby="sheet-orders"
                        className="flex flex-col gap-2"
                    >
                        <h3
                            id="sheet-orders"
                            className="text-sm font-semibold text-ink-muted"
                        >
                            {t('staff_floor.panel.orders')}
                        </h3>
                        {table.orders.length === 0 ? (
                            <p className="text-sm text-ink-muted">
                                {t('staff_floor.panel.no_orders')}
                            </p>
                        ) : (
                            <>
                                <ul className="flex flex-col divide-y divide-line rounded-md border border-line bg-surface">
                                    {table.orders.map((order) => (
                                        <li
                                            key={order.id}
                                            className="flex items-center justify-between gap-3 px-3 py-2.5"
                                        >
                                            <span className="flex flex-col gap-1">
                                                <span className="text-sm font-semibold tabular-nums">
                                                    {order.number}
                                                </span>
                                                <OrderStatusBadge
                                                    status={order.status}
                                                />
                                            </span>
                                            <span className="text-sm font-semibold tabular-nums">
                                                {formatMoney(order.total)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                                <p className="flex justify-between text-sm font-semibold tabular-nums">
                                    <span>
                                        {t('staff_floor.panel.order_total')}
                                    </span>
                                    <span>{formatMoney(table.open_total)}</span>
                                </p>
                            </>
                        )}
                        <div className="mt-1 flex flex-col gap-2">
                            <Button asChild size="lg" variant="outline">
                                <a
                                    href={`/t/${table.qr_token}`}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <BellRing aria-hidden />
                                    {t('staff_floor.panel.take_order')}
                                    <ExternalLink
                                        aria-hidden
                                        className="ml-auto"
                                    />
                                </a>
                            </Button>
                            <Button asChild size="lg" variant="outline">
                                <a
                                    href={`/t/${table.qr_token}/bill`}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Receipt aria-hidden />
                                    {t('staff_floor.panel.bill_page')}
                                    <ExternalLink
                                        aria-hidden
                                        className="ml-auto"
                                    />
                                </a>
                            </Button>
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}
