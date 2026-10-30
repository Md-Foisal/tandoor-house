export type SectionKey =
    | 'hours'
    | 'holidays'
    | 'zones'
    | 'tax'
    | 'receipts'
    | 'notifications'
    | 'hygiene'
    | 'automations';

export const sectionKeys: SectionKey[] = [
    'hours',
    'holidays',
    'zones',
    'tax',
    'receipts',
    'notifications',
    'hygiene',
    'automations',
];

export type Period = { open: string; close: string };
export type DayHours = { day: number; closed: boolean; periods: Period[] };
export type OpenNow = {
    isOpen: boolean;
    until: string | null;
    opensAt: string | null;
};

export type Zone = {
    id: number;
    name: string;
    prefixes: string[];
    fee: number;
    minimum_order: number;
    eta_minutes: number;
    is_active: boolean;
    orders_count: number;
};

export type Tax = {
    vat_rate: number;
    service_charge_rate: number;
    tip_presets: number[];
    deposit_per_head: number;
    deposit_from_party: number;
};

export type Receipts = {
    footer: string;
    vat_number: string;
    business: {
        name: string;
        legal_name: string;
        address: { line1: string; city: string; postcode: string };
        phone: string;
    };
};

export type Notifications = {
    channels: { mail: boolean; sms: boolean; whatsapp: boolean };
    owner_email: string;
};

export type Hygiene = {
    rating: number | string | null;
    opened_year: number;
    year_now: number;
};

export type SettingsContext = {
    online: boolean;
    canEditMoney: boolean;
};

/** Basis points as a percentage string: 2000 → "20", 1250 → "12.5". */
export function bpsToPercent(bps: number): string {
    return String(Math.round(bps) / 100);
}

export function percentToBps(value: string): number | null {
    const n = Number(value.trim());

    return value.trim() !== '' && Number.isFinite(n) && n >= 0
        ? Math.round(n * 100)
        : null;
}

/** Pence as a pounds string: 249 → "2.49", 1500 → "15". */
export function penceToPounds(pence: number): string {
    return String(Math.round(pence) / 100);
}

export function poundsToPence(value: string): number | null {
    const n = Number(value.trim().replace(/^£/, ''));

    return value.trim() !== '' && Number.isFinite(n) && n >= 0
        ? Math.round(n * 100)
        : null;
}
