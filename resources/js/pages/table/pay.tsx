import { Head, Link } from '@inertiajs/react';
import { EmberLine } from '@/components/brand/ember-line';
import {
    PaymentSkeleton,
    useReloading,
} from '@/components/payments/payment-skeleton';
import { PaymentPanel } from '@/components/payments/payment-panel';
import type { PaymentPanelProps } from '@/components/payments/types';
import type { TableInfo } from '@/components/table/types';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';

type Props = {
    table: TableInfo;
    charge: {
        amount: number;
        tip: number;
        total: number;
        service_included: boolean;
    };
    payment: PaymentPanelProps;
    declined: string | null;
    urls: { bill: string };
};

export default function TablePay({
    table,
    charge,
    payment,
    declined,
    urls,
}: Props) {
    const t = useT();
    const reloading = useReloading();

    return (
        <>
            <Head title={t('table.pay.page_title', { table: table.label })} />

            <div className="container-page max-w-xl pt-6 pb-16 md:pt-10">
                <p className="text-sm font-semibold tracking-wide text-ember uppercase">
                    {t('table.banner', {
                        table: table.label,
                        area: table.area,
                    })}
                </p>
                <h1 className="mt-1 font-display text-display-lg text-ink">
                    {t('table.pay.heading')}
                </h1>
                <p className="mt-2 text-ink-muted">
                    {t('table.pay.lead', { table: table.label })}
                </p>
                <EmberLine className="my-6" />

                <dl
                    className="mb-6 flex flex-col gap-2 rounded-lg bg-clay px-4 py-3"
                    aria-label={t('table.pay.total')}
                >
                    <div className="flex justify-between gap-4">
                        <dt className="text-ink-muted">
                            {t('table.pay.bill_part')}
                        </dt>
                        <dd className="tabular-nums">
                            {formatMoney(charge.amount)}
                        </dd>
                    </div>
                    {charge.tip > 0 && (
                        <div className="flex justify-between gap-4">
                            <dt className="text-ink-muted">
                                {t('table.pay.tip')}
                            </dt>
                            <dd className="tabular-nums">
                                {formatMoney(charge.tip)}
                            </dd>
                        </div>
                    )}
                    <div className="flex items-baseline justify-between gap-4 border-t border-line pt-2 font-semibold text-ink">
                        <dt>{t('table.pay.total')}</dt>
                        <dd className="font-display text-title tabular-nums">
                            {formatMoney(charge.total)}
                        </dd>
                    </div>
                </dl>

                {reloading ? (
                    <PaymentSkeleton />
                ) : (
                    <PaymentPanel
                        key={payment.reference}
                        {...payment}
                        initialError={declined}
                    />
                )}

                <Link
                    href={urls.bill}
                    className="mt-6 inline-flex min-h-11 items-center text-sm font-semibold text-ember underline underline-offset-4 outline-hidden focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember"
                >
                    {t('table.pay.back')}
                </Link>
            </div>
        </>
    );
}
