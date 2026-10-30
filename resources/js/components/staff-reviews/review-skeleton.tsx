import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/lib/i18n';

export function ReviewSkeleton({ rows = 3 }: { rows?: number }) {
    const t = useT();

    return (
        <div
            role="status"
            aria-label={t('staff_reviews.states.loading')}
            className="grid gap-3"
        >
            {Array.from({ length: rows }, (_, i) => (
                <div
                    key={i}
                    className="grid gap-3 rounded-lg border border-line bg-surface p-4"
                >
                    <Skeleton className="h-5 w-32" />
                    <Skeleton className="h-4 w-full" />
                    <Skeleton className="h-4 w-3/4" />
                    <Skeleton className="h-11 w-full md:w-72" />
                </div>
            ))}
        </div>
    );
}

export function SummarySkeleton() {
    const t = useT();

    return (
        <div
            role="status"
            aria-label={t('staff_reviews.states.loading')}
            className="grid gap-3 md:grid-cols-3"
        >
            <Skeleton className="h-32" />
            <Skeleton className="h-32" />
            <Skeleton className="h-32" />
        </div>
    );
}
