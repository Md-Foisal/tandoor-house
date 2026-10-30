import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Info, WifiOff } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { EmberLine } from '@/components/brand/ember-line';
import { StickyActionBar } from '@/components/brand/sticky-action-bar';
import { useOnline } from '@/components/brand/states';
import { DatePicker } from '@/components/booking/date-picker';
import { DetailsForm } from '@/components/booking/details-form';
import { PartyPicker } from '@/components/booking/party-picker';
import type { SlotsState } from '@/components/booking/slot-picker';
import { SlotPicker } from '@/components/booking/slot-picker';
import type { BookingForm, CreateProps } from '@/components/booking/types';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { store } from '@/routes/book';

export default function BookCreate({
    rules,
    days,
    query,
    slots,
    contact,
    occasions,
}: CreateProps) {
    const t = useT();
    const online = useOnline();
    const { auth, name } = usePage().props;
    const summaryRef = useRef<HTMLDivElement>(null);
    const [state, setState] = useState<SlotsState>('idle');
    const latest = useRef(0);
    const mounted = useRef(false);

    const form = useForm<BookingForm>({
        ...contact,
        party_size: query.party,
        date: query.date ?? '',
        time: '',
        occasion: '',
        notes: '',
    });
    const { party_size: party, date } = form.data;
    const tooBig = party > rules.max_party;
    const deposit =
        !tooBig && party >= rules.deposit_from_party
            ? party * rules.deposit_per_head
            : 0;
    const errors = form.errors as Record<string, string | undefined>;

    const load = useCallback(() => {
        const id = ++latest.current;

        setState('loading');
        router.reload({
            only: ['slots'],
            data: { party, date },
            replace: true,
            onSuccess: () => {
                if (id === latest.current) setState('idle');
            },
            onNetworkError: () => {
                if (id === latest.current) setState('error');

                return false;
            },
            onHttpException: () => {
                if (id === latest.current) setState('error');

                return false;
            },
        });
    }, [party, date]);

    useEffect(() => {
        if (!mounted.current) {
            mounted.current = true;

            return;
        }

        form.setData('time', '');

        if (!tooBig && date !== '') {
            load();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [party, date]);

    const errorList = Object.entries(errors).filter(
        (entry): entry is [string, string] => Boolean(entry[1]),
    );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!online || tooBig) {
            return;
        }

        if (form.data.time === '') {
            form.setError('time', t('booking.errors.time'));
            summaryRef.current?.focus();

            return;
        }

        form.post(store.url(), {
            preserveScroll: true,
            onError: () => summaryRef.current?.focus(),
        });
    };

    const offline = !online;
    const blocked = offline || tooBig || form.processing;

    return (
        <>
            <Head title={t('booking.title')}>
                <meta
                    name="description"
                    content={t('booking.meta_description', {
                        name,
                    })}
                />
            </Head>

            <div className="mx-auto w-full max-w-[1200px] px-4 pt-6 pb-40 md:px-6 md:pb-16 lg:px-10">
                <header className="mb-8 grid gap-2">
                    <h1 className="font-display text-display-xl">
                        {t('booking.heading')}
                    </h1>
                    <EmberLine draw className="w-24" />
                    <p className="max-w-[56ch] text-body-lg text-ink-muted">
                        {t('booking.intro', { minutes: rules.no_show_minutes })}
                    </p>
                </header>

                <form
                    noValidate
                    onSubmit={submit}
                    className="grid grid-cols-1 items-start gap-10 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)] lg:gap-14"
                >
                    <div className="grid min-w-0 gap-8">
                        <PartyPicker
                            value={party}
                            max={rules.max_party}
                            onChange={(value) =>
                                form.setData('party_size', value)
                            }
                            error={errors.party_size}
                        />
                        <DatePicker
                            days={days}
                            value={date}
                            onChange={(value) => form.setData('date', value)}
                            error={errors.date}
                        />
                        <SlotPicker
                            slots={slots}
                            state={state}
                            value={form.data.time}
                            onChange={(value) => form.setData('time', value)}
                            onRetry={load}
                            ready={date !== ''}
                            tooBig={tooBig}
                            groupKey={`${party}-${date}`}
                            error={errors.time}
                        />
                        <p className="text-sm text-ink-muted">
                            {t('booking.time.last_seating', {
                                minutes: rules.duration_minutes,
                            })}
                        </p>
                    </div>

                    <div className="grid min-w-0 gap-6 rounded-lg border border-line bg-surface p-5 shadow-plate md:p-6">
                        {errorList.length > 0 && (
                            <div
                                ref={summaryRef}
                                tabIndex={-1}
                                role="alert"
                                className="rounded-lg border border-chilli/40 bg-chilli-soft p-4 outline-hidden focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember"
                            >
                                <h2 className="font-semibold text-ink">
                                    {t('booking.errors.summary_title')}
                                </h2>
                                <ul className="mt-2 grid list-disc gap-1 pl-5 text-sm text-ink">
                                    {errorList.map(([field, message]) => (
                                        <li key={field}>{message}</li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <DetailsForm
                            data={form.data}
                            setData={form.setData}
                            errors={errors}
                            occasions={occasions}
                            signedIn={auth.user !== null}
                        />

                        {deposit > 0 && (
                            <div className="grid gap-1 rounded-lg bg-turmeric-soft p-4 text-ink">
                                <p className="flex items-center gap-2 font-semibold">
                                    <Info
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {t('booking.deposit.title', {
                                        count: party,
                                    })}
                                </p>
                                <p className="text-sm">
                                    {t('booking.deposit.body', {
                                        from: rules.deposit_from_party,
                                        per: formatMoney(
                                            rules.deposit_per_head,
                                            { compact: true },
                                        ),
                                        amount: formatMoney(deposit, {
                                            compact: true,
                                        }),
                                    })}
                                </p>
                                <p className="text-sm text-ink-muted">
                                    {t('booking.deposit.test_mode')}
                                </p>
                            </div>
                        )}

                        <p className="text-sm text-ink-muted">
                            {t('booking.policy')}
                        </p>

                        <StickyActionBar>
                            <div className="grid gap-2">
                                {offline && (
                                    <p
                                        role="status"
                                        className="flex items-center gap-2 text-sm font-medium text-chilli"
                                    >
                                        <WifiOff
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {t('booking.offline')}
                                    </p>
                                )}
                                <Button
                                    type="submit"
                                    size="lg"
                                    className="h-12 w-full"
                                    disabled={blocked}
                                >
                                    {form.processing && <Spinner />}
                                    {form.processing
                                        ? t('booking.submitting')
                                        : deposit > 0
                                          ? t('booking.submit_deposit')
                                          : t('booking.submit')}
                                    {deposit > 0 &&
                                        !form.processing &&
                                        ` · ${formatMoney(deposit, { compact: true })}`}
                                </Button>
                            </div>
                        </StickyActionBar>
                    </div>
                </form>
            </div>
        </>
    );
}
