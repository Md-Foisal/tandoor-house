import { formatMoney } from '@/lib/money';
import type { Cell, ColumnType } from './types';

const dateFormat = new Intl.DateTimeFormat('en-GB', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
});

/** "2026-10-26" shown as "Mon 26 Oct" without a timezone shift. */
export function formatDay(value: string): string {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

    if (!match) {
        return value;
    }

    return dateFormat.format(
        new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])),
    );
}

export function formatPercent(value: number, digits = 1): string {
    return `${value.toFixed(digits)}%`;
}

export function formatSigned(value: number): string {
    return `${value > 0 ? '+' : value < 0 ? '−' : ''}${Math.abs(value).toFixed(1)}%`;
}

/** Short axis labels: £0, £250, £1.2k. */
export function formatAxis(pence: number): string {
    const pounds = pence / 100;

    if (pounds >= 1000) {
        return `£${(pounds / 1000).toFixed(pounds % 1000 === 0 ? 0 : 1)}k`;
    }

    return `£${Math.round(pounds)}`;
}

export function formatCell(
    value: Cell | undefined,
    type: ColumnType,
    row: Record<string, Cell> = {},
): string {
    if (value === null || value === undefined || value === '') {
        return '–';
    }

    const kind =
        type === 'auto' ? (row._format === 'money' ? 'money' : 'int') : type;

    switch (kind) {
        case 'money':
            return formatMoney(Number(value));
        case 'int':
            return Number(value).toLocaleString('en-GB');
        case 'percent':
            return formatPercent(Number(value));
        case 'change':
            return formatSigned(Number(value));
        case 'minutes':
            return Number(value).toFixed(1);
        case 'date':
            return formatDay(String(value));
        case 'rate':
            return Number.isFinite(Number(value))
                ? `${Number(value) / 100}%`
                : String(value);
        default:
            return String(value);
    }
}

/** A round upper bound for an axis: 1, 2, 2.5, 5 times a power of ten. */
export function niceMax(value: number): number {
    if (value <= 0) {
        return 100;
    }

    const power = 10 ** Math.floor(Math.log10(value));
    const fraction = value / power;
    const step = [1, 2, 2.5, 5, 10].find((s) => fraction <= s) ?? 10;

    return step * power;
}
