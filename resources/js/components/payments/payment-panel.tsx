import { router } from '@inertiajs/react';
import { CheckCircle2, Lock, TriangleAlert, WifiOff } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useOnline } from '@/components/brand/states';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { PaymentSkeleton } from './payment-skeleton';
import { confirmPayment } from './post';
import { TestCardForm } from './test-card-form';
import type { TestCard } from './test-card-form';
import type { PaymentPanelProps } from './types';

type Status =
    | 'loading'
    | 'ready'
    | 'processing'
    | 'success'
    | 'declined'
    | 'error'
    | 'pending';

type StripeBits = {
    stripe: import('@stripe/stripe-js').Stripe;
    elements: import('@stripe/stripe-js').StripeElements;
};

function themeColour(name: string): string | undefined {
    const value = getComputedStyle(document.documentElement)
        .getPropertyValue(name)
        .trim();

    return value === '' ? undefined : value;
}

/**
 * Takes one payment. Stripe's Payment Element (card, Apple Pay, Google Pay)
 * when the server says the driver is Stripe, otherwise the labelled test
 * form. Used by checkout now; booking deposits and pay-at-table reuse it.
 */
export function PaymentPanel({
    clientSecret,
    driver,
    publishableKey,
    amount,
    reference,
    confirmUrl,
    returnUrl,
    initialError,
    onPaid,
}: PaymentPanelProps & {
    initialError?: string | null;
    onPaid?: (redirect: string | null) => void;
}) {
    const t = useT();
    const online = useOnline();
    const useStripe = driver === 'stripe';
    const mount = useRef<HTMLDivElement>(null);
    const bits = useRef<StripeBits | null>(null);
    const [status, setStatus] = useState<Status>(
        useStripe ? 'loading' : initialError ? 'declined' : 'ready',
    );
    const [message, setMessage] = useState<string | null>(initialError ?? null);
    const [card, setCard] = useState<TestCard>('tok_visa');
    const [loadTry, setLoadTry] = useState(0);

    useEffect(() => {
        if (!useStripe || !publishableKey || !clientSecret) {
            return;
        }

        let cancelled = false;
        let element: { destroy: () => void } | null = null;

        void (async () => {
            try {
                const { loadStripe } = await import('@stripe/stripe-js');
                const stripe = await loadStripe(publishableKey);

                if (!stripe || cancelled || !mount.current) {
                    throw new Error('stripe');
                }

                const elements = stripe.elements({
                    clientSecret,
                    appearance: {
                        variables: {
                            colorText: themeColour('--ink'),
                            colorBackground: themeColour('--surface'),
                            colorDanger: themeColour('--chilli'),
                            colorPrimary: themeColour('--ember'),
                            borderRadius: '8px',
                        },
                    },
                });
                const payment = elements.create('payment');

                payment.mount(mount.current);
                payment.on('ready', () => !cancelled && setStatus('ready'));
                element = payment;
                bits.current = { stripe, elements };
            } catch {
                if (!cancelled) {
                    setMessage(t('payments.panel.load_failed'));
                    setStatus('error');
                }
            }
        })();

        return () => {
            cancelled = true;
            element?.destroy();
            bits.current = null;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [useStripe, publishableKey, clientSecret, loadTry]);

    const finish = (result: Awaited<ReturnType<typeof confirmPayment>>) => {
        if (result.status === 'paid') {
            setStatus('success');
            setMessage(null);
            window.setTimeout(() => {
                if (onPaid) {
                    onPaid(result.redirect);
                } else if (result.redirect) {
                    router.visit(result.redirect);
                }
            }, 600);

            return;
        }

        setMessage(
            result.status === 'failed'
                ? (result.message ?? t('payments.panel.declined'))
                : t('payments.panel.pending'),
        );
        setStatus(result.status === 'failed' ? 'declined' : 'pending');
    };

    const pay = async () => {
        if (!online || status === 'processing' || status === 'success') {
            return;
        }

        if (!useStripe && status === 'declined') {
            // A declined test payment is closed for good; the page starts a fresh one.
            setStatus('ready');
            setMessage(null);
            router.reload();

            return;
        }

        setStatus('processing');
        setMessage(null);

        try {
            if (useStripe) {
                const current = bits.current;

                if (!current) {
                    throw new Error('stripe');
                }

                const { error } = await current.stripe.confirmPayment({
                    elements: current.elements,
                    confirmParams: { return_url: returnUrl },
                    redirect: 'if_required',
                });

                if (error) {
                    setMessage(error.message ?? t('payments.panel.declined'));
                    setStatus('declined');

                    return;
                }

                finish(await confirmPayment(confirmUrl, { reference }));

                return;
            }

            finish(
                await confirmPayment(confirmUrl, { reference, token: card }),
            );
        } catch {
            setMessage(t('payments.panel.error'));
            setStatus('error');
        }
    };

    const busy = status === 'processing' || status === 'success';
    const stripeFailedToLoad = useStripe && status === 'error' && !bits.current;
    const total = formatMoney(amount);

    return (
        <section aria-labelledby="payment-panel-title" className="grid gap-5">
            <h2 id="payment-panel-title" className="sr-only">
                {t('payments.panel.title', { amount: total })}
            </h2>

            {useStripe && status === 'loading' && <PaymentSkeleton />}

            {useStripe ? (
                <div
                    ref={mount}
                    aria-busy={status === 'loading'}
                    className={status === 'loading' ? 'hidden' : undefined}
                />
            ) : (
                <TestCardForm value={card} onChange={setCard} disabled={busy} />
            )}

            <div aria-live="polite" role="status" className="grid gap-3">
                {status === 'processing' && (
                    <p className="text-sm text-ink-muted">
                        {t('payments.panel.processing')}
                    </p>
                )}
                {status === 'success' && (
                    <p className="flex items-center gap-2 font-semibold text-leaf">
                        <CheckCircle2 className="size-5" aria-hidden="true" />
                        {t('payments.panel.success')}
                    </p>
                )}
                {message && status !== 'success' && (
                    <p
                        role="alert"
                        className="flex items-start gap-2 rounded-lg border border-chilli bg-surface p-3 text-sm font-medium text-chilli"
                    >
                        <TriangleAlert
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {message}
                    </p>
                )}
            </div>

            {!online && (
                <p
                    role="status"
                    className="flex items-center gap-2 rounded-lg bg-ink px-4 py-3 text-sm font-medium text-paper"
                >
                    <WifiOff className="size-4 shrink-0" aria-hidden="true" />
                    {t('payments.panel.offline')}
                </p>
            )}

            {stripeFailedToLoad ? (
                <Button
                    type="button"
                    size="lg"
                    variant="outline"
                    onClick={() => {
                        setStatus('loading');
                        setMessage(null);
                        setLoadTry((n) => n + 1);
                    }}
                >
                    {t('payments.panel.retry')}
                </Button>
            ) : (
                <Button
                    type="button"
                    size="lg"
                    disabled={
                        !online || busy || (useStripe && status === 'loading')
                    }
                    aria-busy={status === 'processing'}
                    onClick={pay}
                >
                    {status === 'processing' && <Spinner />}
                    {status === 'processing'
                        ? t('payments.panel.processing')
                        : status === 'declined' || status === 'pending'
                          ? t('payments.panel.retry')
                          : t('payments.panel.pay', { amount: total })}
                </Button>
            )}

            <p className="flex items-center gap-2 text-xs text-ink-muted">
                <Lock className="size-3.5 shrink-0" aria-hidden="true" />
                {t('payments.panel.secure')}
            </p>
        </section>
    );
}
