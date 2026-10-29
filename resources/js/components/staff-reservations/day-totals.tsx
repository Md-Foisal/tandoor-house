import { StatTile } from '@/components/staff/stat-tile';
import { useT, choice } from '@/lib/i18n';
import type { Service } from './types';

/** Covers and bookings per service, plus how much room is left for walk-ins. */
export function DayTotals({ services }: { services: Service[] }) {
    const t = useT();

    return (
        <div className="grid gap-3 sm:grid-cols-2">
            {services.map((service) => (
                <StatTile
                    key={service.key}
                    label={`${t(`staff_reservations.totals.${service.key}`)} · ${service.from}–${service.to}`}
                    value={choice(
                        t('staff_reservations.totals.covers', {
                            count: service.covers,
                        }),
                        service.covers,
                    )}
                    hint={
                        <>
                            <span className="block">
                                {choice(
                                    t('staff_reservations.totals.bookings', {
                                        count: service.bookings,
                                    }),
                                    service.bookings,
                                )}
                            </span>
                            {service.walk_in && (
                                <span className="block">
                                    {service.walk_in.tables > 0
                                        ? t(
                                              'staff_reservations.totals.walk_in',
                                              {
                                                  tables: service.walk_in
                                                      .tables,
                                                  seats: service.walk_in.seats,
                                              },
                                          )
                                        : t(
                                              'staff_reservations.totals.walk_in_none',
                                          )}
                                </span>
                            )}
                        </>
                    }
                    tone={service.walk_in?.tables === 0 ? 'warn' : 'plain'}
                />
            ))}
        </div>
    );
}
