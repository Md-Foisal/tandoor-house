import { Head, Link } from '@inertiajs/react';
import { EmberLine } from '@/components/brand/ember-line';
import { HoldTimer } from '@/components/booking/hold-timer';
import type { PayProps } from '@/components/booking/types';
import { PaymentPanel } from '@/components/payments/payment-panel';
import { Button } from '@/components/ui/button';
import { choice, useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';

export default function BookPay({
    reservation,
    payment,
    declined,
    hold,
    urls,
}: PayProps) {
    const t = useT();
    const party = choice(
        t('booking.confirmation.party_count', {
            count: reservation.party_size,
        }),
        reservation.party_size,
    );

    return (
        <>
            <Head
                title={t('booking.pay.title', {
                    reference: reservation.reference,
                })}
            />

            <div className="mx-auto w-full max-w-xl px-4 pt-8 pb-16 md:px-6">
                <p className="font-mono text-sm text-ink-muted">
                    {reservation.reference}
                </p>
                <h1 className="mt-1 font-display text-display-lg">
                    {hold.expired
                        ? t('booking.pay.expired_heading')
                        : t('booking.pay.heading')}
                </h1>
                <p className="mt-2 text-ink-muted">
                    {hold.expired
                        ? t('booking.pay.expired_body')
                        : t('booking.pay.lead', {
                              party,
                              date: reservation.date_label,
                              time: reservation.time,
                          })}
                </p>
                <EmberLine className="my-6" />

                {hold.expired || payment === null ? (
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Button asChild size="lg" className="h-12">
                            <Link href={urls.new}>
                                {t('booking.pay.book_again')}
                            </Link>
                        </Button>
                        <Button
                            asChild
                            size="lg"
                            variant="outline"
                            className="h-12"
                        >
                            <Link href={urls.booking}>
                                {t('booking.pay.back')}
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <>
                        <div className="mb-4 grid gap-2 rounded-lg bg-clay px-4 py-3">
                            <div className="flex items-baseline justify-between gap-4">
                                <span className="text-sm font-semibold">
                                    {t('booking.pay.amount')}
                                </span>
                                <span className="font-display text-title">
                                    {formatMoney(payment.amount)}
                                </span>
                            </div>
                            <HoldTimer expiresAt={hold.expires_at} />
                        </div>

                        <PaymentPanel
                            key={payment.reference}
                            {...payment}
                            initialError={declined}
                            successMessage="payments.panel.success_booking"
                        />

                        <p className="mt-4 text-sm text-ink-muted">
                            {t('booking.pay.refund_note')}
                        </p>

                        <Link
                            href={urls.booking}
                            className="mt-4 inline-flex min-h-11 items-center text-sm font-semibold text-ember underline underline-offset-4"
                        >
                            {t('booking.pay.back')}
                        </Link>
                    </>
                )}
            </div>
        </>
    );
}
