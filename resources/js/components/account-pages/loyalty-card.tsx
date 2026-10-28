import { Gift } from 'lucide-react';
import { Course, Plate } from '@/components/motion/served';
import { choice, useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { formatDate } from './format';
import type { Loyalty } from './types';

export function LoyaltyCard({ loyalty }: { loyalty: Loyalty }) {
    const t = useT();
    const value = formatMoney(loyalty.reward_value, { compact: true });

    return (
        <Course>
            <Plate>
                <section
                    aria-labelledby="loyalty-title"
                    className="flex flex-col gap-5 rounded-lg border border-line bg-surface p-5 md:p-6"
                >
                    <header className="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2
                                id="loyalty-title"
                                className="text-label text-ember"
                            >
                                {t('account.loyalty.title')}
                            </h2>
                            <p className="font-display text-display-xl tabular-nums">
                                {loyalty.points}
                            </p>
                        </div>
                        <p className="max-w-[28ch] text-sm text-ink-muted">
                            {t('account.loyalty.earn')}
                        </p>
                    </header>

                    <div className="flex flex-col gap-2">
                        <div
                            role="progressbar"
                            aria-label={t('account.loyalty.progress_label')}
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-valuenow={loyalty.progress}
                            className="h-3 overflow-hidden rounded-full bg-clay"
                        >
                            <div
                                className="h-full rounded-full bg-ember"
                                style={{ width: `${loyalty.progress}%` }}
                            />
                        </div>
                        <p className="text-sm text-ink-muted">
                            {t('account.loyalty.to_next', {
                                points: choice(
                                    t('account.loyalty.points', {
                                        count: loyalty.points_to_next,
                                    }),
                                    loyalty.points_to_next,
                                ),
                                value,
                            })}
                        </p>
                    </div>

                    {loyalty.rewards_ready > 0 && (
                        <div className="flex items-start gap-3 rounded-md bg-turmeric-soft p-4 text-ink">
                            <Gift
                                className="mt-0.5 size-5 shrink-0 text-turmeric"
                                aria-hidden="true"
                            />
                            <div>
                                <p className="font-semibold">
                                    {choice(
                                        t('account.loyalty.ready', {
                                            count: loyalty.rewards_ready,
                                        }),
                                        loyalty.rewards_ready,
                                    )}
                                </p>
                                <p className="text-sm">
                                    {t('account.loyalty.ready_body', { value })}
                                </p>
                            </div>
                        </div>
                    )}

                    <div>
                        <h3 className="mb-2 text-label text-ink-muted">
                            {t('account.loyalty.recent')}
                        </h3>
                        {loyalty.transactions.length === 0 ? (
                            <p className="text-ink-muted">
                                {t('account.loyalty.recent_empty')}
                            </p>
                        ) : (
                            <ul className="divide-y divide-line">
                                {loyalty.transactions.map((row) => (
                                    <li
                                        key={row.id}
                                        className="flex items-baseline justify-between gap-3 py-2"
                                    >
                                        <span>
                                            {row.reason}
                                            <span className="ml-2 text-sm text-ink-muted">
                                                {formatDate(row.created_at)}
                                            </span>
                                        </span>
                                        <span className="font-semibold tabular-nums">
                                            {row.points > 0 ? '+' : ''}
                                            {row.points}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </section>
            </Plate>
        </Course>
    );
}
