import { router } from '@inertiajs/react';
import { animate } from 'motion/react';
import { useState } from 'react';

export type AddToBasketPayload = {
    menu_item_id: number;
    item_variant_id?: number | null;
    modifier_ids?: number[];
    quantity?: number;
    notes?: string | null;
};

function prefersReducedMotion(): boolean {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function visibleBasketTarget(): Element | null {
    return (
        Array.from(document.querySelectorAll('a[href="/basket"]')).find(
            (element) => element.getBoundingClientRect().width > 0,
        ) ?? null
    );
}

/**
 * A small plate travels from the dish to the basket icon.
 */
export function flyToBasket(origin: Element | null): void {
    const target = visibleBasketTarget();

    if (origin === null || target === null || prefersReducedMotion()) {
        return;
    }

    const from = origin.getBoundingClientRect();
    const to = target.getBoundingClientRect();
    const plate = document.createElement('span');

    plate.setAttribute('aria-hidden', 'true');
    plate.style.cssText = `position:fixed;z-index:60;left:${from.left + from.width / 2 - 14}px;top:${from.top + from.height / 2 - 14}px;width:28px;height:28px;border-radius:9999px;border:2px solid var(--line-strong);background:var(--surface);box-shadow:inset 0 0 0 6px var(--surface),inset 0 0 0 14px var(--ember);pointer-events:none;`;
    document.body.appendChild(plate);

    const dx = to.left + to.width / 2 - (from.left + from.width / 2);
    const dy = to.top + to.height / 2 - (from.top + from.height / 2);

    void animate(
        plate,
        {
            x: [0, dx * 0.5, dx],
            y: [0, Math.min(dy, 0) - 60, dy],
            scale: [1, 0.9, 0.4],
            opacity: [1, 1, 0],
        },
        { duration: 0.55, ease: 'easeOut' },
    ).then(() => plate.remove());
}

/**
 * Adds a dish to the server-side basket, keeping scroll position and
 * sending the plate on its way when the server confirms.
 */
export function useAddToBasket() {
    const [pending, setPending] = useState<number | null>(null);

    const add = (
        payload: AddToBasketPayload,
        origin: Element | null = null,
    ) => {
        setPending(payload.menu_item_id);

        router.post(
            '/basket/lines',
            { quantity: 1, modifier_ids: [], ...payload },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => flyToBasket(origin),
                onFinish: () => setPending(null),
            },
        );
    };

    return { add, pending };
}
