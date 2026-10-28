import { Form } from '@inertiajs/react';
import { Eye, EyeOff, LockKeyhole, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import AlertError from '@/components/alert-error';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';
import { regenerateRecoveryCodes } from '@/routes/two-factor';

type Props = {
    recoveryCodesList: string[];
    fetchRecoveryCodes: () => Promise<void>;
    errors: string[];
};

export default function TwoFactorRecoveryCodes({
    recoveryCodesList,
    fetchRecoveryCodes,
    errors,
}: Props) {
    const t = useT();
    const [codesAreVisible, setCodesAreVisible] = useState<boolean>(false);
    const codesSectionRef = useRef<HTMLDivElement | null>(null);
    const canRegenerateCodes = recoveryCodesList.length > 0 && codesAreVisible;

    const toggleCodesVisibility = useCallback(async () => {
        if (!codesAreVisible && !recoveryCodesList.length) {
            await fetchRecoveryCodes();
        }

        setCodesAreVisible(!codesAreVisible);

        if (!codesAreVisible) {
            setTimeout(() => {
                codesSectionRef.current?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });
            });
        }
    }, [codesAreVisible, recoveryCodesList.length, fetchRecoveryCodes]);

    useEffect(() => {
        if (!recoveryCodesList.length) {
            void fetchRecoveryCodes();
        }
    }, [recoveryCodesList.length, fetchRecoveryCodes]);

    const RecoveryCodeIconComponent = codesAreVisible ? EyeOff : Eye;

    return (
        <div className="w-full space-y-4 rounded-lg bg-clay p-4">
            <div className="space-y-1">
                <h3 className="flex items-center gap-2 font-semibold">
                    <LockKeyhole className="size-4" aria-hidden="true" />
                    {t('settings_pages.two_factor.recovery_title')}
                </h3>
                <p className="text-ink-muted">
                    {t('settings_pages.two_factor.recovery_body')}
                </p>
            </div>

            <div className="flex flex-wrap items-center gap-3">
                <Button
                    onClick={toggleCodesVisibility}
                    variant="outline"
                    aria-expanded={codesAreVisible}
                    aria-controls="recovery-codes-section"
                >
                    <RecoveryCodeIconComponent
                        className="size-4"
                        aria-hidden="true"
                    />
                    {codesAreVisible
                        ? t('settings_pages.two_factor.recovery_hide')
                        : t('settings_pages.two_factor.recovery_show')}
                </Button>

                {canRegenerateCodes && (
                    <Form
                        {...regenerateRecoveryCodes.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={fetchRecoveryCodes}
                    >
                        {({ processing }) => (
                            <Button
                                variant="secondary"
                                type="submit"
                                disabled={processing}
                                aria-describedby="regenerate-warning"
                            >
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <RefreshCw aria-hidden />
                                )}
                                {processing
                                    ? t(
                                          'settings_pages.two_factor.recovery_regenerating',
                                      )
                                    : t(
                                          'settings_pages.two_factor.recovery_regenerate',
                                      )}
                            </Button>
                        )}
                    </Form>
                )}
            </div>

            <div id="recovery-codes-section" hidden={!codesAreVisible}>
                <div className="space-y-3">
                    {errors?.length ? (
                        <AlertError errors={errors} />
                    ) : (
                        <>
                            <div
                                ref={codesSectionRef}
                                className="grid gap-1 rounded-md bg-surface p-4 font-mono text-sm"
                                role="list"
                                aria-label={t(
                                    'settings_pages.two_factor.recovery_list',
                                )}
                            >
                                {recoveryCodesList.length ? (
                                    recoveryCodesList.map((code, index) => (
                                        <div
                                            key={index}
                                            role="listitem"
                                            className="select-text"
                                        >
                                            {code}
                                        </div>
                                    ))
                                ) : (
                                    <div
                                        className="space-y-2"
                                        role="status"
                                        aria-label={t(
                                            'settings_pages.two_factor.recovery_loading',
                                        )}
                                    >
                                        {Array.from(
                                            { length: 8 },
                                            (_, index) => (
                                                <Skeleton
                                                    key={index}
                                                    className="h-4 w-40"
                                                />
                                            ),
                                        )}
                                    </div>
                                )}
                            </div>

                            <p
                                id="regenerate-warning"
                                className="text-sm text-ink-muted"
                            >
                                {t(
                                    'settings_pages.two_factor.recovery_warning',
                                )}
                            </p>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}
