import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type OpenStatusData = {
    isOpen: boolean;
    /** "22:30" when open; "tomorrow at 12:00" or "at 17:30" when closed. */
    until?: string | null;
    opensAt?: string | null;
};

export function OpenStatus({
    status,
    className,
}: {
    status: OpenStatusData;
    className?: string;
}) {
    const t = useT();

    return (
        <p
            className={cn(
                'inline-flex items-center gap-2 text-sm font-semibold',
                className,
            )}
            aria-live="polite"
        >
            <span className="relative flex size-2.5" aria-hidden="true">
                {status.isOpen && (
                    <span className="absolute inline-flex size-full rounded-full bg-leaf opacity-60 motion-safe:animate-ping" />
                )}
                <span
                    className={cn(
                        'relative inline-flex size-2.5 rounded-full',
                        status.isOpen ? 'bg-leaf' : 'bg-line-strong',
                    )}
                />
            </span>
            {status.isOpen
                ? status.until
                    ? t('ui.open_until', { time: status.until })
                    : t('ui.open_now')
                : status.opensAt
                  ? t('ui.closed_opens_at', { when: status.opensAt })
                  : t('ui.closed_now')}
        </p>
    );
}
