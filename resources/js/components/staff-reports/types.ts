export type ColumnType =
    | 'text'
    | 'int'
    | 'money'
    | 'percent'
    | 'change'
    | 'minutes'
    | 'date'
    | 'rate'
    | 'auto';

export type Cell = string | number | null;

export type ReportTableData = {
    key: string;
    title: string;
    columns: { key: string; label: string; type: ColumnType }[];
    rows: Record<string, Cell>[];
    totals: Record<string, Cell> | null;
    note: string | null;
};

export type Metric = {
    key: string;
    label: string;
    hint: string;
    format: 'money' | 'int';
    value: number;
    previous: number;
    change: number | null;
    direction: 'up' | 'down' | 'none';
};

export type OverviewData = {
    metrics: Metric[];
    previous_label: string;
    tables: ReportTableData[];
};

export type SeriesRow = {
    key: string;
    label: string;
    orders: number;
    sales: number;
};

export type HourRow = {
    hour: number;
    label: string;
    period: 'lunch' | 'dinner' | 'other';
    orders: number;
    sales: number;
};

export type ShareRow = {
    key: string;
    label: string;
    orders: number;
    sales: number;
    share: number;
};

export type BreakdownData = {
    granularity: 'day' | 'week';
    daily: SeriesRow[];
    hours: HourRow[];
    channels: ShareRow[];
    payments: ShareRow[];
    tables: ReportTableData[];
};

export type DishRow = { name: string; quantity: number; revenue: number };

export type CategoryRow = DishRow & { share: number };

export type ItemsData = {
    top_quantity: DishRow[];
    top_revenue: DishRow[];
    slow_quantity: DishRow[];
    slow_revenue: DishRow[];
    categories: CategoryRow[];
    tables: ReportTableData[];
};

export type KitchenRow = {
    key: string;
    label: string;
    orders: number;
    avg_minutes: number;
    late: number;
    late_percent: number;
};

export type KitchenData = {
    overall: KitchenRow;
    stations: KitchenRow[];
    tables: ReportTableData[];
};

export type VatRow = { rate: number; net: number; vat: number; gross: number };

export type VatData = {
    rows: VatRow[];
    totals: { net: number; vat: number; gross: number };
    period: string;
    orders: number;
    tables: ReportTableData[];
};

export type TipsDay = {
    key: string;
    label: string;
    service: number;
    tips: number;
    total: number;
};

export type TipsData = {
    granularity: 'day' | 'week';
    days: TipsDay[];
    totals: { service: number; tips: number; total: number };
    tables: ReportTableData[];
};

export type ReportFilters = {
    period: string;
    from: string;
    to: string;
    channel: string | null;
};

export type ReportLinks = {
    pdf: string;
    export: Record<string, string>;
};

export const chartFill = [
    'fill-chart-1',
    'fill-chart-2',
    'fill-chart-3',
    'fill-chart-4',
    'fill-chart-5',
] as const;

export const chartBg = [
    'bg-chart-1',
    'bg-chart-2',
    'bg-chart-3',
    'bg-chart-4',
    'bg-chart-5',
] as const;
