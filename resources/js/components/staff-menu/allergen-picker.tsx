import { AllergenIcon, allergenCodes } from '@/components/brand/allergen-icon';
import type { AllergenCode } from '@/components/brand/allergen-icon';
import { Checkbox } from '@/components/ui/checkbox';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/** The 14 declarable allergens as a checklist; each shows icon and name. */
export function AllergenPicker({
    value,
    onChange,
    idPrefix,
    compact = false,
}: {
    value: AllergenCode[];
    onChange: (next: AllergenCode[]) => void;
    idPrefix: string;
    compact?: boolean;
}) {
    const t = useT();

    return (
        <div
            className={cn(
                'grid gap-2',
                compact
                    ? 'grid-cols-2 sm:grid-cols-3'
                    : 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
            )}
        >
            {allergenCodes.map((code) => {
                const checked = value.includes(code);
                const id = `${idPrefix}-${code}`;

                return (
                    <label
                        key={code}
                        htmlFor={id}
                        className={cn(
                            'flex min-h-11 cursor-pointer items-center gap-2.5 rounded-md border px-3 py-2 text-sm transition-colors',
                            checked
                                ? 'border-ember bg-ember-soft text-ink'
                                : 'border-line bg-surface text-ink hover:border-line-strong',
                        )}
                    >
                        <Checkbox
                            id={id}
                            checked={checked}
                            onCheckedChange={(on) =>
                                onChange(
                                    on === true
                                        ? [...value, code]
                                        : value.filter((c) => c !== code),
                                )
                            }
                        />
                        <AllergenIcon
                            code={code}
                            className="size-5 text-ink-muted"
                        />
                        <span className="leading-tight">
                            {t(`allergens.${code}`)}
                        </span>
                    </label>
                );
            })}
        </div>
    );
}
