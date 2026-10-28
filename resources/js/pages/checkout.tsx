import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import { ArrowLeft, WifiOff } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { StickyActionBar } from '@/components/brand/sticky-action-bar';
import { TicketSteps } from '@/components/brand/ticket-steps';
import { useOnline } from '@/components/brand/states';
import { DetailsStep } from '@/components/checkout/details-step';
import { ErrorSummary } from '@/components/checkout/error-summary';
import { HowStep } from '@/components/checkout/how-step';
import { OrderSummary } from '@/components/checkout/order-summary';
import { PayStep } from '@/components/checkout/pay-step';
import type {
    CheckoutForm,
    CheckoutProps,
    PostcodeState,
} from '@/components/checkout/types';
import { STEP_FIELDS } from '@/components/checkout/types';
import {
    liveTotals,
    postJson,
    stepOfField,
    tipPence,
} from '@/components/checkout/utils';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { duration, ease } from '@/lib/motion';
import { basket } from '@/routes';
import { postcode as postcodeRoute, store } from '@/routes/checkout';

type PostcodeResponse = {
    covered?: boolean;
    zone?: {
        id: number;
        name: string;
        fee: number;
        minimum: number;
        eta_minutes: number;
    };
    short_by?: number;
    message?: string | null;
    errors?: { postcode?: string[] };
};

export default function Checkout(props: CheckoutProps) {
    const t = useT();
    const reduced = useReducedMotion();
    const online = useOnline();
    const { openStatus } = usePage().props;
    const [step, setStep] = useState(0);
    const [postcode, setPostcode] = useState<PostcodeState>({ status: 'idle' });
    const summaryRef = useRef<HTMLDivElement>(null);
    const headingRef = useRef<HTMLHeadingElement>(null);
    const [attempt, setAttempt] = useState(0);

    const defaultAddress =
        props.addresses.find((address) => address.is_default) ??
        props.addresses[0];
    const form = useForm<CheckoutForm>({
        channel: props.channel,
        ...props.contact,
        address_id: defaultAddress?.id ?? null,
        line1: defaultAddress?.line1 ?? '',
        line2: defaultAddress?.line2 ?? '',
        city: defaultAddress?.city ?? '',
        postcode: defaultAddress?.postcode ?? '',
        instructions: defaultAddress?.instructions ?? '',
        pickup_choice: props.pickup.asap ? 'asap' : '',
        tip_choice: '0',
        tip_custom: '',
        payment_method: 'card',
        age_confirmed: false,
        remove_service_charge: false,
    });
    const errors = form.errors as Record<string, string | undefined>;
    const zone = postcode.status === 'covered' ? postcode.zone : null;
    const totals = useMemo(
        () => liveTotals(form.data, props, zone),
        [form.data, props, zone],
    );

    const { channel, payment_method } = form.data;

    useEffect(() => {
        const cashOnly = payment_method === 'cash' && channel !== 'delivery';
        const venueOnly =
            payment_method === 'pay_at_venue' && channel === 'delivery';

        if (cashOnly || venueOnly) {
            form.setData('payment_method', 'card');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [channel, payment_method]);

    const steps = [
        { key: 'how', label: t('checkout.steps.how') },
        { key: 'details', label: t('checkout.steps.details') },
        { key: 'pay', label: t('checkout.steps.pay') },
    ];

    const checkPostcode = useCallback(
        async (value: string) => {
            if (value.trim() === '') {
                return;
            }

            setPostcode({ status: 'checking' });

            try {
                const { ok, data } = await postJson<PostcodeResponse>(
                    postcodeRoute.url(),
                    { postcode: value },
                );

                if (!ok) {
                    setPostcode({
                        status: 'invalid',
                        message:
                            data.errors?.postcode?.[0] ??
                            t('checkout.errors.postcode'),
                    });

                    return;
                }

                if (data.covered && data.zone) {
                    form.setData('postcode', value.toUpperCase());
                    setPostcode({
                        status: 'covered',
                        zone: data.zone,
                        shortBy: data.short_by ?? 0,
                        message: data.message ?? null,
                    });
                } else {
                    setPostcode({
                        status: 'outside',
                        message: data.message ?? '',
                    });
                }
            } catch {
                setPostcode({
                    status: 'error',
                    message: t('checkout.postcode.failed'),
                });
            }
        },
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [t],
    );

    const resetPostcode = useCallback(
        () => setPostcode({ status: 'idle' }),
        [],
    );

    // Move focus to the step heading when the step changes, for keyboard and
    // screen reader users; the first render keeps the page's own focus.
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        headingRef.current?.focus();
    }, [step]);

    useEffect(() => {
        if (attempt > 0) {
            summaryRef.current?.focus();
        }
    }, [attempt]);

    const validateStep = (index: number): boolean => {
        const { data } = form;
        const found: Record<string, string> = {};

        if (index === 0) {
            if (data.channel === 'delivery' && postcode.status !== 'covered') {
                found.postcode =
                    postcode.status === 'outside' ||
                    postcode.status === 'invalid'
                        ? postcode.message
                        : t('checkout.postcode.check_first');
            }

            if (
                data.channel === 'delivery' &&
                postcode.status === 'covered' &&
                postcode.shortBy > 0
            ) {
                found.cart = t('checkout.postcode.below_minimum', {
                    short: formatMoney(postcode.shortBy, { compact: true }),
                    minimum: formatMoney(postcode.zone.minimum, {
                        compact: true,
                    }),
                });
            }

            if (data.channel === 'pickup' && data.pickup_choice === '') {
                found.pickup_at = t('checkout.pickup.choose');
            }

            if (data.channel !== 'pickup' && !openStatus.isOpen) {
                found.channel = t('checkout.errors.closed_table');
            }
        }

        if (index === 1) {
            if (data.name.trim() === '') found.name = t('checkout.errors.name');
            if (!/^\S+@\S+\.\S+$/.test(data.email.trim()))
                found.email = t('checkout.errors.email');
            if (data.phone.trim() === '')
                found.phone = t('checkout.errors.phone');

            if (data.channel === 'delivery') {
                if (data.line1.trim() === '')
                    found.line1 = t('checkout.errors.line1');
                if (data.city.trim() === '')
                    found.city = t('checkout.errors.city');
            }
        }

        form.clearErrors();

        if (Object.keys(found).length > 0) {
            form.setError(found as never);
            setAttempt((value) => value + 1);

            return false;
        }

        return true;
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!validateStep(step)) {
            return;
        }

        if (step < 2) {
            setStep(step + 1);

            return;
        }

        if (!online) {
            return;
        }

        form.transform((data) => ({
            channel: data.channel,
            name: data.name,
            email: data.email,
            phone: data.phone,
            marketing_opt_in: data.marketing_opt_in,
            address_id: data.address_id,
            line1: data.line1,
            line2: data.line2,
            city: data.city,
            postcode: data.postcode,
            instructions: data.instructions,
            pickup_at:
                data.channel === 'pickup' && data.pickup_choice !== 'asap'
                    ? data.pickup_choice
                    : null,
            tip: tipPence(data, props),
            payment_method: data.payment_method,
            age_confirmed: data.age_confirmed,
            remove_service_charge: data.remove_service_charge,
        }));

        form.post(store.url(), {
            preserveScroll: true,
            onError: (serverErrors) => {
                const first = Object.keys(serverErrors)[0];

                if (first) {
                    setStep(stepOfField(first, STEP_FIELDS));
                }

                setAttempt((value) => value + 1);
            },
        });
    };

    const hasErrors = Object.values(errors).some(Boolean);
    const last = step === 2;

    return (
        <>
            <Head title={t('checkout.title')} />

            <div className="mx-auto w-full max-w-[1200px] px-4 pt-6 pb-40 md:px-6 md:pb-16 lg:px-10">
                <div className="mb-6 flex items-center justify-between gap-4">
                    <h1 className="font-display text-display-lg">
                        {t('checkout.title')}
                    </h1>
                    <Link
                        href={basket()}
                        className="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-ember underline underline-offset-4"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        {t('checkout.back_to_basket')}
                    </Link>
                </div>

                <TicketSteps
                    steps={steps}
                    current={step}
                    orientation="horizontal"
                    className="mb-8"
                />

                <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_24rem] lg:gap-12">
                    <form
                        noValidate
                        onSubmit={submit}
                        aria-labelledby="step-heading"
                        className="grid gap-6"
                    >
                        {hasErrors && (
                            <ErrorSummary ref={summaryRef} errors={errors} />
                        )}

                        <h2
                            id="step-heading"
                            ref={headingRef}
                            tabIndex={-1}
                            className="font-display text-display-md outline-none"
                        >
                            {t(`checkout.heading.${steps[step].key}`)}
                        </h2>

                        <AnimatePresence mode="wait" initial={false}>
                            <motion.div
                                key={step}
                                initial={
                                    reduced
                                        ? { opacity: 0 }
                                        : { opacity: 0, x: 24 }
                                }
                                animate={{ opacity: 1, x: 0 }}
                                exit={
                                    reduced
                                        ? { opacity: 0 }
                                        : { opacity: 0, x: -24 }
                                }
                                transition={{
                                    duration: duration.base,
                                    ease: ease.out,
                                }}
                            >
                                {step === 0 && (
                                    <HowStep
                                        form={form}
                                        props={props}
                                        errors={errors}
                                        postcode={postcode}
                                        onCheckPostcode={checkPostcode}
                                        onResetPostcode={resetPostcode}
                                    />
                                )}
                                {step === 1 && (
                                    <DetailsStep
                                        form={form}
                                        props={props}
                                        errors={errors}
                                        onChangePostcode={() => {
                                            form.setData('address_id', null);
                                            resetPostcode();
                                            setStep(0);
                                        }}
                                    />
                                )}
                                {step === 2 && (
                                    <PayStep
                                        form={form}
                                        props={props}
                                        errors={errors}
                                    />
                                )}
                            </motion.div>
                        </AnimatePresence>

                        {!online && (
                            <p
                                role="status"
                                className="flex items-center gap-2 rounded-lg bg-ink px-4 py-3 text-sm font-medium text-paper"
                            >
                                <WifiOff
                                    className="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                {t('checkout.offline')}
                            </p>
                        )}

                        <StickyActionBar>
                            <div className="flex items-center gap-3">
                                {step > 0 && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="lg"
                                        disabled={form.processing}
                                        onClick={() => {
                                            form.clearErrors();
                                            setStep(step - 1);
                                        }}
                                    >
                                        {t('checkout.back')}
                                    </Button>
                                )}
                                <Button
                                    type="submit"
                                    size="lg"
                                    className="flex-1"
                                    disabled={
                                        form.processing || (last && !online)
                                    }
                                    aria-busy={form.processing}
                                >
                                    {form.processing && <Spinner />}
                                    {last
                                        ? form.processing
                                            ? t('checkout.placing')
                                            : t('checkout.place', {
                                                  total: formatMoney(
                                                      totals.total,
                                                  ),
                                              })
                                        : t('checkout.continue')}
                                </Button>
                            </div>
                        </StickyActionBar>
                    </form>

                    <aside className="lg:sticky lg:top-24">
                        <OrderSummary
                            form={form}
                            props={props}
                            totals={totals}
                        />
                    </aside>
                </div>
            </div>
        </>
    );
}
