import {
    Bike,
    Check,
    ShoppingBag,
    TriangleAlert,
    UtensilsCrossed,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Elapsed,
    useElapsedMinutes,
    waitTone,
} from '@/components/staff/elapsed';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { KitchenLine, KitchenTicket } from './types';

const channelIcon = {
    delivery: Bike,
    pickup: ShoppingBag,
    dine_in: UtensilsCrossed,
} as const;

/**
 * One order on the rail. Only the border and header colour change as it
 * waits; nothing moves.
 */
export function TicketCard({
    ticket,
    position,
    selected,
    stationLabel,
    stationValue,
    disabled,
    onBumpLine,
    onBumpTicket,
}: {
    ticket: KitchenTicket;
    position: number;
    selected: boolean;
    stationLabel: string;
    stationValue: string;
    disabled: boolean;
    onBumpLine: (line: KitchenLine) => void;
    onBumpTicket: (ticket: KitchenTicket) => void;
}) {
    const t = useT();
    const minutes = useElapsedMinutes(ticket.accepted_at);
    const tone = waitTone(minutes, ticket.warn_after, ticket.late_after);
    const Icon = channelIcon[ticket.channel];

    const where =
        ticket.channel === 'dine_in'
            ? (ticket.table ?? t('staff.channel.dine_in'))
            : ticket.channel === 'pickup'
              ? ticket.pickup_time
                  ? t('staff_kitchen.ticket.pickup_at', {
                        time: ticket.pickup_time,
                    })
                  : t('staff.channel.pickup')
              : t('staff_kitchen.ticket.delivery');

    const open = ticket.lines.filter((line) => !line.done).length;

    return (
        <article
            aria-label={ticket.number}
            aria-current={selected ? 'true' : undefined}
            className={cn(
                'flex flex-col overflow-hidden rounded-lg border-2 bg-surface transition-colors duration-300',
                tone === 'fresh' && 'border-line-strong',
                tone === 'waiting' && 'border-turmeric',
                tone === 'late' && 'border-chilli',
                selected && 'outline-3 outline-offset-2 outline-ember',
            )}
        >
            <header
                className={cn(
                    'flex items-center justify-between gap-3 px-4 py-3 transition-colors duration-300',
                    tone === 'fresh' && 'bg-clay',
                    tone === 'waiting' && 'bg-turmeric-soft',
                    tone === 'late' && 'bg-chilli-soft',
                )}
            >
                <div className="min-w-0">
                    <div className="flex items-center gap-2">
                        {position < 9 && (
                            <kbd
                                className="grid size-6 place-items-center rounded bg-ink text-xs font-semibold text-paper"
                                aria-label={t('staff_kitchen.ticket.key', {
                                    key: position + 1,
                                })}
                            >
                                {position + 1}
                            </kbd>
                        )}
                        <h2 className="truncate font-display text-xl">
                            {ticket.number}
                        </h2>
                    </div>
                    <p className="mt-1 flex items-center gap-1.5 text-sm font-semibold text-ink-muted">
                        <Icon className="size-4" aria-hidden="true" />
                        {where}
                    </p>
                </div>
                <div
                    className="text-right text-2xl"
                    aria-label={t('staff_kitchen.ticket.waiting', { minutes })}
                >
                    <Elapsed
                        since={ticket.accepted_at}
                        warnAfter={ticket.warn_after}
                        lateAfter={ticket.late_after}
                        className="text-2xl"
                    />
                </div>
            </header>

            {ticket.notes && (
                <p className="border-b border-line bg-turmeric-soft px-4 py-2 text-sm font-semibold text-ink">
                    <span className="sr-only">
                        {t('staff_kitchen.ticket.order_note')}:{' '}
                    </span>
                    {ticket.notes}
                </p>
            )}

            <ul className="flex flex-col divide-y divide-line">
                {ticket.lines.map((line) => (
                    <li key={line.id}>
                        <button
                            type="button"
                            disabled={disabled || line.done}
                            onClick={() => onBumpLine(line)}
                            aria-label={t('staff_kitchen.ticket.bump_line', {
                                dish: line.name,
                            })}
                            className={cn(
                                'flex min-h-16 w-full items-start gap-3 px-4 py-3 text-left outline-hidden focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ember',
                                line.done
                                    ? 'bg-leaf-soft text-ink-muted'
                                    : 'hover:bg-clay active:bg-clay',
                            )}
                        >
                            <span
                                className={cn(
                                    'w-10 shrink-0 text-center font-display text-3xl leading-none tabular-nums',
                                    line.done && 'text-leaf',
                                )}
                            >
                                {line.done ? (
                                    <Check
                                        className="mx-auto size-7"
                                        aria-label={t(
                                            'staff_kitchen.ticket.plated',
                                        )}
                                    />
                                ) : (
                                    line.quantity
                                )}
                            </span>
                            <span className="min-w-0 flex-1">
                                <span
                                    className={cn(
                                        'block text-lg leading-snug font-semibold',
                                        line.done && 'line-through',
                                    )}
                                >
                                    {line.name}
                                </span>
                                {line.variant && (
                                    <span className="block text-sm font-semibold text-ink-muted">
                                        {line.variant}
                                    </span>
                                )}
                                {line.modifiers.length > 0 && (
                                    <span className="block text-sm text-ink-muted">
                                        {line.modifiers.join(' · ')}
                                    </span>
                                )}
                                {line.notes && (
                                    <span
                                        className={cn(
                                            'mt-1 flex items-start gap-1.5 rounded px-2 py-1 text-sm font-semibold',
                                            line.allergy
                                                ? 'bg-chilli text-ember-ink'
                                                : 'bg-turmeric-soft text-ink',
                                        )}
                                    >
                                        {line.allergy && (
                                            <TriangleAlert
                                                className="mt-0.5 size-4 shrink-0"
                                                aria-label={t(
                                                    'staff_kitchen.ticket.allergy',
                                                )}
                                            />
                                        )}
                                        {line.notes}
                                    </span>
                                )}
                            </span>
                        </button>
                    </li>
                ))}
            </ul>

            <div className="mt-auto p-3">
                <Button
                    size="lg"
                    className="h-14 w-full text-base"
                    disabled={disabled || open === 0}
                    onClick={() => onBumpTicket(ticket)}
                >
                    <Check aria-hidden="true" />
                    {stationValue === 'all'
                        ? t('staff_kitchen.ticket.bump_ticket')
                        : t('staff_kitchen.ticket.bump_station', {
                              station: stationLabel,
                          })}
                </Button>
            </div>
        </article>
    );
}
