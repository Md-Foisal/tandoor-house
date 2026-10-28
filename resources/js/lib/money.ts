const gbp = new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
});

const gbpWhole = new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});

/**
 * Formats integer pence as pounds: 1250 → "£12.50". Whole pounds drop the
 * pence on the menu ("£12") the way printed menus do.
 */
export function formatMoney(
    pence: number,
    options: { compact?: boolean } = {},
): string {
    const pounds = pence / 100;

    if (options.compact && pence % 100 === 0) {
        return gbpWhole.format(pounds);
    }

    return gbp.format(pounds);
}
