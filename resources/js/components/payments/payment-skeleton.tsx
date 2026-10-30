import { Skeleton } from '@/components/ui/skeleton';

/** Shaped like the payment form: a few fields and the pay button. */
export function PaymentSkeleton() {
    return (
        <div className="grid gap-4" aria-hidden="true">
            <Skeleton className="h-12 w-full" />
            <div className="grid grid-cols-2 gap-4">
                <Skeleton className="h-12" />
                <Skeleton className="h-12" />
            </div>
            <Skeleton className="h-12 w-full" />
        </div>
    );
}
