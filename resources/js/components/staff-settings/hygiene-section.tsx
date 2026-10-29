import { useForm } from '@inertiajs/react';
import { Field } from '@/components/form/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useT } from '@/lib/i18n';
import { SectionCard } from './section-card';
import type { Hygiene, SettingsContext } from './types';

const AWAITING = 'Awaiting inspection';

export function HygieneSection({
    hygiene,
    context,
}: {
    hygiene: Hygiene;
    context: SettingsContext;
}) {
    const t = useT();
    const form = useForm({
        rating:
            typeof hygiene.rating === 'number'
                ? String(hygiene.rating)
                : AWAITING,
        opened_year: String(hygiene.opened_year),
    });

    return (
        <SectionCard
            title={t('staff_settings.sections.hygiene.title')}
            description={t('staff_settings.hygiene.intro')}
            dirty={form.isDirty}
            processing={form.processing}
            online={context.online}
            onSubmit={() =>
                form.put('/staff/settings/hygiene', {
                    preserveScroll: true,
                    onSuccess: () => form.setDefaults(),
                })
            }
        >
            <div className="grid gap-5 md:grid-cols-2">
                <Field
                    name="rating"
                    label={t('staff_settings.hygiene.rating')}
                    error={form.errors.rating}
                    required
                >
                    {(p) => (
                        <Select
                            value={form.data.rating}
                            onValueChange={(v) => form.setData('rating', v)}
                        >
                            <SelectTrigger {...p} className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {[5, 4, 3, 2, 1, 0].map((n) => (
                                    <SelectItem key={n} value={String(n)}>
                                        {t(
                                            'staff_settings.hygiene.rating_option',
                                            {
                                                rating: n,
                                            },
                                        )}
                                    </SelectItem>
                                ))}
                                <SelectItem value={AWAITING}>
                                    {t('staff_settings.hygiene.awaiting')}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    )}
                </Field>
                <Field
                    name="opened_year"
                    label={t('staff_settings.hygiene.opened')}
                    hint={t('staff_settings.hygiene.opened_hint')}
                    error={form.errors.opened_year}
                    required
                >
                    {(p) => (
                        <Input
                            {...p}
                            type="number"
                            inputMode="numeric"
                            min={1900}
                            max={hygiene.year_now}
                            className="w-32"
                            value={form.data.opened_year}
                            onChange={(e) =>
                                form.setData('opened_year', e.target.value)
                            }
                        />
                    )}
                </Field>
            </div>
        </SectionCard>
    );
}
