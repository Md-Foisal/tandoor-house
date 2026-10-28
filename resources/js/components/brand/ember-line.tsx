import { motion, useReducedMotion } from 'motion/react';
import { cn } from '@/lib/utils';

/**
 * A hand-drawn underline in ember, slightly uneven like a brush stroke.
 * With `draw`, it draws itself in once when it scrolls into view.
 */
export function EmberLine({
    className,
    draw = false,
}: {
    className?: string;
    draw?: boolean;
}) {
    const reduced = useReducedMotion();
    const animate = draw && !reduced;

    return (
        <motion.svg
            viewBox="0 0 120 8"
            preserveAspectRatio="none"
            aria-hidden="true"
            className={cn('h-2 w-16 text-ember', className)}
            initial={animate ? 'hidden' : false}
            whileInView={animate ? 'shown' : undefined}
            viewport={{ once: true, amount: 0.5 }}
        >
            <motion.path
                d="M2 5.2C18 3.1 31 2.6 46 3.6c13 .9 22 2 37 1.4 12-.5 22-1.8 35-2.6"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.6"
                strokeLinecap="round"
                variants={{
                    hidden: { pathLength: 0 },
                    shown: {
                        pathLength: 1,
                        transition: { duration: 0.5, ease: [0.22, 1, 0.36, 1] },
                    },
                }}
            />
        </motion.svg>
    );
}
