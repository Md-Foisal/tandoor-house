import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/lib/i18n';

export function FeedSkeleton({ rows = 8 }: { rows?: number }) {
    const t = useT();

    return (
        <div
            role="status"
            aria-label={t('staff_activity.states.loading')}
            className="grid gap-3"
        >
            <Skeleton className="h-5 w-32" />
            <div className="divide-y divide-line overflow-hidden rounded-lg border border-line bg-surface">
                {Array.from({ length: rows }, (_, i) => (
                    <div
                        key={i}
                        className="grid gap-2 px-4 py-3 md:grid-cols-[4rem_1fr] md:gap-4"
                    >
                        <Skeleton className="h-4 w-10" />
                        <div className="grid gap-2">
                            <Skeleton className="h-4 w-40" />
                            <Skeleton className="h-4 w-full max-w-lg" />
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
