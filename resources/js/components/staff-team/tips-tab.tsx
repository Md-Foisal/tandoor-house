import { router } from '@inertiajs/react';
import { AlertTriangle, Download, Lock } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/brand/states';
import { StatTile } from '@/components/staff/stat-tile';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import type { TeamContext, TipRecording, Tips } from './types';
import { hours, shortDate } from './types';

export function TipsTab({
    tips,
    context,
}: {
    tips: Tips;
    context: TeamContext;
}) {
    const t = useT();
    const rec = tips.recording;
    const [confirm, setConfirm] = useState<'record' | 'pay' | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const submit = (kind: 'record' | 'pay') => {
        setBusy(true);
        setError(null);
        router.post(
            `/staff/team/tips/${kind}`,
            { week: context.week.start },
            {
                preserveScroll: true,
                only: ['tips'],
                onSuccess: () => setConfirm(null),
                onError: (errors) => {
                    setConfirm(null);
                    setError(errors.week ?? Object.values(errors)[0] ?? null);
                },
                onFinish: () => setBusy(false),
            },
        );
    };

    const recordBlocked = !rec.can_record
        ? rec.status === 'paid'
            ? null
            : t('staff_team.tips.record_wait', {
                  date: shortDate(rec.available_on),
              })
        : null;
    const canAct = context.online && !busy;
    const showRecorded = rec.status !== 'none';

    return (
        <>
            <section
                aria-labelledby="tips-record"
                className="grid gap-3 rounded-lg border border-line bg-surface p-4 sm:grid-cols-[1fr_auto] sm:items-center"
            >
                <div>
                    <h2
                        id="tips-record"
                        className="flex items-center gap-2 font-display text-title"
                    >
                        {t(`staff_team.tips.status_${rec.status}`)}
                        {rec.status === 'paid' && (
                            <Lock
                                aria-hidden="true"
                                className="size-4 text-ink-muted"
                            />
                        )}
                    </h2>
                    <p className="mt-1 text-sm text-ink-muted">
                        {rec.status === 'none' &&
                            (recordBlocked ?? t('staff_team.tips.record_body'))}
                        {rec.status === 'recorded' &&
                            rec.recorded_at &&
                            t('staff_team.tips.recorded_by', {
                                name: rec.recorded_by ?? '',
                                date: shortDate(rec.recorded_at),
                            })}
                        {rec.status === 'paid' &&
                            rec.paid_at &&
                            t('staff_team.tips.paid_on', {
                                date: shortDate(rec.paid_at),
                            })}
                    </p>
                    {!context.online && (
                        <p className="mt-1 text-sm text-ink-muted">
                            {t('staff_team.tips.offline')}
                        </p>
                    )}
                </div>
                <div className="flex flex-wrap gap-2">
                    {rec.status !== 'paid' && (
                        <Button
                            type="button"
                            size="lg"
                            variant={
                                rec.status === 'none' ? 'default' : 'outline'
                            }
                            disabled={!canAct || !rec.can_record}
                            onClick={() => setConfirm('record')}
                        >
                            {rec.status === 'none'
                                ? t('staff_team.tips.record')
                                : t('staff_team.tips.record_again')}
                        </Button>
                    )}
                    {rec.status === 'recorded' && (
                        <Button
                            type="button"
                            size="lg"
                            disabled={!canAct || !rec.can_pay}
                            onClick={() => setConfirm('pay')}
                            title={
                                rec.can_pay
                                    ? undefined
                                    : t('staff_team.tips.owner_only')
                            }
                        >
                            {t('staff_team.tips.pay')}
                        </Button>
                    )}
                </div>
                {rec.status === 'recorded' && !rec.can_pay && (
                    <p className="text-sm text-ink-muted sm:col-span-2">
                        {t('staff_team.tips.owner_only')}
                    </p>
                )}
            </section>

            {error && (
                <p
                    role="alert"
                    className="rounded-md border border-chilli bg-chilli-soft p-3 text-sm"
                >
                    {error}
                </p>
            )}

            {showRecorded && rec.differs && (
                <p
                    role="status"
                    className="flex items-start gap-2 rounded-md border border-turmeric bg-turmeric-soft p-3 text-sm"
                >
                    <AlertTriangle
                        aria-hidden="true"
                        className="mt-0.5 size-4 shrink-0"
                    />
                    <span>
                        <strong>{t('staff_team.tips.changed_title')}.</strong>{' '}
                        {rec.status === 'paid'
                            ? t('staff_team.tips.changed_locked')
                            : t('staff_team.tips.changed_body')}
                    </span>
                </p>
            )}

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

            {showRecorded && <RecordedTable rec={rec} context={context} />}

            {showRecorded ? null : tips.shares.length === 0 ||
              tips.total === 0 ? (
                <EmptyState
                    title={
                        tips.shares.length === 0
                            ? t('staff_team.tips.empty_title')
                            : t('staff_team.tips.no_tips_title')
                    }
                    description={
                        tips.shares.length === 0
                            ? t('staff_team.tips.empty_body')
                            : t('staff_team.tips.no_tips_body')
                    }
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
            <Dialog
                open={confirm !== null}
                onOpenChange={(open) => !open && setConfirm(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t(`staff_team.tips.${confirm ?? 'record'}_title`)}
                        </DialogTitle>
                        <DialogDescription>
                            {t(`staff_team.tips.${confirm ?? 'record'}_body`)}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setConfirm(null)}
                        >
                            {t('staff_team.tips.cancel')}
                        </Button>
                        <Button
                            type="button"
                            size="lg"
                            disabled={!canAct}
                            onClick={() => confirm && submit(confirm)}
                        >
                            {confirm === 'pay'
                                ? t('staff_team.tips.pay_confirm')
                                : t('staff_team.tips.record')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function RecordedTable({
    rec,
    context,
}: {
    rec: TipRecording;
    context: TeamContext;
}) {
    const t = useT();

    return (
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
                        <th className="py-2">{t('staff_team.tips.name')}</th>
                        <th className="py-2 text-right">
                            {t('staff_team.tips.hours')}
                        </th>
                        <th className="py-2 text-right">
                            {t('staff_team.tips.recorded_share')}
                        </th>
                        {rec.differs && (
                            <th className="py-2 text-right">
                                {t('staff_team.tips.live_share')}
                            </th>
                        )}
                    </tr>
                </thead>
                <tbody className="divide-y divide-line">
                    {rec.rows.map((row) => (
                        <tr key={row.id}>
                            <td className="py-3">
                                <p className="font-semibold">{row.name}</p>
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
                                {formatMoney(row.amount)}
                            </td>
                            {rec.differs && (
                                <td
                                    className={`py-3 text-right tabular-nums ${row.live === row.amount ? 'text-ink-muted' : 'font-semibold text-chilli'}`}
                                >
                                    {formatMoney(row.live)}
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr className="border-t-2 border-ink/80">
                        <th className="py-3">{t('staff_team.rota.total')}</th>
                        <td />
                        <td className="py-3 text-right font-semibold tabular-nums">
                            {formatMoney(rec.total)}
                        </td>
                        {rec.differs && <td />}
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}
