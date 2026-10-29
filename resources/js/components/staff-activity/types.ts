export type ActivityEntry = {
    id: number;
    event: string;
    family: string;
    day: string;
    time: string;
    actor: { id: number; name: string } | null;
    description: string;
    subject: { label: string; href: string } | null;
    details: { label: string; value: string }[];
};

export type ActivityFilters = {
    family: string | null;
    person: string | null;
    from: string | null;
    to: string | null;
    q: string | null;
};

export type Option = { value: string; label: string };

export type DayGroup = { day: string; entries: ActivityEntry[] };

/** Entries arrive newest first, so a day is a run of neighbours. */
export function groupByDay(entries: ActivityEntry[]): DayGroup[] {
    const groups: DayGroup[] = [];

    for (const entry of entries) {
        const last = groups[groups.length - 1];

        if (last && last.day === entry.day) {
            last.entries.push(entry);
        } else {
            groups.push({ day: entry.day, entries: [entry] });
        }
    }

    return groups;
}

export function dayLabel(
    day: string,
    today: string,
    t: (key: string) => string,
): string {
    const base = new Date(`${today}T12:00:00Z`);
    base.setUTCDate(base.getUTCDate() - 1);
    const yesterday = base.toISOString().slice(0, 10);

    if (day === today) return t('staff_activity.today');
    if (day === yesterday) return t('staff_activity.yesterday');

    return new Date(`${day}T12:00:00Z`).toLocaleDateString('en-GB', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    });
}
