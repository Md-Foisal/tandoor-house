import { router } from '@inertiajs/react';
import { Gift, Tag, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Price } from '@/components/brand/price';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { promo } from '@/routes/checkout';
import type { CheckoutFormApi, CheckoutProps } from './types';
import type { liveTotals } from './utils';
import { usingReward } from './utils';

export function OrderSummary({
    form,
    props,
    totals,
}: {
    form: CheckoutFormApi;
    props: CheckoutProps;
    totals: ReturnType<typeof liveTotals>;
}) {
    const t = useT();
    const { summary } = props;
    const [code, setCode] = useState('');
    const [busy, setBusy] = useState(false);
    const [codeError, setCodeError] = useState<string | undefined>();
    const rewardOn = usingReward(form.data, props);

    const submitCode = (value: string) => {
        setBusy(true);
        setCodeError(undefined);
        router.post(
            promo.url(),
            { code: value },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['summary'],
                onError: (errors) => setCodeError(errors.code),
                onSuccess: () => setCode(''),
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <section
            aria-labelledby="summary-heading"
            className="grid gap-5 rounded-lg border border-line bg-surface p-5 shadow-plate"
        >
            <h2 id="summary-heading" className="text-title font-semibold">
                {t('checkout.summary.heading')}
            </h2>

            <ul className="grid gap-3">
                {summary.lines.map((line) => (
                    <li key={line.key} className="flex justify-between gap-4">
                        <span className="min-w-0">
                            <span className="font-medium">
                                {line.quantity} × {line.name}
                            </span>
                            {(line.variant || line.modifiers.length > 0) && (
                                <span className="block text-sm text-ink-muted">
                                    {[
                                        line.variant,
                                        ...line.modifiers.map(
                                            (modifier) => modifier.name,
                                        ),
                                    ]
                                        .filter(Boolean)
                                        .join(', ')}
                                </span>
                            )}
                            {line.allergens.length > 0 && (
                                <span className="block text-sm text-ink-muted">
                                    {t('checkout.summary.contains', {
                                        allergens: line.allergens
                                            .map((code) =>
                                                t(`allergens.${code}`),
                                            )
                                            .join(', '),
                                    })}
                                </span>
                            )}
                            {!line.is_available && (
                                <span className="block text-sm font-semibold text-chilli">
                                    {t('checkout.summary.unavailable')}
                                </span>
                            )}
                        </span>
                        <Price
                            pence={line.total}
                            compact={false}
                            className="shrink-0"
                        />
                    </li>
                ))}
            </ul>

            <div className="grid gap-3 border-t border-line pt-4">
                {summary.loyalty && (
                    <LoyaltyControl form={form} props={props} />
                )}
                {rewardOn ? (
                    <p className="text-sm text-ink-muted">
                        {t('checkout.loyalty.with_reward', {
                            value: formatMoney(totals.discount),
                        })}
                    </p>
                ) : summary.promo_code ? (
                    <div className="flex items-center justify-between gap-3 rounded-md bg-leaf-soft px-3 py-2 text-sm">
                        <span className="inline-flex items-center gap-2 font-semibold">
                            <Tag className="size-4" aria-hidden="true" />
                            {t('checkout.promo.applied', {
                                code: summary.promo_code,
                            })}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            aria-label={t('checkout.promo.remove')}
                            disabled={busy}
                            onClick={() => submitCode('')}
                        >
                            <X />
                        </Button>
                    </div>
                ) : (
                    <div className="grid gap-1.5">
                        <label
                            htmlFor="promo-code"
                            className="text-sm font-medium"
                        >
                            {t('checkout.promo.label')}
                        </label>
                        <div className="flex gap-2">
                            <Input
                                id="promo-code"
                                value={code}
                                autoCapitalize="characters"
                                spellCheck={false}
                                aria-invalid={codeError ? true : undefined}
                                aria-describedby={
                                    codeError ? 'promo-code-error' : undefined
                                }
                                className="uppercase"
                                onChange={(event) =>
                                    setCode(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        event.preventDefault();

                                        if (code.trim() !== '') {
                                            submitCode(code);
                                        }
                                    }
                                }}
                            />
                            <Button
                                type="button"
                                variant="outline"
                                disabled={busy || code.trim() === ''}
                                onClick={() => submitCode(code)}
                            >
                                {busy && <Spinner />}
                                {t('checkout.promo.apply')}
                            </Button>
                        </div>
                        {codeError && (
                            <p
                                id="promo-code-error"
                                role="alert"
                                className="text-sm font-medium text-chilli"
                            >
                                {codeError}
                            </p>
                        )}
                    </div>
                )}
            </div>

            <dl
                className="grid gap-2 border-t border-line pt-4"
                aria-live="polite"
            >
                <Row
                    label={t('checkout.summary.subtotal')}
                    pence={totals.subtotal}
                />
                {totals.discount > 0 && (
                    <Row
                        label={t(
                            rewardOn
                                ? 'checkout.summary.loyalty_discount'
                                : 'checkout.summary.discount',
                        )}
                        pence={-totals.discount}
                    />
                )}
                {form.data.channel === 'delivery' && (
                    <div className="flex justify-between gap-4">
                        <dt className="text-ink-muted">
                            {t('checkout.summary.delivery')}
                        </dt>
                        <dd className="tabular-nums">
                            {totals.delivery > 0
                                ? formatMoney(totals.delivery)
                                : t('checkout.summary.delivery_pending')}
                        </dd>
                    </div>
                )}
                {form.data.channel === 'dine_in' && totals.serviceFull > 0 && (
                    <div className="grid gap-2">
                        <Row
                            label={t('checkout.summary.service', {
                                rate: props.serviceChargeRate / 100,
                            })}
                            pence={totals.service}
                        />
                        <div className="flex items-center justify-between gap-3 text-sm">
                            <label
                                htmlFor="remove-service"
                                className="text-ink-muted"
                            >
                                {t('checkout.summary.service_remove')}
                            </label>
                            <Switch
                                id="remove-service"
                                checked={form.data.remove_service_charge}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'remove_service_charge',
                                        checked,
                                    )
                                }
                            />
                        </div>
                        <p className="text-sm text-ink-muted">
                            {t('checkout.summary.service_note')}
                        </p>
                    </div>
                )}
                {totals.tip > 0 && (
                    <Row label={t('checkout.summary.tip')} pence={totals.tip} />
                )}
                <div className="mt-2 flex items-baseline justify-between gap-4 border-t border-line-strong pt-3">
                    <dt className="text-title font-semibold">
                        {t('checkout.summary.total')}
                    </dt>
                    <dd className="font-display text-display-md tabular-nums">
                        {formatMoney(totals.total)}
                    </dd>
                </div>
                <p className="text-sm text-ink-muted">
                    {t('checkout.summary.vat', {
                        vat: formatMoney(totals.vat),
                    })}
                </p>
            </dl>
        </section>
    );
}

function Row({ label, pence }: { label: string; pence: number }) {
    return (
        <div className="flex justify-between gap-4">
            <dt className="text-ink-muted">{label}</dt>
            <dd className="tabular-nums">
                {pence < 0 ? '−' : ''}
                {formatMoney(Math.abs(pence))}
            </dd>
        </div>
    );
}

function LoyaltyControl({
    form,
    props,
}: {
    form: CheckoutFormApi;
    props: CheckoutProps;
}) {
    const t = useT();
    const offer = props.summary.loyalty;
    const hasPromo = props.summary.promo_code !== null;
    const { data, setData } = form;

    // A promo and a reward never go together: clear a stale choice so it is not submitted.
    useEffect(() => {
        if (hasPromo && data.use_loyalty) {
            setData('use_loyalty', false);
        }
    }, [hasPromo, data.use_loyalty, setData]);

    if (!offer) {
        return null;
    }

    const ready = offer.discount > 0;
    const blocked = !ready || hasPromo;
    const error = (form.errors as Record<string, string | undefined>)
        .use_loyalty;
    const note = !ready
        ? t('checkout.loyalty.too_small', {
              value: formatMoney(offer.reward_value),
          })
        : hasPromo
          ? t('checkout.loyalty.with_promo')
          : form.data.use_loyalty
            ? t('checkout.loyalty.applied', {
                  value: formatMoney(offer.reward_value),
                  points: String(offer.reward_points),
                  left: String(offer.points - offer.reward_points),
              })
            : t('checkout.loyalty.balance', { points: String(offer.points) });

    return (
        <div className="grid gap-1.5 rounded-md bg-turmeric-soft px-3 py-3">
            <div className="flex min-h-11 items-center justify-between gap-3">
                <label
                    htmlFor="use-loyalty"
                    className="inline-flex items-center gap-2 text-sm font-semibold"
                >
                    <Gift className="size-4 shrink-0" aria-hidden="true" />
                    {t('checkout.loyalty.title', {
                        points: String(offer.reward_points),
                        value: formatMoney(offer.reward_value),
                    })}
                </label>
                <Switch
                    id="use-loyalty"
                    checked={form.data.use_loyalty && !blocked}
                    disabled={blocked}
                    aria-describedby="use-loyalty-note"
                    onCheckedChange={(checked) =>
                        form.setData('use_loyalty', checked)
                    }
                />
            </div>
            <p id="use-loyalty-note" className="text-sm text-ink-muted">
                {note}
            </p>
            {error && (
                <p role="alert" className="text-sm font-medium text-chilli">
                    {error}
                </p>
            )}
        </div>
    );
}
