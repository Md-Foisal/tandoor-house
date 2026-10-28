import { AlertCircle } from 'lucide-react';
import type { AllergenCode } from '@/components/brand/allergen-icon';
import { Checkbox } from '@/components/ui/checkbox';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { ModifierGroup, Variant } from '@/types';

export const NOTES_MAX = 140;

const row =
    'flex min-h-12 cursor-pointer items-center gap-3 rounded-md border border-line-strong bg-surface px-3.5 py-2 has-[[data-state=checked]]:border-ember has-[[data-state=checked]]:bg-ember-soft has-[[data-disabled]]:cursor-not-allowed has-[[data-disabled]]:opacity-60';

export function FieldError({ id, message }: { id: string; message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <p
            id={id}
            role="alert"
            className="flex items-start gap-2 text-sm font-medium text-chilli"
        >
            <AlertCircle
                className="mt-0.5 size-4 shrink-0"
                aria-hidden="true"
            />
            {message}
        </p>
    );
}

function Pill({ children }: { children: string }) {
    return (
        <span className="rounded-sm bg-clay px-1.5 py-0.5 text-xs font-semibold text-ink-muted">
            {children}
        </span>
    );
}

export function VariantChooser({
    variants,
    value,
    onChange,
    error,
}: {
    variants: Variant[];
    value: number | null;
    onChange: (id: number) => void;
    error?: string;
}) {
    const t = useT();

    return (
        <fieldset className="flex flex-col gap-3" id="field-variant">
            <legend className="mb-3 flex items-center gap-2 text-title font-semibold">
                {t('menu.dish.size')}
                <Pill>{t('menu.dish.required')}</Pill>
            </legend>
            <RadioGroup
                value={value === null ? '' : String(value)}
                onValueChange={(next) => onChange(Number(next))}
                aria-required="true"
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? 'variant-error' : undefined}
            >
                {variants.map((variant) => (
                    <label
                        key={variant.id}
                        htmlFor={`variant-${variant.id}`}
                        className={row}
                    >
                        <RadioGroupItem
                            id={`variant-${variant.id}`}
                            value={String(variant.id)}
                        />
                        <span className="flex-1 font-medium">
                            {variant.name}
                            {variant.kcal !== null && (
                                <span className="ml-2 text-sm font-normal text-ink-muted tabular-nums">
                                    {t('ui.kcal', { kcal: variant.kcal })}
                                </span>
                            )}
                        </span>
                        <span className="font-semibold tabular-nums">
                            {formatMoney(variant.price, { compact: true })}
                        </span>
                    </label>
                ))}
            </RadioGroup>
            <FieldError id="variant-error" message={error} />
        </fieldset>
    );
}

function hint(group: ModifierGroup, t: ReturnType<typeof useT>): string {
    if (group.min === 1 && group.max === 1) {
        return t('menu.dish.choose_exactly');
    }

    if (group.min === 0) {
        return t('menu.dish.choose_up_to', { max: group.max });
    }

    return t('menu.dish.choose_between', { min: group.min, max: group.max });
}

function ModifierAllergens({ codes }: { codes: AllergenCode[] }) {
    const t = useT();

    if (codes.length === 0) {
        return null;
    }

    return (
        <span className="block text-xs font-normal text-ink-muted">
            {t('ui.contains')}:{' '}
            {codes.map((code) => t(`allergens.${code}`)).join(', ')}
        </span>
    );
}

export function ModifierGroupField({
    group,
    selected,
    onToggle,
    onPick,
    error,
}: {
    group: ModifierGroup;
    selected: number[];
    onToggle: (modifierId: number, checked: boolean) => void;
    onPick: (modifierId: number) => void;
    error?: string;
}) {
    const t = useT();
    const chosen = group.modifiers.filter((modifier) =>
        selected.includes(modifier.id),
    ).length;
    const single = group.min === 1 && group.max === 1;
    const errorId = `group-${group.id}-error`;
    const price = (pence: number) =>
        pence > 0 ? `+${formatMoney(pence, { compact: false })}` : null;

    return (
        <fieldset
            className="flex flex-col gap-3"
            id={`field-group-${group.id}`}
            aria-describedby={error ? errorId : undefined}
        >
            <legend className="mb-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-title font-semibold">
                {group.name}
                <Pill>
                    {group.min > 0
                        ? t('menu.dish.required')
                        : t('menu.dish.optional')}
                </Pill>
                <span className="text-sm font-normal text-ink-muted">
                    {hint(group, t)}
                </span>
            </legend>

            {single ? (
                <RadioGroup
                    value={String(
                        group.modifiers.find((m) => selected.includes(m.id))
                            ?.id ?? '',
                    )}
                    onValueChange={(next) => onPick(Number(next))}
                    aria-invalid={error ? true : undefined}
                >
                    {group.modifiers.map((modifier) => (
                        <label
                            key={modifier.id}
                            htmlFor={`modifier-${modifier.id}`}
                            className={row}
                        >
                            <RadioGroupItem
                                id={`modifier-${modifier.id}`}
                                value={String(modifier.id)}
                            />
                            <span className="flex-1 font-medium">
                                {modifier.name}
                                <ModifierAllergens codes={modifier.allergens} />
                            </span>
                            <span className="text-sm font-semibold tabular-nums">
                                {price(modifier.price)}
                            </span>
                        </label>
                    ))}
                </RadioGroup>
            ) : (
                <ul className="grid gap-2">
                    {group.modifiers.map((modifier) => {
                        const checked = selected.includes(modifier.id);

                        return (
                            <li key={modifier.id}>
                                <label
                                    htmlFor={`modifier-${modifier.id}`}
                                    className={cn(row)}
                                >
                                    <Checkbox
                                        id={`modifier-${modifier.id}`}
                                        checked={checked}
                                        disabled={
                                            !checked && chosen >= group.max
                                        }
                                        onCheckedChange={(next) =>
                                            onToggle(modifier.id, next === true)
                                        }
                                    />
                                    <span className="flex-1 font-medium">
                                        {modifier.name}
                                        <ModifierAllergens
                                            codes={modifier.allergens}
                                        />
                                    </span>
                                    <span className="text-sm font-semibold tabular-nums">
                                        {price(modifier.price)}
                                    </span>
                                </label>
                            </li>
                        );
                    })}
                </ul>
            )}
            <FieldError id={errorId} message={error} />
        </fieldset>
    );
}

export function NotesField({
    value,
    onChange,
    error,
}: {
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    const t = useT();

    return (
        <div className="flex flex-col gap-2">
            <label htmlFor="dish-notes" className="text-title font-semibold">
                {t('menu.dish.notes')}
            </label>
            <Textarea
                id="dish-notes"
                value={value}
                maxLength={NOTES_MAX}
                rows={3}
                placeholder={t('menu.dish.notes_placeholder')}
                onChange={(event) => onChange(event.target.value)}
                aria-describedby="dish-notes-help"
                aria-invalid={error ? true : undefined}
            />
            <div className="flex justify-between gap-4 text-sm text-ink-muted">
                <p id="dish-notes-help">{t('menu.dish.notes_hint')}</p>
                <p className="shrink-0 tabular-nums">
                    {t('menu.dish.notes_count', {
                        count: value.length,
                        max: NOTES_MAX,
                    })}
                </p>
            </div>
            <FieldError id="notes-error" message={error} />
        </div>
    );
}
