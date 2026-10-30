import type { CheckoutForm, CheckoutProps, Zone } from './types';

/** True while the points reward is the discount on this order. */
export function usingReward(form: CheckoutForm, props: CheckoutProps): boolean {
    return form.use_loyalty && (props.summary.loyalty?.discount ?? 0) > 0;
}

/** A reward replaces a promo code, so only one of the two is ever taken off. */
export function discountPence(
    form: CheckoutForm,
    props: CheckoutProps,
): number {
    return usingReward(form, props)
        ? (props.summary.loyalty?.discount ?? 0)
        : props.summary.discount;
}

export function tipPence(form: CheckoutForm, props: CheckoutProps): number {
    const base = props.summary.subtotal - discountPence(form, props);

    if (form.tip_choice === 'custom') {
        const pounds = Number.parseFloat(form.tip_custom.replace(',', '.'));

        return Number.isFinite(pounds) && pounds > 0
            ? Math.min(50000, Math.round(pounds * 100))
            : 0;
    }

    const basisPoints = Number.parseInt(form.tip_choice, 10);

    return Number.isFinite(basisPoints)
        ? Math.floor((base * basisPoints) / 10000)
        : 0;
}

/**
 * Mirrors App\Services\Pricing for the channel being chosen, so totals
 * update instantly. The server recomputes everything when the order is placed.
 */
export function liveTotals(
    form: CheckoutForm,
    props: CheckoutProps,
    zone: Zone | null,
) {
    const { subtotal } = props.summary;
    const discount = discountPence(form, props);
    const base = subtotal - discount;
    const delivery = form.channel === 'delivery' ? (zone?.fee ?? 0) : 0;
    const serviceFull =
        form.channel === 'dine_in'
            ? Math.floor((base * props.serviceChargeRate) / 10000)
            : 0;
    const service = form.remove_service_charge ? 0 : serviceFull;
    const tip = tipPence(form, props);

    return {
        subtotal,
        discount,
        delivery,
        serviceFull,
        service,
        tip,
        vat: usingReward(form, props)
            ? (props.summary.loyalty?.vat_total ?? props.summary.vat_total)
            : props.summary.vat_total,
        total: base + delivery + service + tip,
    };
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export async function postJson<T>(
    url: string,
    body: Record<string, unknown>,
): Promise<{ ok: boolean; status: number; data: T }> {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    });

    return {
        ok: response.ok,
        status: response.status,
        data: (await response.json()) as T,
    };
}

export function stepOfField(field: string, steps: Record<number, string[]>) {
    const root = field.split('.')[0];

    for (const [step, fields] of Object.entries(steps)) {
        if (fields.includes(root)) {
            return Number(step);
        }
    }

    return 2;
}
