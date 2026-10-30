import { forwardRef } from 'react';
import { Link } from '@inertiajs/react';
import { useT } from '@/lib/i18n';
import { basket } from '@/routes';

const aliases: Record<string, string> = {
    pickup_at: 'pickup',
    tip: 'tip_custom',
};

function focusField(field: string) {
    const name = aliases[field] ?? field;

    (
        document.getElementById(`field-${name}`) ??
        document.querySelector<HTMLElement>(`[name="${name}"]`)
    )?.focus();
}

export const ErrorSummary = forwardRef<
    HTMLDivElement,
    { errors: Record<string, string | undefined> }
>(function ErrorSummary({ errors }, ref) {
    const t = useT();
    const entries = Object.entries(errors).filter(
        (entry): entry is [string, string] => Boolean(entry[1]),
    );

    if (entries.length === 0) {
        return null;
    }

    return (
        <div
            ref={ref}
            tabIndex={-1}
            role="alert"
            className="rounded-lg border border-chilli/40 bg-chilli-soft p-4 outline-hidden focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember focus-visible:outline-solid"
        >
            <h2 className="font-semibold text-ink">
                {t('checkout.errors.summary_title')}
            </h2>
            <ul className="mt-2 grid list-disc gap-1 pl-5 text-sm text-ink">
                {entries.map(([field, message]) => (
                    <li key={field}>
                        {field === 'cart' ? (
                            <>
                                {message}{' '}
                                <Link
                                    href={basket()}
                                    className="font-semibold underline underline-offset-4"
                                >
                                    {t('checkout.errors.edit_basket')}
                                </Link>
                            </>
                        ) : (
                            <button
                                type="button"
                                className="min-h-8 text-left underline underline-offset-4"
                                onClick={() => focusField(field)}
                            >
                                {message}
                            </button>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
});
