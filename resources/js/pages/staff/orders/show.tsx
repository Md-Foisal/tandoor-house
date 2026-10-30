import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Banknote,
    Download,
    MapPin,
    Pencil,
    Phone,
    Printer,
    RotateCcw,
} from 'lucide-react';
import { useState } from 'react';
import { useOnline } from '@/components/brand/states';
import { Elapsed } from '@/components/staff/elapsed';
import { StaffPageHeader } from '@/components/staff/page-header';
import {
    ChannelBadge,
    OrderStatusBadge,
} from '@/components/staff/status-badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { EditDialog } from '@/components/staff-orders/edit-dialog';
import { OrderActions } from '@/components/staff-orders/order-actions';
import { PaymentLine } from '@/components/staff-orders/order-card';
import { TakePaymentDialog } from '@/components/staff-orders/take-payment-dialog';
import { RefundDialog } from '@/components/staff-orders/refund-dialog';
import {
    isLive,
    type OrderAbilities,
    type OrderDetailData,
} from '@/components/staff-orders/types';
import { useT } from '@/lib/i18n';
import { channels, events, useLive } from '@/lib/realtime';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { AlertTriangle, IdCard } from 'lucide-react';
import { formatStamp } from '@/components/staff-orders/format';

const PAUSED_MS = 2_147_483_647;

function Panel({
    title,
    children,
    className,
}: {
    title: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <section
            className={cn(
                'rounded-lg border border-line bg-surface p-4',
                className,
            )}
        >
            <h2 className="mb-3 text-label text-ink-muted uppercase">
                {title}
            </h2>
            {children}
        </section>
    );
}

export default function OrderShow({
    order,
    abilities,
    riders,
}: {
    order: OrderDetailData;
    abilities: OrderAbilities;
    riders?: { id: number; name: string }[];
}) {
    const t = useT();
    const online = useOnline();
    const [editing, setEditing] = useState(false);
    const [refunding, setRefunding] = useState(false);
    const [taking, setTaking] = useState(false);
    const paused = editing || refunding || taking;

    // useLive cannot be paused, so while a dialog is open it listens to
    // nothing and its fallback poll is pushed out of reach.
    useLive(['order', 'abilities'], {
        channel: paused ? null : channels.staffOrders,
        events: [events.orderStatusChanged, events.riderAssigned],
        fallbackMs: paused ? PAUSED_MS : 5_000,
    });

    const live = isLive(order.status);
    const totals = order.totals;
    const totalRows: [string, number, boolean?][] = [
        ['subtotal', totals.subtotal],
        ['discount', -totals.discount],
        ['delivery_fee', totals.delivery_fee],
        ['service_charge', totals.service_charge],
        ['tip', totals.tip],
    ];

    const assign = (value: string) =>
        router.patch(
            `/staff/orders/${order.number}/rider`,
            { rider_id: value === 'none' ? null : Number(value) },
            { preserveScroll: true },
        );

    return (
        <>
            <Head
                title={t('staff_orders.detail.title', { number: order.number })}
            />
            <StaffPageHeader
                title={t('staff_orders.detail.title', { number: order.number })}
                description={
                    <span className="inline-flex flex-wrap items-center gap-x-3 gap-y-1">
                        <OrderStatusBadge status={order.status} />
                        <ChannelBadge
                            channel={order.channel}
                            table={order.table}
                        />
                        {live && <Elapsed since={order.placed_at} />}
                    </span>
                }
                actions={
                    <>
                        <Button variant="ghost" asChild>
                            <Link href="/staff/orders">
                                <ArrowLeft aria-hidden="true" />
                                {t('staff_orders.detail.back')}
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <a
                                href={`/staff/orders/${order.number}/receipt`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <Printer aria-hidden="true" />
                                {t('staff_orders.detail.print')}
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <a
                                href={`/staff/orders/${order.number}/receipt.pdf`}
                            >
                                <Download aria-hidden="true" />
                                {t('staff_orders.detail.download')}
                            </a>
                        </Button>
                    </>
                }
            />

            <div className="grid gap-4 p-4 md:p-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <div className="grid content-start gap-4">
                    {(order.has_allergy || order.age_check) && (
                        <div className="grid gap-2">
                            {order.has_allergy && (
                                <p className="flex items-start gap-2 rounded-md bg-chilli-soft px-3 py-2 font-semibold text-chilli">
                                    <AlertTriangle
                                        className="mt-0.5 size-5 shrink-0"
                                        aria-hidden="true"
                                    />
                                    {t('staff_orders.detail.allergy_note')}
                                </p>
                            )}
                            {order.age_check && (
                                <p className="flex items-start gap-2 rounded-md bg-turmeric-soft px-3 py-2 font-semibold text-turmeric">
                                    <IdCard
                                        className="mt-0.5 size-5 shrink-0"
                                        aria-hidden="true"
                                    />
                                    {t('staff_orders.detail.age_note')}
                                </p>
                            )}
                        </div>
                    )}

                    <Panel title={t('staff_orders.detail.lines')}>
                        <ul className="divide-y divide-line">
                            {order.lines.map((line) => (
                                <li
                                    key={line.id}
                                    className="flex justify-between gap-3 py-2.5"
                                >
                                    <div className="min-w-0">
                                        <p className="font-semibold">
                                            <span className="tabular-nums">
                                                {line.quantity}×
                                            </span>{' '}
                                            {line.name}
                                            {line.variant && (
                                                <span className="font-normal text-ink-muted">
                                                    {' '}
                                                    ({line.variant})
                                                </span>
                                            )}
                                        </p>
                                        {line.modifiers.map((modifier) => (
                                            <p
                                                key={modifier.name}
                                                className="pl-6 text-sm text-ink-muted"
                                            >
                                                {t(
                                                    'staff_orders.detail.modifier',
                                                    { name: modifier.name },
                                                )}
                                            </p>
                                        ))}
                                        {line.notes && (
                                            <p className="mt-1 ml-6 w-fit rounded-md bg-turmeric-soft px-2 py-0.5 text-sm text-turmeric">
                                                {line.notes}
                                            </p>
                                        )}
                                    </div>
                                    <p className="shrink-0 tabular-nums">
                                        {formatMoney(line.total)}
                                    </p>
                                </li>
                            ))}
                        </ul>
                        {order.notes && (
                            <div className="mt-3 rounded-md bg-turmeric-soft px-3 py-2 text-turmeric">
                                <p className="text-label uppercase">
                                    {t('staff_orders.detail.notes')}
                                </p>
                                <p className="whitespace-pre-line">
                                    {order.notes}
                                </p>
                            </div>
                        )}
                        {(abilities.edit || abilities.refund) && (
                            <div className="mt-4 flex flex-wrap gap-2">
                                {abilities.edit && (
                                    <Button
                                        variant="outline"
                                        disabled={!online}
                                        onClick={() => setEditing(true)}
                                    >
                                        <Pencil aria-hidden="true" />
                                        {t('staff_orders.edit.button')}
                                    </Button>
                                )}
                            </div>
                        )}
                    </Panel>

                    <Panel title={t('staff_orders.detail.totals')}>
                        <dl className="grid gap-1.5">
                            {totalRows
                                .filter(
                                    ([key, value]) =>
                                        key === 'subtotal' || value !== 0,
                                )
                                .map(([key, value]) => (
                                    <div
                                        key={key}
                                        className="flex justify-between gap-3"
                                    >
                                        <dt className="text-ink-muted">
                                            {t(`staff_orders.totals.${key}`)}
                                        </dt>
                                        <dd className="tabular-nums">
                                            {formatMoney(value)}
                                        </dd>
                                    </div>
                                ))}
                            <div className="flex justify-between gap-3 border-t border-line pt-2 text-title font-semibold">
                                <dt>{t('staff_orders.totals.total')}</dt>
                                <dd className="tabular-nums">
                                    {formatMoney(totals.total)}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-3 text-sm text-ink-muted">
                                <dt>{t('staff_orders.totals.vat_total')}</dt>
                                <dd className="tabular-nums">
                                    {formatMoney(totals.vat_total)}
                                </dd>
                            </div>
                        </dl>
                    </Panel>

                    <Panel title={t('staff_orders.detail.payments')}>
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                            <PaymentLine
                                method={order.payment_method}
                                status={order.payment_status}
                            />
                            {order.refunded > 0 && (
                                <span className="text-sm text-ink-muted">
                                    {t('staff_orders.detail.refunded_of', {
                                        refunded: formatMoney(order.refunded),
                                        amount: formatMoney(order.paid),
                                    })}
                                </span>
                            )}
                        </div>
                        {order.payments.length === 0 ? (
                            <p className="text-sm text-ink-muted">
                                {t('staff_orders.detail.no_payments')}
                            </p>
                        ) : (
                            <ul className="divide-y divide-line text-sm">
                                {order.payments.map((payment) => (
                                    <li
                                        key={payment.id}
                                        className="flex flex-wrap justify-between gap-2 py-2"
                                    >
                                        <span>
                                            {t(
                                                `staff_orders.payment.method.${payment.method}`,
                                            )}
                                            {payment.reference && (
                                                <span className="text-ink-muted">
                                                    {' '}
                                                    · {payment.reference}
                                                </span>
                                            )}
                                        </span>
                                        <span className="tabular-nums">
                                            {formatMoney(payment.amount)} ·{' '}
                                            {t(
                                                `staff_orders.payment.status.${payment.status}`,
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                        {abilities.take_payment && (
                            <Button
                                size="lg"
                                className="mt-4 mr-2"
                                disabled={!online}
                                title={
                                    !online
                                        ? t('staff_orders.offline_reason')
                                        : undefined
                                }
                                onClick={() => setTaking(true)}
                            >
                                <Banknote aria-hidden="true" />
                                {t('staff_orders.take_payment.button')}
                            </Button>
                        )}
                        {abilities.refund && (
                            <Button
                                variant="outline"
                                className="mt-4"
                                disabled={!online}
                                onClick={() => setRefunding(true)}
                            >
                                <RotateCcw aria-hidden="true" />
                                {t('staff_orders.refund.button')}
                            </Button>
                        )}
                    </Panel>
                </div>

                <div className="grid content-start gap-4">
                    {(order.next || order.can_reject || order.can_cancel) && (
                        <Panel
                            title={t('staff_orders.detail.title', {
                                number: order.number,
                            })}
                        >
                            <OrderActions order={order} />
                        </Panel>
                    )}
                    {!abilities.edit && live && order.status !== 'placed' && (
                        <p className="rounded-md bg-clay px-3 py-2 text-sm text-ink-muted">
                            {t('staff_orders.edit.locked')}
                        </p>
                    )}

                    <Panel title={t('staff_orders.detail.contact')}>
                        <p className="font-semibold">{order.contact.name}</p>
                        {order.contact.phone && (
                            <Button
                                variant="outline"
                                className="mt-2 w-full justify-start"
                                asChild
                            >
                                <a
                                    href={`tel:${order.contact.phone.replace(/\s+/g, '')}`}
                                >
                                    <Phone aria-hidden="true" />
                                    {t('staff_orders.detail.call', {
                                        phone: order.contact.phone,
                                    })}
                                </a>
                            </Button>
                        )}
                        {order.contact.email && (
                            <p className="mt-2 text-sm break-all text-ink-muted">
                                {order.contact.email}
                            </p>
                        )}
                    </Panel>

                    {order.address && (
                        <Panel title={t('staff_orders.detail.address')}>
                            <address className="not-italic">
                                {Object.values(order.address).map((line) => (
                                    <span key={line} className="block">
                                        {line}
                                    </span>
                                ))}
                            </address>
                            {order.instructions && (
                                <p className="mt-2 rounded-md bg-turmeric-soft px-2.5 py-1.5 text-sm text-turmeric">
                                    {t('staff_orders.detail.instructions')}:{' '}
                                    {order.instructions}
                                </p>
                            )}
                            {order.maps_url && (
                                <Button
                                    variant="outline"
                                    className="mt-3 w-full justify-start"
                                    asChild
                                >
                                    <a
                                        href={order.maps_url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <MapPin aria-hidden="true" />
                                        {t('staff_orders.detail.maps')}
                                    </a>
                                </Button>
                            )}
                        </Panel>
                    )}

                    {order.channel === 'delivery' && (
                        <Panel title={t('staff_orders.rider.title')}>
                            {abilities.assign_rider ? (
                                <Select
                                    value={
                                        order.rider_id
                                            ? String(order.rider_id)
                                            : 'none'
                                    }
                                    onValueChange={assign}
                                    disabled={!online}
                                >
                                    <SelectTrigger
                                        className="min-h-11 w-full"
                                        aria-label={t(
                                            'staff_orders.rider.assign',
                                        )}
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            {t('staff_orders.rider.none')}
                                        </SelectItem>
                                        {(riders ?? []).map((rider) => (
                                            <SelectItem
                                                key={rider.id}
                                                value={String(rider.id)}
                                            >
                                                {rider.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            ) : (
                                <p>
                                    {order.rider ??
                                        t('staff_orders.rider.unassigned')}
                                </p>
                            )}
                        </Panel>
                    )}

                    <Panel title={t('staff_orders.detail.timeline')}>
                        <ol className="relative grid gap-3 border-l-2 border-line pl-4">
                            {order.events.map((event, index) => (
                                <li key={index} className="relative">
                                    <span
                                        className="absolute top-1.5 -left-[1.4rem] size-2.5 rounded-full bg-ember"
                                        aria-hidden="true"
                                    />
                                    <p className="font-semibold">
                                        {t(`staff.status.${event.to}`)}
                                    </p>
                                    <p className="text-sm text-ink-muted">
                                        {formatStamp(event.at)} ·{' '}
                                        {event.by
                                            ? t('staff_orders.detail.by', {
                                                  name: event.by,
                                              })
                                            : t('staff_orders.detail.system')}
                                    </p>
                                </li>
                            ))}
                        </ol>
                        {order.rejection_reason && (
                            <p className="mt-3 rounded-md bg-chilli-soft px-3 py-2 text-sm text-chilli">
                                <strong>
                                    {t('staff_orders.detail.rejection')}:
                                </strong>{' '}
                                {order.rejection_reason}
                            </p>
                        )}
                    </Panel>
                </div>
            </div>

            {abilities.edit && (
                <EditDialog
                    order={order}
                    open={editing}
                    onOpenChange={setEditing}
                />
            )}
            {abilities.take_payment && abilities.payment_due !== null && (
                <TakePaymentDialog
                    number={order.number}
                    amount={abilities.payment_due}
                    open={taking}
                    onOpenChange={setTaking}
                />
            )}
            {abilities.refund && (
                <RefundDialog
                    order={order}
                    limit={abilities.refund_limit}
                    open={refunding}
                    onOpenChange={setRefunding}
                />
            )}
        </>
    );
}
