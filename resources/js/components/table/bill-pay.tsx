import { router } from '@inertiajs/react';
import { CreditCard } from 'lucide-react';
import { useState } from 'react';
import { useOnline } from '@/components/brand/states';
import { ChoiceCard } from '@/components/checkout/choice-card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import type { BillProps } from './types';

const MAX_TIP = 50000;

/**
 * Pay the whole of what is left, or one share of the split shown above,
 * by card. The server works out the limit again and refuses overpayment.
 */
export function BillPay({
    token,
    totals,
    options,
    split,
    tipPresets,
}: {
    token: string;
    totals: BillProps['totals'];
    options: BillProps['options'];
    split: BillProps['split'];
    tipPresets: number[];
}) {
    const t = useT();
    const online = useOnline();
    const [pick, setPick] = useState('whole');
    const [tipChoice, setTipChoice] = useState('0');
    const [custom, setCustom] = useState('');
    const [starting, setStarting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const shareIndex = pick.startsWith('share-')
        ? Number(pick.slice('share-'.length))
        : null;
    const shareAmount =
        shareIndex !== null ? (split?.shares[shareIndex] ?? 0) : null;
    const amount =
        shareAmount !== null ? Math.min(shareAmount, totals.left) : totals.left;

    const tip =
        tipChoice === 'custom'
            ? customPence(custom)
            : Math.floor((amount * Number(tipChoice)) / 10000);

    const label = (index: number) =>
        options.mode === 'even' || options.mode === 'items'
            ? t('table.bill.share', { n: index + 1 })
            : '';

    const start = () => {
        setError(null);
        router.post(
            `/t/${token}/pay`,
            {
                ...(shareIndex === null ? { whole: 1 } : { amount }),
                service: options.service ? 1 : 0,
                tip,
            },
            {
                onStart: () => setStarting(true),
                onError: (errors) =>
                    setError(errors.amount ?? t('table.pay.failed')),
                onNetworkError: () => setError(t('table.pay.failed')),
                onFinish: () => setStarting(false),
            },
        );
    };

    if (totals.left < 1) {
        return (
            <section
                aria-labelledby="pay-title"
                className="flex flex-col gap-2"
            >
                <h2
                    id="pay-title"
                    className="font-display text-display-md text-ink"
                >
                    {t('table.pay.title')}
                </h2>
                <p className="max-w-[56ch] text-ink-muted">
                    {t('table.pay.none_left')}
                </p>
            </section>
        );
    }

    const shares = split?.shares ?? [];
    const disabled = !online || starting || amount < 1;

    return (
        <section aria-labelledby="pay-title" className="flex flex-col gap-6">
            <div className="flex flex-col gap-2">
                <h2
                    id="pay-title"
                    className="font-display text-display-md text-ink"
                >
                    {t('table.pay.title')}
                </h2>
                <p className="max-w-[56ch] text-ink-muted">
                    {t('table.pay.body')}
                </p>
            </div>

            <fieldset className="grid gap-2">
                <legend className="mb-1 font-semibold text-ink">
                    {t('table.pay.what')}
                </legend>
                <ChoiceCard
                    name="pay-what"
                    value="whole"
                    checked={pick === 'whole'}
                    onSelect={setPick}
                    title={t('table.pay.whole')}
                    aside={
                        <span className="font-semibold tabular-nums">
                            {formatMoney(totals.left)}
                        </span>
                    }
                />
                {shares.map((share, index) => (
                    <ChoiceCard
                        key={index}
                        name="pay-what"
                        value={`share-${index}`}
                        checked={pick === `share-${index}`}
                        onSelect={setPick}
                        disabled={share < 1}
                        title={t('table.pay.share', { who: label(index) })}
                        aside={
                            <span className="font-semibold tabular-nums">
                                {formatMoney(share)}
                            </span>
                        }
                    />
                ))}
                {shares.length === 0 && (
                    <p className="text-sm text-ink-muted">
                        {t('table.pay.share_none')}
                    </p>
                )}
                {totals.paid > 0 && (
                    <p className="text-sm text-ink-muted">
                        {t('table.bill.left_note')}
                    </p>
                )}
            </fieldset>

            <fieldset className="grid gap-3">
                <legend className="font-semibold text-ink">
                    {t('table.pay.tip_legend')}
                </legend>
                <p className="text-sm text-ink-muted">
                    {t('table.pay.tip_note')}
                </p>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    {tipPresets.map((basisPoints) => (
                        <ChoiceCard
                            key={basisPoints}
                            tile
                            name="pay-tip"
                            value={String(basisPoints)}
                            checked={tipChoice === String(basisPoints)}
                            onSelect={setTipChoice}
                            title={
                                basisPoints === 0
                                    ? t('table.pay.tip_none')
                                    : `${basisPoints / 100}%`
                            }
                            detail={
                                basisPoints === 0
                                    ? undefined
                                    : formatMoney(
                                          Math.floor(
                                              (amount * basisPoints) / 10000,
                                          ),
                                      )
                            }
                        />
                    ))}
                    <ChoiceCard
                        tile
                        name="pay-tip"
                        value="custom"
                        checked={tipChoice === 'custom'}
                        onSelect={setTipChoice}
                        title={t('table.pay.tip_custom')}
                    />
                </div>
                {tipChoice === 'custom' && (
                    <div className="grid max-w-40 gap-1.5">
                        <label
                            htmlFor="pay-tip-custom"
                            className="text-sm font-semibold text-ink"
                        >
                            {t('table.pay.tip_custom_label')}
                        </label>
                        <div className="relative">
                            <span
                                aria-hidden="true"
                                className="pointer-events-none absolute inset-y-0 left-3.5 grid place-items-center text-ink-muted"
                            >
                                £
                            </span>
                            <Input
                                id="pay-tip-custom"
                                inputMode="decimal"
                                className="pl-8"
                                value={custom}
                                placeholder="0.00"
                                onChange={(event) =>
                                    setCustom(
                                        event.target.value.replace(
                                            /[^0-9.,]/g,
                                            '',
                                        ),
                                    )
                                }
                            />
                        </div>
                    </div>
                )}
                <p className="sr-only" aria-live="polite">
                    {t('table.pay.tip_announce', { tip: formatMoney(tip) })}
                </p>
            </fieldset>

            <dl
                className="flex flex-col gap-2 rounded-lg bg-clay px-4 py-3"
                aria-live="polite"
                aria-atomic="true"
            >
                <div className="flex justify-between gap-4">
                    <dt className="text-ink-muted">
                        {t('table.pay.summary_bill')}
                    </dt>
                    <dd className="tabular-nums">{formatMoney(amount)}</dd>
                </div>
                <div className="flex justify-between gap-4">
                    <dt className="text-ink-muted">
                        {t('table.pay.summary_tip')}
                    </dt>
                    <dd className="tabular-nums">{formatMoney(tip)}</dd>
                </div>
                <div className="flex justify-between gap-4 border-t border-line pt-2 font-semibold text-ink">
                    <dt>{t('table.pay.summary_total')}</dt>
                    <dd className="font-display text-title tabular-nums">
                        {formatMoney(amount + tip)}
                    </dd>
                </div>
            </dl>

            {error && (
                <p role="alert" className="text-sm font-medium text-chilli">
                    {error}
                </p>
            )}
            {!online && (
                <p className="text-sm text-ink-muted">
                    {t('table.pay.offline')}
                </p>
            )}

            <Button
                type="button"
                size="lg"
                className="w-full"
                disabled={disabled}
                onClick={start}
            >
                {starting ? (
                    <>
                        <Spinner />
                        {t('table.pay.starting')}
                    </>
                ) : (
                    <>
                        <CreditCard aria-hidden="true" />
                        {t('table.pay.pay_button', {
                            amount: formatMoney(amount + tip),
                        })}
                    </>
                )}
            </Button>

            <div className="grid gap-1 border-t border-line pt-4">
                <h3 className="font-semibold text-ink">
                    {t('table.pay.waiter_title')}
                </h3>
                <p className="text-sm text-ink-muted">
                    {t('table.pay.waiter_body')}
                </p>
            </div>
        </section>
    );
}

function customPence(value: string): number {
    const pounds = Number.parseFloat(value.replace(',', '.'));

    return Number.isFinite(pounds) && pounds > 0
        ? Math.min(MAX_TIP, Math.round(pounds * 100))
        : 0;
}
