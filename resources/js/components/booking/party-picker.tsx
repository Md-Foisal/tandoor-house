import { Phone } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/** Table size as a row of numbered chips; the last one is "more than we seat online". */
export function PartyPicker({
    value,
    max,
    onChange,
    error,
}: {
    value: number;
    max: number;
    onChange: (party: number) => void;
    error?: string;
}) {
    const t = useT();
    const { restaurant } = usePage().props;
    const options = Array.from({ length: max + 1 }, (_, index) => index + 1);
    const tooBig = value > max;
    const tel = restaurant.phone.replace(/\s+/g, '');

    return (
        <fieldset className="grid min-w-0 grid-cols-1 gap-3">
            <legend className="mb-1 text-title font-semibold">
                <span className="text-ember">1. </span>
                {t('booking.party.legend')}
            </legend>
            <div className="flex flex-wrap gap-2">
                {options.map((party) => {
                    const more = party > max;
                    const label = more
                        ? t('booking.party.more_label', { max })
                        : t('booking.party.option_label', { count: party });

                    return (
                        <label key={party} className="relative">
                            <input
                                type="radio"
                                name="party_size"
                                value={party}
                                checked={value === party}
                                onChange={() => onChange(party)}
                                aria-label={label}
                                className="peer sr-only"
                            />
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'grid h-12 min-w-12 cursor-pointer place-items-center rounded-md border border-line-strong bg-surface px-3 text-lg font-semibold text-ink tabular-nums transition-colors duration-150',
                                    'peer-checked:border-ember peer-checked:bg-ember peer-checked:text-ember-ink hover:bg-clay',
                                    'peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ember',
                                )}
                            >
                                {more
                                    ? t('booking.party.more', { count: party })
                                    : t('booking.party.option', {
                                          count: party,
                                      })}
                            </span>
                        </label>
                    );
                })}
            </div>
            {error && !tooBig && (
                <p className="text-sm font-medium text-chilli">{error}</p>
            )}
            {tooBig && (
                <div
                    role="status"
                    className="flex flex-col gap-2 rounded-lg border border-line bg-turmeric-soft p-4 text-ink"
                >
                    <p className="font-semibold">
                        {t('booking.party.call_title', { max })}
                    </p>
                    <p className="text-sm">
                        {t('booking.party.call_body', {
                            phone: restaurant.phone,
                        })}
                    </p>
                    <a
                        href={`tel:${tel}`}
                        className="inline-flex min-h-11 items-center gap-2 self-start font-semibold text-ember underline underline-offset-4"
                    >
                        <Phone className="size-4" aria-hidden="true" />
                        {t('booking.party.call_action', {
                            phone: restaurant.phone,
                        })}
                    </a>
                </div>
            )}
        </fieldset>
    );
}
