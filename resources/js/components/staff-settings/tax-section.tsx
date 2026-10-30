import { useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { Field } from '@/components/form/field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useT } from '@/lib/i18n';
import { MoneyLocked, SectionCard } from './section-card';
import type { SettingsContext, Tax } from './types';
import {
    bpsToPercent,
    penceToPounds,
    percentToBps,
    poundsToPence,
} from './types';

type Data = {
    vat_rate: string;
    service_charge_rate: string;
    tip_presets: string[];
    deposit_per_head: string;
    deposit_from_party: string;
};

export function TaxSection({
    tax,
    context,
}: {
    tax: Tax;
    context: SettingsContext;
}) {
    const t = useT();
    const locked = !context.canEditMoney;
    const form = useForm<Data>({
        vat_rate: bpsToPercent(tax.vat_rate),
        service_charge_rate: bpsToPercent(tax.service_charge_rate),
        tip_presets: tax.tip_presets.map(bpsToPercent),
        deposit_per_head: penceToPounds(tax.deposit_per_head),
        deposit_from_party: String(tax.deposit_from_party),
    });
    const { data, setData } = form;
    const errors = form.errors as Record<string, string | undefined>;

    const submit = () => {
        const bad = (field: keyof Data) =>
            form.setError(field, t('staff_settings.errors.number'));
        const vat = percentToBps(data.vat_rate);
        const service = percentToBps(data.service_charge_rate);
        const deposit = poundsToPence(data.deposit_per_head);
        const tips = data.tip_presets.map(percentToBps);

        if (vat === null) return bad('vat_rate');
        if (service === null) return bad('service_charge_rate');
        if (deposit === null) return bad('deposit_per_head');
        if (tips.includes(null)) return bad('tip_presets');

        form.transform((d) => ({
            vat_rate: vat,
            service_charge_rate: service,
            tip_presets: tips,
            deposit_per_head: deposit,
            deposit_from_party: Number(d.deposit_from_party),
        }));
        form.put('/staff/settings/tax', {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
        });
    };

    const percentInput =
        (name: 'vat_rate' | 'service_charge_rate') => (p: object) => (
            <Input
                {...p}
                inputMode="decimal"
                readOnly={locked}
                className="w-32"
                value={data[name]}
                onChange={(e) => setData(name, e.target.value)}
            />
        );

    return (
        <SectionCard
            title={t('staff_settings.sections.tax.title')}
            description={t('staff_settings.tax.intro')}
            dirty={form.isDirty}
            processing={form.processing}
            online={context.online}
            locked={locked}
            onSubmit={submit}
        >
            {locked && <MoneyLocked />}
            <div className="grid gap-5 md:grid-cols-2">
                <Field
                    name="vat_rate"
                    label={t('staff_settings.tax.vat')}
                    hint={t('staff_settings.tax.vat_hint')}
                    error={errors.vat_rate}
                    required
                >
                    {percentInput('vat_rate')}
                </Field>
                <Field
                    name="service_charge_rate"
                    label={t('staff_settings.tax.service')}
                    hint={t('staff_settings.tax.service_hint')}
                    error={errors.service_charge_rate}
                    required
                >
                    {percentInput('service_charge_rate')}
                </Field>
                <Field
                    name="deposit_per_head"
                    label={t('staff_settings.tax.deposit')}
                    hint={t('staff_settings.tax.deposit_hint')}
                    error={errors.deposit_per_head}
                    required
                >
                    {(p) => (
                        <Input
                            {...p}
                            inputMode="decimal"
                            readOnly={locked}
                            className="w-32"
                            value={data.deposit_per_head}
                            onChange={(e) =>
                                setData('deposit_per_head', e.target.value)
                            }
                        />
                    )}
                </Field>
                <Field
                    name="deposit_from_party"
                    label={t('staff_settings.tax.party')}
                    hint={t('staff_settings.tax.party_hint')}
                    error={errors.deposit_from_party}
                    required
                >
                    {(p) => (
                        <Input
                            {...p}
                            type="number"
                            inputMode="numeric"
                            min={2}
                            readOnly={locked}
                            className="w-32"
                            value={data.deposit_from_party}
                            onChange={(e) =>
                                setData('deposit_from_party', e.target.value)
                            }
                        />
                    )}
                </Field>
            </div>

            <fieldset className="grid gap-2">
                <legend className="font-semibold">
                    {t('staff_settings.tax.tips')}
                </legend>
                <p className="text-sm text-ink-muted">
                    {t('staff_settings.tax.tips_hint')}
                </p>
                <div className="flex flex-wrap items-center gap-2">
                    {data.tip_presets.map((value, index) => (
                        <div key={index} className="flex items-center gap-1">
                            <Input
                                inputMode="decimal"
                                className="w-24"
                                readOnly={locked}
                                aria-label={t('staff_settings.tax.tip_label', {
                                    number: index + 1,
                                })}
                                value={value}
                                onChange={(e) =>
                                    setData(
                                        'tip_presets',
                                        data.tip_presets.map((v, i) =>
                                            i === index ? e.target.value : v,
                                        ),
                                    )
                                }
                            />
                            <span aria-hidden="true">%</span>
                            {!locked && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label={t(
                                        'staff_settings.tax.tip_remove',
                                        { number: index + 1 },
                                    )}
                                    disabled={data.tip_presets.length <= 1}
                                    onClick={() =>
                                        setData(
                                            'tip_presets',
                                            data.tip_presets.filter(
                                                (_, i) => i !== index,
                                            ),
                                        )
                                    }
                                >
                                    <X aria-hidden="true" />
                                </Button>
                            )}
                        </div>
                    ))}
                    {!locked && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={data.tip_presets.length >= 6}
                            onClick={() =>
                                setData('tip_presets', [
                                    ...data.tip_presets,
                                    '',
                                ])
                            }
                        >
                            <Plus aria-hidden="true" />
                            {t('staff_settings.tax.tip_add')}
                        </Button>
                    )}
                </div>
                {(errors.tip_presets ||
                    Object.keys(errors).some((k) =>
                        k.startsWith('tip_presets.'),
                    )) && (
                    <p role="alert" className="text-sm text-chilli">
                        {errors.tip_presets ??
                            Object.entries(errors).find(([k]) =>
                                k.startsWith('tip_presets.'),
                            )?.[1]}
                    </p>
                )}
            </fieldset>
            {!locked && (
                <p className="text-sm text-ink-muted">
                    {t('staff_settings.tax.vat_changed')}
                </p>
            )}
        </SectionCard>
    );
}
