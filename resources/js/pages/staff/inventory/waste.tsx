import { Deferred, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { EmptyState, useOnline } from '@/components/brand/states';
import { StatTile } from '@/components/staff/stat-tile';
import { QuantityField } from '@/components/staff-inventory/quantity-field';
import {
    InventoryShell,
    RowsSkeleton,
    useWhen,
} from '@/components/staff-inventory/shell';
import type {
    IngredientOption,
    MovementRow,
} from '@/components/staff-inventory/types';
import { formatQuantity } from '@/components/staff-inventory/units';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { choice, useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

const reasons = [
    'over_proofed',
    'dropped',
    'spoiled',
    'over_prepped',
    'returned_dish',
    'other',
] as const;

type Form = {
    ingredient_id: number | null;
    quantity: number | null;
    reason: (typeof reasons)[number] | null;
    note: string;
};

export default function Waste({
    entries,
    ingredients,
    summary,
    lowCount,
}: {
    entries?: MovementRow[];
    ingredients: IngredientOption[];
    summary: {
        cost: number;
        entries: number;
        top: { name: string; cost: number }[];
    };
    lowCount: number;
}) {
    const t = useT();
    const when = useWhen();
    const online = useOnline();
    const [stamp, setStamp] = useState(0);
    const form = useForm<Form>({
        ingredient_id: null,
        quantity: null,
        reason: null,
        note: '',
    });
    const ingredient = ingredients.find(
        (i) => i.id === form.data.ingredient_id,
    );
    const ready =
        form.data.ingredient_id !== null &&
        form.data.quantity !== null &&
        form.data.quantity > 0 &&
        form.data.reason !== null &&
        (form.data.reason !== 'other' || form.data.note.trim() !== '');

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/staff/inventory/waste', {
            preserveScroll: true,
            only: ['entries', 'summary', 'lowCount', 'ingredients'],
            onSuccess: () => {
                form.reset('quantity', 'reason', 'note');
                setStamp((value) => value + 1);
            },
        });
    };

    return (
        <InventoryShell
            active="waste"
            lowCount={lowCount}
            description={t('staff_inventory.waste.description')}
        >
            <div className="grid gap-6 lg:grid-cols-[minmax(0,26rem)_minmax(0,1fr)] lg:items-start">
                <form
                    onSubmit={submit}
                    className="grid gap-4 rounded-lg border border-line bg-surface p-4 lg:sticky lg:top-4"
                    aria-labelledby="waste-form-title"
                >
                    <h2 id="waste-form-title" className="font-display text-xl">
                        {t('staff_inventory.waste.form_title')}
                    </h2>
                    <div className="grid gap-1.5">
                        <Label htmlFor="waste-ingredient">
                            {t('staff_inventory.waste.ingredient')}
                        </Label>
                        <Select
                            value={
                                form.data.ingredient_id
                                    ? String(form.data.ingredient_id)
                                    : undefined
                            }
                            onValueChange={(value) => {
                                form.setData((data) => ({
                                    ...data,
                                    ingredient_id: Number(value),
                                    quantity: null,
                                }));
                            }}
                        >
                            <SelectTrigger
                                id="waste-ingredient"
                                className="min-h-12"
                                aria-invalid={Boolean(
                                    form.errors.ingredient_id,
                                )}
                            >
                                <SelectValue
                                    placeholder={t(
                                        'staff_inventory.waste.pick',
                                    )}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                {ingredients.map((i) => (
                                    <SelectItem key={i.id} value={String(i.id)}>
                                        {i.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {ingredient && (
                            <p className="text-sm text-ink-muted">
                                {t('staff_inventory.stock.current', {
                                    quantity: formatQuantity(
                                        ingredient.stock,
                                        ingredient.unit,
                                    ),
                                })}
                            </p>
                        )}
                        {form.errors.ingredient_id && (
                            <p role="alert" className="text-sm text-chilli">
                                {form.errors.ingredient_id}
                            </p>
                        )}
                    </div>
                    <QuantityField
                        key={`waste-${ingredient?.id ?? 0}-${stamp}`}
                        label={t('staff_inventory.waste.quantity')}
                        unit={ingredient?.unit ?? 'g'}
                        error={form.errors.quantity}
                        onChange={(value) => form.setData('quantity', value)}
                    />
                    <fieldset className="grid gap-2">
                        <legend className="mb-1 text-sm font-medium">
                            {t('staff_inventory.waste.reason')}
                        </legend>
                        <div className="flex flex-wrap gap-2">
                            {reasons.map((reason) => (
                                <button
                                    key={reason}
                                    type="button"
                                    aria-pressed={form.data.reason === reason}
                                    onClick={() =>
                                        form.setData('reason', reason)
                                    }
                                    className={cn(
                                        'min-h-11 rounded-full border px-4 text-sm font-semibold transition-colors',
                                        form.data.reason === reason
                                            ? 'border-ember bg-ember-soft text-ink'
                                            : 'border-line-strong bg-surface hover:bg-clay',
                                    )}
                                >
                                    {t(
                                        `staff_inventory.waste.reasons.${reason}`,
                                    )}
                                </button>
                            ))}
                        </div>
                        {form.errors.reason && (
                            <p role="alert" className="text-sm text-chilli">
                                {form.errors.reason}
                            </p>
                        )}
                    </fieldset>
                    <div className="grid gap-1.5">
                        <Label htmlFor="waste-note">
                            {form.data.reason === 'other'
                                ? t('staff_inventory.waste.note_required')
                                : t('staff_inventory.waste.note')}
                        </Label>
                        <Input
                            id="waste-note"
                            maxLength={160}
                            value={form.data.note}
                            aria-invalid={Boolean(form.errors.note)}
                            className="min-h-11"
                            onChange={(event) =>
                                form.setData('note', event.target.value)
                            }
                        />
                        {form.errors.note && (
                            <p role="alert" className="text-sm text-chilli">
                                {form.errors.note}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Button
                            type="submit"
                            size="lg"
                            className="min-h-12"
                            disabled={!ready || form.processing || !online}
                        >
                            {t('staff_inventory.waste.submit')}
                        </Button>
                        {(!ready || !online) && (
                            <p className="text-sm text-ink-muted">
                                {online
                                    ? t('staff_inventory.waste.incomplete')
                                    : t('staff_inventory.offline')}
                            </p>
                        )}
                    </div>
                </form>

                <div className="grid gap-4">
                    <div className="grid grid-cols-2 gap-3">
                        <StatTile
                            label={t('staff_inventory.waste.week_cost')}
                            value={formatMoney(summary.cost)}
                            hint={choice(
                                t('staff_inventory.waste.week_entries', {
                                    count: summary.entries,
                                }),
                                summary.entries,
                            )}
                            tone={summary.cost > 0 ? 'warn' : 'plain'}
                        />
                        <div className="rounded-lg border border-line bg-surface p-4">
                            <p className="text-label text-ink-muted">
                                {t('staff_inventory.waste.biggest')}
                            </p>
                            {summary.top.length === 0 ? (
                                <p className="mt-2 text-sm text-ink-muted">
                                    {t('staff_inventory.waste.none_week')}
                                </p>
                            ) : (
                                <ol className="mt-2 grid gap-1 text-sm">
                                    {summary.top.map((row) => (
                                        <li
                                            key={row.name}
                                            className="flex justify-between gap-2"
                                        >
                                            <span className="truncate">
                                                {row.name}
                                            </span>
                                            <span className="tabular-nums">
                                                {formatMoney(row.cost)}
                                            </span>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </div>
                    </div>

                    <Deferred
                        data="entries"
                        fallback={<RowsSkeleton rows={6} />}
                    >
                        {entries && entries.length === 0 ? (
                            <div className="rounded-lg border border-line bg-surface">
                                <EmptyState
                                    title={t(
                                        'staff_inventory.waste.empty_title',
                                    )}
                                    description={t(
                                        'staff_inventory.waste.empty',
                                    )}
                                />
                            </div>
                        ) : (
                            <ul className="divide-y divide-line overflow-hidden rounded-lg border border-line bg-surface">
                                {(entries ?? []).map((entry) => (
                                    <li
                                        key={entry.id}
                                        className="flex items-start justify-between gap-3 px-4 py-3"
                                    >
                                        <div className="min-w-0">
                                            <p className="font-semibold">
                                                {entry.ingredient.name}{' '}
                                                <span className="font-normal text-ink-muted tabular-nums">
                                                    {formatQuantity(
                                                        Math.abs(
                                                            entry.quantity,
                                                        ),
                                                        entry.ingredient.unit,
                                                    )}
                                                </span>
                                            </p>
                                            <p className="text-sm text-ink-muted">
                                                {entry.reason ??
                                                    t(
                                                        'staff_inventory.no_reason',
                                                    )}
                                            </p>
                                            <p className="text-sm text-ink-muted">
                                                {entry.user ??
                                                    t(
                                                        'staff_inventory.system',
                                                    )}{' '}
                                                · {when(entry.at)}
                                            </p>
                                        </div>
                                        <p className="shrink-0 text-sm font-semibold tabular-nums">
                                            {formatMoney(entry.cost)}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Deferred>
                </div>
            </div>
        </InventoryShell>
    );
}
