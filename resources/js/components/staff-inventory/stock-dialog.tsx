import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
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
import { QuantityField } from './quantity-field';
import type { IngredientRow } from './types';
import { formatQuantity } from './units';

export type StockMode = 'in' | 'adjust';

/** Delivery received (adds stock) or a stock count (sets the level, reason required). */
export function StockDialog({
    mode,
    ingredient,
    onClose,
}: {
    mode: StockMode | null;
    ingredient: IngredientRow | null;
    onClose: () => void;
}) {
    const t = useT();
    const online = useOnline();
    const form = useForm<{
        quantity: number | null;
        counted: number | null;
        reason: string;
    }>({
        quantity: null,
        counted: null,
        reason: '',
    });
    const [stamp, setStamp] = useState(0);
    const open = mode !== null && ingredient !== null;

    useEffect(() => {
        if (open) {
            form.reset();
            form.clearErrors();
            setStamp((value) => value + 1);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, mode, ingredient?.id]);

    if (!ingredient || !mode) {
        return null;
    }

    const adding = mode === 'in';
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) =>
            adding
                ? { quantity: data.quantity, reason: data.reason }
                : { counted: data.counted, reason: data.reason },
        );
        form.post(
            `/staff/inventory/ingredients/${ingredient.id}/${adding ? 'stock-in' : 'adjust'}`,
            {
                preserveScroll: true,
                onSuccess: onClose,
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t(`staff_inventory.stock.${mode}_title`, {
                            name: ingredient.name,
                        })}
                    </DialogTitle>
                    <DialogDescription>
                        {t('staff_inventory.stock.current', {
                            quantity: formatQuantity(
                                ingredient.stock,
                                ingredient.unit,
                            ),
                        })}{' '}
                        {t(`staff_inventory.stock.${mode}_body`)}
                    </DialogDescription>
                </DialogHeader>
                <form className="grid gap-4" onSubmit={submit}>
                    <QuantityField
                        key={`${mode}-${ingredient.id}-${stamp}`}
                        autoFocus
                        label={t(`staff_inventory.stock.${mode}_label`)}
                        unit={ingredient.unit}
                        error={
                            adding
                                ? form.errors.quantity
                                : (form.errors.counted ?? form.errors.quantity)
                        }
                        onChange={(value) =>
                            form.setData(adding ? 'quantity' : 'counted', value)
                        }
                    />
                    <div className="grid gap-1.5">
                        <Label htmlFor="stock-reason">
                            {t(`staff_inventory.stock.${mode}_reason`)}
                        </Label>
                        <Input
                            id="stock-reason"
                            required={!adding}
                            maxLength={200}
                            value={form.data.reason}
                            placeholder={t(
                                `staff_inventory.stock.${mode}_placeholder`,
                            )}
                            aria-invalid={Boolean(form.errors.reason)}
                            className="min-h-11"
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                        {form.errors.reason && (
                            <p role="alert" className="text-sm text-chilli">
                                {form.errors.reason}
                            </p>
                        )}
                    </div>
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
                            disabled={
                                form.processing ||
                                !online ||
                                (adding
                                    ? form.data.quantity === null
                                    : form.data.counted === null)
                            }
                        >
                            {t(`staff_inventory.stock.${mode}_confirm`)}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
