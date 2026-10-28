import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * A native radio dressed as a card: keyboard and screen reader behaviour
 * come for free. `tile` is the compact place-card used for times.
 */
export function ChoiceCard({
    name,
    value,
    checked,
    onSelect,
    title,
    detail,
    aside,
    disabled,
    tile = false,
    describedBy,
}: {
    name: string;
    value: string;
    checked: boolean;
    onSelect: (value: string) => void;
    title: ReactNode;
    detail?: ReactNode;
    aside?: ReactNode;
    disabled?: boolean;
    tile?: boolean;
    describedBy?: string;
}) {
    return (
        <label
            className={cn(
                'relative flex min-h-12 cursor-pointer gap-3 rounded-lg border-[1.5px] bg-surface transition-[border-color,background-color] duration-150 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-ember',
                tile
                    ? 'items-center justify-center px-3 py-2.5 text-center'
                    : 'items-start p-4',
                checked
                    ? 'border-ember bg-ember-soft'
                    : 'border-line-strong hover:bg-clay',
                disabled && 'cursor-not-allowed opacity-50 hover:bg-surface',
            )}
        >
            <input
                type="radio"
                className="sr-only"
                name={name}
                value={value}
                checked={checked}
                disabled={disabled}
                aria-describedby={describedBy}
                onChange={() => onSelect(value)}
            />
            {!tile && (
                <span
                    aria-hidden="true"
                    className={cn(
                        'mt-0.5 grid size-5 shrink-0 place-items-center rounded-full border-[1.5px]',
                        checked ? 'border-ember' : 'border-line-strong',
                    )}
                >
                    {checked && (
                        <span className="size-2.5 rounded-full bg-ember" />
                    )}
                </span>
            )}
            <span
                className={cn(
                    'flex min-w-0 flex-1 flex-col',
                    tile && 'items-center',
                )}
            >
                <span
                    className={cn(
                        'font-semibold tabular-nums',
                        checked && 'text-ink',
                    )}
                >
                    {title}
                </span>
                {detail && (
                    <span className="text-sm text-ink-muted">{detail}</span>
                )}
            </span>
            {aside}
        </label>
    );
}
