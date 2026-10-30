export type TrackedStatus =
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

export type TrackedChannel = 'delivery' | 'pickup' | 'dine_in';

export type TrackedOrder = {
    status: TrackedStatus;
    channel: TrackedChannel;
    steps: string[];
    /** Index of the active step, steps.length when all are done, null when terminal. */
    current: number | null;
    active: boolean;
    terminal: boolean;
    rejection_reason: string | null;
    refund: 'pending' | 'refunded' | null;
    estimated_at: string;
    estimated_label: string;
    rider_name: string | null;
    placed_label: string;
};

export type TrackedDetails = {
    lines: {
        id: number;
        name: string;
        variant_name: string | null;
        modifiers: { name: string; price: number }[];
        notes: string | null;
        quantity: number;
        line_total: number;
    }[];
    totals: {
        subtotal: number;
        discount: number;
        delivery_fee: number;
        service_charge: number;
        tip: number;
        vat_total: number;
        total: number;
    };
    payment: { method: string; status: string };
    address: Record<string, string | null> | null;
    postcode: string | null;
    pickup_label: string | null;
    table: string | null;
    notes: string | null;
};

export type OrderTrackingProps =
    | { denied: true; number: string }
    | {
          denied: false;
          number: string;
          order: TrackedOrder;
          details?: TrackedDetails;
          can: { reorder: boolean; review: boolean; cancel: boolean };
      };
