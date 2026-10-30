import type { Role } from '@/types';

export type TeamTab = 'people' | 'rota' | 'attendance' | 'tips';

export type WeekInfo = {
    start: string;
    label: string;
    previous: string;
    next: string;
    is_current: boolean;
};

export type Person = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: Role;
    on_shift: boolean;
    minutes: number;
    can_edit: boolean;
    can_remove: boolean;
    is_me: boolean;
};

export type ShiftBlock = {
    id: number;
    user_id: number;
    date: string;
    start: string;
    end: string;
    role: Role;
    notes: string | null;
    outside_hours: boolean;
};

export type RotaDay = {
    date: string;
    label: string;
    day: string;
    is_today: boolean;
    periods: string[];
    covers: number;
};

export type RotaPerson = {
    id: number;
    name: string;
    role: Role;
    can_roster: boolean;
    minutes: number;
    days: ShiftBlock[][];
};

export type Rota = { days: RotaDay[]; people: RotaPerson[] };

export type AttendanceEntry = {
    id: number;
    in: string;
    out: string | null;
    minutes: number;
    open: boolean;
    missed: boolean;
    fixed: boolean;
    late_minutes: number;
    shift: string | null;
};

export type AttendancePerson = {
    id: number;
    name: string;
    email: string;
    role: Role;
    rostered_minutes: number;
    clocked_minutes: number;
    late: number;
    missed: number;
    no_shows: number;
    entries: AttendanceEntry[];
};

export type TipShare = {
    id: number;
    name: string;
    role: Role;
    minutes: number;
    share: number;
};

export type TipRecordRow = {
    id: number;
    name: string;
    role: Role;
    minutes: number;
    amount: number;
    live: number;
};

export type TipRecording = {
    status: 'none' | 'recorded' | 'paid';
    recorded_at: string | null;
    recorded_by: string | null;
    paid_at: string | null;
    rows: TipRecordRow[];
    total: number;
    differs: boolean;
    week_over: boolean;
    available_on: string;
    can_record: boolean;
    can_pay: boolean;
};

export type Tips = {
    recording: TipRecording;
    total: number;
    orders: number;
    minutes: number;
    unfixed: number;
    shares: TipShare[];
};

export type TeamContext = {
    me: { id: number; role: Role };
    assignableRoles: Role[];
    week: WeekInfo;
    online: boolean;
    reload: () => void;
};

export function hours(minutes: number): string {
    return (minutes / 60).toFixed(1).replace(/\.0$/, '');
}

export function shortDate(iso: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'Europe/London',
    }).format(new Date(iso));
}
