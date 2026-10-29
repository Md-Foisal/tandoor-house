import { useForm } from '@inertiajs/react';
import { Field } from '@/components/form/field';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { useT } from '@/lib/i18n';
import { SectionCard } from './section-card';
import type { Notifications, SettingsContext } from './types';

export function NotificationsSection({
    notifications,
    context,
}: {
    notifications: Notifications;
    context: SettingsContext;
}) {
    const t = useT();
    const form = useForm({
        mail: notifications.channels.mail,
        sms: notifications.channels.sms,
        owner_email: notifications.owner_email,
    });

    const rows: {
        key: 'mail' | 'sms' | 'whatsapp';
        on: boolean;
        soon?: boolean;
    }[] = [
        { key: 'mail', on: form.data.mail },
        { key: 'sms', on: form.data.sms },
        { key: 'whatsapp', on: false, soon: true },
    ];

    return (
        <SectionCard
            title={t('staff_settings.sections.notifications.title')}
            description={t('staff_settings.notifications.intro')}
            dirty={form.isDirty}
            processing={form.processing}
            online={context.online}
            onSubmit={() =>
                form.put('/staff/settings/notifications', {
                    preserveScroll: true,
                    onSuccess: () => form.setDefaults(),
                })
            }
        >
            <fieldset className="grid divide-y divide-line">
                <legend className="mb-2 font-semibold">
                    {t('staff_settings.notifications.channels')}
                </legend>
                {rows.map((row) => (
                    <div
                        key={row.key}
                        className="flex items-start justify-between gap-4 py-3"
                    >
                        <div>
                            <label
                                htmlFor={`channel-${row.key}`}
                                className="flex items-center gap-2 font-semibold"
                            >
                                {t(`staff_settings.notifications.${row.key}`)}
                                {row.soon && (
                                    <Badge variant="outline">
                                        {t('staff_settings.notifications.soon')}
                                    </Badge>
                                )}
                            </label>
                            <p
                                id={`channel-${row.key}-hint`}
                                className="text-sm text-ink-muted"
                            >
                                {t(
                                    `staff_settings.notifications.${row.key}_hint`,
                                )}
                            </p>
                        </div>
                        <Switch
                            id={`channel-${row.key}`}
                            aria-describedby={`channel-${row.key}-hint`}
                            checked={row.on}
                            disabled={row.soon}
                            onCheckedChange={(v) =>
                                row.key !== 'whatsapp' &&
                                form.setData(row.key, v)
                            }
                        />
                    </div>
                ))}
            </fieldset>
            <Field
                name="owner_email"
                label={t('staff_settings.notifications.owner_email')}
                hint={t('staff_settings.notifications.owner_email_hint')}
                error={form.errors.owner_email}
            >
                {(p) => (
                    <Input
                        {...p}
                        type="email"
                        autoComplete="off"
                        className="max-w-md"
                        value={form.data.owner_email}
                        onChange={(e) =>
                            form.setData('owner_email', e.target.value)
                        }
                    />
                )}
            </Field>
        </SectionCard>
    );
}
