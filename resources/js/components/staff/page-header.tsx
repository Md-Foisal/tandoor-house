import type { ReactNode } from 'react';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * Top of every staff page: title, optional context line, actions on the
 * right. Sticky so actions stay reachable while lists scroll.
 */
export function StaffPageHeader({
    title,
    description,
    actions,
    children,
    className,
}: {
    title: string;
    description?: ReactNode;
    actions?: ReactNode;
    children?: ReactNode;
    className?: string;
}) {
    const t = useT();

    return (
        <div
            className={cn(
                'border-b border-line bg-paper px-4 pt-5 pb-4 md:px-6 md:pt-6',
                className,
            )}
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex min-w-0 items-start gap-2">
                    <SidebarTrigger
                        className="mt-1 hidden size-9 md:inline-flex"
                        aria-label={t('staff.collapse')}
                    />
                    <div className="min-w-0">
                        <h1 className="font-display text-display-md">
                            {title}
                        </h1>
                        {description && (
                            <p className="mt-1 text-sm text-ink-muted">
                                {description}
                            </p>
                        )}
                    </div>
                </div>
                {actions && (
                    <div className="flex flex-wrap items-center gap-2">
                        {actions}
                    </div>
                )}
            </div>
            {children && <div className="mt-4">{children}</div>}
        </div>
    );
}
