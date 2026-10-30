import type { InertiaFormProps } from '@inertiajs/react';

export type Channel = 'delivery' | 'pickup' | 'dine_in';

export type PaymentMethod =
    | 'card'
    | 'apple_pay'
    | 'google_pay'
    | 'pay_at_venue'
    | 'cash';

export type CheckoutLine = {
    key: string;
    name: string;
    variant: string | null;
    modifiers: { name: string; price: number }[];
    notes: string | null;
    quantity: number;
    total: number;
    allergens: string[];
    is_available: boolean;
};

export type CheckoutSummary = {
    lines: CheckoutLine[];
    count: number;
    channel: Channel;
    table: number | null;
    promo_code: string | null;
    promo_invalid: boolean;
    has_unavailable: boolean;
    has_alcohol: boolean;
    subtotal: number;
    discount: number;
    delivery_fee: number;
    service_charge: number;
    tip: number;
    vat_total: number;
    total: number;
};

export type PickupData = {
    asap: boolean;
    lead_minutes: number;
    day: string | null;
    slots: { value: string; label: string }[];
};

export type SavedAddress = {
    id: number;
    label: string;
    line1: string;
    line2: string | null;
    city: string;
    postcode: string;
    instructions: string | null;
    is_default: boolean;
};

export type CheckoutProps = {
    summary: CheckoutSummary;
    channels: Channel[];
    channel: Channel;
    table: string | null;
    pickup: PickupData;
    addresses: SavedAddress[];
    contact: {
        name: string;
        email: string;
        phone: string;
        marketing_opt_in: boolean;
    };
    tipPresets: number[];
    paymentDriver: string;
    serviceChargeRate: number;
};

export type Zone = {
    id: number;
    name: string;
    fee: number;
    minimum: number;
    eta_minutes: number;
};

export type PostcodeState =
    | { status: 'idle' }
    | { status: 'checking' }
    | { status: 'invalid'; message: string }
    | { status: 'error'; message: string }
    | { status: 'outside'; message: string }
    | {
          status: 'covered';
          zone: Zone;
          shortBy: number;
          message: string | null;
      };

export type CheckoutForm = {
    channel: Channel;
    name: string;
    email: string;
    phone: string;
    marketing_opt_in: boolean;
    address_id: number | null;
    line1: string;
    line2: string;
    city: string;
    postcode: string;
    instructions: string;
    /** '' until chosen, 'asap', or an ISO time. */
    pickup_choice: string;
    /** Basis points of the bill, or 'custom'. */
    tip_choice: string;
    tip_custom: string;
    payment_method: PaymentMethod;
    age_confirmed: boolean;
    remove_service_charge: boolean;
};

export type CheckoutFormApi = InertiaFormProps<CheckoutForm>;

export const STEP_FIELDS: Record<number, string[]> = {
    0: ['channel', 'postcode', 'pickup_at'],
    1: [
        'name',
        'email',
        'phone',
        'line1',
        'line2',
        'city',
        'instructions',
        'marketing_opt_in',
    ],
    2: ['tip', 'payment_method', 'age_confirmed', 'cart'],
};
