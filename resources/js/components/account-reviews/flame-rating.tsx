import { Flame } from 'lucide-react';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const values = [1, 2, 3, 4, 5] as const;

/** Read-only marks for a rating that has already been given. */
export function FlameScore({ value }: { value: number }) {
    const t = useT();

    return (
        <p className="flex items-center gap-0.5">
            <span className="sr-only">
                {t('reviews.rating', { rating: value })}
            </span>
            {values.map((flame) => (
                <Flame
                    key={flame}
                    aria-hidden="true"
                    className={cn(
                        'size-5',
                        flame <= value
                            ? 'fill-ember text-ember'
                            : 'text-line-strong',
                    )}
                />
            ))}
        </p>
    );
}

/**
 * A radio group drawn as five flames. Native radios, so arrow keys and
 * screen readers behave; each flame carries its number and word as text.
 */
export function FlameInput({
    id,
    value,
    onChange,
    error,
}: {
    id: string;
    value: number;
    onChange: (value: number) => void;
    error?: string;
}) {
    const t = useT();
    const chosen = value ? t(`reviews.form.rating_labels.${value}`) : null;

    return (
        <fieldset
            className="grid gap-1.5"
            aria-describedby={error ? `${id}-error` : undefined}
        >
            <legend className="mb-1.5 text-sm leading-none font-medium">
                {t('reviews.form.rating_legend')}
            </legend>
            <div className="flex items-center gap-1">
                {values.map((flame) => {
                    const label = t(`reviews.form.rating_labels.${flame}`);

                    return (
                        <label
                            key={flame}
                            className="relative grid size-11 cursor-pointer place-items-center rounded-md has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-ember"
                        >
                            <input
                                type="radio"
                                name="rating"
                                value={flame}
                                checked={value === flame}
                                onChange={() => onChange(flame)}
                                aria-invalid={error ? true : undefined}
                                className="peer sr-only"
                            />
                            <span className="sr-only">
                                {t('reviews.form.rating_option', {
                                    count: flame,
                                    label,
                                })}
                            </span>
                            <Flame
                                aria-hidden="true"
                                className={cn(
                                    'size-8 transition-colors duration-150',
                                    flame <= value
                                        ? 'fill-ember text-ember'
                                        : 'text-line-strong',
                                )}
                            />
                        </label>
                    );
                })}
                <span
                    aria-hidden="true"
                    className="ml-2 min-w-24 text-sm font-medium text-ink-muted"
                >
                    {chosen}
                </span>
            </div>
            {error && (
                <p
                    id={`${id}-error`}
                    className="text-sm font-medium text-chilli"
                >
                    {error}
                </p>
            )}
        </fieldset>
    );
}
