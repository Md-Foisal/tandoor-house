import { usePage } from '@inertiajs/react';
import { WifiOff } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/** An empty plate seen from above: the brand's illustration for "nothing here". */
function EmptyPlate() {
    return (
        <svg
            viewBox="0 0 120 96"
            aria-hidden="true"
            className="h-24 w-30 text-line-strong"
        >
            <g
                fill="none"
                stroke="currentColor"
                strokeLinecap="round"
                strokeLinejoin="round"
            >
                <circle cx="60" cy="48" r="34" strokeWidth="2" />
                <circle
                    cx="60"
                    cy="48"
                    r="23"
                    strokeWidth="1.5"
                    strokeDasharray="2 5"
                />
                <path
                    d="M14 18v18c0 3 2 5 4 5s4-2 4-5V18M18 18v60"
                    strokeWidth="2"
                />
                <path d="M106 18c-4 4-6 11-6 19h6v41" strokeWidth="2" />
            </g>
        </svg>
    );
}

export function EmptyState({
    title,
    description,
    action,
    className,
}: {
    title?: string;
    description?: ReactNode;
    action?: ReactNode;
    className?: string;
}) {
    const t = useT();

    return (
        <div
            className={cn(
                'flex flex-col items-center gap-4 px-6 py-12 text-center',
                className,
            )}
        >
            <EmptyPlate />
            <h3 className="font-display text-display-md text-ink">
                {title ?? t('ui.states.empty_title')}
            </h3>
            {description && (
                <p className="max-w-[40ch] text-ink-muted">{description}</p>
            )}
            {action}
        </div>
    );
}

export function ErrorState({
    title,
    onRetry,
    className,
}: {
    title?: string;
    onRetry?: () => void;
    className?: string;
}) {
    const t = useT();
    const { restaurant } = usePage().props;

    return (
        <div
            role="alert"
            className={cn(
                'flex flex-col items-start gap-3 rounded-lg border border-chilli/40 bg-chilli-soft p-5 text-ink',
                className,
            )}
        >
            <h3 className="text-title font-semibold">
                {title ?? t('ui.states.error_title')}
            </h3>
            <p className="text-ink-muted">
                {restaurant?.phone
                    ? t('ui.states.error_body', { phone: restaurant.phone })
                    : t('ui.states.error_body_no_phone')}
            </p>
            {onRetry && (
                <Button variant="outline" size="sm" onClick={onRetry}>
                    {t('ui.states.try_again')}
                </Button>
            )}
        </div>
    );
}

export function useOnline(): boolean {
    const [online, setOnline] = useState(true);

    useEffect(() => {
        const update = () => setOnline(navigator.onLine);

        update();
        window.addEventListener('online', update);
        window.addEventListener('offline', update);

        return () => {
            window.removeEventListener('online', update);
            window.removeEventListener('offline', update);
        };
    }, []);

    return online;
}

export function DemoBanner() {
    const t = useT();
    const { demo } = usePage().props;

    if (demo === null) {
        return null;
    }

    return (
        <p className="bg-turmeric-soft px-4 py-2 text-center text-sm font-semibold text-ink">
            {t('ui.demo.banner')}
        </p>
    );
}

export function OfflineBanner({
    forceOffline = false,
}: {
    forceOffline?: boolean;
}) {
    const t = useT();
    const online = useOnline();

    if (online && !forceOffline) {
        return null;
    }

    return (
        <div
            role="status"
            className="flex items-center gap-3 bg-ink px-4 py-3 text-sm font-medium text-paper"
        >
            <WifiOff className="size-4 shrink-0" aria-hidden="true" />
            {t('ui.states.offline')}
        </div>
    );
}
