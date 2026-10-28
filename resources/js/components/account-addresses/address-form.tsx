import { Form } from '@inertiajs/react';
import { useOnline } from '@/components/brand/states';
import { Field } from '@/components/form/field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { useIsMobile } from '@/hooks/use-mobile';
import { useT } from '@/lib/i18n';
import { store, update } from '@/routes/account/addresses';
import type { SavedAddress } from './types';

function AddressFields({
    address,
    first,
    onDone,
}: {
    address: SavedAddress | null;
    first: boolean;
    onDone: () => void;
}) {
    const t = useT();
    const online = useOnline();
    const action = address ? update.form(address.id) : store.form();
    const locked = first || address?.is_default === true;

    return (
        <Form
            {...action}
            options={{ preserveScroll: true }}
            onSuccess={onDone}
            className="flex flex-col gap-4 px-4 pb-4 sm:px-0 sm:pb-0"
        >
            {({ errors, processing }) => (
                <>
                    <Field
                        name="label"
                        label={t('addresses.form.label')}
                        hint={t('addresses.form.label_hint')}
                        error={errors.label}
                    >
                        {(props) => (
                            <Input
                                {...props}
                                name="label"
                                defaultValue={address?.label ?? ''}
                                maxLength={30}
                                autoComplete="off"
                                required
                            />
                        )}
                    </Field>
                    <Field
                        name="line1"
                        label={t('addresses.form.line1')}
                        error={errors.line1}
                    >
                        {(props) => (
                            <Input
                                {...props}
                                name="line1"
                                defaultValue={address?.line1 ?? ''}
                                autoComplete="address-line1"
                                required
                            />
                        )}
                    </Field>
                    <Field
                        name="line2"
                        label={t('addresses.form.line2')}
                        error={errors.line2}
                    >
                        {(props) => (
                            <Input
                                {...props}
                                name="line2"
                                defaultValue={address?.line2 ?? ''}
                                autoComplete="address-line2"
                            />
                        )}
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            name="city"
                            label={t('addresses.form.city')}
                            error={errors.city}
                        >
                            {(props) => (
                                <Input
                                    {...props}
                                    name="city"
                                    defaultValue={address?.city ?? 'London'}
                                    autoComplete="address-level2"
                                    required
                                />
                            )}
                        </Field>
                        <Field
                            name="postcode"
                            label={t('addresses.form.postcode')}
                            hint={t('addresses.form.postcode_hint')}
                            error={errors.postcode}
                        >
                            {(props) => (
                                <Input
                                    {...props}
                                    name="postcode"
                                    defaultValue={address?.postcode ?? ''}
                                    autoComplete="postal-code"
                                    autoCapitalize="characters"
                                    className="uppercase"
                                    required
                                />
                            )}
                        </Field>
                    </div>
                    <Field
                        name="instructions"
                        label={t('addresses.form.instructions')}
                        hint={t('addresses.form.instructions_hint')}
                        error={errors.instructions}
                    >
                        {(props) => (
                            <Textarea
                                {...props}
                                name="instructions"
                                defaultValue={address?.instructions ?? ''}
                                maxLength={200}
                                rows={2}
                            />
                        )}
                    </Field>

                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="field-is_default"
                            name="is_default"
                            value="1"
                            defaultChecked={locked}
                            disabled={locked}
                            aria-describedby="field-is_default-hint"
                            className="mt-0.5"
                        />
                        <div className="grid gap-0.5">
                            <label
                                htmlFor="field-is_default"
                                className="font-medium"
                            >
                                {t('addresses.form.default')}
                            </label>
                            {locked && (
                                <p
                                    id="field-is_default-hint"
                                    className="text-sm text-ink-muted"
                                >
                                    {first
                                        ? t('addresses.form.default_first')
                                        : t('addresses.form.default_current')}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="flex flex-col gap-2 pt-2">
                        {!online && (
                            <p role="status" className="text-sm text-ink-muted">
                                {t('addresses.form.offline')}
                            </p>
                        )}
                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button
                                type="button"
                                variant="ghost"
                                className="min-h-12 sm:min-h-11"
                                onClick={onDone}
                            >
                                {t('addresses.form.cancel')}
                            </Button>
                            <Button
                                type="submit"
                                className="min-h-12 sm:min-h-11"
                                disabled={processing || !online}
                            >
                                {processing
                                    ? t('addresses.form.saving')
                                    : t('addresses.form.save')}
                            </Button>
                        </div>
                    </div>
                </>
            )}
        </Form>
    );
}

/**
 * The add/edit form: a bottom sheet on phones, a dialog from 768px.
 * `address` null means a new one.
 */
export function AddressFormDialog({
    open,
    address,
    first,
    onOpenChange,
}: {
    open: boolean;
    address: SavedAddress | null;
    first: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const t = useT();
    const mobile = useIsMobile();
    const title = address
        ? t('addresses.form.edit_title', { label: address.label })
        : t('addresses.form.add_title');
    const close = () => onOpenChange(false);

    if (mobile) {
        return (
            <Sheet open={open} onOpenChange={onOpenChange}>
                <SheetContent
                    side="bottom"
                    className="max-h-[92dvh] overflow-y-auto rounded-t-lg"
                >
                    <SheetHeader>
                        <SheetTitle>{title}</SheetTitle>
                        <SheetDescription>
                            {t('addresses.form.description')}
                        </SheetDescription>
                    </SheetHeader>
                    <AddressFields
                        address={address}
                        first={first}
                        onDone={close}
                    />
                </SheetContent>
            </Sheet>
        );
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="font-display text-display-md">
                        {title}
                    </DialogTitle>
                    <DialogDescription>
                        {t('addresses.form.description')}
                    </DialogDescription>
                </DialogHeader>
                <AddressFields address={address} first={first} onDone={close} />
            </DialogContent>
        </Dialog>
    );
}
