import { useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Field } from '@/components/form/field';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { router } from '@inertiajs/react';
import { useT } from '@/lib/i18n';
import type { RotaPerson, ShiftBlock, TeamContext } from './types';

const roles = ['waiter', 'cashier', 'kitchen', 'rider', 'manager', 'owner'];

export type ShiftDraft = {
    shift: ShiftBlock | null;
    userId: number | null;
    date: string;
};

type Data = {
    user_id: string;
    date: string;
    start: string;
    end: string;
    role: string;
    notes: string;
};

export function ShiftDialog({
    draft,
    people,
    context,
    onClose,
}: {
    draft: ShiftDraft | null;
    people: RotaPerson[];
    context: TeamContext;
    onClose: () => void;
}) {
    const t = useT();
    const form = useForm<Data>({
        user_id: '',
        date: '',
        start: '17:00',
        end: '22:00',
        role: 'waiter',
        notes: '',
    });
    const { data, setData, errors, processing, clearErrors } = form;
    const [confirming, setConfirming] = useState(false);
    const shift = draft?.shift ?? null;

    useEffect(() => {
        if (!draft) {
            return;
        }

        clearErrors();
        setConfirming(false);
        const person = people.find(
            (p) => p.id === (shift?.user_id ?? draft.userId),
        );
        setData({
            user_id: String(shift?.user_id ?? draft.userId ?? ''),
            date: shift?.date ?? draft.date,
            start: shift?.start ?? '17:00',
            end: shift?.end ?? '22:00',
            role: shift?.role ?? person?.role ?? 'waiter',
            notes: shift?.notes ?? '',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [draft]);

    const options = {
        preserveScroll: true,
        only: ['rota'],
        onSuccess: onClose,
    };
    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (shift) {
            form.patch(`/staff/team/shifts/${shift.id}`, options);
        } else {
            form.post('/staff/team/shifts', options);
        }
    };
    const remove = () =>
        shift &&
        router.delete(`/staff/team/shifts/${shift.id}`, {
            preserveScroll: true,
            only: ['rota'],
            onSuccess: onClose,
        });
    const person = people.find((p) => String(p.id) === data.user_id);

    return (
        <Dialog
            open={draft !== null}
            onOpenChange={(next) => !next && onClose()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {shift
                            ? t('staff_team.shift.edit_title')
                            : t('staff_team.shift.add_title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('staff_team.shift.end_hint')}
                    </DialogDescription>
                </DialogHeader>
                {confirming && shift ? (
                    <div className="grid gap-4">
                        <p>
                            {t('staff_team.shift.delete_body', {
                                name: person?.name ?? '',
                                when: `${shift.date} ${shift.start}–${shift.end}`,
                            })}
                        </p>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setConfirming(false)}
                            >
                                {t('staff_team.shift.cancel')}
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                size="lg"
                                disabled={!context.online}
                                onClick={remove}
                            >
                                {t('staff_team.shift.delete')}
                            </Button>
                        </DialogFooter>
                    </div>
                ) : (
                    <form className="grid gap-4" onSubmit={submit} noValidate>
                        <Field
                            name="user_id"
                            label={t('staff_team.shift.person')}
                            error={errors.user_id}
                            required
                        >
                            {(p) => (
                                <Select
                                    value={data.user_id}
                                    onValueChange={(v) => setData('user_id', v)}
                                >
                                    <SelectTrigger {...p} className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {people
                                            .filter(
                                                (row) =>
                                                    row.can_roster ||
                                                    String(row.id) ===
                                                        data.user_id,
                                            )
                                            .map((row) => (
                                                <SelectItem
                                                    key={row.id}
                                                    value={String(row.id)}
                                                >
                                                    {row.name}
                                                </SelectItem>
                                            ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </Field>
                        <Field
                            name="date"
                            label={t('staff_team.shift.date')}
                            error={errors.date}
                            required
                        >
                            {(p) => (
                                <Input
                                    {...p}
                                    type="date"
                                    value={data.date}
                                    onChange={(e) =>
                                        setData('date', e.target.value)
                                    }
                                />
                            )}
                        </Field>
                        <div className="grid grid-cols-2 gap-3">
                            <Field
                                name="start"
                                label={t('staff_team.shift.start')}
                                error={errors.start}
                                required
                            >
                                {(p) => (
                                    <Input
                                        {...p}
                                        type="time"
                                        step={300}
                                        value={data.start}
                                        onChange={(e) =>
                                            setData('start', e.target.value)
                                        }
                                    />
                                )}
                            </Field>
                            <Field
                                name="end"
                                label={t('staff_team.shift.end')}
                                error={errors.end}
                                required
                            >
                                {(p) => (
                                    <Input
                                        {...p}
                                        type="time"
                                        step={300}
                                        value={data.end}
                                        onChange={(e) =>
                                            setData('end', e.target.value)
                                        }
                                    />
                                )}
                            </Field>
                        </div>
                        <Field
                            name="role"
                            label={t('staff_team.shift.role')}
                            error={errors.role}
                            required
                        >
                            {(p) => (
                                <Select
                                    value={data.role}
                                    onValueChange={(v) => setData('role', v)}
                                >
                                    <SelectTrigger {...p} className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {roles.map((role) => (
                                            <SelectItem key={role} value={role}>
                                                {t(`roles.${role}`)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </Field>
                        <Field
                            name="notes"
                            label={t('staff_team.shift.notes')}
                            error={errors.notes}
                        >
                            {(p) => (
                                <Input
                                    {...p}
                                    maxLength={190}
                                    value={data.notes}
                                    onChange={(e) =>
                                        setData('notes', e.target.value)
                                    }
                                />
                            )}
                        </Field>
                        <DialogFooter className="sm:justify-between">
                            {shift ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="text-chilli"
                                    onClick={() => setConfirming(true)}
                                >
                                    <Trash2 aria-hidden="true" />
                                    {t('staff_team.shift.delete')}
                                </Button>
                            ) : (
                                <span />
                            )}
                            <div className="flex flex-col-reverse gap-2 sm:flex-row">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={onClose}
                                >
                                    {t('staff_team.shift.cancel')}
                                </Button>
                                <Button
                                    type="submit"
                                    size="lg"
                                    disabled={processing || !context.online}
                                >
                                    {t('staff_team.shift.save')}
                                </Button>
                            </div>
                        </DialogFooter>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}
