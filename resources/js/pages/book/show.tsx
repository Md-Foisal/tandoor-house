import { Head, Link, usePage } from '@inertiajs/react';
import { motion, useReducedMotion } from 'motion/react';
import { CreditCard, Phone } from 'lucide-react';
import { useState } from 'react';
import { EmberLine } from '@/components/brand/ember-line';
import { Settle } from '@/components/motion/served';
import { HoldTimer } from '@/components/booking/hold-timer';
import { CancelBooking } from '@/components/booking/cancel-booking';
import type { ShowProps } from '@/components/booking/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { choice, useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { create } from '@/routes/book';
import { index as menu } from '@/routes/menu';

const draw = (delay: number) => ({
    hidden: { pathLength: 0 },
    shown: {
        pathLength: 1,
        transition: { duration: 0.6, delay, ease: [0.22, 1, 0.36, 1] as const },
    },
});

function Fork({ onDone }: { onDone?: () => void }) {
    return (
        <motion.svg
            viewBox="0 0 24 160"
            aria-hidden="true"
            className="h-40 w-6 shrink-0 text-line-strong sm:w-8"
            initial="hidden"
            animate="shown"
        >
            <motion.path
                d="M4 6v30c0 7 3 11 8 11s8-4 8-11V6M12 6v41M12 47v107"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                variants={draw(0)}
                onAnimationComplete={onDone}
            />
        </motion.svg>
    );
}

function Knife() {
    return (
        <motion.svg
            viewBox="0 0 24 160"
            aria-hidden="true"
            className="h-40 w-6 shrink-0 text-line-strong sm:w-8"
            initial="hidden"
            animate="shown"
        >
            <motion.path
                d="M8 154V70M8 70C8 30 12 10 18 6c3 22 2 48-2 64H8"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                variants={draw(0.15)}
            />
        </motion.svg>
    );
}

function Row({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-0.5 border-b border-line py-3 last:border-0 sm:grid-cols-[7rem_1fr] sm:gap-4">
            <dt className="text-label text-ink-muted">{label}</dt>
            <dd className="font-medium text-ink">{children}</dd>
        </div>
    );
}

export default function BookShow({ reservation, cancel, pay }: ShowProps) {
    const t = useT();
    const reduced = useReducedMotion();
    const { restaurant } = usePage().props;
    const [laid, setLaid] = useState(reduced === true);
    const pending = reservation.status === 'pending_deposit';
    const tel = restaurant.phone.replace(/\s+/g, '');
    const party = choice(
        t('booking.confirmation.party_count', {
            count: reservation.party_size,
        }),
        reservation.party_size,
    );
    const statusKey = `booking.confirmation.status_${reservation.status}`;

    const deposit =
        reservation.deposit === 0
            ? t('booking.confirmation.deposit_none')
            : reservation.deposit_status === 'paid'
              ? t('booking.confirmation.deposit_paid', {
                    amount: formatMoney(reservation.deposit, { compact: true }),
                })
              : reservation.deposit_status === 'pending'
                ? t('booking.confirmation.deposit_pending', {
                      amount: formatMoney(reservation.deposit, {
                          compact: true,
                      }),
                  })
                : t('booking.confirmation.deposit_other', {
                      amount: formatMoney(reservation.deposit, {
                          compact: true,
                      }),
                  });

    return (
        <>
            <Head title={t('booking.confirmation.title')} />

            <div className="mx-auto w-full max-w-[1200px] px-4 pt-8 pb-16 md:px-6 lg:px-10">
                <header className="mx-auto mb-8 grid max-w-xl gap-2 text-center">
                    <h1 className="font-display text-display-lg">
                        {pending
                            ? t('booking.confirmation.heading_pending')
                            : t('booking.confirmation.heading_confirmed')}
                    </h1>
                    <EmberLine draw className="mx-auto w-24" />
                    <p className="text-body-lg text-ink-muted">
                        {pending
                            ? t('booking.confirmation.sub_pending', {
                                  name: reservation.name,
                              })
                            : t('booking.confirmation.sub_confirmed', {
                                  name: reservation.name,
                              })}
                    </p>
                </header>

                <div className="mx-auto flex max-w-xl items-center justify-center gap-2 sm:gap-4">
                    <Fork onDone={() => setLaid(true)} />

                    <Settle
                        initial="hidden"
                        animate={laid ? 'shown' : 'hidden'}
                        className="w-full max-w-md"
                    >
                        <section
                            aria-label={t('booking.confirmation.card_label', {
                                name: reservation.name,
                                party,
                                date: reservation.date_label,
                                time: reservation.time,
                            })}
                            className="rounded-arch border border-line bg-surface px-5 pt-14 pb-6 shadow-plate sm:px-8"
                        >
                            <div className="mb-4 grid justify-items-center gap-2 text-center">
                                <Badge variant={pending ? 'turmeric' : 'leaf'}>
                                    {t(statusKey)}
                                </Badge>
                                <p className="text-label text-ink-muted">
                                    {t('booking.confirmation.reference')}
                                </p>
                                <p className="font-display text-display-md tracking-[0.12em]">
                                    {reservation.reference}
                                </p>
                            </div>
                            <dl>
                                <Row label={t('booking.confirmation.date')}>
                                    {reservation.date_label}
                                </Row>
                                <Row label={t('booking.confirmation.time')}>
                                    <span className="tabular-nums">
                                        {reservation.time}
                                    </span>
                                    <span className="block text-sm font-normal text-ink-muted">
                                        {t('booking.confirmation.time_until', {
                                            end: reservation.ends_at,
                                        })}
                                    </span>
                                </Row>
                                <Row label={t('booking.confirmation.party')}>
                                    {party}
                                </Row>
                                <Row label={t('booking.confirmation.area')}>
                                    {reservation.area ??
                                        t('booking.confirmation.area_pending')}
                                </Row>
                                {reservation.occasion && (
                                    <Row
                                        label={t(
                                            'booking.confirmation.occasion',
                                        )}
                                    >
                                        {reservation.occasion}
                                    </Row>
                                )}
                                {reservation.notes && (
                                    <Row
                                        label={t('booking.confirmation.notes')}
                                    >
                                        {reservation.notes}
                                    </Row>
                                )}
                                <Row label={t('booking.confirmation.deposit')}>
                                    {deposit}
                                </Row>
                            </dl>
                        </section>
                    </Settle>

                    <Knife />
                </div>

                <div className="mx-auto mt-8 grid max-w-md gap-4">
                    {pending && pay && (
                        <div className="grid gap-2">
                            {pay.expired ? (
                                <p className="text-sm text-ink-muted">
                                    {t('booking.pay.expired_body')}
                                </p>
                            ) : (
                                <>
                                    <Button
                                        asChild
                                        size="lg"
                                        className="h-12 w-full"
                                    >
                                        <Link href={pay.url}>
                                            <CreditCard aria-hidden="true" />
                                            {t('booking.confirmation.pay')}
                                        </Link>
                                    </Button>
                                    <HoldTimer expiresAt={pay.expires_at} />
                                </>
                            )}
                        </div>
                    )}
                    <p className="text-sm text-ink-muted">
                        {t('booking.confirmation.policy', {
                            hours: cancel.hours,
                        })}{' '}
                        {t('booking.confirmation.late', {
                            minutes: reservation.no_show_minutes,
                        })}
                    </p>
                    <p className="flex flex-wrap items-center gap-x-2 text-sm text-ink">
                        <Phone className="size-4" aria-hidden="true" />
                        <span>
                            {t('booking.confirmation.change', {
                                phone: restaurant.phone,
                            })}
                        </span>
                        <a
                            href={`tel:${tel}`}
                            className="inline-flex min-h-11 items-center font-semibold text-ember underline underline-offset-4"
                        >
                            {restaurant.phone}
                        </a>
                    </p>
                    <div className="flex flex-wrap gap-3">
                        {cancel.allowed && (
                            <CancelBooking
                                reference={reservation.reference}
                                terms={cancel}
                                summary={{
                                    party,
                                    date: reservation.date_label,
                                    time: reservation.time,
                                }}
                            />
                        )}
                        <Button asChild variant="outline">
                            <Link href={menu()}>
                                {t('booking.confirmation.see_menu')}
                            </Link>
                        </Button>
                        <Button asChild variant="ghost">
                            <Link href={create()}>
                                {t('booking.confirmation.book_another')}
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}
