import { Form } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useOnline } from '@/components/brand/states';
import Heading from '@/components/heading';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import { useT } from '@/lib/i18n';
import { disable, enable } from '@/routes/two-factor';

export type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

export default function ManageTwoFactor(props: Props) {
    const t = useT();
    const online = useOnline();
    const requiresConfirmation = props.requiresConfirmation ?? false;
    const twoFactorEnabled = props.twoFactorEnabled ?? false;

    const {
        qrCodeSvg,
        hasSetupData,
        manualSetupKey,
        clearSetupData,
        clearTwoFactorAuthData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors,
    } = useTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState<boolean>(false);
    const prevTwoFactorEnabled = useRef(twoFactorEnabled);

    useEffect(() => {
        if (prevTwoFactorEnabled.current && !twoFactorEnabled) {
            clearTwoFactorAuthData();
        }

        prevTwoFactorEnabled.current = twoFactorEnabled;
    }, [twoFactorEnabled, clearTwoFactorAuthData]);

    if (!(props.canManageTwoFactor ?? false)) {
        return null;
    }

    return (
        <section
            aria-labelledby="two-factor-heading"
            className="space-y-6 border-t border-line pt-10"
        >
            <div className="flex items-start justify-between gap-3">
                <Heading
                    id="two-factor-heading"
                    variant="small"
                    title={t('settings_pages.two_factor.title')}
                    description={t('settings_pages.two_factor.description')}
                />
                <Badge variant={twoFactorEnabled ? 'leaf' : 'secondary'}>
                    {twoFactorEnabled
                        ? t('settings_pages.two_factor.status_on')
                        : t('settings_pages.two_factor.status_off')}
                </Badge>
            </div>

            {twoFactorEnabled ? (
                <div className="flex flex-col items-start gap-4">
                    <p className="text-ink-muted">
                        {t('settings_pages.two_factor.on_body')}
                    </p>

                    <Form {...disable.form()}>
                        {({ processing }) => (
                            <Button
                                variant="destructive"
                                type="submit"
                                disabled={processing || !online}
                            >
                                {processing && <Spinner />}
                                {processing
                                    ? t('settings_pages.two_factor.disabling')
                                    : t('settings_pages.two_factor.disable')}
                            </Button>
                        )}
                    </Form>

                    <TwoFactorRecoveryCodes
                        recoveryCodesList={recoveryCodesList}
                        fetchRecoveryCodes={fetchRecoveryCodes}
                        errors={errors}
                    />
                </div>
            ) : (
                <div className="flex flex-col items-start gap-4">
                    <p className="text-ink-muted">
                        {t('settings_pages.two_factor.off_body')}
                    </p>

                    {hasSetupData ? (
                        <Button onClick={() => setShowSetupModal(true)}>
                            <ShieldCheck aria-hidden />
                            {t('settings_pages.two_factor.continue')}
                        </Button>
                    ) : (
                        <Form
                            {...enable.form()}
                            onSuccess={() => setShowSetupModal(true)}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    disabled={processing || !online}
                                >
                                    {processing && <Spinner />}
                                    {processing
                                        ? t(
                                              'settings_pages.two_factor.enabling',
                                          )
                                        : t('settings_pages.two_factor.enable')}
                                </Button>
                            )}
                        </Form>
                    )}
                </div>
            )}

            <TwoFactorSetupModal
                isOpen={showSetupModal}
                onClose={() => setShowSetupModal(false)}
                requiresConfirmation={requiresConfirmation}
                twoFactorEnabled={twoFactorEnabled}
                qrCodeSvg={qrCodeSvg}
                manualSetupKey={manualSetupKey}
                clearSetupData={clearSetupData}
                fetchSetupData={fetchSetupData}
                errors={errors}
            />
        </section>
    );
}
