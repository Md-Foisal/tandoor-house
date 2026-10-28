import { Form, Head } from '@inertiajs/react';
import { useRef } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import { useOnline } from '@/components/brand/states';
import Heading from '@/components/heading';
import { Field } from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';
import { edit } from '@/routes/security';
import type { Props as ManagePasskeysProps } from '@/components/manage-passkeys';
import ManagePasskeys from '@/components/manage-passkeys';
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';
import ManageTwoFactor from '@/components/manage-two-factor';

// oxfmt-ignore
type Props = {
    passwordRules: string;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

export default function Security(props: Props) {
    const t = useT();
    const online = useOnline();
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    return (
        <>
            <Head title={t('settings_pages.security.head')} />

            <section aria-labelledby="password-heading" className="space-y-6">
                <Heading
                    id="password-heading"
                    variant="small"
                    title={t('settings_pages.password.title')}
                    description={t('settings_pages.password.description')}
                />

                <Form
                    {...SecurityController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    resetOnError={[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]}
                    resetOnSuccess
                    onError={(errors) => {
                        if (errors.password) {
                            passwordInput.current?.focus();
                        }

                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();
                        }
                    }}
                    className="flex flex-col gap-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <Field
                                id="current_password"
                                label={t('settings_pages.password.current')}
                                error={errors.current_password}
                            >
                                {(control) => (
                                    <PasswordInput
                                        {...control}
                                        ref={currentPasswordInput}
                                        name="current_password"
                                        autoComplete="current-password"
                                    />
                                )}
                            </Field>

                            <Field
                                id="password"
                                label={t('settings_pages.password.new')}
                                error={errors.password}
                            >
                                {(control) => (
                                    <PasswordInput
                                        {...control}
                                        ref={passwordInput}
                                        name="password"
                                        autoComplete="new-password"
                                        passwordrules={props.passwordRules}
                                    />
                                )}
                            </Field>

                            <Field
                                id="password_confirmation"
                                label={t('settings_pages.password.confirm')}
                                error={errors.password_confirmation}
                            >
                                {(control) => (
                                    <PasswordInput
                                        {...control}
                                        name="password_confirmation"
                                        autoComplete="new-password"
                                        passwordrules={props.passwordRules}
                                    />
                                )}
                            </Field>

                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    disabled={processing || !online}
                                    data-test="update-password-button"
                                >
                                    {processing && <Spinner />}
                                    {processing
                                        ? t('settings_pages.common.saving')
                                        : t('settings_pages.password.submit')}
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

            <ManageTwoFactor
                canManageTwoFactor={props.canManageTwoFactor}
                requiresConfirmation={props.requiresConfirmation}
                twoFactorEnabled={props.twoFactorEnabled}
            />

            <ManagePasskeys
                canManagePasskeys={props.canManagePasskeys}
                passkeys={props.passkeys}
            />
        </>
    );
}

Security.layout = {
    breadcrumbs: [
        {
            title: 'Security settings',
            href: edit(),
        },
    ],
};
