import type { HTMLAttributes, ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export default function InputError({
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    return message ? (
        <p
            {...props}
            className={cn('text-sm font-medium text-chilli', className)}
        >
            {message}
        </p>
    ) : null;
}

type ControlProps = {
    id: string;
    'aria-describedby': string | undefined;
    'aria-invalid': true | undefined;
};

/**
 * Label, control, hint and inline error wired together: the error and hint
 * are announced with the control and the control is flagged invalid.
 */
export function Field({
    id,
    label,
    error,
    hint,
    aside,
    className,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    aside?: ReactNode;
    className?: string;
    children: (control: ControlProps) => ReactNode;
}) {
    const errorId = `${id}-error`;
    const hintId = `${id}-hint`;
    const describedBy =
        [hint ? hintId : null, error ? errorId : null]
            .filter(Boolean)
            .join(' ') || undefined;

    return (
        <div className={cn('grid gap-2', className)}>
            <div className="flex items-baseline justify-between gap-3">
                <Label htmlFor={id} className="text-base font-semibold">
                    {label}
                </Label>
                {aside}
            </div>
            {children({
                id,
                'aria-describedby': describedBy,
                'aria-invalid': error ? true : undefined,
            })}
            {hint && (
                <p id={hintId} className="text-sm text-ink-muted">
                    {hint}
                </p>
            )}
            <InputError id={errorId} message={error} />
        </div>
    );
}
