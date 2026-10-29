import { StatTile } from '@/components/staff/stat-tile';
import { choice, useT } from '@/lib/i18n';
import type { ReviewSummary } from './types';

export function SummaryStrip({ summary }: { summary: ReviewSummary }) {
    const t = useT();
    const max = Math.max(1, ...Object.values(summary.stars));
    const change = summary.change;

    const hint =
        summary.previous_average === null
            ? t('staff_reviews.summary.vs_none')
            : `${
                  change === null || change === 0
                      ? t('staff_reviews.summary.same')
                      : change > 0
                        ? t('staff_reviews.summary.up', {
                              change: change.toFixed(1),
                          })
                        : t('staff_reviews.summary.down', {
                              change: Math.abs(change).toFixed(1),
                          })
              }. ${t('staff_reviews.summary.vs', { previous: summary.previous_average.toFixed(1) })}`;

    return (
        <section
            aria-label={t('staff_reviews.summary.title')}
            className="grid gap-3 md:grid-cols-3"
        >
            <StatTile
                label={t('staff_reviews.summary.average')}
                value={
                    summary.average === null ? (
                        <span className="text-ink-muted">–</span>
                    ) : (
                        <>
                            {summary.average.toFixed(1)}
                            <span className="ml-1 text-base text-ink-muted">
                                {t('staff_reviews.summary.out_of')}
                            </span>
                        </>
                    )
                }
                hint={`${choice(t('staff_reviews.summary.count', { count: summary.count }), summary.count)}. ${hint}`}
                tone={
                    change === null || change === 0
                        ? 'plain'
                        : change > 0
                          ? 'good'
                          : 'bad'
                }
            />

            <div className="rounded-lg border border-line bg-surface p-4">
                <h2 className="text-label text-ink-muted">
                    {t('staff_reviews.summary.by_star')}
                </h2>
                <ul className="mt-2 grid gap-1.5">
                    {[5, 4, 3, 2, 1].map((star) => {
                        const count = summary.stars[String(star)] ?? 0;

                        return (
                            <li
                                key={star}
                                aria-label={t(
                                    'staff_reviews.summary.star_row',
                                    {
                                        stars: star,
                                        count,
                                    },
                                )}
                                className="flex items-center gap-2 text-sm tabular-nums"
                            >
                                <span aria-hidden="true" className="w-3">
                                    {star}
                                </span>
                                <span
                                    aria-hidden="true"
                                    className="h-2 flex-1 rounded-full bg-clay"
                                >
                                    <span
                                        className="block h-full rounded-full bg-turmeric"
                                        style={{
                                            width: `${(count / max) * 100}%`,
                                        }}
                                    />
                                </span>
                                <span
                                    aria-hidden="true"
                                    className="w-6 text-right text-ink-muted"
                                >
                                    {count}
                                </span>
                            </li>
                        );
                    })}
                </ul>
            </div>

            <div className="rounded-lg border border-line bg-surface p-4">
                <h2 className="text-label text-ink-muted">
                    {t('staff_reviews.summary.themes')}
                </h2>
                {summary.themes.length === 0 ? (
                    <p className="mt-2 text-sm text-ink-muted">
                        {t('staff_reviews.summary.themes_none')}
                    </p>
                ) : (
                    <ul className="mt-2 flex flex-wrap gap-2">
                        {summary.themes.map((theme) => (
                            <li
                                key={theme.word}
                                aria-label={t(
                                    'staff_reviews.summary.theme_count',
                                    theme,
                                )}
                                className="inline-flex items-center gap-1.5 rounded-full border border-line-strong bg-paper px-3 py-1 text-sm"
                            >
                                <span aria-hidden="true">{theme.word}</span>
                                <span
                                    aria-hidden="true"
                                    className="font-bold tabular-nums"
                                >
                                    {theme.count}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
                <p className="mt-2 text-xs text-ink-muted">
                    {t('staff_reviews.summary.themes_hint')}
                </p>
            </div>
        </section>
    );
}
