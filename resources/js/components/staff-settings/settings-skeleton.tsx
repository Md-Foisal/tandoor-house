import { Skeleton } from '@/components/ui/skeleton';

export function SettingsSkeleton() {
    return (
        <div
            className="grid gap-4 rounded-lg border border-line bg-surface p-6"
            aria-hidden="true"
        >
            <Skeleton className="h-8 w-1/3" />
            <Skeleton className="h-4 w-2/3" />
            {Array.from({ length: 4 }, (_, i) => (
                <Skeleton key={i} className="h-12 w-full" />
            ))}
        </div>
    );
}
