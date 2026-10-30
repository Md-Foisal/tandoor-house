export type StaffArea =
    | 'today'
    | 'orders'
    | 'kitchen'
    | 'floor'
    | 'reservations'
    | 'deliveries'
    | 'menu'
    | 'inventory'
    | 'team'
    | 'reports'
    | 'reviews'
    | 'settings'
    | 'activity';

export type OrderStatus =
    | 'placed'
    | 'accepted'
    | 'cooking'
    | 'ready'
    | 'out_for_delivery'
    | 'served'
    | 'collected'
    | 'delivered'
    | 'completed'
    | 'rejected'
    | 'cancelled';

export type OrderChannel = 'delivery' | 'pickup' | 'dine_in';
