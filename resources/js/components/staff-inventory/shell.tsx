import { Head, Link, router } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import { useOnline } from '@/components/brand/states';
import { StaffPageHeader } from '@/components/staff/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { useStale } from './use-stale';

export type InventoryTab =
    | 'ingredients'
    | 'recipes'
    | 'movements'
    | 'waste'
    | 'suppliers'
    | 'purchase_orders';

const tabs: { key: InventoryTab; href: string }[] = [
    { key: 'ingredients', href: '/staff/inventory' },
    { key: 'recipes', href: '/staff/inventory/recipes' },
    { key: 'movements', href: '/staff/inventory/movements' },
    { key: 'waste', href: '/staff/inventory/waste' },
    { key: 'suppliers', href: '/staff/inventory/suppliers' },
    { key: 'purchase_orders', href: '/staff/inventory/purchase-orders' },
];

/** Page header, the six tabs and the "could not refresh" notice shared by every stock page. */
export function InventoryShell({
    active,
    lowCount,
    title,
    description,
    actions,
    toolbar,
    children,
}: {
    active: InventoryTab;
    lowCount: number;
    title?: string;
    description?: ReactNode;
    actions?: ReactNode;
    toolbar?: ReactNode;
    children: ReactNode;
}) {
    const t = useT();
    const stale = useStale();
    const online = useOnline();

    return (
        <>
            <Head title={t('staff_inventory.title')} />
            <StaffPageHeader
                title={title ?? t('staff_inventory.title')}
                description={description ?? t('staff_inventory.description')}
                actions={actions}
            >
                <nav
                    aria-label={t('staff_inventory.tabs_label')}
                    className="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0"
                >
                    <ul className="flex min-w-max gap-1 border-b border-line">
                        {tabs.map((tab) => (
                            <li key={tab.key}>
                                <Link
                                    href={tab.href}
                                    prefetch
                                    aria-current={
                                        tab.key === active ? 'page' : undefined
                                    }
                                    className={cn(
                                        '-mb-px flex min-h-11 items-center gap-2 border-b-2 px-3 text-sm font-semibold whitespace-nowrap',
                                        tab.key === active
                                            ? 'border-ember text-ink'
                                            : 'border-transparent text-ink-muted hover:text-ink',
                                    )}
                                >
                                    {t(`staff_inventory.tabs.${tab.key}`)}
                                    {tab.key === 'ingredients' &&
                                        lowCount > 0 && (
                                            <Badge variant="destructive">
                                                <span className="sr-only">
                                                    {t(
                                                        'staff_inventory.low_sr',
                                                    )}{' '}
                                                </span>
                                                {lowCount}
                                            </Badge>
                                        )}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </nav>
                {toolbar && <div className="mt-4">{toolbar}</div>}
            </StaffPageHeader>
            <div className="grid gap-4 p-4 md:p-6">
                {!online && (
                    <p
                        role="status"
                        className="rounded-lg border border-turmeric/50 bg-turmeric-soft p-3 text-sm"
                    >
                        {t('staff_inventory.offline')}
                    </p>
                )}
                {stale && (
                    <div
                        role="alert"
                        className="flex flex-wrap items-center gap-3 rounded-lg border border-chilli/40 bg-chilli-soft p-3 text-sm"
                    >
                        <TriangleAlert
                            className="size-4 text-chilli"
                            aria-hidden="true"
                        />
                        <span className="flex-1">
                            {t('staff_inventory.stale')}
                        </span>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => router.reload()}
                        >
                            {t('staff_inventory.retry')}
                        </Button>
                    </div>
                )}
                {children}
            </div>
        </>
    );
}

export function RowsSkeleton({ rows = 8 }: { rows?: number }) {
    return (
        <div
            className="grid gap-px overflow-hidden rounded-lg border border-line bg-line"
            aria-hidden="true"
        >
            {Array.from({ length: rows }, (_, index) => (
                <div
                    key={index}
                    className="flex items-center gap-4 bg-surface p-4"
                >
                    <div className="grid flex-1 gap-2">
                        <Skeleton className="h-4 w-1/3" />
                        <Skeleton className="h-2 w-2/3" />
                    </div>
                    <Skeleton className="hidden h-4 w-20 md:block" />
                    <Skeleton className="h-4 w-14" />
                </div>
            ))}
        </div>
    );
}

/** "12 min ago", "3 d ago", or a short date once it is over a week old. */
export function useWhen(): (iso: string | null) => string {
    const t = useT();

    return (iso) => {
        if (!iso) {
            return '–';
        }

        const date = new Date(iso);
        const minutes = Math.round((Date.now() - date.getTime()) / 60_000);

        if (minutes < 1) return t('staff_inventory.time.now');
        if (minutes < 60)
            return t('staff_inventory.time.minutes', { n: minutes });
        if (minutes < 60 * 24)
            return t('staff_inventory.time.hours', {
                n: Math.round(minutes / 60),
            });
        if (minutes < 60 * 24 * 7)
            return t('staff_inventory.time.days', {
                n: Math.round(minutes / 60 / 24),
            });

        return date.toLocaleDateString('en-GB', {
            day: 'numeric',
            month: 'short',
        });
    };
}
