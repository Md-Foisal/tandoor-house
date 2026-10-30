import { Link } from '@inertiajs/react';
import { Bot, ChevronDown, UserRound } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { ActivityEntry, Option } from './types';

export function EntryRow({
    entry,
    families,
}: {
    entry: ActivityEntry;
    families: Option[];
}) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const automation = entry.actor === null;
    const who = entry.actor?.name ?? t('staff_activity.automation');
    const family =
        families.find((f) => f.value === entry.family)?.label ?? entry.family;

    return (
        <li className="grid gap-2 px-4 py-3 md:grid-cols-[4rem_minmax(0,1fr)] md:gap-4">
            <time className="text-sm font-semibold text-ink-muted tabular-nums">
                {entry.time}
            </time>
            <div className="grid min-w-0 gap-1.5">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                    <span
                        className={cn(
                            'inline-flex items-center gap-1.5 font-semibold',
                            automation && 'text-ink-muted',
                        )}
                    >
                        {automation ? (
                            <Bot className="size-4" aria-hidden="true" />
                        ) : (
                            <UserRound className="size-4" aria-hidden="true" />
                        )}
                        {who}
                    </span>
                    <Badge variant="secondary">{family}</Badge>
                </div>
                <p className="text-ink">{entry.description}</p>
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
                    {entry.subject && (
                        <Link
                            href={entry.subject.href}
                            className="inline-flex min-h-11 items-center text-sm font-semibold text-ember underline-offset-4 hover:underline md:min-h-0"
                        >
                            {entry.subject.label}
                        </Link>
                    )}
                    {entry.details.length > 0 && (
                        <Collapsible open={open} onOpenChange={setOpen}>
                            <CollapsibleTrigger className="inline-flex min-h-11 items-center gap-1 text-sm text-ink-muted hover:text-ink md:min-h-0">
                                {open
                                    ? t('staff_activity.details_hide')
                                    : t('staff_activity.details_show')}
                                <ChevronDown
                                    className={cn(
                                        'size-4 transition-transform',
                                        open && 'rotate-180',
                                    )}
                                    aria-hidden="true"
                                />
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <dl className="mt-2 grid max-w-xl grid-cols-[minmax(0,10rem)_minmax(0,1fr)] gap-x-4 gap-y-1 rounded-md bg-clay p-3 text-sm">
                                    {entry.details.map((detail) => (
                                        <div
                                            key={detail.label}
                                            className="contents"
                                        >
                                            <dt className="text-ink-muted">
                                                {detail.label}
                                            </dt>
                                            <dd className="font-medium break-words">
                                                {detail.value}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            </CollapsibleContent>
                        </Collapsible>
                    )}
                </div>
            </div>
        </li>
    );
}
