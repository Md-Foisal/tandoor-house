import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { QuantityField } from './quantity-field';
import type { DishRow, IngredientOption } from './types';
import { formatMargin } from './units';

type Line = {
    key: number;
    ingredient_id: number | null;
    quantity: number | null;
    initial: number | null;
};

let counter = 0;
const nextKey = () => ++counter;

/** Edit the ingredient lines for one dish, with the cost and margin updating as you type. */
export function RecipeDialog({
    dish,
    ingredients,
    target,
    onClose,
}: {
    dish: DishRow | null;
    ingredients: IngredientOption[];
    target: number;
    onClose: () => void;
}) {
    const t = useT();
    const online = useOnline();
    const form = useForm<{
        lines: { ingredient_id: number | null; quantity: number | null }[];
    }>({ lines: [] });
    const [lines, setLines] = useState<Line[]>([]);
    const byId = useMemo(
        () => new Map(ingredients.map((i) => [i.id, i])),
        [ingredients],
    );

    useEffect(() => {
        if (dish) {
            setLines(
                dish.lines.map((line) => ({
                    key: nextKey(),
                    ingredient_id: line.ingredient_id,
                    quantity: line.quantity,
                    initial: line.quantity,
                })),
            );
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [dish]);

    if (!dish) {
        return null;
    }

    const patch = (key: number, change: Partial<Line>) =>
        setLines((current) =>
            current.map((line) =>
                line.key === key ? { ...line, ...change } : line,
            ),
        );

    const thousandths = lines.reduce(
        (sum, line) =>
            sum +
            (line.ingredient_id && line.quantity
                ? line.quantity *
                  (byId.get(line.ingredient_id)?.cost_per_thousand ?? 0)
                : 0),
        0,
    );
    const cost = Math.round(thousandths / 1000);
    const margin =
        dish.net_price > 0
            ? Math.round(((dish.net_price - cost) / dish.net_price) * 1000) / 10
            : null;
    const used = new Set(lines.map((line) => line.ingredient_id));
    const complete = lines.every(
        (line) =>
            line.ingredient_id !== null &&
            line.quantity !== null &&
            line.quantity > 0,
    );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform(() => ({
            lines: lines.map(({ ingredient_id, quantity }) => ({
                ingredient_id,
                quantity,
            })),
        }));
        form.put(`/staff/inventory/recipes/${dish.id}`, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const lineError = Object.entries(form.errors).find(([key]) =>
        key.startsWith('lines'),
    )?.[1];

    return (
        <Dialog open onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="max-h-[92dvh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {t('staff_inventory.recipe.edit_title', {
                            name: dish.name,
                        })}
                    </DialogTitle>
                    <DialogDescription>
                        {t('staff_inventory.recipe.edit_body')}
                    </DialogDescription>
                </DialogHeader>
                <form className="grid gap-4" onSubmit={submit}>
                    <ul className="grid gap-3">
                        {lines.length === 0 && (
                            <li className="rounded-md border border-dashed border-line-strong p-4 text-sm text-ink-muted">
                                {t('staff_inventory.recipe.no_lines')}
                            </li>
                        )}
                        {lines.map((line, index) => {
                            const ingredient = line.ingredient_id
                                ? byId.get(line.ingredient_id)
                                : undefined;

                            return (
                                <li
                                    key={line.key}
                                    className="grid gap-2 rounded-md border border-line p-3 sm:grid-cols-[1fr_14rem_auto] sm:items-end"
                                >
                                    <div className="grid gap-1.5">
                                        <span
                                            className="text-sm font-semibold"
                                            id={`line-${line.key}`}
                                        >
                                            {t(
                                                'staff_inventory.recipe.ingredient',
                                            )}
                                        </span>
                                        <Select
                                            value={
                                                line.ingredient_id
                                                    ? String(line.ingredient_id)
                                                    : undefined
                                            }
                                            onValueChange={(value) =>
                                                patch(line.key, {
                                                    ingredient_id:
                                                        Number(value),
                                                })
                                            }
                                        >
                                            <SelectTrigger
                                                aria-labelledby={`line-${line.key}`}
                                                className="min-h-11"
                                            >
                                                <SelectValue
                                                    placeholder={t(
                                                        'staff_inventory.recipe.pick',
                                                    )}
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {ingredients
                                                    .filter(
                                                        (i) =>
                                                            i.id ===
                                                                line.ingredient_id ||
                                                            !used.has(i.id),
                                                    )
                                                    .map((i) => (
                                                        <SelectItem
                                                            key={i.id}
                                                            value={String(i.id)}
                                                        >
                                                            {i.name}
                                                        </SelectItem>
                                                    ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <QuantityField
                                        key={`${line.key}-${ingredient?.unit ?? 'g'}`}
                                        label={t(
                                            'staff_inventory.recipe.quantity',
                                        )}
                                        unit={ingredient?.unit ?? 'g'}
                                        initial={line.initial}
                                        onChange={(value) =>
                                            patch(line.key, { quantity: value })
                                        }
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t(
                                            'staff_inventory.recipe.remove_line',
                                            { n: index + 1 },
                                        )}
                                        onClick={() =>
                                            setLines((current) =>
                                                current.filter(
                                                    (l) => l.key !== line.key,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 />
                                    </Button>
                                </li>
                            );
                        })}
                    </ul>
                    {lineError && (
                        <p role="alert" className="text-sm text-chilli">
                            {lineError}
                        </p>
                    )}
                    <Button
                        type="button"
                        variant="outline"
                        className="justify-self-start"
                        onClick={() =>
                            setLines((current) => [
                                ...current,
                                {
                                    key: nextKey(),
                                    ingredient_id: null,
                                    quantity: null,
                                    initial: null,
                                },
                            ])
                        }
                    >
                        <Plus /> {t('staff_inventory.recipe.add_line')}
                    </Button>
                    <dl
                        aria-live="polite"
                        className="grid grid-cols-3 gap-3 rounded-md bg-clay p-3 text-sm"
                    >
                        <div>
                            <dt className="text-label text-ink-muted uppercase">
                                {t('staff_inventory.recipe.cost')}
                            </dt>
                            <dd className="font-semibold tabular-nums">
                                {formatMoney(cost)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-label text-ink-muted uppercase">
                                {t('staff_inventory.recipe.net_price')}
                            </dt>
                            <dd className="font-semibold tabular-nums">
                                {formatMoney(dish.net_price)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-label text-ink-muted uppercase">
                                {t('staff_inventory.recipe.margin')}
                            </dt>
                            <dd
                                className={cn(
                                    'font-semibold tabular-nums',
                                    lines.length > 0 &&
                                        margin !== null &&
                                        margin < target
                                        ? 'text-chilli'
                                        : 'text-leaf',
                                )}
                            >
                                {lines.length > 0 ? formatMargin(margin) : '–'}
                            </dd>
                        </div>
                    </dl>
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
                            disabled={form.processing || !online || !complete}
                        >
                            {t('staff_inventory.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
