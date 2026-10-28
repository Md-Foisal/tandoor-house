import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { OrderList } from '@/components/account-pages/order-list';
import type {
    OrderFilter,
    OrdersProps,
} from '@/components/account-pages/types';
import { AccountShell } from '@/components/account/account-nav';
import { EmptyState } from '@/components/brand/states';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const filters: OrderFilter[] = ['all', 'active', 'completed', 'cancelled'];

export default function AccountOrders({
    filter,
    orders,
    pagination,
}: OrdersProps) {
    const t = useT();

    return (
        <AccountShell title={t('account.orders.title')}>
            <Head title={t('account.meta.orders_title')} />

            <nav aria-label={t('account.orders.filters_label')}>
                <ul className="flex flex-wrap gap-2">
                    {filters.map((key) => (
                        <li key={key}>
                            <Link
                                href={
                                    key === 'all'
                                        ? '/account/orders'
                                        : `/account/orders?status=${key}`
                                }
                                preserveScroll
                                aria-current={
                                    filter === key ? 'true' : undefined
                                }
                                className={cn(
                                    'flex h-11 items-center rounded-full border border-line-strong px-4 font-semibold outline-none hover:bg-clay focus-visible:outline-2 focus-visible:outline-ember',
                                    filter === key &&
                                        'border-ink bg-ink text-paper hover:bg-ink',
                                )}
                            >
                                {t(`account.orders.filter.${key}`)}
                            </Link>
                        </li>
                    ))}
                </ul>
            </nav>

            {orders.length === 0 ? (
                <EmptyState
                    title={
                        filter === 'all'
                            ? t('account.orders.empty_title')
                            : t('account.orders.empty_filtered_title')
                    }
                    description={
                        filter === 'all'
                            ? t('account.orders.empty_body')
                            : t('account.orders.empty_filtered_body')
                    }
                    action={
                        filter === 'all' ? (
                            <Button asChild>
                                <Link href="/menu" prefetch>
                                    {t('account.orders.empty_action')}
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />
            ) : (
                <>
                    <OrderList
                        key={`${filter}-${pagination.current_page}`}
                        orders={orders}
                    />

                    {pagination.last_page > 1 && (
                        <nav
                            aria-label={t('account.orders.pagination')}
                            className="flex flex-wrap items-center justify-between gap-3"
                        >
                            <PageLink
                                href={pagination.prev_url}
                                label={t('account.orders.previous')}
                                direction="prev"
                            />
                            <p
                                className="text-sm text-ink-muted"
                                aria-live="polite"
                            >
                                {t('account.orders.page', {
                                    current: pagination.current_page,
                                    last: pagination.last_page,
                                })}
                            </p>
                            <PageLink
                                href={pagination.next_url}
                                label={t('account.orders.next')}
                                direction="next"
                            />
                        </nav>
                    )}
                </>
            )}
        </AccountShell>
    );
}

function PageLink({
    href,
    label,
    direction,
}: {
    href: string | null;
    label: string;
    direction: 'prev' | 'next';
}) {
    const Icon = direction === 'prev' ? ChevronLeft : ChevronRight;
    const content =
        direction === 'prev' ? (
            <>
                <Icon aria-hidden="true" />
                {label}
            </>
        ) : (
            <>
                {label}
                <Icon aria-hidden="true" />
            </>
        );

    if (href === null) {
        return (
            <Button variant="outline" disabled>
                {content}
            </Button>
        );
    }

    return (
        <Button asChild variant="outline">
            <Link href={href} rel={direction} preserveScroll>
                {content}
            </Link>
        </Button>
    );
}
