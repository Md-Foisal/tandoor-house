import { Skeleton } from '@/components/ui/skeleton';

export function TicketSkeleton() {
    return (
        <div className="flex flex-col gap-3 rounded-lg border-2 border-line bg-surface p-4">
            <div className="flex items-center justify-between">
                <Skeleton className="h-7 w-28" />
                <Skeleton className="h-6 w-12" />
            </div>
            <Skeleton className="h-4 w-36" />
            <Skeleton className="h-16 w-full" />
            <Skeleton className="h-16 w-full" />
            <Skeleton className="h-14 w-full" />
        </div>
    );
}
