import type { Unit } from './types';

const big: Record<Unit, string | null> = { g: 'kg', ml: 'l', pc: null };

/** 12500 g → "12.5 kg"; below 1000 the base unit is shown whole. */
export function formatQuantity(quantity: number, unit: Unit): string {
    const abs = Math.abs(quantity);
    const sign = quantity < 0 ? '−' : '';
    const bigUnit = big[unit];

    if (bigUnit && abs >= 1000) {
        return `${sign}${(abs / 1000).toLocaleString('en-GB', {
            minimumFractionDigits: 1,
            maximumFractionDigits: 1,
        })} ${bigUnit}`;
    }

    return `${sign}${abs.toLocaleString('en-GB')} ${unit}`;
}

/** The units a person can type a quantity in, with their size in base units. */
export function unitChoices(unit: Unit): { label: string; factor: number }[] {
    const bigUnit = big[unit];

    return bigUnit
        ? [
              { label: unit, factor: 1 },
              { label: bigUnit, factor: 1000 },
          ]
        : [{ label: unit, factor: 1 }];
}

/** Text typed in the chosen unit → whole base units, or null when it is not a usable number. */
export function toBase(text: string, factor: number): number | null {
    const value = Number.parseFloat(text.replace(',', '.'));

    if (!Number.isFinite(value) || value < 0) {
        return null;
    }

    return Math.round(value * factor);
}

/** Pence per 1,000 base units, as pounds per kg, litre or 1,000 pieces. */
export function poundsFromCost(cost: number): string {
    return (cost / 100).toFixed(2);
}

export function costFromPounds(text: string): number | null {
    const value = Number.parseFloat(text.replace(',', '.'));

    return Number.isFinite(value) && value >= 0
        ? Math.round(value * 100)
        : null;
}

export function formatMargin(margin: number | null): string {
    return margin === null
        ? '–'
        : `${margin.toLocaleString('en-GB', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`;
}
