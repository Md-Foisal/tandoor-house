import { useForm } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useEffect, useState } from 'react';
import { EmptyState } from '@/components/brand/states';
import { Field } from '@/components/form/field';
import { StatTile } from '@/components/staff/stat-tile';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useT } from '@/lib/i18n';
import type { AttendanceEntry, AttendancePerson, TeamContext } from './types';
import { hours } from './types';

const clockTime = (iso: string) => iso.slice(11, 16);
const clockDay = (iso: string) =>
    new Date(`${iso.slice(0, 10)}T12:00`).toLocaleDateString('en-GB', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });

function defaultOut(iso: string): string {
    const when = new Date(`${iso.slice(0, 16)}`);
    when.setHours(when.getHours() + 8);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${when.getFullYear()}-${pad(when.getMonth() + 1)}-${pad(when.getDate())}T${pad(when.getHours())}:${pad(when.getMinutes())}`;
}

function FixDialog({
    target,
    context,
    onClose,
}: {
    target: { person: AttendancePerson; entry: AttendanceEntry } | null;
    context: TeamContext;
    onClose: () => void;
}) {
    const t = useT();
    const form = useForm({ clocked_out_at: '', reason: '' });
    const { data, setData, errors, processing, clearErrors } = form;

    useEffect(() => {
        if (target) {
            clearErrors();
            setData({
                clocked_out_at: defaultOut(target.entry.in),
                reason: '',
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [target]);

    return (
        <Dialog
            open={target !== null}
            onOpenChange={(next) => !next && onClose()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('staff_team.attendance.fix_title')}
                    </DialogTitle>
                    <DialogDescription>
                        {target &&
                            t('staff_team.attendance.fix_body', {
                                name: target.person.name,
                                when: `${clockDay(target.entry.in)} ${clockTime(target.entry.in)}`,
                            })}
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="grid gap-4"
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(
                            `/staff/team/attendance/${target?.entry.id}`,
                            {
                                preserveScroll: true,
                                only: ['attendance'],
                                onSuccess: onClose,
                            },
                        );
                    }}
                >
                    <Field
                        name="clocked_out_at"
                        label={t('staff_team.attendance.clock_out')}
                        error={errors.clocked_out_at}
                        required
                    >
                        {(p) => (
                            <Input
                                {...p}
                                type="datetime-local"
                                value={data.clocked_out_at}
                                onChange={(e) =>
                                    setData('clocked_out_at', e.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Field
                        name="reason"
                        label={t('staff_team.attendance.reason')}
                        hint={t('staff_team.attendance.reason_hint')}
                        error={errors.reason}
                        required
                    >
                        {(p) => (
                            <Input
                                {...p}
                                maxLength={190}
                                value={data.reason}
                                onChange={(e) =>
                                    setData('reason', e.target.value)
                                }
                            />
                        )}
                    </Field>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            {t('staff_team.attendance.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            size="lg"
                            disabled={processing || !context.online}
                        >
                            {t('staff_team.attendance.fix_save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function AttendanceTab({
    rows,
    context,
}: {
    rows: AttendancePerson[];
    context: TeamContext;
}) {
    const t = useT();
    const [target, setTarget] = useState<{
        person: AttendancePerson;
        entry: AttendanceEntry;
    } | null>(null);
    const sum = (
        key: 'rostered_minutes' | 'clocked_minutes' | 'late' | 'missed',
    ) => rows.reduce((total, row) => total + row[key], 0);

    return (
        <>
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatTile
                    label={t('staff_team.attendance.rostered')}
                    value={t('staff_team.hours', {
                        hours: hours(sum('rostered_minutes')),
                    })}
                />
                <StatTile
                    label={t('staff_team.attendance.clocked')}
                    value={t('staff_team.hours', {
                        hours: hours(sum('clocked_minutes')),
                    })}
                />
                <StatTile
                    label={t('staff_team.attendance.late')}
                    value={sum('late')}
                    hint={t('staff_team.attendance.late_hint')}
                    tone={sum('late') > 0 ? 'warn' : 'plain'}
                />
                <StatTile
                    label={t('staff_team.attendance.missed')}
                    value={sum('missed')}
                    hint={t('staff_team.attendance.missed_hint')}
                    tone={sum('missed') > 0 ? 'bad' : 'plain'}
                />
            </div>

            <div className="flex justify-end">
                <Button asChild variant="outline">
                    <a
                        href={`/staff/team/attendance/export?week=${context.week.start}`}
                    >
                        <Download aria-hidden="true" />
                        {t('staff_team.attendance.export')}
                    </a>
                </Button>
            </div>

            {rows.length === 0 ? (
                <EmptyState
                    title={t('staff_team.attendance.empty_title')}
                    description={t('staff_team.attendance.empty_body')}
                />
            ) : (
                <ul className="grid gap-3">
                    {rows.map((row) => (
                        <li
                            key={row.id}
                            className="grid gap-3 rounded-lg border border-line bg-surface p-4"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p className="font-semibold">{row.name}</p>
                                    <p className="text-sm text-ink-muted">
                                        {t(`roles.${row.role}`)}
                                    </p>
                                </div>
                                <p className="text-sm tabular-nums">
                                    <span className="font-semibold">
                                        {t('staff_team.hours', {
                                            hours: hours(row.clocked_minutes),
                                        })}
                                    </span>{' '}
                                    <span className="text-ink-muted">
                                        /{' '}
                                        {t('staff_team.hours', {
                                            hours: hours(row.rostered_minutes),
                                        })}{' '}
                                        {t(
                                            'staff_team.attendance.rostered',
                                        ).toLowerCase()}
                                    </span>
                                </p>
                            </div>
                            {row.no_shows > 0 && (
                                <Badge variant="turmeric">
                                    {t('staff_team.attendance.no_shows')}:{' '}
                                    {row.no_shows}
                                </Badge>
                            )}
                            <ul className="divide-y divide-line">
                                {row.entries.map((entry) => (
                                    <li
                                        key={entry.id}
                                        className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm"
                                    >
                                        <span className="tabular-nums">
                                            <span className="font-medium">
                                                {clockDay(entry.in)}
                                            </span>{' '}
                                            {clockTime(entry.in)}–
                                            {entry.out
                                                ? clockTime(entry.out)
                                                : t(
                                                      'staff_team.attendance.still_in',
                                                  )}
                                            {entry.shift && (
                                                <span className="text-ink-muted">
                                                    {' '}
                                                    ({entry.shift})
                                                </span>
                                            )}
                                        </span>
                                        <span className="flex flex-wrap items-center gap-2">
                                            {entry.late_minutes > 0 && (
                                                <Badge variant="turmeric">
                                                    {t(
                                                        'staff_team.attendance.late_by',
                                                        {
                                                            count: entry.late_minutes,
                                                        },
                                                    )}
                                                </Badge>
                                            )}
                                            {entry.fixed && (
                                                <Badge variant="secondary">
                                                    {t(
                                                        'staff_team.attendance.fixed',
                                                    )}
                                                </Badge>
                                            )}
                                            {entry.missed ? (
                                                <Button
                                                    type="button"
                                                    variant="destructive"
                                                    size="sm"
                                                    className="min-h-11"
                                                    disabled={!context.online}
                                                    onClick={() =>
                                                        setTarget({
                                                            person: row,
                                                            entry,
                                                        })
                                                    }
                                                >
                                                    {t(
                                                        'staff_team.attendance.fix',
                                                    )}
                                                </Button>
                                            ) : (
                                                <span className="font-semibold tabular-nums">
                                                    {t('staff_team.hours', {
                                                        hours: hours(
                                                            entry.minutes,
                                                        ),
                                                    })}
                                                </span>
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </li>
                    ))}
                </ul>
            )}

            <FixDialog
                target={target}
                context={context}
                onClose={() => setTarget(null)}
            />
        </>
    );
}
