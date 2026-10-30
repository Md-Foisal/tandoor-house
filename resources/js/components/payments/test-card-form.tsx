import { ChoiceCard } from '@/components/checkout/choice-card';
import { useT } from '@/lib/i18n';

export const TEST_CARDS = [
    'tok_visa',
    'tok_mastercard',
    'tok_decline',
] as const;

export type TestCard = (typeof TEST_CARDS)[number];

/**
 * The fake gateway's stand-in for card fields: a choice of test cards. No
 * number is typed, so nothing that looks like card data is ever sent.
 */
export function TestCardForm({
    value,
    onChange,
    disabled,
}: {
    value: TestCard;
    onChange: (value: TestCard) => void;
    disabled: boolean;
}) {
    const t = useT();

    return (
        <fieldset className="grid gap-3" disabled={disabled}>
            <legend className="mb-1 flex items-center gap-2 font-semibold">
                {t('payments.panel.test_title')}
                <span className="rounded-full bg-turmeric-soft px-2 py-0.5 text-xs font-semibold text-ink">
                    {t('payments.panel.test_badge')}
                </span>
            </legend>
            <p className="text-sm text-ink-muted">
                {t('payments.panel.test_note')}
            </p>
            <div className="grid gap-2">
                {TEST_CARDS.map((card) => (
                    <ChoiceCard
                        key={card}
                        name="test_card"
                        value={card}
                        checked={value === card}
                        disabled={disabled}
                        onSelect={(next) => onChange(next as TestCard)}
                        title={t(`payments.panel.cards.${card}`)}
                    />
                ))}
            </div>
        </fieldset>
    );
}
