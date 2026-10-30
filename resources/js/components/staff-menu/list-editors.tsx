import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import { AllergenPicker } from './allergen-picker';
import {
    blankGroup,
    blankModifier,
    blankVariant,
    type GroupForm,
    type ModifierForm,
    type VariantForm,
} from './form-state';
import { Field } from '@/components/form/field';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { useT } from '@/lib/i18n';

type Errors = Record<string, string | undefined>;

function swap<T>(list: T[], from: number, to: number): T[] {
    if (to < 0 || to >= list.length) return list;
    const next = [...list];
    [next[from], next[to]] = [next[to], next[from]];

    return next;
}

function RowTools({
    index,
    count,
    removeLabel,
    onMove,
    onRemove,
}: {
    index: number;
    count: number;
    removeLabel: string;
    onMove: (to: number) => void;
    onRemove: () => void;
}) {
    const t = useT();

    return (
        <div className="flex shrink-0 items-center">
            <Button
                type="button"
                variant="ghost"
                size="icon"
                disabled={index === 0}
                aria-label={t('staff_menu.editor.move_up')}
                onClick={() => onMove(index - 1)}
            >
                <ArrowUp aria-hidden="true" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                disabled={index === count - 1}
                aria-label={t('staff_menu.editor.move_down')}
                onClick={() => onMove(index + 1)}
            >
                <ArrowDown aria-hidden="true" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label={removeLabel}
                onClick={onRemove}
            >
                <Trash2 aria-hidden="true" />
            </Button>
        </div>
    );
}

export function VariantsEditor({
    value,
    errors,
    onChange,
}: {
    value: VariantForm[];
    errors: Errors;
    onChange: (next: VariantForm[]) => void;
}) {
    const t = useT();
    const update = (index: number, patch: Partial<VariantForm>) =>
        onChange(value.map((v, i) => (i === index ? { ...v, ...patch } : v)));

    return (
        <div className="grid gap-3">
            <p className="text-sm text-ink-muted">
                {t('staff_menu.editor.variants_help')}
            </p>
            {value.length === 0 && (
                <p className="rounded-md border border-dashed border-line-strong px-3 py-4 text-sm text-ink-muted">
                    {t('staff_menu.editor.no_variants')}
                </p>
            )}
            {value.map((variant, index) => (
                <div
                    key={variant.key}
                    className="flex flex-wrap items-start gap-2 rounded-md border border-line bg-surface p-3"
                >
                    <Field
                        name={`variant-${variant.key}-name`}
                        label={t('staff_menu.editor.variant_name')}
                        error={errors[`variants.${index}.name`]}
                        className="min-w-40 flex-1"
                    >
                        {(props) => (
                            <Input
                                {...props}
                                className="h-11"
                                value={variant.name}
                                maxLength={60}
                                onChange={(e) =>
                                    update(index, { name: e.target.value })
                                }
                            />
                        )}
                    </Field>
                    <Field
                        name={`variant-${variant.key}-price`}
                        label={t('staff_menu.editor.variant_price')}
                        error={errors[`variants.${index}.price`]}
                        className="w-28"
                    >
                        {(props) => (
                            <PoundsInput
                                {...props}
                                value={variant.price}
                                onChange={(price) => update(index, { price })}
                            />
                        )}
                    </Field>
                    <Field
                        name={`variant-${variant.key}-kcal`}
                        label={t('staff_menu.editor.variant_kcal')}
                        error={errors[`variants.${index}.kcal`]}
                        className="w-24"
                    >
                        {(props) => (
                            <Input
                                {...props}
                                className="h-11"
                                inputMode="numeric"
                                value={variant.kcal}
                                onChange={(e) =>
                                    update(index, {
                                        kcal: e.target.value.replace(/\D/g, ''),
                                    })
                                }
                            />
                        )}
                    </Field>
                    <div className="self-end">
                        <RowTools
                            index={index}
                            count={value.length}
                            removeLabel={t('staff_menu.editor.remove_variant', {
                                n: index + 1,
                            })}
                            onMove={(to) => onChange(swap(value, index, to))}
                            onRemove={() =>
                                onChange(value.filter((_, i) => i !== index))
                            }
                        />
                    </div>
                </div>
            ))}
            <Button
                type="button"
                variant="outline"
                size="lg"
                className="justify-self-start"
                onClick={() => onChange([...value, blankVariant()])}
            >
                <Plus aria-hidden="true" />
                {t('staff_menu.editor.add_variant')}
            </Button>
        </div>
    );
}

export function PoundsInput({
    value,
    onChange,
    ...props
}: {
    value: string;
    onChange: (next: string) => void;
} & Omit<React.ComponentProps<typeof Input>, 'value' | 'onChange'>) {
    return (
        <div className="relative">
            <span
                aria-hidden="true"
                className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-ink-muted"
            >
                £
            </span>
            <Input
                {...props}
                inputMode="decimal"
                className="h-11 pl-7 tabular-nums"
                value={value}
                onChange={(e) =>
                    onChange(e.target.value.replace(/[^\d.]/g, ''))
                }
                onBlur={() => {
                    if (/^\d+(\.\d{1,2})?$/.test(value))
                        onChange(Number(value).toFixed(2));
                }}
            />
        </div>
    );
}

function ModifierRow({
    modifier,
    index,
    count,
    path,
    errors,
    onChange,
    onMove,
    onRemove,
}: {
    modifier: ModifierForm;
    index: number;
    count: number;
    path: string;
    errors: Errors;
    onChange: (patch: Partial<ModifierForm>) => void;
    onMove: (to: number) => void;
    onRemove: () => void;
}) {
    const t = useT();

    return (
        <li className="grid gap-2 rounded-md border border-line bg-paper p-3">
            <div className="flex flex-wrap items-start gap-2">
                <Field
                    name={`mod-${modifier.key}-name`}
                    label={t('staff_menu.editor.modifier_name')}
                    error={errors[`${path}.name`]}
                    className="min-w-36 flex-1"
                >
                    {(props) => (
                        <Input
                            {...props}
                            className="h-11"
                            value={modifier.name}
                            maxLength={60}
                            onChange={(e) => onChange({ name: e.target.value })}
                        />
                    )}
                </Field>
                <Field
                    name={`mod-${modifier.key}-price`}
                    label={t('staff_menu.editor.modifier_price')}
                    error={errors[`${path}.price`]}
                    className="w-28"
                >
                    {(props) => (
                        <PoundsInput
                            {...props}
                            value={modifier.price}
                            onChange={(price) => onChange({ price })}
                        />
                    )}
                </Field>
                <div className="self-end">
                    <RowTools
                        index={index}
                        count={count}
                        removeLabel={t('staff_menu.editor.remove_modifier', {
                            n: index + 1,
                        })}
                        onMove={onMove}
                        onRemove={onRemove}
                    />
                </div>
            </div>
            <Collapsible>
                <CollapsibleTrigger asChild>
                    <Button
                        type="button"
                        variant="link"
                        className="h-auto min-h-11 px-0"
                    >
                        {t('staff_menu.editor.modifier_allergens')}
                        {modifier.allergens.length > 0 &&
                            ` (${modifier.allergens.length})`}
                    </Button>
                </CollapsibleTrigger>
                <CollapsibleContent className="pt-2">
                    <AllergenPicker
                        compact
                        idPrefix={`mod-${modifier.key}`}
                        value={modifier.allergens}
                        onChange={(allergens) => onChange({ allergens })}
                    />
                </CollapsibleContent>
            </Collapsible>
        </li>
    );
}

export function GroupsEditor({
    value,
    errors,
    onChange,
}: {
    value: GroupForm[];
    errors: Errors;
    onChange: (next: GroupForm[]) => void;
}) {
    const t = useT();
    const update = (index: number, patch: Partial<GroupForm>) =>
        onChange(value.map((g, i) => (i === index ? { ...g, ...patch } : g)));

    return (
        <div className="grid gap-4">
            <p className="text-sm text-ink-muted">
                {t('staff_menu.editor.groups_help')}
            </p>
            {value.length === 0 && (
                <p className="rounded-md border border-dashed border-line-strong px-3 py-4 text-sm text-ink-muted">
                    {t('staff_menu.editor.no_groups')}
                </p>
            )}
            {value.map((group, gi) => (
                <fieldset
                    key={group.key}
                    className="grid gap-3 rounded-lg border border-line bg-surface p-3 sm:p-4"
                >
                    <legend className="sr-only">
                        {group.name || t('staff_menu.editor.group_name')}
                    </legend>
                    <div className="flex flex-wrap items-start gap-2">
                        <Field
                            name={`group-${group.key}-name`}
                            label={t('staff_menu.editor.group_name')}
                            error={errors[`modifier_groups.${gi}.name`]}
                            className="min-w-40 flex-1"
                        >
                            {(props) => (
                                <Input
                                    {...props}
                                    className="h-11"
                                    value={group.name}
                                    maxLength={60}
                                    onChange={(e) =>
                                        update(gi, { name: e.target.value })
                                    }
                                />
                            )}
                        </Field>
                        <Field
                            name={`group-${group.key}-min`}
                            label={t('staff_menu.editor.group_min')}
                            error={errors[`modifier_groups.${gi}.min`]}
                            className="w-28"
                        >
                            {(props) => (
                                <Input
                                    {...props}
                                    className="h-11"
                                    type="number"
                                    min={0}
                                    max={20}
                                    value={group.min}
                                    onChange={(e) =>
                                        update(gi, {
                                            min: Number(e.target.value),
                                        })
                                    }
                                />
                            )}
                        </Field>
                        <Field
                            name={`group-${group.key}-max`}
                            label={t('staff_menu.editor.group_max')}
                            error={errors[`modifier_groups.${gi}.max`]}
                            className="w-28"
                        >
                            {(props) => (
                                <Input
                                    {...props}
                                    className="h-11"
                                    type="number"
                                    min={1}
                                    max={20}
                                    value={group.max}
                                    onChange={(e) =>
                                        update(gi, {
                                            max: Number(e.target.value),
                                        })
                                    }
                                />
                            )}
                        </Field>
                        <div className="self-end">
                            <RowTools
                                index={gi}
                                count={value.length}
                                removeLabel={t(
                                    'staff_menu.editor.remove_group',
                                    { n: gi + 1 },
                                )}
                                onMove={(to) => onChange(swap(value, gi, to))}
                                onRemove={() =>
                                    onChange(value.filter((_, i) => i !== gi))
                                }
                            />
                        </div>
                    </div>
                    {errors[`modifier_groups.${gi}.modifiers`] && (
                        <p
                            role="alert"
                            className="text-sm font-medium text-chilli"
                        >
                            {errors[`modifier_groups.${gi}.modifiers`]}
                        </p>
                    )}
                    <ul className="grid gap-2">
                        {group.modifiers.map((modifier, mi) => (
                            <ModifierRow
                                key={modifier.key}
                                modifier={modifier}
                                index={mi}
                                count={group.modifiers.length}
                                path={`modifier_groups.${gi}.modifiers.${mi}`}
                                errors={errors}
                                onChange={(patch) =>
                                    update(gi, {
                                        modifiers: group.modifiers.map(
                                            (m, i) =>
                                                i === mi
                                                    ? { ...m, ...patch }
                                                    : m,
                                        ),
                                    })
                                }
                                onMove={(to) =>
                                    update(gi, {
                                        modifiers: swap(
                                            group.modifiers,
                                            mi,
                                            to,
                                        ),
                                    })
                                }
                                onRemove={() =>
                                    update(gi, {
                                        modifiers: group.modifiers.filter(
                                            (_, i) => i !== mi,
                                        ),
                                    })
                                }
                            />
                        ))}
                    </ul>
                    <Button
                        type="button"
                        variant="outline"
                        size="lg"
                        className="justify-self-start"
                        onClick={() =>
                            update(gi, {
                                modifiers: [
                                    ...group.modifiers,
                                    blankModifier(),
                                ],
                            })
                        }
                    >
                        <Plus aria-hidden="true" />
                        {t('staff_menu.editor.add_modifier')}
                    </Button>
                </fieldset>
            ))}
            <Button
                type="button"
                variant="outline"
                size="lg"
                className="justify-self-start"
                onClick={() => onChange([...value, blankGroup()])}
            >
                <Plus aria-hidden="true" />
                {t('staff_menu.editor.add_group')}
            </Button>
        </div>
    );
}
