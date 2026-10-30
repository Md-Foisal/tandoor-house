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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useT } from '@/lib/i18n';
import { QuantityField } from './quantity-field';
import type { IngredientRow, Unit } from './types';
import { costFromPounds, poundsFromCost } from './units';

type Supplier = { id: number; name: string };

type Form = {
    name: string;
    unit: Unit;
    low_threshold: number | null;
    par_level: number | null;
    cost_per_thousand: number | null;
    supplier_id: number | null;
};

const units: Unit[] = ['g', 'ml', 'pc'];

export function IngredientDialog({
    open,
    onClose,
    ingredient,
    suppliers,
}: {
    open: boolean;
    onClose: () => void;
    ingredient: IngredientRow | null;
    suppliers: Supplier[];
}) {
    const t = useT();
    const online = useOnline();
    const form = useForm<Form>({
        name: '',
        unit: 'g',
        low_threshold: null,
        par_level: null,
        cost_per_thousand: null,
        supplier_id: null,
    });

    useEffect(() => {
        if (open) {
            form.setData({
                name: ingredient?.name ?? '',
                unit: ingredient?.unit ?? 'g',
                low_threshold: ingredient?.low_threshold ?? null,
                par_level: ingredient?.par_level ?? null,
                cost_per_thousand: ingredient?.cost_per_thousand ?? null,
                supplier_id: ingredient?.supplier?.id ?? null,
            });
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, ingredient]);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (ingredient) {
            form.patch(
                `/staff/inventory/ingredients/${ingredient.id}`,
                options,
            );
        } else {
            form.post('/staff/inventory/ingredients', options);
        }
    };

    const fieldKey = `${ingredient?.id ?? 'new'}-${form.data.unit}-${open}`;

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="max-h-[92dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {ingredient
                            ? t('staff_inventory.ingredient.edit_title', {
                                  name: ingredient.name,
                              })
                            : t('staff_inventory.ingredient.new_title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('staff_inventory.ingredient.body')}
                    </DialogDescription>
                </DialogHeader>
                <form className="grid gap-4" onSubmit={submit}>
                    <div className="grid gap-1.5">
                        <Label htmlFor="ing-name">
                            {t('staff_inventory.ingredient.name')}
                        </Label>
                        <Input
                            id="ing-name"
                            required
                            maxLength={120}
                            value={form.data.name}
                            aria-invalid={Boolean(form.errors.name)}
                            className="min-h-11"
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                        />
                        {form.errors.name && (
                            <p role="alert" className="text-sm text-chilli">
                                {form.errors.name}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="ing-unit">
                            {t('staff_inventory.ingredient.unit')}
                        </Label>
                        <Select
                            value={form.data.unit}
                            disabled={ingredient !== null}
                            onValueChange={(value) =>
                                form.setData('unit', value as Unit)
                            }
                        >
                            <SelectTrigger
                                id="ing-unit"
                                className="min-h-11 w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {units.map((unit) => (
                                    <SelectItem key={unit} value={unit}>
                                        {t(`staff_inventory.units.${unit}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {ingredient && (
                            <p className="text-sm text-ink-muted">
                                {t('staff_inventory.ingredient.unit_locked')}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <QuantityField
                            key={`low-${fieldKey}`}
                            label={t('staff_inventory.ingredient.low')}
                            hint={t('staff_inventory.ingredient.low_hint')}
                            unit={form.data.unit}
                            initial={ingredient?.low_threshold ?? null}
                            error={form.errors.low_threshold}
                            onChange={(value) =>
                                form.setData('low_threshold', value)
                            }
                        />
                        <QuantityField
                            key={`par-${fieldKey}`}
                            label={t('staff_inventory.ingredient.par')}
                            hint={t('staff_inventory.ingredient.par_hint')}
                            unit={form.data.unit}
                            initial={ingredient?.par_level ?? null}
                            error={form.errors.par_level}
                            onChange={(value) =>
                                form.setData('par_level', value)
                            }
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="ing-cost">
                            {t(
                                `staff_inventory.ingredient.cost_${form.data.unit}`,
                            )}
                        </Label>
                        <div className="flex items-center gap-2">
                            <span aria-hidden="true" className="text-ink-muted">
                                £
                            </span>
                            <Input
                                id="ing-cost"
                                key={fieldKey}
                                required
                                inputMode="decimal"
                                autoComplete="off"
                                defaultValue={
                                    ingredient?.cost_per_thousand !== undefined
                                        ? poundsFromCost(
                                              ingredient.cost_per_thousand,
                                          )
                                        : ''
                                }
                                aria-invalid={Boolean(
                                    form.errors.cost_per_thousand,
                                )}
                                className="min-h-11 tabular-nums"
                                onChange={(event) =>
                                    form.setData(
                                        'cost_per_thousand',
                                        costFromPounds(event.target.value),
                                    )
                                }
                            />
                        </div>
                        {form.errors.cost_per_thousand && (
                            <p role="alert" className="text-sm text-chilli">
                                {form.errors.cost_per_thousand}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="ing-supplier">
                            {t('staff_inventory.ingredient.supplier')}
                        </Label>
                        <Select
                            value={
                                form.data.supplier_id === null
                                    ? 'none'
                                    : String(form.data.supplier_id)
                            }
                            onValueChange={(value) =>
                                form.setData(
                                    'supplier_id',
                                    value === 'none' ? null : Number(value),
                                )
                            }
                        >
                            <SelectTrigger
                                id="ing-supplier"
                                className="min-h-11"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">
                                    {t(
                                        'staff_inventory.ingredient.no_supplier',
                                    )}
                                </SelectItem>
                                {suppliers.map((supplier) => (
                                    <SelectItem
                                        key={supplier.id}
                                        value={String(supplier.id)}
                                    >
                                        {supplier.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
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
