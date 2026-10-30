import { Head, Link, router } from "@inertiajs/react";
import { Store } from "lucide-react";
import { useState } from "react";
import { EmberLine } from "@/components/brand/ember-line";
import {
    PaymentSkeleton,
    useReloading,
} from "@/components/payments/payment-skeleton";
import { PaymentPanel } from "@/components/payments/payment-panel";
import type { PaymentPanelProps } from "@/components/payments/types";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { useT } from "@/lib/i18n";
import { formatMoney } from "@/lib/money";

type Props = {
    order: { number: string; channel: string; total: number };
    payment: PaymentPanelProps;
    declined: string | null;
    urls: { tracking: string; venue: string | null };
};

export default function Pay({ order, payment, declined, urls }: Props) {
    const t = useT();
    const reloading = useReloading();
    const [switching, setSwitching] = useState(false);

    return (
        <>
            <Head
                title={t("checkout.payment_page.title", {
                    number: order.number,
                })}
            />

            <div className="mx-auto w-full max-w-xl px-4 pt-8 pb-16 md:px-6">
                <p className="font-mono text-sm text-ink-muted">
                    {order.number}
                </p>
                <h1 className="mt-1 font-display text-display-lg">
                    {t("checkout.payment_page.heading")}
                </h1>
                <p className="mt-2 text-ink-muted">
                    {t("checkout.payment_page.lead", { number: order.number })}
                </p>
                <EmberLine className="my-6" />

                <div className="mb-6 flex items-baseline justify-between gap-4 rounded-lg bg-clay px-4 py-3">
                    <span className="text-sm font-semibold">
                        {t("checkout.payment_page.total")}
                    </span>
                    <span className="font-display text-title">
                        {formatMoney(payment.amount)}
                    </span>
                </div>

                {reloading ? (
                    <PaymentSkeleton />
                ) : (
                    <PaymentPanel
                        key={payment.reference}
                        {...payment}
                        initialError={declined}
                    />
                )}

                {urls.venue && (
                    <div className="mt-8 grid gap-2 border-t border-line pt-6">
                        <h2 className="font-semibold">
                            {t("checkout.payment_page.venue_title")}
                        </h2>
                        <p className="text-sm text-ink-muted">
                            {t("checkout.payment_page.venue_body")}
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            size="lg"
                            disabled={switching}
                            onClick={() =>
                                router.post(
                                    urls.venue as string,
                                    {},
                                    {
                                        onStart: () => setSwitching(true),
                                        onFinish: () => setSwitching(false),
                                    },
                                )
                            }
                        >
                            {switching ? (
                                <Spinner />
                            ) : (
                                <Store aria-hidden="true" />
                            )}
                            {t("checkout.payment_page.venue")}
                        </Button>
                    </div>
                )}

                <Link
                    href={urls.tracking}
                    className="mt-6 inline-flex min-h-11 items-center text-sm font-semibold text-ember underline underline-offset-4"
                >
                    {t("checkout.payment_page.tracking")}
                </Link>
            </div>
        </>
    );
}
