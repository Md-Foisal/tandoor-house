import { AllergenIcon, allergenCodes } from '@/components/brand/allergen-icon';
import type { AllergenCode } from '@/components/brand/allergen-icon';
import type { MenuFilters } from '@/components/menu-page/filters';
import { MAX_SPICE } from '@/components/menu-page/filters';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Props = {
    filters: MenuFilters;
    update: (patch: Partial<MenuFilters>) => void;
    idPrefix: string;
    className?: string;
};

const chip =
    'flex min-h-11 cursor-pointer items-center gap-2 rounded-md border border-line-strong bg-surface px-3 text-base font-medium has-[[data-state=checked]]:border-ember has-[[data-state=checked]]:bg-ember-soft';

function Group({
    legend,
    children,
}: {
    legend: string;
    children: React.ReactNode;
}) {
    return (
        <fieldset className="flex min-w-0 flex-col gap-3">
            <legend className="mb-3 text-label text-ink-muted">{legend}</legend>
            {children}
        </fieldset>
    );
}

function parsePounds(value: string): number | null {
    if (value.trim() === '') {
        return null;
    }

    const parsed = Math.floor(Number(value));

    return Number.isFinite(parsed) && parsed >= 0 ? parsed : null;
}

export function FilterFields({ filters, update, idPrefix, className }: Props) {
    const t = useT();
    const spiceValue = filters.spice === null ? 'any' : String(filters.spice);

    const toggleAllergen = (code: AllergenCode, checked: boolean) =>
        update({
            exclude: checked
                ? [...filters.exclude, code]
                : filters.exclude.filter((existing) => existing !== code),
        });

    return (
        <div className={cn('grid gap-8', className)}>
            <Group legend={t('menu.filters.diet')}>
                <div className="flex flex-wrap gap-2">
                    <label className={chip} htmlFor={`${idPrefix}-veg`}>
                        <Checkbox
                            id={`${idPrefix}-veg`}
                            checked={filters.vegetarian}
                            onCheckedChange={(checked) =>
                                update({ vegetarian: checked === true })
                            }
                        />
                        {t('menu.filters.vegetarian')}
                    </label>
                    <label className={chip} htmlFor={`${idPrefix}-vegan`}>
                        <Checkbox
                            id={`${idPrefix}-vegan`}
                            checked={filters.vegan}
                            onCheckedChange={(checked) =>
                                update({ vegan: checked === true })
                            }
                        />
                        {t('menu.filters.vegan')}
                    </label>
                </div>
            </Group>

            <Group legend={t('menu.filters.spice')}>
                <RadioGroup
                    value={spiceValue}
                    onValueChange={(value) =>
                        update({
                            spice: value === 'any' ? null : Number(value),
                        })
                    }
                    className="flex flex-wrap gap-2"
                >
                    {[
                        'any',
                        ...Array.from({ length: MAX_SPICE }, (_, i) =>
                            String(i),
                        ),
                    ].map((value) => (
                        <label
                            key={value}
                            htmlFor={`${idPrefix}-spice-${value}`}
                            className={chip}
                        >
                            <RadioGroupItem
                                id={`${idPrefix}-spice-${value}`}
                                value={value}
                            />
                            {value === 'any'
                                ? t('menu.filters.spice_any')
                                : t(`ui.spice.${value}`)}
                        </label>
                    ))}
                </RadioGroup>
            </Group>

            <Group legend={t('menu.filters.allergens')}>
                <ul className="grid grid-cols-1 gap-2 min-[420px]:grid-cols-2 lg:grid-cols-3">
                    {allergenCodes.map((code) => (
                        <li key={code}>
                            <label
                                htmlFor={`${idPrefix}-ex-${code}`}
                                className={cn(chip, 'h-full w-full')}
                            >
                                <Checkbox
                                    id={`${idPrefix}-ex-${code}`}
                                    checked={filters.exclude.includes(code)}
                                    onCheckedChange={(checked) =>
                                        toggleAllergen(code, checked === true)
                                    }
                                />
                                <AllergenIcon
                                    code={code}
                                    className="size-5 shrink-0 text-ember"
                                />
                                <span className="text-sm leading-tight">
                                    {t(`allergens.${code}`)}
                                </span>
                            </label>
                        </li>
                    ))}
                </ul>
            </Group>

            <Group legend={t('menu.filters.price')}>
                <div className="grid max-w-sm grid-cols-2 gap-3">
                    <div className="grid gap-1.5">
                        <Label htmlFor={`${idPrefix}-min`}>
                            {t('menu.filters.price_min')}
                        </Label>
                        <Input
                            id={`${idPrefix}-min`}
                            type="number"
                            inputMode="numeric"
                            min={0}
                            step={1}
                            value={filters.minPrice ?? ''}
                            onChange={(event) =>
                                update({
                                    minPrice: parsePounds(event.target.value),
                                })
                            }
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor={`${idPrefix}-max`}>
                            {t('menu.filters.price_max')}
                        </Label>
                        <Input
                            id={`${idPrefix}-max`}
                            type="number"
                            inputMode="numeric"
                            min={0}
                            step={1}
                            value={filters.maxPrice ?? ''}
                            onChange={(event) =>
                                update({
                                    maxPrice: parsePounds(event.target.value),
                                })
                            }
                        />
                    </div>
                </div>
            </Group>
        </div>
    );
}
