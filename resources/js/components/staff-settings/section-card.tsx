import type { FormEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';

/**
 * One settings section: a heading, its fields and its own save button.
 * The button says why it is disabled instead of just greying out.
 */
export function SectionCard({
    title,
    description,
    children,
    onSubmit,
    dirty,
    processing,
    online,
    locked,
}: {
    title: string;
    description?: string;
    children: ReactNode;
    onSubmit: () => void;
    dirty: boolean;
    processing: boolean;
    online: boolean;
    locked?: boolean;
}) {
    const t = useT();
    const reason = !online
        ? t('staff_settings.offline_reason')
        : !dirty && !locked
          ? t('staff_settings.saved_nothing')
          : null;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        onSubmit();
    };

    return (
        <form
            onSubmit={submit}
            noValidate
            className="grid gap-5 rounded-lg border border-line bg-surface p-4 md:p-6"
        >
            <div>
                <h2 className="font-display text-display-md">{title}</h2>
                {description && (
                    <p className="mt-1 max-w-prose text-ink-muted">
                        {description}
                    </p>
                )}
            </div>
            {children}
            {!locked && (
                <div className="sticky bottom-0 -mx-4 flex flex-wrap items-center gap-3 border-t border-line bg-surface px-4 py-3 md:static md:mx-0 md:border-0 md:p-0">
                    <Button
                        type="submit"
                        size="lg"
                        disabled={!dirty || processing || !online}
                    >
                        {processing
                            ? t('staff_settings.saving')
                            : t('staff_settings.save')}
                    </Button>
                    {reason && (
                        <p className="text-sm text-ink-muted" role="status">
                            {reason}
                        </p>
                    )}
                </div>
            )}
        </form>
    );
}

/** Explains why a money section is read-only for managers. */
export function MoneyLocked() {
    const t = useT();

    return (
        <p
            role="note"
            className="rounded-md border border-turmeric bg-turmeric-soft p-3 text-sm"
        >
            <strong className="block">
                {t('staff_settings.money.locked_title')}
            </strong>
            {t('staff_settings.money.locked_body')}
        </p>
    );
}
