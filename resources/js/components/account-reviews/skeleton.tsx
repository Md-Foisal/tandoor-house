import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/lib/i18n';

export function ReviewsSkeleton() {
    const t = useT();

    return (
        <div
            role="status"
            aria-label={t('reviews.loading')}
            className="flex flex-col gap-10"
        >
            <div className="flex flex-col gap-4">
                <Skeleton className="h-8 w-64 max-w-full" />
                <div className="grid gap-5 rounded-lg border border-line bg-surface p-5 md:grid-cols-[16rem_1fr]">
                    <div className="flex flex-col gap-2">
                        <Skeleton className="h-6 w-32" />
                        <Skeleton className="h-4 w-40" />
                        <Skeleton className="h-4 w-36" />
                    </div>
                    <div className="flex flex-col gap-3">
                        <Skeleton className="h-8 w-56" />
                        <Skeleton className="h-24 w-full" />
                        <Skeleton className="h-11 w-full" />
                    </div>
                </div>
            </div>
            <div className="flex flex-col gap-4">
                <Skeleton className="h-8 w-40" />
                <div className="grid gap-5 md:grid-cols-2">
                    {[0, 1].map((key) => (
                        <div
                            key={key}
                            className="flex flex-col gap-3 rounded-lg border border-line bg-surface p-5"
                        >
                            <Skeleton className="h-5 w-28" />
                            <Skeleton className="h-4 w-full" />
                            <Skeleton className="h-4 w-2/3" />
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
