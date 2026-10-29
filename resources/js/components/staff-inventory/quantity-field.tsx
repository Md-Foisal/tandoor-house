import { useId, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { Unit } from './types';
import { toBase, unitChoices } from './units';

/**
 * A number box with a unit switch (g | kg, ml | l). Reports whole base units,
 * or null while the box is empty or not a number. Remount with a new `key`
 * to reset it.
 */
export function QuantityField({
    label,
    unit,
    initial = null,
    onChange,
    error,
    hint,
    required = true,
    autoFocus = false,
    hideLabel = false,
}: {
    label: string;
    unit: Unit;
    initial?: number | null;
    onChange: (base: number | null) => void;
    error?: string;
    hint?: string;
    required?: boolean;
    autoFocus?: boolean;
    hideLabel?: boolean;
}) {
    const id = useId();
    const choices = unitChoices(unit);
    const startBig =
        initial !== null &&
        initial >= 1000 &&
        initial % 100 === 0 &&
        choices.length > 1;
    const [factor, setFactor] = useState(startBig ? 1000 : 1);
    const [text, setText] = useState(
        initial === null ? '' : String(initial / (startBig ? 1000 : 1)),
    );

    const update = (nextText: string, nextFactor: number) => {
        setText(nextText);
        setFactor(nextFactor);
        onChange(nextText.trim() === '' ? null : toBase(nextText, nextFactor));
    };

    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id} className={hideLabel ? 'sr-only' : undefined}>
                {label}
            </Label>
            <div className="flex gap-2">
                <Input
                    id={id}
                    inputMode="decimal"
                    autoComplete="off"
                    autoFocus={autoFocus}
                    required={required}
                    value={text}
                    aria-invalid={Boolean(error)}
                    aria-describedby={
                        error ? `${id}-error` : hint ? `${id}-hint` : undefined
                    }
                    className="min-h-11 flex-1 tabular-nums"
                    onChange={(event) => update(event.target.value, factor)}
                />
                {choices.length > 1 ? (
                    <div
                        role="group"
                        aria-label={label}
                        className="flex rounded-md border border-line-strong"
                    >
                        {choices.map((choice) => (
                            <button
                                key={choice.label}
                                type="button"
                                aria-pressed={factor === choice.factor}
                                onClick={() => update(text, choice.factor)}
                                className={cn(
                                    'min-h-11 min-w-11 px-3 text-sm font-semibold first:rounded-l-md last:rounded-r-md',
                                    factor === choice.factor
                                        ? 'bg-ink text-paper'
                                        : 'bg-surface hover:bg-clay',
                                )}
                            >
                                {choice.label}
                            </button>
                        ))}
                    </div>
                ) : (
                    <span className="flex min-h-11 min-w-11 items-center justify-center rounded-md border border-line bg-clay px-3 text-sm text-ink-muted">
                        {unit}
                    </span>
                )}
            </div>
            {error ? (
                <p
                    id={`${id}-error`}
                    role="alert"
                    className="text-sm text-chilli"
                >
                    {error}
                </p>
            ) : (
                hint && (
                    <p id={`${id}-hint`} className="text-sm text-ink-muted">
                        {hint}
                    </p>
                )
            )}
        </div>
    );
}
