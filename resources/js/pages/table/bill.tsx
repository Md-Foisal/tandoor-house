import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { EmberLine } from '@/components/brand/ember-line';
import { EmptyState, ErrorState, useOnline } from '@/components/brand/states';
import { BillPay } from '@/components/table/bill-pay';
import { BillSplit } from '@/components/table/bill-split';
import { HelpButtons } from '@/components/table/help-buttons';
import type { BillProps } from '@/components/table/types';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { events, useLive } from '@/lib/realtime';

export default function TableBill({
    table,
    orders,
    units,
    totals,
    tip_presets: tipPresets,
    service_rate: serviceRate,
    options,
    split,
    requests,
}: BillProps) {
    const t = useT();
    const online = useOnline();
    const [failed, setFailed] = useState(false);
    const [loading, setLoading] = useState(false);

    useLive(['orders', 'totals', 'units', 'split', 'requests'], {
        channel: null,
        events: [events.tableRequestRaised],
    });

    const toggleService = (include: boolean) => {
        const query: Record<string, unknown> = {
            service: include ? 1 : 0,
            mode: options.mode === 'none' ? undefined : options.mode,
        };

        if (options.mode === 'even') {
            query.people = options.people;
        }

        if (options.mode === 'items') {
            query.guests = options.guests;
            query.claims = options.claims;
        }

        router.get(`/t/${table.token}/bill`, query as Record<string, never>, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['options', 'split', 'totals'],
            onStart: () => {
                setLoading(true);
                setFailed(false);
            },
            onError: () => setFailed(true),
            onNetworkError: () => setFailed(true),
            onFinish: () => setLoading(false),
        });
    };

    return (
        <>
            <Head title={t('table.bill.page_title', { table: table.label })} />
            <div className="container-page max-w-3xl pt-6 pb-56 md:pt-10 md:pb-16">
                <header className="flex flex-col gap-2">
                    <p className="text-sm font-semibold tracking-wide text-ember uppercase">
                        {t('table.banner', {
                            table: table.label,
                            area: table.area,
                        })}
                    </p>
                    <h1 className="font-display text-display-lg text-ink">
                        {t('table.bill.title')}
                    </h1>
                    <EmberLine />
                    <Link
                        href={`/t/${table.token}`}
                        className="inline-flex min-h-11 items-center font-semibold text-ember underline underline-offset-4 outline-hidden focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember"
                    >
                        {t('table.bill.back')}
                    </Link>
                </header>

                {failed && (
                    <ErrorState
                        className="mt-4"
                        title={t('table.bill.error')}
                        onRetry={() => router.reload()}
                    />
                )}

                {orders.length === 0 ? (
                    <EmptyState
                        title={t('table.bill.empty_title')}
                        description={t('table.bill.empty_body')}
                    />
                ) : (
                    <div className="mt-6 flex flex-col gap-8">
                        <section
                            aria-label={t('table.bill.title')}
                            className="flex flex-col gap-5"
                        >
                            {orders.map((order) => (
                                <div key={order.number}>
                                    <h2 className="text-sm font-semibold text-ink-muted tabular-nums">
                                        {t('table.bill.order', {
                                            number: order.number,
                                        })}
                                        {' · '}
                                        {order.placed_label}
                                    </h2>
                                    <ul className="mt-1 divide-y divide-line border-y border-line">
                                        {order.lines.map((line) => (
                                            <li
                                                key={line.id}
                                                className="flex items-baseline justify-between gap-4 py-2.5"
                                            >
                                                <span className="text-ink">
                                                    {line.quantity} ×{' '}
                                                    {line.name}
                                                    {line.detail && (
                                                        <span className="block text-sm text-ink-muted">
                                                            {line.detail}
                                                        </span>
                                                    )}
                                                </span>
                                                <span className="text-ink tabular-nums">
                                                    {formatMoney(
                                                        line.line_total,
                                                    )}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </section>

                        <section
                            aria-label={t('table.bill.total')}
                            className="rounded-lg border border-line bg-surface p-4"
                        >
                            {loading ? (
                                <div
                                    className="flex flex-col gap-2"
                                    aria-label={t('table.bill.loading')}
                                >
                                    <Skeleton className="h-5 w-full" />
                                    <Skeleton className="h-5 w-full" />
                                    <Skeleton className="h-7 w-full" />
                                </div>
                            ) : (
                                <dl
                                    className="flex flex-col gap-2"
                                    aria-live="polite"
                                    aria-atomic="true"
                                >
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-ink-muted">
                                            {t('table.bill.subtotal')}
                                        </dt>
                                        <dd className="tabular-nums">
                                            {formatMoney(totals.subtotal)}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-ink-muted">
                                            {totals.service_included
                                                ? t('table.bill.service', {
                                                      rate: serviceRate / 100,
                                                  })
                                                : t(
                                                      'table.bill.service_removed',
                                                  )}
                                        </dt>
                                        <dd className="tabular-nums">
                                            {totals.service_included
                                                ? formatMoney(
                                                      totals.service_charge,
                                                  )
                                                : formatMoney(0)}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-ink-muted">
                                            {t('table.bill.vat')}
                                        </dt>
                                        <dd className="tabular-nums">
                                            {formatMoney(totals.vat_total)}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-4 border-t border-line pt-3 text-title font-semibold text-ink">
                                        <dt>{t('table.bill.total')}</dt>
                                        <dd className="tabular-nums">
                                            {formatMoney(totals.total)}
                                        </dd>
                                    </div>
                                    {totals.paid > 0 && (
                                        <>
                                            <div className="flex justify-between gap-4">
                                                <dt className="text-ink-muted">
                                                    {t('table.bill.paid')}
                                                </dt>
                                                <dd className="tabular-nums">
                                                    {formatMoney(totals.paid)}
                                                </dd>
                                            </div>
                                            <div className="flex justify-between gap-4 font-semibold text-ink">
                                                <dt>{t('table.bill.left')}</dt>
                                                <dd className="tabular-nums">
                                                    {formatMoney(totals.left)}
                                                </dd>
                                            </div>
                                        </>
                                    )}
                                </dl>
                            )}
                            {totals.service_charge > 0 && (
                                <div className="mt-4 flex items-start gap-3 border-t border-line pt-4">
                                    <Switch
                                        id="service"
                                        checked={options.service}
                                        disabled={!online || loading}
                                        onCheckedChange={toggleService}
                                    />
                                    <div>
                                        <label
                                            htmlFor="service"
                                            className="font-semibold text-ink"
                                        >
                                            {t('table.bill.service_toggle')}
                                        </label>
                                        <p className="text-sm text-ink-muted">
                                            {t('table.bill.service_note')}
                                        </p>
                                    </div>
                                </div>
                            )}
                        </section>

                        <BillSplit
                            token={table.token}
                            options={options}
                            units={units}
                            split={split}
                        />

                        <BillPay
                            token={table.token}
                            totals={totals}
                            options={options}
                            split={split}
                            tipPresets={tipPresets}
                        />
                    </div>
                )}
            </div>

            {orders.length > 0 && (
                <div className="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-30 border-t border-line bg-paper/95 px-4 py-3 backdrop-blur-sm md:bottom-0">
                    <div className="mx-auto max-w-3xl">
                        {requests.bill ? (
                            <Button size="lg" className="w-full" disabled>
                                {t('table.bill.asked')} ·{' '}
                                {t('table.help.asked_at', {
                                    time: requests.bill,
                                })}
                            </Button>
                        ) : (
                            <HelpButtons
                                token={table.token}
                                requests={requests}
                            />
                        )}
                    </div>
                </div>
            )}
        </>
    );
}
