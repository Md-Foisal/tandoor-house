import { usePage } from '@inertiajs/react';
import { Bike, Clock, Footprints, Utensils } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { ChoiceCard } from './choice-card';
import { Field } from '@/components/form/field';
import type {
    Channel,
    CheckoutFormApi,
    CheckoutProps,
    PostcodeState,
} from './types';

const icons = { delivery: Bike, pickup: Footprints, dine_in: Utensils };

export function HowStep({
    form,
    props,
    errors,
    postcode,
    onCheckPostcode,
    onResetPostcode,
}: {
    form: CheckoutFormApi;
    props: CheckoutProps;
    errors: Record<string, string | undefined>;
    postcode: PostcodeState;
    onCheckPostcode: (value: string) => void;
    onResetPostcode: () => void;
}) {
    const t = useT();
    const { openStatus } = usePage().props;
    const { data, setData } = form;

    return (
        <div className="grid gap-8">
            <fieldset className="grid gap-3">
                <legend className="mb-1 text-title font-semibold">
                    {t('checkout.how.legend')}
                </legend>
                <div className="grid gap-3 sm:grid-cols-2">
                    {props.channels.map((channel) => {
                        const Icon = icons[channel];

                        return (
                            <ChoiceCard
                                key={channel}
                                name="channel"
                                value={channel}
                                checked={data.channel === channel}
                                onSelect={(value) => {
                                    setData('channel', value as Channel);
                                    form.clearErrors();
                                }}
                                title={
                                    <span className="inline-flex items-center gap-2">
                                        <Icon
                                            className="size-4 text-ember"
                                            aria-hidden="true"
                                        />
                                        {t(`checkout.channel.${channel}`)}
                                    </span>
                                }
                                detail={t(`checkout.channel.${channel}_hint`, {
                                    table: props.table ?? '',
                                })}
                            />
                        );
                    })}
                </div>
                {errors.channel && (
                    <p className="text-sm font-medium text-chilli">
                        {errors.channel}
                    </p>
                )}
            </fieldset>

            {!openStatus.isOpen && data.channel !== 'pickup' && (
                <p
                    role="status"
                    className="rounded-lg border border-turmeric/50 bg-turmeric-soft p-4 text-sm text-ink"
                >
                    {t('checkout.how.closed', {
                        when: openStatus.opensAt ?? '',
                    })}
                </p>
            )}

            {data.channel === 'delivery' && (
                <DeliveryCheck
                    form={form}
                    props={props}
                    errors={errors}
                    postcode={postcode}
                    onCheckPostcode={onCheckPostcode}
                    onResetPostcode={onResetPostcode}
                />
            )}

            {data.channel === 'pickup' && (
                <PickupSlots form={form} props={props} errors={errors} />
            )}

            {data.channel === 'dine_in' && (
                <p className="rounded-lg border border-line bg-surface p-4 text-ink-muted">
                    {t('checkout.how.dine_in_note', {
                        table: props.table ?? '',
                    })}
                </p>
            )}
        </div>
    );
}

function DeliveryCheck({
    form,
    props,
    errors,
    postcode,
    onCheckPostcode,
    onResetPostcode,
}: {
    form: CheckoutFormApi;
    props: CheckoutProps;
    errors: Record<string, string | undefined>;
    postcode: PostcodeState;
    onCheckPostcode: (value: string) => void;
    onResetPostcode: () => void;
}) {
    const t = useT();
    const { data, setData } = form;
    const usingSaved = data.address_id !== null;
    const checked = useRef<string | null>(null);

    useEffect(() => {
        if (
            usingSaved &&
            data.postcode &&
            checked.current !== data.postcode &&
            postcode.status === 'idle'
        ) {
            checked.current = data.postcode;
            onCheckPostcode(data.postcode);
        }
    }, [usingSaved, data.postcode, postcode.status, onCheckPostcode]);

    const pickSaved = (id: string) => {
        onResetPostcode();
        checked.current = null;

        if (id === 'new') {
            setData((previous) => ({
                ...previous,
                address_id: null,
                line1: '',
                line2: '',
                city: '',
                postcode: '',
                instructions: '',
            }));

            return;
        }

        const address = props.addresses.find(
            (candidate) => String(candidate.id) === id,
        );

        if (address) {
            setData((previous) => ({
                ...previous,
                address_id: address.id,
                line1: address.line1,
                line2: address.line2 ?? '',
                city: address.city,
                postcode: address.postcode,
                instructions: address.instructions ?? '',
            }));
        }
    };

    return (
        <div className="grid gap-4">
            {props.addresses.length > 0 && (
                <fieldset className="grid gap-3">
                    <legend className="mb-1 text-title font-semibold">
                        {t('checkout.address.saved')}
                    </legend>
                    {props.addresses.map((address) => (
                        <ChoiceCard
                            key={address.id}
                            name="saved_address"
                            value={String(address.id)}
                            checked={data.address_id === address.id}
                            onSelect={pickSaved}
                            title={address.label}
                            detail={`${address.line1}, ${address.postcode}`}
                        />
                    ))}
                    <ChoiceCard
                        name="saved_address"
                        value="new"
                        checked={data.address_id === null}
                        onSelect={pickSaved}
                        title={t('checkout.address.another')}
                    />
                </fieldset>
            )}

            {!usingSaved && (
                <Field
                    name="postcode"
                    label={t('checkout.postcode.label')}
                    hint={t('checkout.postcode.hint')}
                    error={
                        postcode.status === 'invalid'
                            ? postcode.message
                            : errors.postcode
                    }
                >
                    {(control) => (
                        <div className="flex gap-2">
                            <Input
                                {...control}
                                name="postcode"
                                value={data.postcode}
                                autoComplete="postal-code"
                                autoCapitalize="characters"
                                spellCheck={false}
                                maxLength={10}
                                className="uppercase"
                                onChange={(event) => {
                                    setData('postcode', event.target.value);
                                    onResetPostcode();
                                }}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        event.preventDefault();
                                        onCheckPostcode(data.postcode);
                                    }
                                }}
                                onBlur={() => {
                                    if (
                                        data.postcode.trim() !== '' &&
                                        postcode.status === 'idle'
                                    ) {
                                        onCheckPostcode(data.postcode);
                                    }
                                }}
                            />
                            <Button
                                type="button"
                                variant="outline"
                                disabled={
                                    postcode.status === 'checking' ||
                                    data.postcode.trim() === ''
                                }
                                onClick={() => onCheckPostcode(data.postcode)}
                            >
                                {postcode.status === 'checking' && <Spinner />}
                                {t('checkout.postcode.check')}
                            </Button>
                        </div>
                    )}
                </Field>
            )}

            <div aria-live="polite" className="min-h-6">
                <PostcodeResult postcode={postcode} />
            </div>
        </div>
    );
}

function PostcodeResult({ postcode }: { postcode: PostcodeState }) {
    const t = useT();

    if (postcode.status === 'checking') {
        return (
            <p className="text-sm text-ink-muted">
                {t('checkout.postcode.checking')}
            </p>
        );
    }

    if (postcode.status === 'outside' || postcode.status === 'error') {
        return (
            <p className="rounded-lg border border-chilli/40 bg-chilli-soft p-4 text-sm text-ink">
                {postcode.message}
            </p>
        );
    }

    if (postcode.status !== 'covered') {
        return null;
    }

    return (
        <div
            className={cn(
                'grid gap-1 rounded-lg border p-4 text-sm',
                postcode.shortBy > 0
                    ? 'border-turmeric/50 bg-turmeric-soft'
                    : 'border-leaf/50 bg-leaf-soft',
            )}
        >
            <p className="font-semibold text-ink">
                {t('checkout.postcode.covered', {
                    zone: postcode.zone.name,
                    minutes: postcode.zone.eta_minutes,
                })}
            </p>
            <p className="text-ink-muted">
                {t('checkout.postcode.terms', {
                    fee: formatMoney(postcode.zone.fee, { compact: true }),
                    minimum: formatMoney(postcode.zone.minimum, {
                        compact: true,
                    }),
                })}
            </p>
            {postcode.message && (
                <p className="font-medium text-ink">{postcode.message}</p>
            )}
        </div>
    );
}

function PickupSlots({
    form,
    props,
    errors,
}: {
    form: CheckoutFormApi;
    props: CheckoutProps;
    errors: Record<string, string | undefined>;
}) {
    const t = useT();
    const { pickup } = props;
    const { data, setData } = form;

    if (!pickup.asap && pickup.slots.length === 0) {
        return (
            <p className="rounded-lg border border-line bg-surface p-4 text-ink-muted">
                {t('checkout.pickup.none')}
            </p>
        );
    }

    return (
        <fieldset className="grid gap-3">
            <legend className="mb-1 flex items-center gap-2 text-title font-semibold">
                <Clock className="size-4 text-ember" aria-hidden="true" />
                {pickup.day
                    ? t('checkout.pickup.legend_day', { day: pickup.day })
                    : t('checkout.pickup.legend')}
            </legend>
            {pickup.asap && (
                <ChoiceCard
                    name="pickup"
                    value="asap"
                    checked={data.pickup_choice === 'asap'}
                    onSelect={(value) => setData('pickup_choice', value)}
                    title={t('checkout.pickup.asap')}
                    detail={t('checkout.pickup.asap_hint', {
                        minutes: pickup.lead_minutes,
                    })}
                />
            )}
            {!pickup.asap && (
                <p className="text-sm text-ink-muted">
                    {t('checkout.pickup.booked_only')}
                </p>
            )}
            <div className="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-5">
                {pickup.slots.map((slot) => (
                    <ChoiceCard
                        key={slot.value}
                        tile
                        name="pickup"
                        value={slot.value}
                        checked={data.pickup_choice === slot.value}
                        onSelect={(value) => setData('pickup_choice', value)}
                        title={slot.label}
                    />
                ))}
            </div>
            {errors.pickup_at && (
                <p className="text-sm font-medium text-chilli">
                    {errors.pickup_at}
                </p>
            )}
        </fieldset>
    );
}
