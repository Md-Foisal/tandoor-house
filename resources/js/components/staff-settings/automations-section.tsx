import { useForm } from '@inertiajs/react';
import { Switch } from '@/components/ui/switch';
import { useT } from '@/lib/i18n';
import { SectionCard } from './section-card';
import type { SettingsContext } from './types';

export function AutomationsSection({
    automations,
    context,
}: {
    automations: Record<string, boolean>;
    context: SettingsContext;
}) {
    const t = useT();
    const form = useForm<Record<string, boolean>>(automations);

    return (
        <SectionCard
            title={t('staff_settings.sections.automations.title')}
            description={t('staff_settings.automations.intro')}
            dirty={form.isDirty}
            processing={form.processing}
            online={context.online}
            onSubmit={() =>
                form.put('/staff/settings/automations', {
                    preserveScroll: true,
                    onSuccess: () => form.setDefaults(),
                })
            }
        >
            <p className="mb-2 text-sm text-ink-muted">
                {t('staff_settings.automations.always')}
            </p>
            <ul className="grid divide-y divide-line">
                {Object.keys(automations).map((key) => (
                    <li
                        key={key}
                        className="flex items-start justify-between gap-4 py-3"
                    >
                        <div>
                            <label
                                htmlFor={`auto-${key}`}
                                className="font-semibold"
                            >
                                {t(
                                    `staff_settings.automations.items.${key}.name`,
                                )}
                            </label>
                            <p
                                id={`auto-${key}-hint`}
                                className="text-sm text-ink-muted"
                            >
                                {t(
                                    `staff_settings.automations.items.${key}.body`,
                                )}
                            </p>
                        </div>
                        <div className="flex shrink-0 items-center gap-2">
                            <span
                                className="text-sm text-ink-muted"
                                aria-hidden="true"
                            >
                                {form.data[key]
                                    ? t('staff_settings.automations.on')
                                    : t('staff_settings.automations.off')}
                            </span>
                            <Switch
                                id={`auto-${key}`}
                                aria-describedby={`auto-${key}-hint`}
                                checked={form.data[key]}
                                onCheckedChange={(v) => form.setData(key, v)}
                            />
                        </div>
                    </li>
                ))}
            </ul>
        </SectionCard>
    );
}
