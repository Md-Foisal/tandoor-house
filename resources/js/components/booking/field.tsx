import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type ControlProps = {
    id: string;
    'aria-invalid'?: true;
    'aria-describedby'?: string;
};

/** A labelled field with its hint and inline error wired up for screen readers. */
export function Field({
    name,
    label,
    error,
    hint,
    className,
    children,
}: {
    name: string;
    label: string;
    error?: string;
    hint?: string;
    className?: string;
    children: (props: ControlProps) => ReactNode;
}) {
    const id = `book-${name}`;
    const describedBy =
        [hint ? `${id}-hint` : null, error ? `${id}-error` : null]
            .filter(Boolean)
            .join(' ') || undefined;

    return (
        <div className={cn('grid gap-1.5', className)}>
            <Label htmlFor={id}>{label}</Label>
            {children({
                id,
                'aria-invalid': error ? true : undefined,
                'aria-describedby': describedBy,
            })}
            {hint && (
                <p id={`${id}-hint`} className="text-sm text-ink-muted">
                    {hint}
                </p>
            )}
            {error && (
                <p
                    id={`${id}-error`}
                    className="text-sm font-medium text-chilli"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
