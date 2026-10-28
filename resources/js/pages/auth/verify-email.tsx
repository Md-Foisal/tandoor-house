import { Form, Head } from '@inertiajs/react';
import { Notice } from '@/components/alert-error';
import { useOnline } from '@/components/brand/states';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    const t = useT();
    const online = useOnline();

    return (
        <>
            <Head title={t('auth_pages.verify.head')} />

            {status === 'verification-link-sent' && (
                <Notice>{t('auth_pages.verify.sent')}</Notice>
            )}

            <Form {...send.form()} className="flex flex-col items-start gap-4">
                {({ processing }) => (
                    <>
                        <Button
                            size="lg"
                            className="w-full"
                            disabled={processing || !online}
                        >
                            {processing && <Spinner />}
                            {processing
                                ? t('auth_pages.verify.busy')
                                : t('auth_pages.verify.resend')}
                        </Button>

                        <TextLink href={logout()}>
                            {t('auth_pages.verify.sign_out')}
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: 'auth_pages.verify.title',
    description: 'auth_pages.verify.description',
};
