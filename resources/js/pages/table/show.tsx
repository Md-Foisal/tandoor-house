import { Deferred, Head } from '@inertiajs/react';
import { EmberLine } from '@/components/brand/ember-line';
import { MenuErrorBoundary } from '@/components/menu-page/menu-error-boundary';
import { OrderBar } from '@/components/table/order-bar';
import { SentOrders } from '@/components/table/sent-orders';
import { TableMenu, TableMenuSkeleton } from '@/components/table/table-menu';
import type { TableShowProps } from '@/components/table/types';
import { useT } from '@/lib/i18n';
import { events, useLive } from '@/lib/realtime';

const POLL_MS = 15_000;

export default function TableShow({
    table,
    categories,
    basket,
    service_rate: serviceRate,
    orders,
    requests,
}: TableShowProps) {
    const t = useT();
    useLive(['orders', 'requests'], {
        channel: null,
        events: [events.tableRequestRaised],
        fallbackMs: POLL_MS,
    });

    return (
        <>
            <Head title={t('table.page_title', { table: table.label })} />

            <div className="container-page flex flex-col gap-2 pt-6 pb-4 md:pt-10">
                <p className="text-sm font-semibold tracking-wide text-ember uppercase">
                    {t('table.banner', {
                        table: table.label,
                        area: table.area,
                    })}
                </p>
                <h1 className="font-display text-display-lg text-ink">
                    {t('table.menu_label')}
                </h1>
                <EmberLine />
                <p className="max-w-[56ch] text-ink-muted">
                    {t('table.banner_note')}
                </p>
            </div>

            <div className="pb-56 md:pb-44">
                <MenuErrorBoundary title={t('menu.error.title')}>
                    <Deferred
                        data="categories"
                        fallback={<TableMenuSkeleton />}
                    >
                        <TableMenu categories={categories ?? []} />
                    </Deferred>
                </MenuErrorBoundary>

                <div className="container-page">
                    <SentOrders orders={orders} token={table.token} />
                </div>
            </div>

            <OrderBar
                token={table.token}
                basket={basket}
                serviceRate={serviceRate}
                requests={requests}
            />
        </>
    );
}
