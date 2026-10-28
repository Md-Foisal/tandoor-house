import { CategoryTabsSkeleton } from '@/components/menu-page/category-tabs';
import { DishRowSkeleton } from '@/components/menu/dish-row';
import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/lib/i18n';

/** Shaped like the loaded menu: tab bar, two sections of rows. */
export function MenuSkeleton() {
    const t = useT();

    return (
        <div aria-busy="true">
            <span className="sr-only" role="status">
                {t('ui.states.loading')}
            </span>
            <CategoryTabsSkeleton />
            <div className="container-page" aria-hidden="true">
                {[3, 3].map((rows, section) => (
                    <div key={section} className="py-8">
                        <Skeleton className="h-8 w-48" />
                        <Skeleton className="mt-3 h-2 w-16" />
                        <div className="mt-4 divide-y divide-line">
                            {Array.from({ length: rows }, (_, row) => (
                                <DishRowSkeleton key={row} />
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
