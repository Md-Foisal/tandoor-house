export type ReservationStatus =
    | 'pending_deposit'
    | 'confirmed'
    | 'seated'
    | 'completed'
    | 'no_show'
    | 'cancelled';

export type DepositStatus = 'pending' | 'paid' | 'refunded';

export type BoardTable = {
    id: number;
    label: string;
    seats: number;
    area: string;
};

export type Period = { start: string; end: string };

export type BookingLogEntry = {
    description: string;
    at: string;
    by: string | null;
};

export type Booking = {
    id: number;
    reference: string;
    name: string;
    party_size: number;
    phone: string;
    email: string | null;
    starts_at: string;
    start: string;
    end: string;
    start_minute: number;
    end_minute: number;
    dining_table_id: number | null;
    table_label: string | null;
    status: ReservationStatus;
    deposit: number;
    deposit_status: DepositStatus | null;
    occasion: string | null;
    notes: string | null;
    seated_at: string | null;
    previous_visits: number;
    free_table_ids: number[];
    log: BookingLogEntry[];
};

export type Service = {
    key: 'lunch' | 'dinner';
    from: string;
    to: string;
    bookings: number;
    covers: number;
    walk_in: { tables: number; seats: number } | null;
};

export type WeekDay = {
    date: string;
    weekday: string;
    day: number;
    month: string;
    closed: boolean;
    is_today: boolean;
    bookings: number;
    covers: number;
};

export type Rules = {
    max_party: number;
    deposit_from_party: number;
    deposit_per_head: number;
    duration_minutes: number;
    no_show_minutes: number;
};

export type ReservationsProps = {
    view: 'day' | 'week';
    date: string;
    today: string;
    weekday: string;
    date_label: string;
    prev: string;
    next: string;
    is_today: boolean;
    now: { iso: string; minute: number };
    rules: Rules;
    occasions: string[];
    can_waive_deposit: boolean;
    tables: BoardTable[];
    periods: Period[];
    bookings: Booking[];
    services: Service[];
    week: WeekDay[];
};

export const ACTIVE_STATUSES: ReservationStatus[] = [
    'pending_deposit',
    'confirmed',
    'seated',
];

export function minutesToTime(minutes: number): string {
    const m = ((minutes % 1440) + 1440) % 1440;

    return `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;
}

export function timeToMinutes(time: string): number {
    const [h, m] = time.split(':').map(Number);

    return h * 60 + m;
}
