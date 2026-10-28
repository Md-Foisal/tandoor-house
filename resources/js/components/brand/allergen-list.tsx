import type { AllergenCode } from '@/components/brand/allergen-icon';
import { AllergenIcon } from '@/components/brand/allergen-icon';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * Allergens are always written out in text; the icon is a reading aid.
 * `compact` keeps the names visible but small for menu rows.
 */
export function AllergenList({
    allergens,
    optional = [],
    compact = false,
    className,
}: {
    allergens: AllergenCode[];
    /** Allergens that depend on the options chosen. */
    optional?: AllergenCode[];
    compact?: boolean;
    className?: string;
}) {
    const t = useT();

    if (allergens.length === 0 && optional.length === 0) {
        return (
            <p className={cn('text-sm text-ink-muted', className)}>
                {t('ui.no_allergens')}
            </p>
        );
    }

    return (
        <div className={className}>
            <span className="sr-only">{t('ui.contains')}: </span>
            <ul
                className={cn(
                    'flex flex-wrap',
                    compact ? 'gap-x-2.5 gap-y-1' : 'gap-2',
                )}
            >
                {allergens.map((code) => (
                    <li
                        key={code}
                        className={cn(
                            'inline-flex items-center text-ink-muted',
                            compact
                                ? 'gap-1 text-xs'
                                : 'gap-1.5 rounded-sm border border-line px-2 py-1 text-sm text-ink',
                        )}
                    >
                        <AllergenIcon
                            code={code}
                            className={
                                compact
                                    ? 'size-3.5'
                                    : 'size-[1.125rem] text-ember'
                            }
                        />
                        {t(`allergens.${code}`)}
                    </li>
                ))}
            </ul>
            {optional.length > 0 && (
                <p
                    className={cn(
                        'text-ink-muted',
                        compact ? 'mt-1 text-xs' : 'mt-2 text-sm',
                    )}
                >
                    {t('ui.depends_on_choice', {
                        allergens: optional
                            .map((code) => t(`allergens.${code}`))
                            .join(', '),
                    })}
                </p>
            )}
        </div>
    );
}
