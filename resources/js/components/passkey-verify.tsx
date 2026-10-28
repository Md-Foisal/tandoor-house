import type { UrlMethodPair } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { KeyRound } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';

type Props = {
    routes?: {
        options: UrlMethodPair;
        submit: UrlMethodPair;
    };
    label?: string;
    loadingLabel?: string;
    separator?: string;
};

export default function PasskeyVerify({
    routes,
    label,
    loadingLabel,
    separator,
}: Props = {}) {
    const t = useT();
    const { verify, isLoading, error, isSupported } = usePasskeyVerify({
        ...(routes && {
            routes: {
                options: routes.options.url,
                submit: routes.submit.url,
            },
        }),
        onSuccess: (response) => {
            router.visit(response.redirect ?? '/account');
        },
    });

    if (!isSupported) {
        return null;
    }

    return (
        <div className="flex flex-col gap-6">
            <div className="grid gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="lg"
                    className="w-full"
                    onClick={verify}
                    disabled={isLoading}
                >
                    {isLoading ? (
                        <Spinner />
                    ) : (
                        <KeyRound className="size-4" aria-hidden />
                    )}
                    {isLoading
                        ? (loadingLabel ?? t('auth_pages.passkey.signing_in'))
                        : (label ?? t('auth_pages.passkey.sign_in'))}
                </Button>
                {error && <InputError message={error} />}
            </div>

            <div className="flex items-center gap-3 text-ink-muted">
                <span className="h-px flex-1 bg-line" aria-hidden />
                <span className="text-sm">
                    {separator ?? t('auth_pages.passkey.or_email')}
                </span>
                <span className="h-px flex-1 bg-line" aria-hidden />
            </div>
        </div>
    );
}
