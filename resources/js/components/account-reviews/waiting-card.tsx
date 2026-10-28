import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { useOnline } from '@/components/brand/states';
import { Field } from '@/components/form/field';
import { Plate } from '@/components/motion/served';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/lib/i18n';
import { store } from '@/routes/account/reviews';
import { FlameInput } from './flame-rating';
import type { WaitingOrder } from './types';

const MAX_BODY = 1000;

export function WaitingCard({ order }: { order: WaitingOrder }) {
    const t = useT();
    const online = useOnline();
    const [rating, setRating] = useState(0);
    const [body, setBody] = useState('');
    const key = order.number;

    return (
        <Plate>
            <article
                aria-labelledby={`waiting-${key}`}
                className="grid gap-5 rounded-lg border border-line bg-surface p-5 shadow-plate md:grid-cols-[minmax(0,16rem)_1fr] md:gap-8"
            >
                <div className="flex flex-col gap-2">
                    <h3
                        id={`waiting-${key}`}
                        className="text-title font-semibold"
                    >
                        {t('reviews.waiting.order', { number: order.number })}
                    </h3>
                    <p className="text-sm text-ink-muted">
                        <time dateTime={order.placed_at}>
                            {order.date_label}
                        </time>
                    </p>
                    <p className="sr-only">
                        {t('reviews.waiting.items_label')}
                    </p>
                    <ul className="text-sm text-ink">
                        {order.items.map((item) => (
                            <li key={item}>{item}</li>
                        ))}
                    </ul>
                </div>

                <Form
                    {...store.form(order.number)}
                    options={{ preserveScroll: true }}
                    className="flex flex-col gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <FlameInput
                                id={`rating-${key}`}
                                value={rating}
                                onChange={setRating}
                                error={errors.rating}
                            />
                            <Field
                                name={`body-${key}`}
                                label={t('reviews.form.body')}
                                hint={t('reviews.form.body_hint')}
                                error={errors.body}
                            >
                                {(props) => (
                                    <>
                                        <Textarea
                                            {...props}
                                            name="body"
                                            rows={4}
                                            minLength={20}
                                            maxLength={MAX_BODY}
                                            value={body}
                                            onChange={(e) =>
                                                setBody(e.target.value)
                                            }
                                        />
                                        <p
                                            className="text-right text-sm text-ink-muted tabular-nums"
                                            aria-hidden="true"
                                        >
                                            {t('reviews.form.body_count', {
                                                count: body.length,
                                            })}
                                        </p>
                                    </>
                                )}
                            </Field>
                            <Field
                                name={`area-${key}`}
                                label={t('reviews.form.area')}
                                hint={t('reviews.form.area_hint')}
                                error={errors.author_area}
                            >
                                {(props) => (
                                    <Input
                                        {...props}
                                        name="author_area"
                                        maxLength={60}
                                        autoComplete="off"
                                    />
                                )}
                            </Field>
                            <div className="flex flex-col gap-2">
                                {!online && (
                                    <p
                                        role="status"
                                        className="text-sm text-ink-muted"
                                    >
                                        {t('reviews.form.offline')}
                                    </p>
                                )}
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <p className="text-sm text-ink-muted">
                                        {t('reviews.form.moderation')}
                                    </p>
                                    <Button
                                        type="submit"
                                        className="min-h-12 sm:min-h-11"
                                        disabled={processing || !online}
                                    >
                                        {processing
                                            ? t('reviews.form.sending')
                                            : t('reviews.form.submit')}
                                    </Button>
                                </div>
                            </div>
                        </>
                    )}
                </Form>
            </article>
        </Plate>
    );
}
