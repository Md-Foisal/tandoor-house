import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { useOnline } from '@/components/brand/states';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/lib/i18n';
import type { SupplierRow } from './types';

type Form = {
    name: string;
    contact_name: string;
    email: string;
    phone: string;
    lead_days: string;
};

const fields = [
    { key: 'name', type: 'text', required: true },
    { key: 'contact_name', type: 'text', required: false },
    { key: 'email', type: 'email', required: false },
    { key: 'phone', type: 'tel', required: false },
    { key: 'lead_days', type: 'number', required: true },
] as const;

export function SupplierDialog({
    open,
    supplier,
    onClose,
}: {
    open: boolean;
    supplier: SupplierRow | null;
    onClose: () => void;
}) {
    const t = useT();
    const online = useOnline();
    const form = useForm<Form>({
        name: '',
        contact_name: '',
        email: '',
        phone: '',
        lead_days: '1',
    });

    useEffect(() => {
        if (open) {
            form.setData({
                name: supplier?.name ?? '',
                contact_name: supplier?.contact_name ?? '',
                email: supplier?.email ?? '',
                phone: supplier?.phone ?? '',
                lead_days: String(supplier?.lead_days ?? 1),
            });
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, supplier]);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (supplier) {
            form.patch(`/staff/inventory/suppliers/${supplier.id}`, options);
        } else {
            form.post('/staff/inventory/suppliers', options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="max-h-[92dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {supplier
                            ? t('staff_inventory.supplier.edit_title', {
                                  name: supplier.name,
                              })
                            : t('staff_inventory.supplier.new_title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('staff_inventory.supplier.body')}
                    </DialogDescription>
                </DialogHeader>
                <form className="grid gap-4" onSubmit={submit}>
                    {fields.map((field) => (
                        <div key={field.key} className="grid gap-1.5">
                            <Label htmlFor={`sup-${field.key}`}>
                                {t(`staff_inventory.supplier.${field.key}`)}
                            </Label>
                            <Input
                                id={`sup-${field.key}`}
                                type={field.type}
                                required={field.required}
                                min={field.type === 'number' ? 0 : undefined}
                                max={field.type === 'number' ? 30 : undefined}
                                inputMode={
                                    field.type === 'number'
                                        ? 'numeric'
                                        : undefined
                                }
                                value={form.data[field.key]}
                                aria-invalid={Boolean(form.errors[field.key])}
                                className="min-h-11"
                                onChange={(event) =>
                                    form.setData(field.key, event.target.value)
                                }
                            />
                            {form.errors[field.key] && (
                                <p role="alert" className="text-sm text-chilli">
                                    {form.errors[field.key]}
                                </p>
                            )}
                        </div>
                    ))}
                    <DialogFooter className="items-center">
                        {!online && (
                            <p className="mr-auto text-sm text-chilli">
                                {t('staff_inventory.offline')}
                            </p>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            {t('staff_inventory.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing || !online}
                        >
                            {t('staff_inventory.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
