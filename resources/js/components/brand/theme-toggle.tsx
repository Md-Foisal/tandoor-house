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

export function ThemeToggle({ className }: { className?: string }) {
    const t = useT();
    const { appearance, updateAppearance } = useAppearance();

    return (
        <div
            role="radiogroup"
            aria-label={t('ui.theme.label')}
            className={cn(
                'inline-flex gap-0.5 rounded-full bg-clay p-1',
                className,
            )}
        >
            {options.map(({ value, icon: Icon }) => {
                const active = appearance === value;

                return (
                    <button
                        key={value}
                        type="button"
                        role="radio"
                        aria-checked={active}
                        aria-label={t(`ui.theme.${value}`)}
                        title={t(`ui.theme.${value}`)}
                        onClick={() => updateAppearance(value)}
                        className={cn(
                            'grid size-9 place-items-center rounded-full text-ink-muted transition-colors duration-150 outline-none hover:text-ink focus-visible:outline-2 focus-visible:outline-ember',
                            active && 'bg-surface text-ink shadow-plate',
                        )}
                    >
                        <Icon className="size-4" aria-hidden="true" />
                    </button>
                );
            })}
        </div>
    );
}
