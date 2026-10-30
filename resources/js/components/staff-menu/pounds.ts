/** "8.50" to 850. Returns null for anything that is not a sensible amount. */
export function toPence(pounds: string): number | null {
    const text = pounds.trim().replace(/^£/, '');

    if (!/^\d{1,4}(\.\d{1,2})?$/.test(text)) {
        return null;
    }

    return Math.round(Number(text) * 100);
}

/** 850 to "8.50". */
export function toPounds(pence: number): string {
    return (pence / 100).toFixed(2);
}
