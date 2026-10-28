import { Check } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { Stamp } from '@/components/motion/served';
import { useT } from '@/lib/i18n';
import { spring } from '@/lib/motion';
import { cn } from '@/lib/utils';

export type TicketStep = {
    key: string;
    label: string;
    detail?: string;
};

/**
 * Progress that moves like a kitchen ticket: finished steps are stamped,
 * the active step lifts off the rail.
 */
export function TicketSteps({
    steps,
    current,
    orientation = 'vertical',
    className,
}: {
    steps: TicketStep[];
    current: number;
    orientation?: 'vertical' | 'horizontal';
    className?: string;
}) {
    const reduced = useReducedMotion();
    const t = useT();
    const horizontal = orientation === 'horizontal';

    return (
        <ol
            className={cn(
                'flex',
                horizontal ? 'flex-row gap-2' : 'flex-col gap-2',
                className,
            )}
        >
            {steps.map((step, index) => {
                const done = index < current;
                const active = index === current;

                return (
                    <motion.li
                        key={step.key}
                        aria-current={active ? 'step' : undefined}
                        animate={active && !reduced ? { y: -3 } : { y: 0 }}
                        transition={spring.ticket}
                        className={cn(
                            'relative flex items-center gap-3 rounded-md border px-3.5 py-3',
                            horizontal && 'flex-1 flex-col items-start gap-2',
                            active && 'border-ember bg-surface',
                            done && 'border-line bg-surface',
                            !active &&
                                !done &&
                                'border-dashed border-line-strong/60 text-ink-muted',
                        )}
                    >
                        <motion.span
                            aria-hidden="true"
                            className="pointer-events-none absolute inset-0 rounded-[inherit] shadow-lift"
                            initial={false}
                            animate={{ opacity: active ? 1 : 0 }}
                            transition={{ duration: 0.2 }}
                        />
                        <span
                            className={cn(
                                'grid size-8 shrink-0 place-items-center rounded-full border-[1.5px] text-sm font-bold tabular-nums',
                                active && 'border-ember text-ember',
                                done && 'border-leaf text-leaf',
                                !active && !done && 'border-line-strong/60',
                            )}
                        >
                            {done ? (
                                <Stamp>
                                    <Check
                                        className="size-4"
                                        strokeWidth={3}
                                        aria-hidden="true"
                                    />
                                </Stamp>
                            ) : (
                                index + 1
                            )}
                        </span>
                        <span className="flex min-w-0 flex-col">
                            <span
                                className={cn(
                                    'font-semibold',
                                    active && 'text-ink',
                                )}
                            >
                                {step.label}
                            </span>
                            <span className="sr-only">
                                {t(
                                    done
                                        ? 'ui.step_done'
                                        : active
                                          ? 'ui.step_current'
                                          : 'ui.step_upcoming',
                                )}
                            </span>
                            {step.detail && (
                                <span className="text-sm text-ink-muted">
                                    {step.detail}
                                </span>
                            )}
                        </span>
                    </motion.li>
                );
            })}
        </ol>
    );
}
