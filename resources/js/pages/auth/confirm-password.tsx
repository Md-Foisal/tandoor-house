import { Form, Head } from '@inertiajs/react';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import { useOnline } from '@/components/brand/states';
import { Field } from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';
import { store } from '@/routes/password/confirm';

export default function ConfirmPassword() {
    const t = useT();
    const online = useOnline();

    return (
        <>
            <Head title={t('auth_pages.confirm.head')} />

            <PasskeyVerify
                routes={{
                    options: confirmOptions(),
                    submit: confirmStore(),
                }}
                label={t('auth_pages.passkey.confirm')}
                loadingLabel={t('auth_pages.passkey.confirming')}
                separator={t('auth_pages.passkey.or_password')}
            />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <Field
                            id="password"
                            label={t('auth_pages.common.password')}
                            error={errors.password}
                        >
                            {(control) => (
                                <PasswordInput
                                    {...control}
                                    name="password"
                                    autoComplete="current-password"
                                    autoFocus
                                    required
                                />
                            )}
                        </Field>

                        <Button
                            type="submit"
                            size="lg"
                            className="w-full"
                            disabled={processing || !online}
                            data-test="confirm-password-button"
                        >
                            {processing && <Spinner />}
                            {processing
                                ? t('auth_pages.confirm.busy')
                                : t('auth_pages.confirm.submit')}
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

ConfirmPassword.layout = {
    title: 'auth_pages.confirm.title',
    description: 'auth_pages.confirm.description',
};
