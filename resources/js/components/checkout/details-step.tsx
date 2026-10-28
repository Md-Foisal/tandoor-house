import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/lib/i18n';
import { Field } from '@/components/form/field';
import type { CheckoutFormApi, CheckoutProps } from './types';

export function DetailsStep({
    form,
    props,
    errors,
    onChangePostcode,
}: {
    form: CheckoutFormApi;
    props: CheckoutProps;
    errors: Record<string, string | undefined>;
    onChangePostcode: () => void;
}) {
    const t = useT();
    const { data, setData } = form;
    const delivery = data.channel === 'delivery';

    return (
        <div className="grid gap-8">
            {delivery && (
                <fieldset className="grid gap-4">
                    <legend className="mb-1 text-title font-semibold">
                        {t('checkout.address.legend')}
                    </legend>
                    <p className="flex flex-wrap items-center gap-x-3 text-sm text-ink-muted">
                        <span>
                            {t('checkout.address.postcode_is', {
                                postcode: data.postcode,
                            })}
                        </span>
                        <button
                            type="button"
                            className="min-h-11 font-semibold text-ember underline underline-offset-4"
                            onClick={onChangePostcode}
                        >
                            {t('checkout.address.change')}
                        </button>
                    </p>
                    <Field
                        name="line1"
                        label={t('checkout.address.line1')}
                        error={errors.line1}
                    >
                        {(control) => (
                            <Input
                                {...control}
                                name="line1"
                                value={data.line1}
                                autoComplete="address-line1"
                                onChange={(event) =>
                                    setData('line1', event.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Field
                        name="line2"
                        label={t('checkout.address.line2')}
                        error={errors.line2}
                    >
                        {(control) => (
                            <Input
                                {...control}
                                name="line2"
                                value={data.line2}
                                autoComplete="address-line2"
                                onChange={(event) =>
                                    setData('line2', event.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Field
                        name="city"
                        label={t('checkout.address.city')}
                        error={errors.city}
                    >
                        {(control) => (
                            <Input
                                {...control}
                                name="city"
                                value={data.city}
                                autoComplete="address-level2"
                                onChange={(event) =>
                                    setData('city', event.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Field
                        name="instructions"
                        label={t('checkout.address.instructions')}
                        hint={t('checkout.address.instructions_hint')}
                        error={errors.instructions}
                    >
                        {(control) => (
                            <Textarea
                                {...control}
                                name="instructions"
                                rows={2}
                                maxLength={200}
                                value={data.instructions}
                                onChange={(event) =>
                                    setData('instructions', event.target.value)
                                }
                            />
                        )}
                    </Field>
                </fieldset>
            )}

            <fieldset className="grid gap-4">
                <legend className="mb-1 text-title font-semibold">
                    {t('checkout.contact.legend')}
                </legend>
                <Field
                    name="name"
                    label={t('checkout.contact.name')}
                    error={errors.name}
                >
                    {(control) => (
                        <Input
                            {...control}
                            name="name"
                            value={data.name}
                            autoComplete="name"
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                        />
                    )}
                </Field>
                <Field
                    name="email"
                    label={t('checkout.contact.email')}
                    hint={t('checkout.contact.email_hint')}
                    error={errors.email}
                >
                    {(control) => (
                        <Input
                            {...control}
                            name="email"
                            type="email"
                            inputMode="email"
                            value={data.email}
                            autoComplete="email"
                            onChange={(event) =>
                                setData('email', event.target.value)
                            }
                        />
                    )}
                </Field>
                <Field
                    name="phone"
                    label={t('checkout.contact.phone')}
                    hint={t('checkout.contact.phone_hint')}
                    error={errors.phone}
                >
                    {(control) => (
                        <Input
                            {...control}
                            name="phone"
                            type="tel"
                            inputMode="tel"
                            value={data.phone}
                            autoComplete="tel"
                            placeholder="07700 900123"
                            onChange={(event) =>
                                setData('phone', event.target.value)
                            }
                        />
                    )}
                </Field>
                <div className="flex items-start gap-3">
                    <Checkbox
                        id="field-marketing_opt_in"
                        checked={data.marketing_opt_in}
                        onCheckedChange={(checked) =>
                            setData('marketing_opt_in', checked === true)
                        }
                        className="mt-0.5"
                    />
                    <Label
                        htmlFor="field-marketing_opt_in"
                        className="leading-snug font-normal"
                    >
                        {t('checkout.contact.marketing')}
                    </Label>
                </div>
                {!props.contact.email && (
                    <p className="text-sm text-ink-muted">
                        {t('checkout.contact.guest_note')}
                    </p>
                )}
            </fieldset>
        </div>
    );
}
