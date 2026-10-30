import { Form, Head, Link } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import { useOnline } from '@/components/brand/states';
import { Field } from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';

type Props = {
    token: string;
    email: string;
    valid: boolean;
    validDays: number;
    passwordRules: string;
};

export default function AcceptInvite({
    token,
    email,
    valid,
    validDays,
    passwordRules,
}: Props) {
    const t = useT();
    const online = useOnline();

    if (!valid) {
        return (
            <>
                <Head title={t('staff_team.invite.page.expired_title')} />
                <div className="flex flex-col gap-4" role="alert">
                    <h2 className="text-lg font-semibold">
                        {t('staff_team.invite.page.expired_title')}
                    </h2>
                    <p className="text-ink-muted">
                        {t('staff_team.invite.page.expired_body', {
                            days: validDays,
                        })}
                    </p>
                    <Button asChild size="lg" className="w-full">
                        <Link href="/login">
                            {t('staff_team.invite.page.sign_in')}
                        </Link>
                    </Button>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={t('staff_team.invite.page.title')} />

            <Form
                action={`/invite/${token}`}
                method="post"
                transform={(data) => ({ ...data, email })}
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        {errors.token && (
                            <Alert
                                variant="destructive"
                                className="border-chilli text-chilli"
                            >
                                <CircleAlert aria-hidden="true" />
                                <AlertTitle>
                                    {t('staff_team.invite.page.expired_title')}
                                </AlertTitle>
                                <AlertDescription className="text-ink">
                                    <p>{errors.token}</p>
                                    <Link
                                        href="/login"
                                        className="font-medium text-ember underline underline-offset-4"
                                    >
                                        {t('staff_team.invite.page.sign_in')}
                                    </Link>
                                </AlertDescription>
                            </Alert>
                        )}

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
                                    autoComplete="username"
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
                        >
                            {processing && <Spinner />}
                            {processing
                                ? t('staff_team.invite.page.busy')
                                : t('staff_team.invite.page.submit')}
                        </Button>
                        {!online && (
                            <p className="text-sm text-ink-muted" role="status">
                                {t('staff_team.invite.page.offline')}
                            </p>
                        )}
                    </>
                )}
            </Form>
        </>
    );
}

AcceptInvite.layout = {
    title: 'staff_team.invite.page.title',
    description: 'staff_team.invite.page.description',
};
