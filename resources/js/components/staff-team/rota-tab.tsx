import { router } from '@inertiajs/react';
import { AlertTriangle, CopyPlus, Plus } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/brand/states';
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
import { cn } from '@/lib/utils';
import type { ShiftDraft } from './shift-dialog';
import { ShiftDialog } from './shift-dialog';
import type { Rota, ShiftBlock, TeamContext } from './types';
import { hours } from './types';

function Block({
    shift,
    onOpen,
    label,
}: {
    shift: ShiftBlock;
    onOpen: () => void;
    label?: string;
}) {
    const t = useT();

    return (
        <button
            type="button"
            onClick={onOpen}
            className={cn(
                'flex min-h-11 w-full flex-col rounded-md border px-2 py-1 text-left text-sm tabular-nums',
                shift.outside_hours
                    ? 'border-turmeric bg-turmeric-soft'
                    : 'border-line-strong bg-surface hover:bg-clay',
            )}
        >
            {label && <span className="font-semibold">{label}</span>}
            <span className="font-semibold">
                {shift.start}–{shift.end}
            </span>
            <span className="text-xs text-ink-muted">
                {t(`roles.${shift.role}`)}
                {shift.outside_hours && (
                    <>
                        {' · '}
                        <AlertTriangle
                            className="inline size-3 text-turmeric"
                            aria-hidden="true"
                        />{' '}
                        {t('staff_team.rota.outside')}
                    </>
                )}
            </span>
        </button>
    );
}

export function RotaTab({
    rota,
    weekStart,
    context,
}: {
    rota: Rota;
    weekStart: string;
    context: TeamContext;
}) {
    const t = useT();
    const [draft, setDraft] = useState<ShiftDraft | null>(null);
    const [copying, setCopying] = useState(false);
    const [busy, setBusy] = useState(false);
    const empty = rota.people.every((p) => p.minutes === 0);

    const copy = () => {
        setBusy(true);
        router.post(
            '/staff/team/shifts/copy-week',
            { week: weekStart },
            {
                preserveScroll: true,
                only: ['rota'],
                onSuccess: () => setCopying(false),
                onFinish: () => setBusy(false),
            },
        );
    };

    const addFor = (userId: number | null, date: string) =>
        setDraft({ shift: null, userId, date });
    const dayMinutes = (index: number) =>
        rota.people.reduce(
            (sum, p) =>
                sum +
                p.days[index].reduce(
                    (inner, s) => inner + span(s.start, s.end),
                    0,
                ),
            0,
        );

    return (
        <>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <Button
                    type="button"
                    variant="outline"
                    disabled={!context.online}
                    onClick={() => setCopying(true)}
                >
                    <CopyPlus aria-hidden="true" />
                    {t('staff_team.rota.copy')}
                </Button>
                <Button
                    type="button"
                    size="lg"
                    disabled={!context.online}
                    onClick={() =>
                        addFor(
                            null,
                            context.week.is_current ? today(rota) : weekStart,
                        )
                    }
                >
                    <Plus aria-hidden="true" />
                    {t('staff_team.rota.add_shift')}
                </Button>
            </div>

            {empty && (
                <EmptyState
                    title={t('staff_team.rota.empty_title')}
                    description={t('staff_team.rota.empty_body')}
                />
            )}

            {!empty && (
                <>
                    <div className="hidden overflow-x-auto md:block">
                        <table className="w-full min-w-[56rem] border-separate border-spacing-0 text-left">
                            <thead>
                                <tr>
                                    <th className="sticky left-0 z-20 w-40 border-b border-line bg-paper py-2 pr-3 text-label text-ink-muted uppercase">
                                        {t('staff_team.rota.person')}
                                    </th>
                                    {rota.days.map((day) => (
                                        <th
                                            key={day.date}
                                            className={cn(
                                                'border-b border-line px-1 py-2 align-top',
                                                day.is_today && 'bg-ember-soft',
                                            )}
                                        >
                                            <p className="font-semibold">
                                                {day.label}{' '}
                                                <span className="font-normal text-ink-muted">
                                                    {day.day}
                                                </span>
                                            </p>
                                            <p className="text-xs font-normal text-ink-muted">
                                                {day.periods.length
                                                    ? day.periods.join(', ')
                                                    : t(
                                                          'staff_team.rota.closed',
                                                      )}
                                            </p>
                                            <p className="text-xs font-normal text-ink-muted">
                                                {t('staff_team.rota.covers', {
                                                    count: day.covers,
                                                })}
                                            </p>
                                        </th>
                                    ))}
                                    <th className="border-b border-line py-2 pl-3 text-right text-label text-ink-muted uppercase">
                                        {t('staff_team.rota.total')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {rota.people.map((person) => (
                                    <tr key={person.id}>
                                        <th className="sticky left-0 z-10 border-b border-line bg-paper py-2 pr-3 align-top">
                                            <p className="font-semibold">
                                                {person.name}
                                            </p>
                                            <p className="text-xs font-normal text-ink-muted">
                                                {t(`roles.${person.role}`)}
                                            </p>
                                        </th>
                                        {rota.days.map((day, index) => (
                                            <td
                                                key={day.date}
                                                className="border-b border-line px-1 py-1.5 align-top"
                                            >
                                                <div className="grid gap-1">
                                                    {person.days[index].map(
                                                        (shift) => (
                                                            <Block
                                                                key={shift.id}
                                                                shift={shift}
                                                                onOpen={() =>
                                                                    setDraft({
                                                                        shift,
                                                                        userId: person.id,
                                                                        date: day.date,
                                                                    })
                                                                }
                                                            />
                                                        ),
                                                    )}
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="min-h-11 w-full text-ink-muted"
                                                        disabled={
                                                            !context.online
                                                        }
                                                        onClick={() =>
                                                            addFor(
                                                                person.id,
                                                                day.date,
                                                            )
                                                        }
                                                    >
                                                        <Plus aria-hidden="true" />
                                                        <span className="sr-only">
                                                            {t(
                                                                'staff_team.rota.add_for',
                                                                {
                                                                    name: person.name,
                                                                    day: `${day.label} ${day.day}`,
                                                                },
                                                            )}
                                                        </span>
                                                    </Button>
                                                </div>
                                            </td>
                                        ))}
                                        <td className="border-b border-line py-2 pl-3 text-right font-semibold whitespace-nowrap tabular-nums">
                                            {t('staff_team.hours', {
                                                hours: hours(person.minutes),
                                            })}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th className="sticky left-0 bg-paper py-2 pr-3">
                                        {t('staff_team.rota.total')}
                                    </th>
                                    {rota.days.map((day, index) => (
                                        <td
                                            key={day.date}
                                            className="px-1 py-2 text-sm font-semibold tabular-nums"
                                        >
                                            {t('staff_team.hours', {
                                                hours: hours(dayMinutes(index)),
                                            })}
                                        </td>
                                    ))}
                                    <td />
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div
                        role="region"
                        aria-label={t('staff_team.rota.agenda')}
                        className="grid gap-4 md:hidden"
                    >
                        {rota.days.map((day, index) => {
                            const shifts = rota.people.flatMap((person) =>
                                person.days[index].map((shift) => ({
                                    person,
                                    shift,
                                })),
                            );

                            return (
                                <section
                                    key={day.date}
                                    className="grid gap-2 rounded-lg border border-line bg-surface p-3"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <h3 className="font-display text-title">
                                                {day.label} {day.day}
                                            </h3>
                                            <p className="text-xs text-ink-muted">
                                                {day.periods.length
                                                    ? day.periods.join(', ')
                                                    : t(
                                                          'staff_team.rota.closed',
                                                      )}
                                                {' · '}
                                                {t('staff_team.rota.covers', {
                                                    count: day.covers,
                                                })}
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="min-h-11"
                                            disabled={!context.online}
                                            onClick={() =>
                                                addFor(null, day.date)
                                            }
                                        >
                                            <Plus aria-hidden="true" />
                                            {t('staff_team.rota.add_shift')}
                                        </Button>
                                    </div>
                                    {shifts.length === 0 ? (
                                        <p className="text-sm text-ink-muted">
                                            {t('staff_team.rota.nobody')}
                                        </p>
                                    ) : (
                                        <ul className="grid gap-2">
                                            {shifts.map(({ person, shift }) => (
                                                <li key={shift.id}>
                                                    <Block
                                                        shift={shift}
                                                        label={person.name}
                                                        onOpen={() =>
                                                            setDraft({
                                                                shift,
                                                                userId: person.id,
                                                                date: day.date,
                                                            })
                                                        }
                                                    />
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </section>
                            );
                        })}
                    </div>
                </>
            )}

            <ShiftDialog
                draft={draft}
                people={rota.people}
                context={context}
                onClose={() => setDraft(null)}
            />

            <Dialog open={copying} onOpenChange={setCopying}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('staff_team.rota.copy_title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('staff_team.rota.copy_body')}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setCopying(false)}
                        >
                            {t('staff_team.shift.cancel')}
                        </Button>
                        <Button
                            type="button"
                            size="lg"
                            disabled={busy || !context.online}
                            onClick={copy}
                        >
                            {t('staff_team.rota.copy_confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function span(start: string, end: string): number {
    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = end.split(':').map(Number);
    const minutes = eh * 60 + em - (sh * 60 + sm);

    return minutes <= 0 ? minutes + 1440 : minutes;
}

function today(rota: Rota): string {
    return (rota.days.find((d) => d.is_today) ?? rota.days[0]).date;
}
