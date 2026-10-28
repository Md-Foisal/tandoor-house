import { Form, Head } from '@inertiajs/react';
import { useOnline } from '@/components/brand/states';
import { Field } from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

export default function ResetPassword({ token, email, passwordRules }: Props) {
    const t = useT();
    const online = useOnline();

    return (
        <>
            <Head title={t('auth_pages.reset.head')} />

            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <Field
                            id="email"
                            label={t('auth_pages.common.email')}
                            error={errors.email}
                        >
                            {(control) => (
                                <Input
                                    {...control}
                                    type="email"
                                    name="email"
                                    autoComplete="email"
                                    value={email}
                                    readOnly
                                    className="bg-clay"
                                />
                            )}
                        </Field>

                        <Field
                            id="password"
                            label={t('auth_pages.reset.new_password')}
                            error={errors.password}
                        >
                            {(control) => (
                                <PasswordInput
                                    {...control}
                                    name="password"
                                    autoComplete="new-password"
                                    autoFocus
                                    required
                                    passwordrules={passwordRules}
                                />
                            )}
                        </Field>

                        <Field
                            id="password_confirmation"
                            label={t('auth_pages.common.confirm_password')}
                            error={errors.password_confirmation}
                        >
                            {(control) => (
                                <PasswordInput
                                    {...control}
                                    name="password_confirmation"
                                    autoComplete="new-password"
                                    required
                                    passwordrules={passwordRules}
                                />
                            )}
                        </Field>

                        <Button
                            type="submit"
                            size="lg"
                            className="w-full"
                            disabled={processing || !online}
                            data-test="reset-password-button"
                        >
                            {processing && <Spinner />}
                            {processing
                                ? t('auth_pages.reset.busy')
                                : t('auth_pages.reset.submit')}
                        </Button>
                        {!online && (
                            <p className="text-sm text-ink-muted" role="status">
                                {t('auth_pages.common.offline')}
                            </p>
                        )}
                    </>
                )}
            </Form>
        </>
    );
}

ResetPassword.layout = {
    title: 'auth_pages.reset.title',
    description: 'auth_pages.reset.description',
};
