import { Banknote, CreditCard, Store } from 'lucide-react';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { ChoiceCard } from './choice-card';
import { Field } from '@/components/form/field';
import type { CheckoutFormApi, CheckoutProps, PaymentMethod } from './types';
import { discountPence, tipPence } from './utils';

const methodIcons = {
    card: CreditCard,
    apple_pay: CreditCard,
    google_pay: CreditCard,
    pay_at_venue: Store,
    cash: Banknote,
} satisfies Record<PaymentMethod, typeof CreditCard>;

export function PayStep({
    form,
    props,
    errors,
}: {
    form: CheckoutFormApi;
    props: CheckoutProps;
    errors: Record<string, string | undefined>;
}) {
    const t = useT();
    const { data, setData } = form;
    const methods: PaymentMethod[] = [
        'card',
        data.channel === 'delivery' ? 'cash' : 'pay_at_venue',
    ];
    const online =
        data.payment_method !== 'cash' &&
        data.payment_method !== 'pay_at_venue';
    const testMode = props.paymentDriver === 'fake';
    const base = props.summary.subtotal - discountPence(data, props);

    return (
        <div className="grid gap-8">
            <fieldset className="grid gap-3">
                <legend className="text-title font-semibold">
                    {t('checkout.tip.legend')}
                </legend>
                <p className="text-sm text-ink-muted">
                    {t('checkout.tip.note')}
                </p>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    {props.tipPresets.map((basisPoints) => (
                        <ChoiceCard
                            key={basisPoints}
                            tile
                            name="tip"
                            value={String(basisPoints)}
                            checked={data.tip_choice === String(basisPoints)}
                            onSelect={(value) => setData('tip_choice', value)}
                            title={
                                basisPoints === 0
                                    ? t('checkout.tip.none')
                                    : `${basisPoints / 100}%`
                            }
                            detail={
                                basisPoints === 0
                                    ? undefined
                                    : formatMoney(
                                          Math.floor(
                                              (base * basisPoints) / 10000,
                                          ),
                                      )
                            }
                        />
                    ))}
                    <ChoiceCard
                        tile
                        name="tip"
                        value="custom"
                        checked={data.tip_choice === 'custom'}
                        onSelect={(value) => setData('tip_choice', value)}
                        title={t('checkout.tip.custom')}
                    />
                </div>
                {data.tip_choice === 'custom' && (
                    <Field
                        name="tip_custom"
                        label={t('checkout.tip.custom_label')}
                        error={errors.tip}
                    >
                        {(control) => (
                            <div className="relative max-w-40">
                                <span
                                    aria-hidden="true"
                                    className="pointer-events-none absolute inset-y-0 left-3.5 grid place-items-center text-ink-muted"
                                >
                                    £
                                </span>
                                <Input
                                    {...control}
                                    inputMode="decimal"
                                    className="pl-8"
                                    value={data.tip_custom}
                                    placeholder="0.00"
                                    onChange={(event) =>
                                        setData(
                                            'tip_custom',
                                            event.target.value.replace(
                                                /[^0-9.,]/g,
                                                '',
                                            ),
                                        )
                                    }
                                />
                            </div>
                        )}
                    </Field>
                )}
                {data.tip_choice !== 'custom' && errors.tip && (
                    <p className="text-sm font-medium text-chilli">
                        {errors.tip}
                    </p>
                )}
                <p className="sr-only" aria-live="polite">
                    {t('checkout.tip.announce', {
                        tip: formatMoney(tipPence(data, props)),
                    })}
                </p>
            </fieldset>

            <fieldset className="grid gap-3">
                <legend className="mb-1 text-title font-semibold">
                    {t('checkout.pay.legend')}
                </legend>
                <div className="grid gap-3 sm:grid-cols-2">
                    {methods.map((method) => {
                        const Icon = methodIcons[method];

                        return (
                            <ChoiceCard
                                key={method}
                                name="payment_method"
                                value={method}
                                checked={data.payment_method === method}
                                onSelect={(value) =>
                                    setData(
                                        'payment_method',
                                        value as PaymentMethod,
                                    )
                                }
                                title={
                                    <span className="inline-flex items-center gap-2">
                                        <Icon
                                            className="size-4 text-ember"
                                            aria-hidden="true"
                                        />
                                        {t(`checkout.pay.${method}`)}
                                    </span>
                                }
                                detail={t(`checkout.pay.${method}_hint`)}
                            />
                        );
                    })}
                </div>
                {errors.payment_method && (
                    <p className="text-sm font-medium text-chilli">
                        {errors.payment_method}
                    </p>
                )}
                <div
                    className={cn(
                        'rounded-lg border border-dashed border-line-strong p-4 text-sm',
                        online ? 'bg-turmeric-soft' : 'bg-clay',
                    )}
                >
                    <p className="font-semibold text-ink">
                        {online
                            ? t('checkout.pay.online_title')
                            : t('checkout.pay.later_title')}
                    </p>
                    <p className="mt-1 text-ink-muted">
                        {online
                            ? t('checkout.pay.online_body')
                            : t(
                                  data.payment_method === 'cash'
                                      ? 'checkout.pay.cash_body'
                                      : 'checkout.pay.venue_body',
                              )}
                    </p>
                    {online && testMode && (
                        <p className="mt-2 text-ink-muted">
                            <span className="font-semibold text-ink">
                                {t('checkout.pay.test_title')}.
                            </span>{' '}
                            {t('checkout.pay.test_body')}
                        </p>
                    )}
                </div>
            </fieldset>

            {props.summary.has_alcohol && (
                <div className="grid gap-2">
                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="field-age_confirmed"
                            checked={data.age_confirmed}
                            aria-invalid={
                                errors.age_confirmed ? true : undefined
                            }
                            aria-describedby={
                                errors.age_confirmed
                                    ? 'field-age_confirmed-error'
                                    : undefined
                            }
                            onCheckedChange={(checked) =>
                                setData('age_confirmed', checked === true)
                            }
                            className="mt-0.5"
                        />
                        <Label
                            htmlFor="field-age_confirmed"
                            className="leading-snug font-normal"
                        >
                            {t('checkout.age.label')}
                        </Label>
                    </div>
                    {errors.age_confirmed && (
                        <p
                            id="field-age_confirmed-error"
                            className="text-sm font-medium text-chilli"
                        >
                            {errors.age_confirmed}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}
