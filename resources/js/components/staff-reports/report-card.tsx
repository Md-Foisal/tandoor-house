import { Download } from 'lucide-react';
import type { ReactNode } from 'react';
import { useOnline } from '@/components/brand/states';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export function ReportCard({
    id,
    title,
    description,
    exportHref,
    children,
    className,
}: {
    id: string;
    title: string;
    description?: ReactNode;
    exportHref?: string;
    children: ReactNode;
    className?: string;
}) {
    const t = useT();
    const online = useOnline();

    return (
        <section
            aria-labelledby={`${id}-title`}
            className={cn(
                'flex min-w-0 flex-col gap-5 rounded-lg border border-line bg-surface p-4 md:p-6',
                className,
            )}
        >
            <header className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <h2
                        id={`${id}-title`}
                        className="font-display text-display-md"
                    >
                        {title}
                    </h2>
                    {description && (
                        <p className="mt-1 max-w-[70ch] text-sm text-ink-muted">
                            {description}
                        </p>
                    )}
                </div>
                {exportHref &&
                    (online ? (
                        <Button asChild variant="outline" size="sm">
                            <a
                                href={exportHref}
                                aria-label={t(
                                    'staff_reports.export.csv_label',
                                    {
                                        section: title,
                                    },
                                )}
                            >
                                <Download />
                                {t('staff_reports.export.csv')}
                            </a>
                        </Button>
                    ) : (
                        <Button
                            variant="outline"
                            size="sm"
                            disabled
                            title={t('staff_reports.export.offline')}
                        >
                            <Download />
                            {t('staff_reports.export.csv')}
                        </Button>
                    ))}
            </header>
            {children}
        </section>
    );
}

export function ReportCardSkeleton({
    tall = false,
    className,
}: {
    tall?: boolean;
    className?: string;
}) {
    return (
        <div
            aria-hidden="true"
            className={cn(
                'flex flex-col gap-5 rounded-lg border border-line bg-surface p-4 md:p-6',
                className,
            )}
        >
            <Skeleton className="h-8 w-56" />
            <Skeleton className={tall ? 'h-56 w-full' : 'h-32 w-full'} />
            <div className="flex flex-col gap-2">
                <Skeleton className="h-8 w-full" />
                <Skeleton className="h-8 w-full" />
                <Skeleton className="h-8 w-3/4" />
            </div>
        </div>
    );
}

export function EmptyPeriod() {
    const t = useT();

    return (
        <div className="rounded-md bg-clay px-4 py-6">
            <p className="font-semibold text-ink">
                {t('staff_reports.empty.title')}
            </p>
            <p className="mt-1 text-sm text-ink-muted">
                {t('staff_reports.empty.body')}
            </p>
        </div>
    );
}
