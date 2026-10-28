import { Form, Head } from '@inertiajs/react';
import { Notice } from '@/components/alert-error';
import { useOnline } from '@/components/brand/states';
import { Field } from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    const t = useT();
    const online = useOnline();

    return (
        <>
            <Head title={t('auth_pages.forgot.head')} />

            {status && <Notice>{status}</Notice>}

            <Form {...email.form()} className="flex flex-col gap-6">
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
                                    required
                                    autoFocus
                                    placeholder={t(
                                        'auth_pages.common.email_placeholder',
                                    )}
                                />
                            )}
                        </Field>

                        <Button
                            type="submit"
                            size="lg"
                            className="w-full"
                            disabled={processing || !online}
                            data-test="email-password-reset-link-button"
                        >
                            {processing && <Spinner />}
                            {processing
                                ? t('auth_pages.forgot.busy')
                                : t('auth_pages.forgot.submit')}
                        </Button>
                        {!online && (
                            <p className="text-sm text-ink-muted" role="status">
                                {t('auth_pages.common.offline')}
                            </p>
                        )}
                    </>
                )}
            </Form>

            <TextLink href={login()}>
                {t('auth_pages.common.back_to_sign_in')}
            </TextLink>
        </>
    );
}

ForgotPassword.layout = {
    title: 'auth_pages.forgot.title',
    description: 'auth_pages.forgot.description',
};
