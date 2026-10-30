import { Form, Head, Link, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { Notice } from '@/components/alert-error';
import { useOnline } from '@/components/brand/states';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import { Field } from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';
import { dataExport } from '@/routes/account';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { User } from '@/types';

// Settings pages sit behind the auth middleware, so the user is always present.
type PageProps = {
    auth: { user: User };
};

export default function Profile({
    mustVerifyEmail,
    status,
    marketingOptIn,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    marketingOptIn: boolean;
}) {
    const t = useT();
    const online = useOnline();
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title={t('settings_pages.profile.head')} />

            <section aria-labelledby="profile-heading" className="space-y-6">
                <Heading
                    id="profile-heading"
                    variant="small"
                    title={t('settings_pages.profile.title')}
                    description={t('settings_pages.profile.description')}
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="flex flex-col gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                id="name"
                                label={t('settings_pages.profile.name')}
                                error={errors.name}
                            >
                                {(control) => (
                                    <Input
                                        {...control}
                                        defaultValue={auth.user.name}
                                        name="name"
                                        required
                                        autoComplete="name"
                                    />
                                )}
                            </Field>

                            <Field
                                id="email"
                                label={t('settings_pages.profile.email')}
                                error={errors.email}
                            >
                                {(control) => (
                                    <Input
                                        {...control}
                                        type="email"
                                        defaultValue={auth.user.email}
                                        name="email"
                                        required
                                        autoComplete="username"
                                    />
                                )}
                            </Field>

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <div className="space-y-3">
                                        <p className="text-ink-muted">
                                            {t(
                                                'settings_pages.profile.unverified',
                                            )}{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="min-h-11 font-semibold text-ember underline underline-offset-4 outline-none focus-visible:outline-2 focus-visible:outline-ember"
                                            >
                                                {t(
                                                    'settings_pages.profile.resend',
                                                )}
                                            </Link>
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <Notice>
                                                {t(
                                                    'settings_pages.profile.link_sent',
                                                )}
                                            </Notice>
                                        )}
                                    </div>
                                )}

                            <div className="flex items-start gap-3">
                                <input
                                    type="hidden"
                                    name="marketing_opt_in"
                                    value="0"
                                />
                                <Checkbox
                                    id="marketing_opt_in"
                                    name="marketing_opt_in"
                                    value="1"
                                    defaultChecked={marketingOptIn}
                                    aria-describedby="marketing-hint"
                                    className="mt-0.5"
                                />
                                <div className="space-y-1">
                                    <Label
                                        htmlFor="marketing_opt_in"
                                        className="font-medium"
                                    >
                                        {t('settings_pages.profile.marketing')}
                                    </Label>
                                    <p
                                        id="marketing-hint"
                                        className="text-sm text-ink-muted"
                                    >
                                        {t(
                                            'settings_pages.profile.marketing_hint',
                                        )}
                                    </p>
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    disabled={processing || !online}
                                    data-test="update-profile-button"
                                >
                                    {processing && <Spinner />}
                                    {processing
                                        ? t('settings_pages.common.saving')
                                        : t('settings_pages.common.save')}
                                </Button>
                                {!online && (
                                    <p className="text-sm text-ink-muted">
                                        {t('settings_pages.common.offline')}
                                    </p>
                                )}
                            </div>
                        </>
                    )}
                </Form>
            </section>

            <section
                aria-labelledby="export-heading"
                className="space-y-4 border-t border-line pt-10"
            >
                <Heading
                    id="export-heading"
                    variant="small"
                    title={t('settings_pages.export.title')}
                    description={t('settings_pages.export.description')}
                />
                <Button
                    asChild
                    variant="secondary"
                    aria-disabled={!online}
                    className="aria-disabled:pointer-events-none aria-disabled:opacity-45"
                >
                    <a
                        href={online ? dataExport.url() : undefined}
                        download
                        data-test="download-data-button"
                    >
                        {t('settings_pages.export.action')}
                    </a>
                </Button>
                <p className="text-sm text-ink-muted">
                    {online
                        ? t('settings_pages.export.hint')
                        : t('settings_pages.export.offline')}
                </p>
            </section>

            <DeleteUser />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
