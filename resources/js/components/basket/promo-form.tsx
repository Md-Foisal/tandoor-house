import { useForm } from '@inertiajs/react';
import { Tag, X } from 'lucide-react';
import type { FormEvent } from 'react';
import { useOnline } from '@/components/brand/states';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/lib/i18n';

export function PromoForm({
    appliedCode,
    invalid,
}: {
    appliedCode: string | null;
    invalid: boolean;
}) {
    const t = useT();
    const online = useOnline();
    const form = useForm({ code: '' });
    const remove = useForm({ code: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/checkout/promo', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    if (appliedCode !== null) {
        return (
            <section
                aria-labelledby="basket-promo"
                className="flex items-center justify-between gap-3 rounded-lg border border-leaf/40 bg-leaf-soft p-4"
            >
                <h2 id="basket-promo" className="sr-only">
                    {t('basket.promo.heading')}
                </h2>
                <p className="flex items-center gap-2 font-semibold text-ink">
                    <Tag className="size-4 text-leaf" aria-hidden="true" />
                    {t('basket.promo.applied', { code: appliedCode })}
                </p>
                <Button
                    type="button"
                    variant="ghost"
                    disabled={remove.processing || !online}
                    onClick={() =>
                        remove.post('/checkout/promo', {
                            preserveScroll: true,
                        })
                    }
                >
                    <X aria-hidden="true" />
                    {t('basket.promo.remove')}
                </Button>
            </section>
        );
    }

    const error =
        form.errors.code ?? (invalid ? t('basket.promo.invalid') : null);

    return (
        <section aria-labelledby="basket-promo">
            <form onSubmit={submit} className="flex flex-col gap-2" noValidate>
                <Label id="basket-promo" htmlFor="promo-code">
                    {t('basket.promo.heading')}
                </Label>
                <div className="flex gap-2">
                    <Input
                        id="promo-code"
                        name="code"
                        autoComplete="off"
                        autoCapitalize="characters"
                        spellCheck={false}
                        value={form.data.code}
                        onChange={(event) =>
                            form.setData('code', event.target.value)
                        }
                        placeholder={t('basket.promo.label')}
                        aria-invalid={error !== null}
                        aria-describedby={error ? 'promo-error' : undefined}
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={
                            form.processing ||
                            !online ||
                            form.data.code.trim() === ''
                        }
                    >
                        {t('basket.promo.apply')}
                    </Button>
                </div>
                {error && (
                    <p id="promo-error" className="text-sm text-chilli">
                        {error}
                    </p>
                )}
            </form>
        </section>
    );
}
