import * as RadioGroupPrimitive from '@radix-ui/react-radio-group';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const options: Array<{ value: Appearance; icon: typeof Sun }> = [
    { value: 'light', icon: Sun },
    { value: 'dark', icon: Moon },
    { value: 'system', icon: Monitor },
];

function isAppearance(value: string): value is Appearance {
    return options.some((option) => option.value === value);
}

/**
 * Light, dark or match the device. A radio group, so arrow keys move
 * between the three choices.
 */
export function ThemeToggle({ className }: { className?: string }) {
    const t = useT();
    const { appearance, updateAppearance } = useAppearance();

    return (
        <RadioGroupPrimitive.Root
            value={appearance}
            onValueChange={(value) =>
                isAppearance(value) && updateAppearance(value)
            }
            aria-label={t('ui.theme.label')}
            orientation="horizontal"
            className={cn(
                'inline-flex gap-0.5 rounded-full bg-clay p-0.5',
                className,
            )}
        >
            {options.map(({ value, icon: Icon }) => (
                <RadioGroupPrimitive.Item
                    key={value}
                    value={value}
                    aria-label={t(`ui.theme.${value}`)}
                    title={t(`ui.theme.${value}`)}
                    className="grid size-11 place-items-center rounded-full text-ink-muted outline-hidden transition-colors duration-150 hover:text-ink focus-visible:outline-2 focus-visible:outline-ember data-[state=checked]:bg-surface data-[state=checked]:text-ink data-[state=checked]:shadow-plate"
                >
                    <Icon className="size-4" aria-hidden="true" />
                </RadioGroupPrimitive.Item>
            ))}
        </RadioGroupPrimitive.Root>
    );
}
