import { Form } from '@inertiajs/react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { useOnline } from '@/components/brand/states';
import Heading from '@/components/heading';
import { Field } from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';

export default function DeleteUser() {
    const t = useT();
    const online = useOnline();
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <section
            aria-labelledby="delete-heading"
            className="space-y-6 border-t border-line pt-10"
        >
            <Heading
                id="delete-heading"
                variant="small"
                title={t('settings_pages.delete.title')}
                description={t('settings_pages.delete.description')}
            />
            <div className="space-y-4 rounded-lg bg-chilli-soft p-4 text-chilli">
                <p className="font-medium">
                    {t('settings_pages.delete.warning')}
                </p>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="destructive"
                            data-test="delete-user-button"
                        >
                            {t('settings_pages.delete.open')}
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>
                            {t('settings_pages.delete.dialog_title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('settings_pages.delete.dialog_body')}
                        </DialogDescription>

                        <Form
                            {...ProfileController.destroy.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            onError={() => passwordInput.current?.focus()}
                            resetOnSuccess
                            className="flex flex-col gap-6"
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    <Field
                                        id="delete-password"
                                        label={t(
                                            'settings_pages.delete.password',
                                        )}
                                        error={errors.password}
                                    >
                                        {(control) => (
                                            <PasswordInput
                                                {...control}
                                                name="password"
                                                ref={passwordInput}
                                                autoComplete="current-password"
                                            />
                                        )}
                                    </Field>

                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                variant="secondary"
                                                onClick={() =>
                                                    resetAndClearErrors()
                                                }
                                            >
                                                {t(
                                                    'settings_pages.common.cancel',
                                                )}
                                            </Button>
                                        </DialogClose>

                                        <Button
                                            variant="destructive"
                                            disabled={processing || !online}
                                            asChild
                                        >
                                            <button
                                                type="submit"
                                                data-test="confirm-delete-user-button"
                                            >
                                                {processing && <Spinner />}
                                                {processing
                                                    ? t(
                                                          'settings_pages.delete.deleting',
                                                      )
                                                    : t(
                                                          'settings_pages.delete.confirm',
                                                      )}
                                            </button>
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </section>
    );
}
