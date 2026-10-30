import { Undo2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import type { RecentLine } from './types';

export function RecentRail({
    lines,
    disabled,
    onRecall,
}: {
    lines: RecentLine[];
    disabled: boolean;
    onRecall: (line: RecentLine) => void;
}) {
    const t = useT();

    return (
        <section
            aria-label={t('staff_kitchen.recent.title')}
            className="flex shrink-0 items-center gap-3 border-t border-line bg-surface px-3 py-2"
        >
            <h2 className="shrink-0 text-sm font-semibold text-ink-muted">
                {t('staff_kitchen.recent.title')}
            </h2>
            {lines.length === 0 ? (
                <p className="text-sm text-ink-muted">
                    {t('staff_kitchen.recent.empty')}
                </p>
            ) : (
                <ul className="flex min-w-0 gap-2 overflow-x-auto">
                    {lines.map((line, index) => (
                        <li key={line.id} className="shrink-0">
                            <Button
                                variant={index === 0 ? 'outline' : 'secondary'}
                                className="h-12 gap-2"
                                disabled={disabled}
                                onClick={() => onRecall(line)}
                                aria-label={t(
                                    'staff_kitchen.recent.recall_label',
                                    {
                                        dish: line.name,
                                        number: line.number,
                                    },
                                )}
                            >
                                <Undo2 aria-hidden="true" />
                                <span className="tabular-nums">
                                    {line.number}
                                </span>
                                <span className="font-normal">
                                    {line.quantity}× {line.name}
                                </span>
                                {index === 0 && (
                                    <kbd className="rounded bg-ink px-1.5 text-xs text-paper">
                                        R
                                    </kbd>
                                )}
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
