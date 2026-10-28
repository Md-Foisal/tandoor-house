import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useState } from 'react';
import { useOnline } from '@/components/brand/states';
import InputError, { Field } from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { useT } from '@/lib/i18n';
import { store } from '@/routes/two-factor/login';

export default function TwoFactorChallenge() {
    const t = useT();
    const online = useOnline();
    const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);
    const [code, setCode] = useState<string>('');

    setLayoutProps({
        title: showRecoveryInput
            ? 'auth_pages.two_factor.recovery_title'
            : 'auth_pages.two_factor.code_title',
        description: showRecoveryInput
            ? 'auth_pages.two_factor.recovery_description'
            : 'auth_pages.two_factor.code_description',
    });

    const toggleRecoveryMode = (clearErrors: () => void): void => {
        setShowRecoveryInput(!showRecoveryInput);
        clearErrors();
        setCode('');
    };

    return (
        <>
            <Head title={t('auth_pages.two_factor.head')} />

            <Form
                {...store.form()}
                className="flex flex-col gap-6"
                resetOnError
                resetOnSuccess={!showRecoveryInput}
            >
                {({ errors, processing, clearErrors }) => (
                    <>
                        {showRecoveryInput ? (
                            <Field
                                id="recovery_code"
                                label={t(
                                    'auth_pages.two_factor.recovery_label',
                                )}
                                error={errors.recovery_code}
                            >
                                {(control) => (
                                    <Input
                                        {...control}
                                        name="recovery_code"
                                        type="text"
                                        autoComplete="one-time-code"
                                        placeholder={t(
                                            'auth_pages.two_factor.recovery_placeholder',
                                        )}
                                        autoFocus
                                        required
                                    />
                                )}
                            </Field>
                        ) : (
                            <div className="grid gap-3">
                                <Label
                                    htmlFor="otp"
                                    className="text-base font-semibold"
                                >
                                    {t('auth_pages.two_factor.code_label')}
                                </Label>
                                <InputOTP
                                    id="otp"
                                    name="code"
                                    maxLength={OTP_MAX_LENGTH}
                                    value={code}
                                    onChange={(value) => setCode(value)}
                                    disabled={processing}
                                    pattern={REGEXP_ONLY_DIGITS}
                                    aria-invalid={
                                        errors.code ? true : undefined
                                    }
                                    aria-describedby={
                                        errors.code ? 'otp-error' : undefined
                                    }
                                    autoFocus
                                >
                                    <InputOTPGroup className="gap-2">
                                        {Array.from(
                                            { length: OTP_MAX_LENGTH },
                                            (_, index) => (
                                                <InputOTPSlot
                                                    key={index}
                                                    index={index}
                                                    className="h-12 w-11 rounded-md border border-line-strong bg-surface text-lg shadow-none first:rounded-l-md last:rounded-r-md"
                                                />
                                            ),
                                        )}
                                    </InputOTPGroup>
                                </InputOTP>
                                <InputError
                                    id="otp-error"
                                    message={errors.code}
                                />
                            </div>
                        )}

                        <Button
                            type="submit"
                            size="lg"
                            className="w-full"
                            disabled={processing || !online}
                        >
                            {processing && <Spinner />}
                            {processing
                                ? t('auth_pages.two_factor.busy')
                                : t('auth_pages.two_factor.submit')}
                        </Button>

                        <Button
                            type="button"
                            variant="link"
                            className="h-11 w-fit"
                            onClick={() => toggleRecoveryMode(clearErrors)}
                        >
                            {showRecoveryInput
                                ? t('auth_pages.two_factor.use_code')
                                : t('auth_pages.two_factor.use_recovery')}
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}
