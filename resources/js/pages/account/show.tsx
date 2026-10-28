import { Head, Link } from '@inertiajs/react';
import {
    ActiveOrders,
    AddressSummary,
    PromoWallet,
    RecentOrders,
    Reservations,
} from '@/components/account-pages/overview-blocks';
import { LoyaltyCard } from '@/components/account-pages/loyalty-card';
import type { AccountProps } from '@/components/account-pages/types';
import { AccountShell } from '@/components/account/account-nav';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';

export default function AccountShow({
    firstName,
    isStaff,
    loyalty,
    activeOrders,
    recentOrders,
    promos,
    reservations,
    address,
}: AccountProps) {
    const t = useT();

    return (
        <AccountShell title={t('account.overview.title', { name: firstName })}>
            <Head title={t('account.meta.title')} />
            <p className="-mt-2 max-w-[60ch] text-ink-muted">
                {t('account.overview.lede')}
            </p>

            {isStaff && (
                <div
                    role="note"
                    className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-line bg-clay p-4"
                >
                    <p>{t('account.overview.staff_note')}</p>
                    <Button asChild>
                        <Link href="/staff">
                            {t('account.overview.staff_action')}
                        </Link>
                    </Button>
                </div>
            )}

            <LoyaltyCard loyalty={loyalty} />
            <ActiveOrders orders={activeOrders} />
            <RecentOrders orders={recentOrders} />
            <PromoWallet promos={promos} />
            <Reservations reservations={reservations} />
            <AddressSummary address={address} />
        </AccountShell>
    );
}
