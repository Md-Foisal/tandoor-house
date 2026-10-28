const dateTime = new Intl.DateTimeFormat('en-GB', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: 'Europe/London',
});

const dateOnly = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
    timeZone: 'Europe/London',
});

/** "Tue 27 Oct, 19:42" */
export function formatDateTime(iso: string): string {
    const parts = Object.fromEntries(
        dateTime.formatToParts(new Date(iso)).map((p) => [p.type, p.value]),
    );

    return `${parts.weekday} ${parts.day} ${parts.month}, ${parts.hour}:${parts.minute}`;
}

export function formatDate(iso: string): string {
    return dateOnly.format(new Date(iso));
}

/** "Butter Chicken, 2× Garlic Naan" plus a remainder count. */
export function summarise(
    lines: { name: string; quantity: number }[],
    shown = 2,
): { text: string; extra: number } {
    const text = lines
        .slice(0, shown)
        .map((l) => (l.quantity > 1 ? `${l.quantity}× ${l.name}` : l.name))
        .join(', ');

    return { text, extra: Math.max(0, lines.length - shown) };
}
