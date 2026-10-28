import type { Transition, Variants } from 'motion/react';

/*
 * Motion tokens. Documented in docs/design-system.md → Motion.
 */
export const duration = {
    fast: 0.15,
    base: 0.2,
    slow: 0.25,
} as const;

export const ease = {
    out: [0.22, 1, 0.36, 1],
    inOut: [0.65, 0, 0.35, 1],
} as const;

export const spring = {
    plate: { type: 'spring', stiffness: 260, damping: 24, mass: 0.9 },
    ticket: { type: 'spring', stiffness: 420, damping: 32 },
} as const satisfies Record<string, Transition>;

export const stagger = {
    course: 0.09,
    groupMax: 0.6,
} as const;

export const inView = {
    once: true,
    amount: 0.2,
} as const;

export const reducedFade: Transition = {
    duration: duration.fast,
    ease: 'linear',
};

/**
 * Stagger for a group, shrunk so the whole group lands within groupMax.
 */
export function courseStagger(count: number): number {
    if (count <= 1) {
        return 0;
    }

    return Math.min(stagger.course, stagger.groupMax / (count - 1));
}

export const plateVariants: Variants = {
    hidden: { opacity: 0, y: 32, scale: 0.96 },
    shown: { opacity: 1, y: 0, scale: 1, transition: spring.plate },
};

export const fadeVariants: Variants = {
    hidden: { opacity: 0 },
    shown: { opacity: 1, transition: reducedFade },
};

export const uiTransition: Transition = {
    duration: duration.base,
    ease: ease.out,
};
