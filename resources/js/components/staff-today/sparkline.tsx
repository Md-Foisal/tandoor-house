import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import type { HourPoint } from './types';

const W = 320;
const H = 96;
const PAD = { left: 4, right: 76, top: 8, bottom: 20 };

/**
 * Two lines on one scale, labelled at their ends instead of in a legend.
 * Today stops at the current hour; last week runs the whole day.
 */
export function Sparkline({
    points,
    weekday,
}: {
    points: HourPoint[];
    weekday: string;
}) {
    const t = useT();
    const max = Math.max(
        1,
        ...points.flatMap((p) => [p.today ?? 0, p.last_week]),
    );
    const first = points[0]?.hour ?? 0;
    const last = points[points.length - 1]?.hour ?? 1;
    const x = (hour: number) =>
        PAD.left +
        ((hour - first) / (last - first)) * (W - PAD.left - PAD.right);
    const y = (value: number) =>
        PAD.top + (1 - value / max) * (H - PAD.top - PAD.bottom);

    const todayPoints = points.filter((p) => p.today !== null);
    const line = (items: { hour: number; value: number }[]) =>
        items.map((p) => `${x(p.hour)},${y(p.value)}`).join(' ');

    const todaySoFar = todayPoints.reduce((sum, p) => sum + (p.today ?? 0), 0);
    const lastSoFar = points
        .filter((p) => p.today !== null)
        .reduce((sum, p) => sum + p.last_week, 0);
    const peak = todayPoints.reduce<HourPoint | null>(
        (best, p) => ((p.today ?? 0) > (best?.today ?? 0) ? p : best),
        null,
    );

    const summary =
        todaySoFar === 0
            ? t('staff_today.sparkline.summary_empty')
            : t('staff_today.sparkline.summary', {
                  today: formatMoney(todaySoFar),
                  last: formatMoney(lastSoFar),
                  day: weekday,
                  peak: peak
                      ? t('staff_today.sparkline.peak', {
                            hour: t('staff_today.sparkline.hour', {
                                hour: peak.hour,
                            }),
                            amount: formatMoney(peak.today ?? 0),
                        })
                      : '',
              });

    const lastToday = todayPoints[todayPoints.length - 1];
    const lastPoint = points[points.length - 1];

    return (
        <section className="rounded-lg border border-line bg-surface p-4">
            <h2 className="text-title font-semibold">
                {t('staff_today.sparkline.title')}
            </h2>
            <svg
                viewBox={`0 0 ${W} ${H}`}
                role="img"
                aria-label={summary}
                className="mt-2 h-auto w-full"
            >
                <line
                    x1={PAD.left}
                    x2={W - PAD.right}
                    y1={y(0)}
                    y2={y(0)}
                    className="stroke-line"
                    strokeWidth="1"
                />
                {[first, Math.round((first + last) / 2), last].map((hour) => (
                    <text
                        key={hour}
                        x={x(hour)}
                        y={H - 4}
                        textAnchor={hour === first ? 'start' : 'middle'}
                        className="fill-ink-muted text-[9px]"
                    >
                        {hour}:00
                    </text>
                ))}
                <polyline
                    points={line(
                        points.map((p) => ({
                            hour: p.hour,
                            value: p.last_week,
                        })),
                    )}
                    fill="none"
                    className="stroke-ink-muted"
                    strokeWidth="1.5"
                    strokeDasharray="4 3"
                    strokeLinejoin="round"
                />
                {todayPoints.length > 0 && (
                    <polyline
                        points={line(
                            todayPoints.map((p) => ({
                                hour: p.hour,
                                value: p.today ?? 0,
                            })),
                        )}
                        fill="none"
                        className="stroke-ember"
                        strokeWidth="2.5"
                        strokeLinejoin="round"
                        strokeLinecap="round"
                    />
                )}
                {lastToday && (
                    <circle
                        cx={x(lastToday.hour)}
                        cy={y(lastToday.today ?? 0)}
                        r="3.5"
                        className="fill-ember"
                    />
                )}
                {lastToday && (
                    <text
                        x={x(lastToday.hour) + 7}
                        y={y(lastToday.today ?? 0) + 3}
                        className="fill-ink text-[10px] font-semibold"
                    >
                        {t('staff_today.sparkline.today')}
                    </text>
                )}
                {lastPoint && (
                    <text
                        x={x(lastPoint.hour) + 6}
                        y={y(lastPoint.last_week) + 3}
                        className="fill-ink-muted text-[10px]"
                    >
                        {t('staff_today.sparkline.last_week', {
                            day: weekday,
                        })}
                    </text>
                )}
            </svg>
            <p className="mt-1 text-sm text-ink-muted">{summary}</p>
        </section>
    );
}
