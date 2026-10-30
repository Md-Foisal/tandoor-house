import { Cake, Gift, Briefcase, PartyPopper } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import type { Booking, ReservationStatus } from './types';

export function statusVariant(
    status: ReservationStatus,
): 'default' | 'secondary' | 'destructive' | 'leaf' | 'turmeric' {
    switch (status) {
        case 'pending_deposit':
            return 'turmeric';
        case 'confirmed':
            return 'default';
        case 'seated':
            return 'leaf';
        case 'no_show':
            return 'destructive';
        default:
            return 'secondary';
    }
}

export function StatusBadge({ status }: { status: ReservationStatus }) {
    const t = useT();

    return (
        <Badge variant={statusVariant(status)}>
            {t(`staff_reservations.status.${status}`)}
        </Badge>
    );
}

export function OccasionIcon({ occasion }: { occasion: string | null }) {
    const props = { className: 'size-3.5 shrink-0', 'aria-hidden': true };

    switch (occasion) {
        case 'birthday':
            return <Cake {...props} />;
        case 'anniversary':
            return <PartyPopper {...props} />;
        case 'business':
            return <Briefcase {...props} />;
        case 'other':
            return <Gift {...props} />;
        default:
            return null;
    }
}

/** Text for the deposit state, or null when no deposit applies. */
export function useDepositLabel() {
    const t = useT();

    return (
        booking: Pick<Booking, 'deposit' | 'deposit_status'>,
    ): ReactNode => {
        if (booking.deposit <= 0) {
            return null;
        }

        const amount = formatMoney(booking.deposit);

        return booking.deposit_status === 'paid'
            ? t('staff_reservations.deposit.paid', { amount })
            : t('staff_reservations.deposit.pending', { amount });
    };
}
