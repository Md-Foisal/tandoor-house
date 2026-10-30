import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
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
import { useT } from '@/lib/i18n';
import type { Role } from '@/types';
import type { Person, TeamContext } from './types';

type Data = { name: string; email: string; phone: string; role: string };

/** Adds a team member, or edits phone and role when given a person. */
export function StaffFormDialog({
    open,
    person,
    context,
    onClose,
}: {
    open: boolean;
    person: Person | null;
    context: TeamContext;
    onClose: () => void;
}) {
    const t = useT();
    const form = useForm<Data>({
        name: '',
        email: '',
        phone: '',
        role: 'waiter',
    });
    const { data, setData, errors, processing, reset, clearErrors } = form;
    const editing = person !== null;

    useEffect(() => {
        if (open) {
            clearErrors();
            setData({
                name: person?.name ?? '',
                email: person?.email ?? '',
                phone: person?.phone ?? '',
                role: person?.role ?? 'waiter',
            });
        } else {
            reset();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, person?.id]);

    const roles: Role[] = context.assignableRoles.includes(data.role as Role)
        ? context.assignableRoles
        : [data.role as Role, ...context.assignableRoles];
    const locked = editing && person.is_me;
    const options = {
        preserveScroll: true,
        only: ['people'],
        onSuccess: onClose,
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (editing) {
            form.transform((d) => ({ phone: d.phone, role: d.role }));
            form.patch(`/staff/team/people/${person.id}`, options);
        } else {
            form.transform((d) => d);
            form.post('/staff/team/people', options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {editing
                            ? t('staff_team.form.edit_title', {
                                  name: person.name,
                              })
                            : t('staff_team.form.add_title')}
                    </DialogTitle>
                    <DialogDescription>
                        {editing ? person.email : t('staff_team.form.add_body')}
                    </DialogDescription>
                </DialogHeader>
                <form className="grid gap-4" onSubmit={submit} noValidate>
                    {!editing && (
                        <>
                            <Field
                                name="name"
                                label={t('staff_team.form.name')}
                                error={errors.name}
                                required
                            >
                                {(p) => (
                                    <Input
                                        {...p}
                                        autoComplete="off"
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                    />
                                )}
                            </Field>
                            <Field
                                name="email"
                                label={t('staff_team.form.email')}
                                error={errors.email}
                                required
                            >
                                {(p) => (
                                    <Input
                                        {...p}
                                        type="email"
                                        autoComplete="off"
                                        value={data.email}
                                        onChange={(e) =>
                                            setData('email', e.target.value)
                                        }
                                    />
                                )}
                            </Field>
                        </>
                    )}
                    <Field
                        name="phone"
                        label={t('staff_team.form.phone')}
                        error={errors.phone}
                    >
                        {(p) => (
                            <Input
                                {...p}
                                type="tel"
                                inputMode="tel"
                                autoComplete="off"
                                value={data.phone}
                                onChange={(e) =>
                                    setData('phone', e.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Field
                        name="role"
                        label={t('staff_team.form.role')}
                        error={errors.role}
                        hint={
                            locked
                                ? t('staff_team.form.role_locked_self')
                                : context.me.role === 'manager'
                                  ? t('staff_team.form.role_locked_rank')
                                  : undefined
                        }
                        required
                    >
                        {(p) => (
                            <Select
                                value={data.role}
                                disabled={locked}
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
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            {t('staff_team.form.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            size="lg"
                            disabled={processing || !context.online}
                        >
                            {editing
                                ? t('staff_team.form.save')
                                : t('staff_team.form.add')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
