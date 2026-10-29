import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/lib/i18n';

export function TeamSkeleton({ rows = 6 }: { rows?: number }) {
    const t = useT();

    return (
        <div
            role="status"
            aria-label={t('staff_team.states.loading')}
            className="grid gap-3"
        >
            {Array.from({ length: rows }, (_, i) => (
                <Skeleton key={i} className="h-16 w-full" />
            ))}
        </div>
    );
}
