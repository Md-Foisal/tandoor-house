import { useForm } from '@inertiajs/react';
import { Field } from '@/components/form/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/lib/i18n';
import { SectionCard } from './section-card';
import type { Receipts, SettingsContext } from './types';

export function ReceiptsSection({
    receipts,
    context,
}: {
    receipts: Receipts;
    context: SettingsContext;
}) {
    const t = useT();
    const form = useForm({ footer: receipts.footer });
    const { business } = receipts;

    return (
        <SectionCard
            title={t('staff_settings.sections.receipts.title')}
            description={t('staff_settings.receipts.intro')}
            dirty={form.isDirty}
            processing={form.processing}
            online={context.online}
            onSubmit={() =>
                form.put('/staff/settings/receipts', {
                    preserveScroll: true,
                    onSuccess: () => form.setDefaults(),
                })
            }
        >
            <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
                <div className="grid content-start gap-5">
                    <Field
                        name="footer"
                        label={t('staff_settings.receipts.footer')}
                        hint={`${t('staff_settings.receipts.footer_hint')} ${t('staff_settings.receipts.count', { count: form.data.footer.length })}`}
                        error={form.errors.footer}
                    >
                        {(p) => (
                            <Textarea
                                {...p}
                                rows={4}
                                maxLength={240}
                                value={form.data.footer}
                                onChange={(e) =>
                                    form.setData('footer', e.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Field
                        name="vat-number"
                        label={t('staff_settings.receipts.vat_number')}
                        hint={t('staff_settings.receipts.vat_number_hint')}
                    >
                        {(p) => (
                            <Input
                                {...p}
                                readOnly
                                className="max-w-xs"
                                value={receipts.vat_number}
                            />
                        )}
                    </Field>
                </div>

                <div aria-label={t('staff_settings.receipts.preview')}>
                    <p className="mb-2 text-label text-ink-muted uppercase">
                        {t('staff_settings.receipts.preview')}
                    </p>
                    <div className="mx-auto max-w-72 rounded-md border border-dashed border-line-strong bg-paper p-4 font-mono text-xs leading-relaxed">
                        <p className="text-center text-sm font-bold">
                            {business.name}
                        </p>
                        <p className="text-center text-ink-muted">
                            {business.address.line1}, {business.address.city}{' '}
                            {business.address.postcode}
                            <br />
                            {business.phone}
                            <br />
                            {business.legal_name}
                            <br />
                            VAT {receipts.vat_number}
                        </p>
                        <hr className="my-2 border-dashed border-line-strong" />
                        <p>{t('staff_settings.receipts.sample.order')}</p>
                        <p className="mt-1 flex justify-between">
                            <span>
                                {t('staff_settings.receipts.sample.line_one')}
                            </span>
                            <span>£32.00</span>
                        </p>
                        <p className="flex justify-between">
                            <span>
                                {t('staff_settings.receipts.sample.line_two')}
                            </span>
                            <span>£4.50</span>
                        </p>
                        <hr className="my-2 border-dashed border-line-strong" />
                        <p className="flex justify-between text-ink-muted">
                            <span>
                                {t('staff_settings.receipts.sample.vat')}
                            </span>
                            <span>£6.08</span>
                        </p>
                        <p className="flex justify-between text-sm font-bold">
                            <span>
                                {t('staff_settings.receipts.sample.total')}
                            </span>
                            <span>£36.50</span>
                        </p>
                        <hr className="my-2 border-dashed border-line-strong" />
                        <p className="text-center whitespace-pre-wrap">
                            {form.data.footer.trim() ||
                                t('staff_settings.receipts.footer_empty')}
                        </p>
                    </div>
                </div>
            </div>
        </SectionCard>
    );
}
