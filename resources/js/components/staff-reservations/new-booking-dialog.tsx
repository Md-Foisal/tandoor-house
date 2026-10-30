import { useForm } from '@inertiajs/react';
import { Minus, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { Rules } from './types';

type Slot = { time: string; available: boolean };

function FieldError({ id, message }: { id: string; message?: string }) {
    return message ? (
        <p id={id} role="alert" className="text-sm text-chilli">
            {message}
        </p>
    ) : null;
}

/**
 * A booking taken over the phone. The time buttons come from the same
 * availability the public form uses, so staff and guests never disagree.
 */
export function NewBookingDialog({
    open,
    onOpenChange,
    defaultDate,
    rules,
    occasions,
    canWaive,
    online,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    defaultDate: string;
    rules: Rules;
    occasions: string[];
    canWaive: boolean;
    online: boolean;
}) {
    const t = useT();
    const form = useForm({
        party_size: 2,
        date: defaultDate,
        time: '',
        name: '',
        phone: '',
        email: '',
        occasion: '',
        notes: '',
        waive_deposit: false,
    });
    const [slots, setSlots] = useState<Slot[] | null>(null);
    const [slotsFailed, setSlotsFailed] = useState(false);
    const { data, setData, errors } = form;

    useEffect(() => {
        if (open) {
            setData('date', defaultDate);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, defaultDate]);

    useEffect(() => {
        if (!open || !data.date) {
            return;
        }

        const controller = new AbortController();
        setSlots(null);
        setSlotsFailed(false);

        fetch(
            `/book/slots?party=${data.party_size}&date=${encodeURIComponent(data.date)}`,
            {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            },
        )
            .then((response) =>
                response.ok
                    ? response.json()
                    : Promise.reject(new Error(String(response.status))),
            )
            .then((json: { slots: Slot[] }) => {
                setSlots(json.slots);
                setData((current) =>
                    json.slots.some(
                        (s) => s.time === current.time && s.available,
                    )
                        ? current
                        : { ...current, time: '' },
                );
            })
            .catch((error: Error) => {
                if (error.name !== 'AbortError') {
                    setSlotsFailed(true);
                }
            });

        return () => controller.abort();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, data.date, data.party_size]);

    const deposit =
        data.party_size >= rules.deposit_from_party
            ? data.party_size * rules.deposit_per_head
            : 0;
    const available = slots?.filter((s) => s.available) ?? [];

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/staff/reservations', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92dvh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle className="font-display text-display-md">
                        {t('staff_reservations.form.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('staff_reservations.form.description')}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-4" noValidate>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="flex flex-col gap-2">
                            <Label id="party-label">
                                {t('staff_reservations.form.party')}
                            </Label>
                            <div
                                role="group"
                                aria-labelledby="party-label"
                                className="flex items-center gap-2"
                            >
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    aria-label={t(
                                        'staff_reservations.form.party_less',
                                    )}
                                    disabled={data.party_size <= 1}
                                    onClick={() =>
                                        setData(
                                            'party_size',
                                            data.party_size - 1,
                                        )
                                    }
                                >
                                    <Minus aria-hidden="true" />
                                </Button>
                                <output
                                    aria-live="polite"
                                    className="w-8 text-center font-display text-title tabular-nums"
                                >
                                    {data.party_size}
                                </output>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    aria-label={t(
                                        'staff_reservations.form.party_more',
                                    )}
                                    disabled={
                                        data.party_size >= rules.max_party
                                    }
                                    onClick={() =>
                                        setData(
                                            'party_size',
                                            data.party_size + 1,
                                        )
                                    }
                                >
                                    <Plus aria-hidden="true" />
                                </Button>
                            </div>
                            <FieldError
                                id="party-error"
                                message={errors.party_size}
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="booking-date">
                                {t('staff_reservations.form.date')}
                            </Label>
                            <Input
                                id="booking-date"
                                type="date"
                                value={data.date}
                                onChange={(e) =>
                                    setData('date', e.target.value)
                                }
                                aria-invalid={!!errors.date}
                            />
                            <FieldError id="date-error" message={errors.date} />
                        </div>
                    </div>

                    <fieldset className="flex flex-col gap-2">
                        <legend className="mb-2 text-sm font-medium">
                            {t('staff_reservations.form.time')}
                        </legend>
                        {slotsFailed ? (
                            <p role="alert" className="text-sm text-chilli">
                                {t('staff_reservations.form.times_error')}
                            </p>
                        ) : slots === null ? (
                            <div
                                className="grid grid-cols-4 gap-2"
                                role="status"
                                aria-label={t(
                                    'staff_reservations.form.times_loading',
                                )}
                            >
                                {Array.from({ length: 8 }, (_, i) => (
                                    <Skeleton key={i} className="h-11" />
                                ))}
                            </div>
                        ) : slots.length === 0 || available.length === 0 ? (
                            <p className="text-sm text-ink-muted">
                                {t('staff_reservations.form.times_none', {
                                    count: data.party_size,
                                })}
                            </p>
                        ) : (
                            <div className="grid max-h-48 grid-cols-4 gap-2 overflow-y-auto sm:grid-cols-5">
                                {slots.map((slot) => (
                                    <button
                                        key={slot.time}
                                        type="button"
                                        disabled={!slot.available}
                                        aria-pressed={data.time === slot.time}
                                        onClick={() =>
                                            setData('time', slot.time)
                                        }
                                        className={cn(
                                            'h-11 rounded-md border text-sm font-semibold tabular-nums outline-hidden focus-visible:outline-2 focus-visible:outline-ember',
                                            data.time === slot.time
                                                ? 'border-ember bg-ember text-ember-ink'
                                                : 'border-line-strong bg-surface hover:bg-clay',
                                            !slot.available &&
                                                'cursor-not-allowed border-line bg-clay text-ink-muted line-through opacity-60 hover:bg-clay',
                                        )}
                                    >
                                        {slot.time}
                                    </button>
                                ))}
                            </div>
                        )}
                        <p className="text-sm text-ink-muted">
                            {t('staff_reservations.form.times_hint')}
                        </p>
                        <FieldError id="time-error" message={errors.time} />
                    </fieldset>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="booking-name">
                                {t('staff_reservations.form.name')}
                            </Label>
                            <Input
                                id="booking-name"
                                autoComplete="off"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                aria-invalid={!!errors.name}
                                aria-describedby={
                                    errors.name ? 'name-error' : undefined
                                }
                            />
                            <FieldError id="name-error" message={errors.name} />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="booking-phone">
                                {t('staff_reservations.form.phone')}
                            </Label>
                            <Input
                                id="booking-phone"
                                type="tel"
                                inputMode="tel"
                                autoComplete="off"
                                value={data.phone}
                                onChange={(e) =>
                                    setData('phone', e.target.value)
                                }
                                aria-invalid={!!errors.phone}
                                aria-describedby={
                                    errors.phone ? 'phone-error' : undefined
                                }
                            />
                            <FieldError
                                id="phone-error"
                                message={errors.phone}
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="booking-email">
                                {t('staff_reservations.form.email')}
                            </Label>
                            <Input
                                id="booking-email"
                                type="email"
                                autoComplete="off"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                                aria-invalid={!!errors.email}
                                aria-describedby={
                                    errors.email ? 'email-error' : undefined
                                }
                            />
                            <FieldError
                                id="email-error"
                                message={errors.email}
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="booking-occasion">
                                {t('staff_reservations.form.occasion')}
                            </Label>
                            <Select
                                value={
                                    data.occasion === ''
                                        ? 'none'
                                        : data.occasion
                                }
                                onValueChange={(value) =>
                                    setData(
                                        'occasion',
                                        value === 'none' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger id="booking-occasion">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        {t(
                                            'staff_reservations.form.occasion_none',
                                        )}
                                    </SelectItem>
                                    {occasions.map((occasion) => (
                                        <SelectItem
                                            key={occasion}
                                            value={occasion}
                                        >
                                            {t(
                                                `staff_reservations.occasion.${occasion}`,
                                            )}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label htmlFor="booking-notes">
                            {t('staff_reservations.form.notes')}
                        </Label>
                        <Textarea
                            id="booking-notes"
                            rows={2}
                            maxLength={500}
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            aria-invalid={!!errors.notes}
                        />
                        <FieldError id="notes-error" message={errors.notes} />
                    </div>

                    {deposit > 0 && (
                        <div className="flex flex-col gap-2 rounded-md bg-turmeric-soft p-3 text-sm">
                            <p>
                                {t('staff_reservations.form.deposit_needed', {
                                    from: rules.deposit_from_party,
                                    amount: formatMoney(rules.deposit_per_head),
                                })}
                            </p>
                            {canWaive && (
                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        id="waive-deposit"
                                        checked={data.waive_deposit}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'waive_deposit',
                                                checked === true,
                                            )
                                        }
                                        className="mt-0.5"
                                    />
                                    <div>
                                        <Label
                                            htmlFor="waive-deposit"
                                            className="font-semibold"
                                        >
                                            {t('staff_reservations.form.waive')}
                                        </Label>
                                        <p className="text-ink-muted">
                                            {t(
                                                'staff_reservations.form.waive_hint',
                                            )}
                                        </p>
                                    </div>
                                </div>
                            )}
                            <FieldError
                                id="waive-error"
                                message={errors.waive_deposit}
                            />
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            {t('staff_reservations.form.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            size="lg"
                            disabled={
                                !online || form.processing || data.time === ''
                            }
                        >
                            {form.processing
                                ? t('staff_reservations.form.submitting')
                                : t('staff_reservations.form.submit')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
