import { Form, Head } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useOnline } from '@/components/brand/states';
import { Field } from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    const t = useT();
    const online = useOnline();
    const benefits = [
        t('auth_pages.register.benefit_reorder'),
        t('auth_pages.register.benefit_addresses'),
        t('auth_pages.register.benefit_points'),
    ];

    return (
        <>
            <Head title={t('auth_pages.register.head')} />

            <section
                aria-labelledby="register-benefits"
                className="rounded-lg bg-ember-soft p-4"
            >
                <h2
                    id="register-benefits"
                    className="text-label text-ember uppercase"
                >
                    {t('auth_pages.register.benefits_title')}
                </h2>
                <ul className="mt-3 grid gap-2">
                    {benefits.map((benefit) => (
                        <li key={benefit} className="flex items-start gap-3">
                            <Check
                                className="mt-1 size-4 shrink-0 text-ember"
                                aria-hidden="true"
                            />
                            <span>{benefit}</span>
                        </li>
                    ))}
                </ul>
            </section>

            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <Field
                            id="name"
                            label={t('auth_pages.register.name')}
                            error={errors.name}
                        >
                            {(control) => (
                                <Input
                                    {...control}
                                    type="text"
                                    name="name"
                                    required
                                    autoFocus
                                    autoComplete="name"
                                    placeholder={t(
                                        'auth_pages.register.name_placeholder',
                                    )}
                                />
                            )}
                        </Field>

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
                            hint={t('auth_pages.register.password_hint')}
                        >
                            {(control) => (
                                <PasswordInput
                                    {...control}
                                    name="password"
                                    required
                                    autoComplete="new-password"
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
                                    required
                                    autoComplete="new-password"
                                    passwordrules={passwordRules}
                                />
                            )}
                        </Field>

                        <Button
                            type="submit"
                            size="lg"
                            className="w-full"
                            disabled={processing || !online}
                            data-test="register-user-button"
                        >
                            {processing && <Spinner />}
                            {processing
                                ? t('auth_pages.register.busy')
                                : t('auth_pages.register.submit')}
                        </Button>
                        {!online && (
                            <p className="text-sm text-ink-muted" role="status">
                                {t('auth_pages.common.offline')}
                            </p>
                        )}

                        <p className="flex flex-wrap items-center gap-x-2 text-ink-muted">
                            {t('auth_pages.register.have_account')}
                            <TextLink href={login()}>
                                {t('auth_pages.register.sign_in')}
                            </TextLink>
                        </p>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'auth_pages.register.title',
    description: 'auth_pages.register.description',
    photo: 'hands',
};
