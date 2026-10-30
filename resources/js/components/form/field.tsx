import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type ControlProps = {
    id: string;
    'aria-invalid'?: true;
    'aria-required'?: true;
    'aria-describedby'?: string;
};

/**
 * A labelled form field with its hint and inline error wired up for
 * screen readers. Children receive the props the control needs.
 */
export function Field({
    name,
    idPrefix = 'field',
    label,
    error,
    hint,
    required,
    className,
    children,
}: {
    name: string;
    idPrefix?: string;
    label: string;
    error?: string;
    hint?: string;
    required?: boolean;
    className?: string;
    children: (props: ControlProps) => ReactNode;
}) {
    const t = useT();
    const id = `${idPrefix}-${name}`;
    const describedBy =
        [hint ? `${id}-hint` : null, error ? `${id}-error` : null]
            .filter(Boolean)
            .join(' ') || undefined;

    return (
        <div className={cn('grid gap-1.5', className)}>
            <Label htmlFor={id}>
                {label}
                {required && (
                    <>
                        <span aria-hidden="true" className="text-chilli">
                            *
                        </span>
                        <span className="sr-only"> ({t('ui.required')})</span>
                    </>
                )}
            </Label>
            {children({
                id,
                'aria-invalid': error ? true : undefined,
                'aria-required': required ? true : undefined,
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

export function FieldError({ id, message }: { id: string; message?: string }) {
    return message ? (
        <p id={id} className="text-sm font-medium text-chilli">
            {message}
        </p>
    ) : null;
}
