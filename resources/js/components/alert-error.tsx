import { AlertCircleIcon, CheckCircle2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export default function AlertError({
    errors,
    title,
}: {
    errors: string[];
    title?: string;
}) {
    const t = useT();

    return (
        <div
            role="alert"
            className="flex gap-3 rounded-lg bg-chilli-soft p-4 text-chilli"
        >
            <AlertCircleIcon className="mt-0.5 size-5 shrink-0" aria-hidden />
            <div className="space-y-1">
                <p className="font-semibold">
                    {title || t('settings_pages.two_factor.error_title')}
                </p>
                <ul className="list-inside list-disc text-sm">
                    {Array.from(new Set(errors)).map((error, index) => (
                        <li key={index}>{error}</li>
                    ))}
                </ul>
            </div>
        </div>
    );
}

/**
 * A short success note for status flashes. Announced politely.
 */
export function Notice({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <p
            role="status"
            className={cn(
                'flex items-start gap-3 rounded-lg bg-leaf-soft p-4 font-medium text-leaf',
                className,
            )}
        >
            <CheckCircle2 className="mt-0.5 size-5 shrink-0" aria-hidden />
            <span>{children}</span>
        </p>
    );
}
