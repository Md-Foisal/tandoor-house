import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ErrorState } from '@/components/brand/states';
import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/lib/i18n';

const tiles = [
    'left-[0%] top-[0%] w-[20%] h-[34%]',
    'left-[24%] top-[0%] w-[20%] h-[34%]',
    'left-[48%] top-[0%] w-[14%] h-[34%]',
    'left-[0%] top-[44%] w-[20%] h-[34%]',
    'left-[24%] top-[44%] w-[20%] h-[34%]',
    'left-[48%] top-[44%] w-[24%] h-[34%]',
    'left-[76%] top-[0%] w-[20%] h-[34%]',
    'left-[76%] top-[44%] w-[20%] h-[34%]',
];

export function FloorSkeleton() {
    const t = useT();

    return (
        <div
            role="status"
            aria-label={t('staff_floor.states.loading')}
            className="flex flex-col gap-4 lg:flex-row"
        >
            <div className="flex min-w-0 flex-1 flex-col gap-4">
                <div className="flex gap-2">
                    <Skeleton className="h-11 w-28" />
                    <Skeleton className="h-11 w-28" />
                    <Skeleton className="h-11 w-28" />
                </div>
                <div className="relative hidden aspect-[8/5] w-full md:block">
                    {tiles.map((position) => (
                        <Skeleton
                            key={position}
                            className={`absolute rounded-lg ${position}`}
                        />
                    ))}
                </div>
                <div className="flex flex-col gap-2 md:hidden">
                    {[0, 1, 2, 3, 4].map((row) => (
                        <Skeleton
                            key={row}
                            className="h-16 w-full rounded-lg"
                        />
                    ))}
                </div>
            </div>
            <div className="flex w-full flex-col gap-3 lg:w-80">
                <Skeleton className="h-8 w-48" />
                <Skeleton className="h-32 w-full rounded-md" />
                <Skeleton className="h-32 w-full rounded-md" />
            </div>
        </div>
    );
}

/** The skeleton, and an error with a retry if the plan never arrives. */
export function FloorLoading() {
    const t = useT();
    const [slow, setSlow] = useState(false);

    useEffect(() => {
        const timer = window.setTimeout(() => setSlow(true), 15_000);

        return () => window.clearTimeout(timer);
    }, []);

    if (!slow) {
        return <FloorSkeleton />;
    }

    return (
        <ErrorState
            title={t('staff_floor.states.error_title')}
            onRetry={() => router.reload({ only: ['tables', 'upcoming'] })}
        />
    );
}
