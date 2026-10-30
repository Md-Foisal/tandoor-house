import { router } from "@inertiajs/react";
import { Check, MailPlus, Pencil, Phone, Plus, UserMinus } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { EmptyState } from "@/components/brand/states";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { useT } from "@/lib/i18n";
import { StaffFormDialog } from "./staff-form-dialog";
import type { Person, TeamContext } from "./types";
import { hours } from "./types";

function OnShift({ person }: { person: Person }) {
    const t = useT();

    return person.on_shift ? (
        <Badge variant="leaf">{t("staff_team.people.on_shift")}</Badge>
    ) : (
        <span className="text-sm text-ink-muted">
            {t("staff_team.people.off_shift")}
        </span>
    );
}

function InviteNote({ person }: { person: Person }) {
    const t = useT();
    const { state, sent_at } = person.invite;

    if ((state !== "pending" && state !== "expired") || !sent_at) {
        return null;
    }

    const sent = new Date(sent_at);
    const londonYear = (value: Date) =>
        new Intl.DateTimeFormat("en-GB", {
            year: "numeric",
            timeZone: "Europe/London",
        }).format(value);
    const date = new Intl.DateTimeFormat("en-GB", {
        day: "numeric",
        month: "short",
        ...(londonYear(sent) !== londonYear(new Date())
            ? { year: "numeric" }
            : {}),
        timeZone: "Europe/London",
    }).format(sent);

    return (
        <p
            className={
                state === "expired"
                    ? "text-sm font-medium text-chilli"
                    : "text-sm text-ink-muted"
            }
        >
            {t(
                state === "expired"
                    ? "staff_team.people.invite_expired"
                    : "staff_team.people.invite_pending",
                { date },
            )}
        </p>
    );
}

export function PeopleTab({
    people,
    context,
}: {
    people: Person[];
    context: TeamContext;
}) {
    const t = useT();
    const [adding, setAdding] = useState(false);
    const [editing, setEditing] = useState<Person | null>(null);
    const [removing, setRemoving] = useState<Person | null>(null);
    const [busy, setBusy] = useState(false);
    const [resending, setResending] = useState<number | null>(null);
    const [justSent, setJustSent] = useState<number | null>(null);

    useEffect(() => {
        if (justSent === null) {
            return;
        }

        const timer = window.setTimeout(() => setJustSent(null), 6000);

        return () => window.clearTimeout(timer);
    }, [justSent]);

    const resend = (person: Person) => {
        setResending(person.id);
        setJustSent(null);
        router.post(`/staff/team/people/${person.id}/invite`, undefined, {
            preserveScroll: true,
            only: ["people"],
            onSuccess: () => setJustSent(person.id),
            onError: (errors) => toast.error(Object.values(errors)[0]),
            onFinish: () => setResending(null),
        });
    };

    const remove = () => {
        if (!removing) {
            return;
        }

        setBusy(true);
        router.delete(`/staff/team/people/${removing.id}`, {
            preserveScroll: true,
            only: ["people"],
            onSuccess: () => setRemoving(null),
            onFinish: () => setBusy(false),
        });
    };

    const actions = (person: Person) => (
        <div className="flex flex-wrap justify-end gap-2">
            {person.can_edit && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="min-h-11"
                    aria-label={t("staff_team.people.edit", {
                        name: person.name,
                    })}
                    onClick={() => setEditing(person)}
                >
                    <Pencil aria-hidden="true" />
                    <span className="hidden lg:inline">
                        {t("staff_team.people.edit_short")}
                    </span>
                </Button>
            )}
            {justSent === person.id && (
                <span
                    className="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-leaf"
                    role="status"
                >
                    <Check className="size-4" aria-hidden="true" />
                    <span aria-hidden="true">
                        {t("staff_team.people.invite_just_sent")}
                    </span>
                    <span className="sr-only">
                        {t("staff_team.people.invite_just_sent_to", {
                            name: person.name,
                        })}
                    </span>
                </span>
            )}
            {person.can_invite &&
                (person.invite.state === "pending" ||
                    person.invite.state === "expired") && (
                    <Button
                        type="button"
                        variant={
                            person.invite.state === "expired"
                                ? "default"
                                : "outline"
                        }
                        size="sm"
                        className="min-h-11"
                        disabled={!context.online || resending === person.id}
                        aria-label={t("staff_team.people.invite_resend_for", {
                            name: person.name,
                        })}
                        onClick={() => resend(person)}
                    >
                        <MailPlus aria-hidden="true" />
                        <span className="hidden lg:inline">
                            {t("staff_team.people.invite_resend")}
                        </span>
                    </Button>
                )}
            {person.can_remove && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="min-h-11 text-chilli"
                    disabled={!context.online}
                    onClick={() => setRemoving(person)}
                >
                    <UserMinus aria-hidden="true" />
                    <span className="hidden lg:inline">
                        {t("staff_team.people.remove")}
                    </span>
                    <span className="sr-only lg:hidden">
                        {t("staff_team.people.remove")} {person.name}
                    </span>
                </Button>
            )}
        </div>
    );

    return (
        <>
            <div className="flex items-center justify-between gap-3">
                <p className="text-sm text-ink-muted">
                    {t("staff_team.people.count", { count: people.length })}
                </p>
                <Button
                    type="button"
                    size="lg"
                    disabled={!context.online}
                    onClick={() => setAdding(true)}
                >
                    <Plus aria-hidden="true" />
                    {t("staff_team.people.add")}
                </Button>
            </div>

            {people.length === 0 ? (
                <EmptyState
                    title={t("staff_team.people.empty_title")}
                    description={t("staff_team.people.empty_body")}
                />
            ) : (
                <>
                    <table className="hidden w-full text-left md:table">
                        <thead className="sticky top-0 z-10 bg-paper text-label text-ink-muted uppercase">
                            <tr className="border-b border-line">
                                <th className="py-2 pr-3">
                                    {t("staff_team.people.name")}
                                </th>
                                <th className="py-2 pr-3">
                                    {t("staff_team.people.role")}
                                </th>
                                <th className="py-2 pr-3">
                                    {t("staff_team.people.phone")}
                                </th>
                                <th className="py-2 pr-3">
                                    {t("staff_team.people.on_shift")}
                                </th>
                                <th className="py-2 pr-3 text-right">
                                    {t("staff_team.people.hours_week")}
                                </th>
                                <th className="py-2">
                                    <span className="sr-only">
                                        {t("staff_team.people.actions")}
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {people.map((person) => (
                                <tr key={person.id}>
                                    <td className="py-3 pr-3">
                                        <p className="font-semibold">
                                            {person.name}
                                            {person.is_me && (
                                                <Badge
                                                    variant="secondary"
                                                    className="ml-2"
                                                >
                                                    {t("staff_team.people.you")}
                                                </Badge>
                                            )}
                                        </p>
                                        <p className="text-sm text-ink-muted">
                                            {person.email}
                                        </p>
                                        <InviteNote person={person} />
                                    </td>
                                    <td className="py-3 pr-3">
                                        <Badge variant="outline">
                                            {t(`roles.${person.role}`)}
                                        </Badge>
                                    </td>
                                    <td className="py-3 pr-3 tabular-nums">
                                        {person.phone ?? (
                                            <span className="text-ink-muted">
                                                {t(
                                                    "staff_team.people.no_phone",
                                                )}
                                            </span>
                                        )}
                                    </td>
                                    <td className="py-3 pr-3">
                                        <OnShift person={person} />
                                    </td>
                                    <td className="py-3 pr-3 text-right tabular-nums">
                                        {t("staff_team.hours", {
                                            hours: hours(person.minutes),
                                        })}
                                    </td>
                                    <td className="py-3">{actions(person)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    <ul className="grid gap-3 md:hidden">
                        {people.map((person) => (
                            <li
                                key={person.id}
                                className="grid gap-2 rounded-lg border border-line bg-surface p-4"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="font-semibold">
                                            {person.name}
                                            {person.is_me && (
                                                <span className="ml-2 text-sm text-ink-muted">
                                                    {t("staff_team.people.you")}
                                                </span>
                                            )}
                                        </p>
                                        <p className="truncate text-sm text-ink-muted">
                                            {person.email}
                                        </p>
                                        <InviteNote person={person} />
                                    </div>
                                    <Badge variant="outline">
                                        {t(`roles.${person.role}`)}
                                    </Badge>
                                </div>
                                <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    {person.phone ? (
                                        <a
                                            href={`tel:${person.phone}`}
                                            className="inline-flex min-h-11 items-center gap-1.5 font-medium text-ember"
                                        >
                                            <Phone
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            {person.phone}
                                        </a>
                                    ) : (
                                        <span className="text-ink-muted">
                                            {t("staff_team.people.no_phone")}
                                        </span>
                                    )}
                                    <OnShift person={person} />
                                    <span className="tabular-nums">
                                        {t("staff_team.hours", {
                                            hours: hours(person.minutes),
                                        })}
                                    </span>
                                </div>
                                {actions(person)}
                            </li>
                        ))}
                    </ul>
                </>
            )}

            <StaffFormDialog
                open={adding || editing !== null}
                person={editing}
                context={context}
                onClose={() => {
                    setAdding(false);
                    setEditing(null);
                }}
            />

            <Dialog
                open={removing !== null}
                onOpenChange={(next) => !next && setRemoving(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t("staff_team.remove.title", {
                                name: removing?.name ?? "",
                            })}
                        </DialogTitle>
                        <DialogDescription>
                            {t("staff_team.remove.body")}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setRemoving(null)}
                        >
                            {t("staff_team.form.cancel")}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            size="lg"
                            disabled={busy || !context.online}
                            onClick={remove}
                        >
                            {t("staff_team.remove.confirm")}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
