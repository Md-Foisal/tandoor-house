import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/lib/i18n';
import { Field } from '@/components/form/field';
import type { BookingForm } from './types';

export function DetailsForm({
    data,
    setData,
    errors,
    occasions,
    signedIn,
}: {
    data: BookingForm;
    setData: <K extends keyof BookingForm>(
        key: K,
        value: BookingForm[K],
    ) => void;
    errors: Record<string, string | undefined>;
    occasions: string[];
    signedIn: boolean;
}) {
    const t = useT();

    return (
        <fieldset className="grid min-w-0 grid-cols-1 gap-4">
            <legend className="mb-1 text-title font-semibold">
                <span className="text-ember">4. </span>
                {t('booking.details.legend')}
            </legend>
            {signedIn && (
                <p className="text-sm text-ink-muted">
                    {t('booking.details.signed_in')}
                </p>
            )}
            <Field
                idPrefix="book"
                name="name"
                label={t('booking.details.name')}
                error={errors.name}
            >
                {(control) => (
                    <Input
                        {...control}
                        name="name"
                        autoComplete="name"
                        value={data.name}
                        onChange={(event) =>
                            setData('name', event.target.value)
                        }
                    />
                )}
            </Field>
            <Field
                idPrefix="book"
                name="email"
                label={t('booking.details.email')}
                hint={t('booking.details.email_hint')}
                error={errors.email}
            >
                {(control) => (
                    <Input
                        {...control}
                        name="email"
                        type="email"
                        inputMode="email"
                        autoComplete="email"
                        value={data.email}
                        onChange={(event) =>
                            setData('email', event.target.value)
                        }
                    />
                )}
            </Field>
            <Field
                idPrefix="book"
                name="phone"
                label={t('booking.details.phone')}
                hint={t('booking.details.phone_hint')}
                error={errors.phone}
            >
                {(control) => (
                    <Input
                        {...control}
                        name="phone"
                        type="tel"
                        inputMode="tel"
                        autoComplete="tel"
                        value={data.phone}
                        onChange={(event) =>
                            setData('phone', event.target.value)
                        }
                    />
                )}
            </Field>
            <Field
                idPrefix="book"
                name="occasion"
                label={`${t('booking.details.occasion')} (${t('booking.details.occasion_optional').toLowerCase()})`}
                error={errors.occasion}
            >
                {(control) => (
                    <select
                        {...control}
                        name="occasion"
                        value={data.occasion}
                        onChange={(event) =>
                            setData('occasion', event.target.value)
                        }
                        className="h-11 w-full rounded-md border border-line-strong bg-surface px-3 text-base text-ink outline-hidden focus-visible:border-ember focus-visible:shadow-[0_0_0_3px_var(--ember-soft)] aria-invalid:border-chilli"
                    >
                        <option value="">
                            {t('booking.details.occasion_none')}
                        </option>
                        {occasions.map((occasion) => (
                            <option key={occasion} value={occasion}>
                                {t(`booking.occasions.${occasion}`)}
                            </option>
                        ))}
                    </select>
                )}
            </Field>
            <Field
                idPrefix="book"
                name="notes"
                label={t('booking.details.notes')}
                hint={t('booking.details.notes_hint')}
                error={errors.notes}
            >
                {(control) => (
                    <Textarea
                        {...control}
                        name="notes"
                        rows={3}
                        maxLength={500}
                        value={data.notes}
                        onChange={(event) =>
                            setData('notes', event.target.value)
                        }
                    />
                )}
            </Field>
            {signedIn && (
                <div className="flex items-start gap-3">
                    <Checkbox
                        id="book-marketing"
                        checked={data.marketing_opt_in}
                        onCheckedChange={(checked) =>
                            setData('marketing_opt_in', checked === true)
                        }
                        className="mt-0.5"
                    />
                    <Label htmlFor="book-marketing" className="font-normal">
                        {t('booking.details.marketing')}
                    </Label>
                </div>
            )}
        </fieldset>
    );
}
