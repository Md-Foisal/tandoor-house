import { Form, Head } from '@inertiajs/react';
import { useOnline } from '@/components/brand/states';
import { Notice } from '@/components/alert-error';
import { Field } from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    const t = useT();
    const online = useOnline();

    return (
        <>
            <Head title={t('auth_pages.login.head')} />

            {status && <Notice>{status}</Notice>}

            <PasskeyVerify />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
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
                                    required
                                    autoFocus
                                    autoComplete="email"
                                    placeholder={t(
                                        'auth_pages.common.email_placeholder',
                                    )}
                                />
                            )}
                        </Field>

                        <Field
                            id="password"
                            label={t('auth_pages.common.password')}
                            error={errors.password}
                            aside={
                                canResetPassword && (
                                    <TextLink
                                        href={request()}
                                        className="text-sm"
                                    >
                                        {t('auth_pages.login.forgot')}
                                    </TextLink>
                                )
                            }
                        >
                            {(control) => (
                                <PasswordInput
                                    {...control}
                                    name="password"
                                    required
                                    autoComplete="current-password"
                                />
                            )}
                        </Field>

                        <div className="flex items-center gap-3">
                            <Checkbox id="remember" name="remember" />
                            <Label
                                htmlFor="remember"
                                className="flex min-h-11 flex-1 items-center text-base"
                            >
                                {t('auth_pages.login.remember')}
                            </Label>
                        </div>

                        <Button
                            type="submit"
                            size="lg"
                            className="w-full"
                            disabled={processing || !online}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            {processing
                                ? t('auth_pages.login.busy')
                                : t('auth_pages.login.submit')}
                        </Button>
                        {!online && (
                            <p className="text-sm text-ink-muted" role="status">
                                {t('auth_pages.common.offline')}
                            </p>
                        )}

                        <p className="flex flex-wrap items-center gap-x-2 text-ink-muted">
                            {t('auth_pages.login.new_here')}
                            <TextLink href={register()}>
                                {t('auth_pages.login.create')}
                            </TextLink>
                        </p>
                    </>
                )}
            </Form>
        </>
    );
}

Login.layout = {
    title: 'auth_pages.login.title',
    description: 'auth_pages.login.description',
    photo: 'naan',
};
