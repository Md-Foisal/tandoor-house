import * as RadioGroupPrimitive from '@radix-ui/react-radio-group';
import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const options: Array<{ value: Appearance; icon: LucideIcon }> = [
    { value: 'light', icon: Sun },
    { value: 'dark', icon: Moon },
    { value: 'system', icon: Monitor },
];

function isAppearance(value: string): value is Appearance {
    return options.some((option) => option.value === value);
}

/**
 * The same three choices as the header ThemeToggle, with words, for the
 * appearance settings page.
 */
export default function AppearanceToggleTab({
    className = '',
    label,
}: {
    className?: string;
    label: string;
}) {
    const t = useT();
    const { appearance, updateAppearance } = useAppearance();

    return (
        <RadioGroupPrimitive.Root
            value={appearance}
            onValueChange={(value) =>
                isAppearance(value) && updateAppearance(value)
            }
            aria-label={label}
            className={cn('grid gap-3 sm:grid-cols-3', className)}
        >
            {options.map(({ value, icon: Icon }) => (
                <RadioGroupPrimitive.Item
                    key={value}
                    value={value}
                    className="group flex min-h-11 items-center gap-3 rounded-lg border border-line-strong bg-surface p-4 text-left transition-colors duration-150 outline-none hover:bg-clay focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember data-[state=checked]:border-ember data-[state=checked]:bg-ember-soft sm:flex-col sm:items-start"
                >
                    <Icon
                        className="size-6 text-ink-muted group-data-[state=checked]:text-ember"
                        aria-hidden="true"
                    />
                    <span className="grid">
                        <span className="font-semibold text-ink">
                            {t(`settings_pages.appearance.${value}`)}
                        </span>
                        <span className="text-sm text-ink-muted">
                            {t(`settings_pages.appearance.${value}_hint`)}
                        </span>
                    </span>
                </RadioGroupPrimitive.Item>
            ))}
        </RadioGroupPrimitive.Root>
    );
}
