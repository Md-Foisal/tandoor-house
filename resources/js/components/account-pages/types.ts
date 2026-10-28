export type OrderLineSummary = { name: string; quantity: number };

export type Loyalty = {
    points: number;
    reward_points: number;
    reward_value: number;
    rewards_ready: number;
    points_to_next: number;
    progress: number;
    transactions: {
        id: number;
        points: number;
        reason: string;
        created_at: string;
    }[];
};

export type ActiveOrder = {
    number: string;
    status: string;
    channel: string;
    total: number;
    placed_at: string;
};

export type RecentOrder = {
    number: string;
    placed_at: string;
    total: number;
    lines: OrderLineSummary[];
};

export type Promo = {
    code: string;
    description: string;
    minimum_order: number;
    ends_at: string | null;
};

export type UpcomingReservation = {
    reference: string;
    starts_at: string;
    party_size: number;
    status: string;
};

export type DefaultAddress = {
    label: string;
    line1: string;
    line2: string | null;
    city: string;
    postcode: string;
};

export type AccountProps = {
    firstName: string;
    isStaff: boolean;
    loyalty: Loyalty;
    activeOrders: ActiveOrder[];
    recentOrders: RecentOrder[];
    promos: Promo[];
    reservations: UpcomingReservation[];
    address: DefaultAddress | null;
};

export type HistoryOrder = {
    number: string;
    status: string;
    channel: string;
    placed_at: string;
    total: number;
    lines: OrderLineSummary[];
};

export type OrderFilter = 'all' | 'active' | 'completed' | 'cancelled';

export type Pagination = {
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_url: string | null;
    next_url: string | null;
};

export type OrdersProps = {
    filter: OrderFilter;
    orders: HistoryOrder[];
    pagination: Pagination;
};
