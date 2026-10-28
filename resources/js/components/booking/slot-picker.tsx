import { Course, Settle } from '@/components/motion/served';
import { EmptyState, ErrorState } from '@/components/brand/states';
import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Slot } from './types';

export type SlotsState = 'idle' | 'loading' | 'error';

const GRID = 'grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-4';

function SlotSkeleton() {
    return (
        <div className={GRID} aria-hidden="true">
            {Array.from({ length: 8 }, (_, index) => (
                <Skeleton key={index} className="h-16 rounded-lg" />
            ))}
        </div>
    );
}

function Cards({
    slots,
    value,
    onChange,
    groupKey,
}: {
    slots: Slot[];
    value: string;
    onChange: (time: string) => void;
    groupKey: string;
}) {
    const t = useT();

    return (
        <Course key={groupKey} className={GRID}>
            {slots.map((slot, index) => (
                <Settle key={slot.time} tilt={index % 2 === 0 ? -2 : 2}>
                    <label className="relative block">
                        <input
                            type="radio"
                            name="time"
                            value={slot.time}
                            checked={value === slot.time}
                            disabled={!slot.available}
                            onChange={() => onChange(slot.time)}
                            aria-label={
                                slot.available
                                    ? slot.time
                                    : `${slot.time}, ${t('booking.time.full')}`
                            }
                            className="peer sr-only"
                        />
                        <span
                            aria-hidden="true"
                            className={cn(
                                'grid h-16 cursor-pointer place-items-center content-center rounded-lg border border-line-strong bg-surface text-ink shadow-plate transition-colors duration-150',
                                'peer-checked:border-ember peer-checked:bg-ember peer-checked:text-ember-ink hover:bg-clay',
                                'peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ember',
                                'peer-disabled:cursor-not-allowed peer-disabled:border-line peer-disabled:bg-clay peer-disabled:text-ink-muted peer-disabled:shadow-none peer-disabled:hover:bg-clay',
                            )}
                        >
                            <span
                                className={cn(
                                    'text-lg font-semibold tabular-nums',
                                    !slot.available && 'line-through',
                                )}
                            >
                                {slot.time}
                            </span>
                            {!slot.available && (
                                <span className="text-xs">
                                    {t('booking.time.full')}
                                </span>
                            )}
                        </span>
                    </label>
                </Settle>
            ))}
        </Course>
    );
}

/** Times as place cards set on a table, split into lunch and dinner. */
export function SlotPicker({
    slots,
    state,
    value,
    onChange,
    onRetry,
    ready,
    tooBig,
    groupKey,
    error,
}: {
    slots: Slot[];
    state: SlotsState;
    value: string;
    onChange: (time: string) => void;
    onRetry: () => void;
    ready: boolean;
    tooBig: boolean;
    groupKey: string;
    error?: string;
}) {
    const t = useT();
    const lunch = slots.filter((slot) => slot.time < '17:00');
    const dinner = slots.filter((slot) => slot.time >= '17:00');
    const none = slots.every((slot) => !slot.available);

    let body;

    if (tooBig) {
        body = null;
    } else if (!ready) {
        body = <p className="text-ink-muted">{t('booking.time.pick_party')}</p>;
    } else if (state === 'error') {
        body = <ErrorState title={t('booking.time.error')} onRetry={onRetry} />;
    } else if (state === 'loading') {
        body = <SlotSkeleton />;
    } else if (slots.length === 0 || none) {
        body = (
            <EmptyState
                className="rounded-lg border border-dashed border-line-strong py-8"
                title={t('booking.time.empty_title')}
                description={t('booking.time.empty_body')}
            />
        );
    } else {
        body = (
            <div className="grid gap-5">
                {lunch.length > 0 && (
                    <div className="grid gap-2">
                        <h3 className="text-label text-ink-muted">
                            {t('booking.time.lunch')}
                        </h3>
                        <Cards
                            slots={lunch}
                            value={value}
                            onChange={onChange}
                            groupKey={`${groupKey}-l`}
                        />
                    </div>
                )}
                {dinner.length > 0 && (
                    <div className="grid gap-2">
                        <h3 className="text-label text-ink-muted">
                            {t('booking.time.dinner')}
                        </h3>
                        <Cards
                            slots={dinner}
                            value={value}
                            onChange={onChange}
                            groupKey={`${groupKey}-d`}
                        />
                    </div>
                )}
            </div>
        );
    }

    return (
        <fieldset
            className="grid min-w-0 gap-3"
            aria-busy={state === 'loading'}
        >
            <legend className="mb-1 text-title font-semibold">
                <span className="text-ember">3. </span>
                {t('booking.time.legend')}
            </legend>
            {body}
            <p className="sr-only" aria-live="polite">
                {state === 'loading'
                    ? t('booking.time.loading')
                    : value !== ''
                      ? t('booking.time.selected', { time: value })
                      : ''}
            </p>
            {error && (
                <p className="text-sm font-medium text-chilli">{error}</p>
            )}
        </fieldset>
    );
}
