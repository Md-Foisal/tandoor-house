import { Form, Head, Link, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { Notice } from '@/components/alert-error';
import { useOnline } from '@/components/brand/states';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import { Field } from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';
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
}: {
    mustVerifyEmail: boolean;
    status?: string;
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
