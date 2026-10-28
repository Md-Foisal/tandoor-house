import { AllergenIcon, allergenCodes } from '@/components/brand/allergen-icon';
import { useT } from '@/lib/i18n';

export function AllergenAbout() {
    const t = useT();

    return (
        <ul className="grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
            {allergenCodes.map((code) => (
                <li key={code} className="flex gap-3">
                    <AllergenIcon
                        code={code}
                        className="mt-0.5 size-7 text-ember"
                    />
                    <div>
                        <p className="font-semibold text-ink">
                            {t(`allergens.${code}`)}
                        </p>
                        <p className="text-ink-muted">
                            {t(`pages.allergens.about.${code}`)}
                        </p>
                    </div>
                </li>
            ))}
        </ul>
    );
}
