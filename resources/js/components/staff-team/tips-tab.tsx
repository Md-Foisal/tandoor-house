import { Download } from 'lucide-react';
import { EmptyState } from '@/components/brand/states';
import { StatTile } from '@/components/staff/stat-tile';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import type { TeamContext, Tips } from './types';
import { hours } from './types';

export function TipsTab({
    tips,
    context,
}: {
    tips: Tips;
    context: TeamContext;
}) {
    const t = useT();

    return (
        <>
            <div className="grid gap-3 sm:grid-cols-2">
                <StatTile
                    label={t('staff_team.tips.pot')}
                    value={formatMoney(tips.total)}
                    hint={
                        tips.total === 0
                            ? t('staff_team.tips.none')
                            : t('staff_team.tips.pot_hint', {
                                  count: tips.orders,
                              })
                    }
                />
                <StatTile
                    label={t('staff_team.tips.hours')}
                    value={t('staff_team.hours', {
                        hours: hours(tips.minutes),
                    })}
                />
            </div>

            {tips.unfixed > 0 && (
                <p
                    role="alert"
                    className="rounded-md border border-turmeric bg-turmeric-soft p-3 text-sm"
                >
                    {t('staff_team.tips.warning', { count: tips.unfixed })}
                </p>
            )}

            {tips.shares.length === 0 ? (
                <EmptyState
                    title={t('staff_team.tips.empty_title')}
                    description={t('staff_team.tips.empty_body')}
                />
            ) : (
                <div className="grid gap-2">
                    <div className="flex justify-end">
                        <Button asChild variant="outline">
                            <a
                                href={`/staff/team/tips/export?week=${context.week.start}`}
                            >
                                <Download aria-hidden="true" />
                                {t('staff_team.tips.export')}
                            </a>
                        </Button>
                    </div>
                    <table className="w-full text-left">
                        <thead className="text-label text-ink-muted uppercase">
                            <tr className="border-b border-line">
                                <th className="py-2">
                                    {t('staff_team.tips.name')}
                                </th>
                                <th className="py-2 text-right">
                                    {t('staff_team.tips.hours')}
                                </th>
                                <th className="py-2 text-right">
                                    {t('staff_team.tips.share')}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {tips.shares.map((row) => (
                                <tr key={row.id}>
                                    <td className="py-3">
                                        <p className="font-semibold">
                                            {row.name}
                                        </p>
                                        <p className="text-sm text-ink-muted">
                                            {t(`roles.${row.role}`)}
                                        </p>
                                    </td>
                                    <td className="py-3 text-right tabular-nums">
                                        {t('staff_team.hours', {
                                            hours: hours(row.minutes),
                                        })}
                                    </td>
                                    <td className="py-3 text-right font-semibold tabular-nums">
                                        {formatMoney(row.share)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="border-t-2 border-ink/80">
                                <th className="py-3">
                                    {t('staff_team.rota.total')}
                                </th>
                                <td className="py-3 text-right tabular-nums">
                                    {t('staff_team.hours', {
                                        hours: hours(tips.minutes),
                                    })}
                                </td>
                                <td className="py-3 text-right font-semibold tabular-nums">
                                    {formatMoney(
                                        tips.shares.reduce(
                                            (sum, r) => sum + r.share,
                                            0,
                                        ),
                                    )}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            )}

            <section className="grid gap-3 rounded-lg border border-line bg-surface p-4 md:grid-cols-2">
                <div>
                    <h2 className="font-display text-title">
                        {t('staff_team.tips.rule_title')}
                    </h2>
                    <p className="mt-1 text-ink-muted">
                        {t('staff_team.tips.rule')}
                    </p>
                </div>
                <div>
                    <h2 className="font-display text-title">
                        {t('staff_team.tips.policy_title')}
                    </h2>
                    <p className="mt-1 text-ink-muted">
                        {t('staff_team.tips.policy')}
                    </p>
                </div>
            </section>
        </>
    );
}
