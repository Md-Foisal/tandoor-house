import type { PaymentPanelProps } from '@/components/payments/types';

export type Slot = { time: string; available: boolean };

export type DayStatus = 'open' | 'closed' | 'late';

export type Day = { date: string; status: DayStatus };

export type Rules = {
    max_party: number;
    deposit_from_party: number;
    deposit_per_head: number;
    duration_minutes: number;
    no_show_minutes: number;
};

export type Contact = {
    name: string;
    email: string;
    phone: string;
    marketing_opt_in: boolean;
};

export type BookingForm = Contact & {
    party_size: number;
    date: string;
    time: string;
    occasion: string;
    notes: string;
};

export type CreateProps = {
    rules: Omit<Rules, 'no_show_minutes'> & { no_show_minutes: number };
    days: Day[];
    query: { party: number; date: string | null };
    slots: Slot[];
    contact: Contact;
    occasions: string[];
};

export type ReservationView = {
    reference: string;
    name: string;
    party_size: number;
    date_label: string;
    time: string;
    ends_at: string;
    starts_at: string;
    area: string | null;
    no_show_minutes: number;
    status:
        | 'pending_deposit'
        | 'confirmed'
        | 'seated'
        | 'completed'
        | 'no_show'
        | 'cancelled';
    deposit: number;
    deposit_status: string | null;
    occasion: string | null;
    notes: string | null;
};

export type CancelTerms = {
    allowed: boolean;
    free: boolean;
    hours: number;
    deadline_label: string;
    deposit_paid: number;
};

export type PendingPay = {
    url: string;
    expires_at: string;
    expired: boolean;
};

export type ShowProps = {
    reservation: ReservationView;
    cancel: CancelTerms;
    pay: PendingPay | null;
};

export type PayProps = {
    reservation: {
        reference: string;
        party_size: number;
        date_label: string;
        time: string;
        deposit: number;
    };
    payment: PaymentPanelProps | null;
    declined: string | null;
    hold: { expires_at: string; expired: boolean };
    urls: { booking: string; new: string };
};
