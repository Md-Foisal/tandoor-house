import type { HTMLMotionProps, Variants } from 'motion/react';
import {
    animate,
    motion,
    useInView,
    useMotionValue,
    useReducedMotion,
    useTransform,
} from 'motion/react';
import type { ReactNode } from 'react';
import { Children, useEffect, useRef } from 'react';
import {
    courseStagger,
    ease,
    fadeVariants,
    inView,
    plateVariants,
    spring,
} from '@/lib/motion';
import { cn } from '@/lib/utils';

/*
 * "Served from the pass": each pattern moves the way its real counterpart
 * does in a restaurant. Every element carries data-reveal so a <noscript>
 * rule in the document head keeps content visible without JavaScript.
 */

type DivProps = Omit<HTMLMotionProps<'div'>, 'children'> & {
    children?: ReactNode;
};

function useVariants(full: Variants): Variants {
    return useReducedMotion() ? fadeVariants : full;
}

/** A dish card rising onto the table; its shadow arrives last. */
export function Plate({ children, className, ...props }: DivProps) {
    const variants = useVariants(plateVariants);
    const reduced = useReducedMotion();

    return (
        <motion.div
            data-reveal
            variants={variants}
            className={cn('relative', className)}
            {...props}
        >
            {!reduced && (
                <motion.span
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 rounded-[inherit] shadow-plate"
                    variants={{
                        hidden: { opacity: 0 },
                        shown: {
                            opacity: 1,
                            transition: {
                                delay: 0.12,
                                duration: 0.3,
                                ease: ease.out,
                            },
                        },
                    }}
                />
            )}
            {children}
        </motion.div>
    );
}

/**
 * A group served course by course: children land one after another in
 * reading order, the whole group within 600ms. Direct children should be
 * <Plate> or another variant-aware element.
 */
export function Course({ children, className, ...props }: DivProps) {
    const count = Children.count(children);

    return (
        <motion.div
            data-reveal
            initial="hidden"
            whileInView="shown"
            viewport={inView}
            variants={{
                hidden: {},
                shown: {
                    transition: { staggerChildren: courseStagger(count) },
                },
            }}
            className={className}
            {...props}
        >
            {children}
        </motion.div>
    );
}

/** A single element served on its own when it scrolls into view. */
export function Serve({ children, className, ...props }: DivProps) {
    const variants = useVariants(plateVariants);

    return (
        <motion.div
            data-reveal
            initial="hidden"
            whileInView="shown"
            viewport={inView}
            variants={variants}
            className={className}
            {...props}
        >
            {children}
        </motion.div>
    );
}

/** Place cards on a table, notes in a guest book: lands askew, then straightens. */
export function Settle({
    children,
    tilt = -2,
    className,
    ...props
}: DivProps & { tilt?: number }) {
    const variants = useVariants({
        hidden: { opacity: 0, y: 12, rotate: tilt },
        shown: { opacity: 1, y: 0, rotate: 0, transition: spring.plate },
    });

    return (
        <motion.div
            data-reveal
            variants={variants}
            className={className}
            {...props}
        >
            {children}
        </motion.div>
    );
}

/** A photograph developing like a print; its caption writes in after. */
export function PhotoPrint({
    children,
    caption,
    className,
}: {
    children: ReactNode;
    caption?: ReactNode;
    className?: string;
}) {
    const reduced = useReducedMotion();

    return (
        <motion.figure
            data-reveal
            initial="hidden"
            whileInView="shown"
            viewport={inView}
            className={className}
        >
            <motion.div
                data-reveal
                className="relative"
                variants={
                    reduced
                        ? fadeVariants
                        : {
                              hidden: {
                                  opacity: 0.2,
                                  filter: 'saturate(0.3) brightness(1.15)',
                              },
                              shown: {
                                  opacity: 1,
                                  filter: 'saturate(1) brightness(1)',
                                  transition: { duration: 0.7, ease: ease.out },
                              },
                          }
                }
            >
                {children}
            </motion.div>
            {caption && (
                <motion.figcaption
                    data-reveal
                    className="mt-2 text-sm text-ink-muted italic"
                    variants={
                        reduced
                            ? fadeVariants
                            : {
                                  hidden: {
                                      opacity: 0,
                                      clipPath: 'inset(0 100% 0 0)',
                                  },
                                  shown: {
                                      opacity: 1,
                                      clipPath: 'inset(0 0% 0 0)',
                                      transition: {
                                          delay: 0.55,
                                          duration: 0.6,
                                          ease: 'linear',
                                      },
                                  },
                              }
                    }
                >
                    {caption}
                </motion.figcaption>
            )}
        </motion.figure>
    );
}

/** A door opening onto the dining room: the frame uncovers from a soft crop. */
export function DoorReveal({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    const variants = useVariants({
        hidden: { opacity: 0.4, clipPath: 'inset(0% 14% 0% 14%)' },
        shown: {
            opacity: 1,
            clipPath: 'inset(0% 0% 0% 0%)',
            transition: { duration: 0.6, ease: ease.out },
        },
    });

    return (
        <motion.div
            data-reveal
            initial="hidden"
            whileInView="shown"
            viewport={inView}
            variants={variants}
            className={className}
        >
            {children}
        </motion.div>
    );
}

/** A table marker dropped onto the map. */
export function MarkerDrop({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    const variants = useVariants({
        hidden: { opacity: 0, y: -16 },
        shown: { opacity: 1, y: 0, transition: spring.ticket },
    });

    return (
        <motion.div
            data-reveal
            initial="hidden"
            whileInView="shown"
            viewport={inView}
            variants={variants}
            className={className}
        >
            {children}
        </motion.div>
    );
}

/** A stamp pressed onto a finished ticket step. */
export function Stamp({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    const reduced = useReducedMotion();

    return (
        <motion.span
            data-reveal
            className={cn('inline-grid place-items-center', className)}
            initial={
                reduced
                    ? { opacity: 0 }
                    : { opacity: 0, scale: 1.4, rotate: -8 }
            }
            animate={
                reduced ? { opacity: 1 } : { opacity: 1, scale: 1, rotate: -8 }
            }
            transition={reduced ? { duration: 0.15 } : spring.ticket}
        >
            {children}
        </motion.span>
    );
}

/** Numbers count up like a receipt total. */
export function CountUp({
    to,
    format = (value) => Math.round(value).toLocaleString('en-GB'),
    className,
}: {
    to: number;
    format?: (value: number) => string;
    className?: string;
}) {
    const ref = useRef<HTMLSpanElement>(null);
    const seen = useInView(ref, inView);
    const reduced = useReducedMotion();
    const value = useMotionValue(reduced ? to : 0);
    const text = useTransform(value, format);

    useEffect(() => {
        if (!seen || reduced) {
            return;
        }

        const controls = animate(value, to, { duration: 1.1, ease: ease.out });

        return () => controls.stop();
    }, [seen, reduced, to, value]);

    return (
        <motion.span ref={ref} className={cn('tabular-nums', className)}>
            {text}
        </motion.span>
    );
}

/** One faint wisp of steam rising once from a hot dish. */
export function SteamWisp({ className }: { className?: string }) {
    const reduced = useReducedMotion();

    if (reduced) {
        return null;
    }

    return (
        <motion.svg
            viewBox="0 0 24 48"
            aria-hidden="true"
            className={cn('pointer-events-none h-12 w-6 text-paper', className)}
            initial={{ opacity: 0, y: 8 }}
            whileInView={{ opacity: [0, 0.55, 0], y: -24 }}
            viewport={inView}
            transition={{ duration: 2.2, ease: 'easeOut', delay: 0.4 }}
        >
            <path
                d="M12 46c-5-6 5-10 0-17s5-11 0-18S9 3 12 1"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
            />
        </motion.svg>
    );
}
