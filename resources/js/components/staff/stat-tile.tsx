import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function StatTile({
    label,
    value,
    hint,
    tone = 'plain',
    className,
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    tone?: 'plain' | 'good' | 'warn' | 'bad';
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-col gap-1 rounded-lg border border-line bg-surface p-4',
                className,
            )}
        >
            <p className="text-label text-ink-muted">{label}</p>
            <p className="font-display text-display-md tabular-nums">{value}</p>
            {hint && (
                <p
                    className={cn(
                        'text-sm',
                        tone === 'good' && 'text-leaf',
                        tone === 'warn' && 'text-turmeric',
                        tone === 'bad' && 'text-chilli',
                        tone === 'plain' && 'text-ink-muted',
                    )}
                >
                    {hint}
                </p>
            )}
        </div>
    );
}
