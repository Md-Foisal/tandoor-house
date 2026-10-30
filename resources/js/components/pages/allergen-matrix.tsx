import { ArrowLeftRight } from 'lucide-react';
import { Fragment } from 'react';
import { AllergenIcon, allergenCodes } from '@/components/brand/allergen-icon';
import { EmptyState } from '@/components/brand/states';
import { useT } from '@/lib/i18n';
import type { MatrixGroup } from './types';

/*
 * Rules for print: drop the site chrome, let the table run to its natural
 * size and stop the sticky offsets from fighting the page break.
 */
const printStyles = `
@media print {
    header, footer, nav, [data-print-hide] { display: none !important; }
    [data-matrix-scroll] { max-height: none !important; overflow: visible !important; }
    [data-matrix] th, [data-matrix] td { position: static !important; }
    [data-matrix] tr { break-inside: avoid; }
    [data-matrix] { font-size: 10px; }
}
`;

export function AllergenMatrix({ groups }: { groups: MatrixGroup[] }) {
    const t = useT();

    if (groups.length === 0) {
        return <EmptyState />;
    }

    const columns = allergenCodes.length + 1;

    return (
        <div className="flex flex-col gap-3">
            <style>{printStyles}</style>
            <p
                data-print-hide
                className="flex items-center gap-2 text-sm text-ink-muted md:hidden"
            >
                <ArrowLeftRight aria-hidden="true" className="size-4" />
                {t('pages.allergens.matrix.scroll_hint')}
            </p>

            <div
                data-matrix-scroll
                role="region"
                tabIndex={0}
                aria-label={t('pages.allergens.matrix.caption')}
                className="max-h-[75vh] overflow-auto rounded-lg border border-line bg-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember focus-visible:outline-solid"
            >
                <table
                    data-matrix
                    className="w-max min-w-full border-separate border-spacing-0 text-left text-sm"
                >
                    <caption className="sr-only">
                        {t('pages.allergens.matrix.caption')}
                    </caption>
                    <thead>
                        <tr>
                            <th
                                scope="col"
                                className="sticky top-0 left-0 z-30 w-40 min-w-40 border-b border-line-strong bg-surface p-3 align-bottom text-label text-ink-muted md:w-64 md:min-w-64"
                            >
                                {t('pages.allergens.matrix.dish')}
                            </th>
                            {allergenCodes.map((code) => (
                                <th
                                    key={code}
                                    scope="col"
                                    className="sticky top-0 z-20 w-12 min-w-12 border-b border-line-strong bg-surface px-1 pt-3 pb-2 align-bottom font-semibold text-ink"
                                >
                                    <span className="flex flex-col items-center gap-2">
                                        <AllergenIcon
                                            code={code}
                                            className="text-ember"
                                        />
                                        <span className="rotate-180 text-xs leading-tight font-semibold [writing-mode:vertical-rl]">
                                            {t(`allergens.${code}`)}
                                        </span>
                                    </span>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    {groups.map((group) => (
                        <tbody key={group.id}>
                            <tr>
                                <th
                                    scope="colgroup"
                                    colSpan={columns}
                                    className="sticky left-0 z-10 border-b border-line bg-clay p-0 text-left"
                                >
                                    <span className="sticky left-0 inline-block px-3 py-2 font-display text-lg text-ink">
                                        {group.name}
                                    </span>
                                </th>
                            </tr>
                            {group.dishes.map((dish) => (
                                <Fragment key={dish.slug}>
                                    <tr data-dish={dish.slug}>
                                        <th
                                            scope="row"
                                            className="sticky left-0 z-10 w-40 min-w-40 border-b border-line bg-surface p-3 align-top font-semibold text-ink md:w-64 md:min-w-64"
                                        >
                                            {dish.name}
                                            {dish.allergens.length === 0 && (
                                                <span className="block text-xs font-normal text-ink-muted">
                                                    {t(
                                                        'pages.allergens.matrix.none',
                                                    )}
                                                </span>
                                            )}
                                            {dish.options.map((option) => (
                                                <span
                                                    key={option.name}
                                                    className="mt-1 block text-xs font-normal text-ink-muted"
                                                >
                                                    {t(
                                                        'pages.allergens.matrix.options_line',
                                                        {
                                                            option: option.name,
                                                            allergens:
                                                                option.allergens
                                                                    .map(
                                                                        (
                                                                            code,
                                                                        ) =>
                                                                            t(
                                                                                `allergens.${code}`,
                                                                            ).toLowerCase(),
                                                                    )
                                                                    .join(', '),
                                                        },
                                                    )}
                                                </span>
                                            ))}
                                        </th>
                                        {allergenCodes.map((code) => {
                                            const has =
                                                dish.allergens.includes(code);
                                            const name = t(`allergens.${code}`);

                                            return (
                                                <td
                                                    key={code}
                                                    data-allergen={code}
                                                    data-contains={has}
                                                    className="border-b border-line p-0 text-center align-middle"
                                                >
                                                    <span className="grid h-11 place-items-center">
                                                        {has ? (
                                                            <AllergenIcon
                                                                code={code}
                                                                className="size-6 text-ember"
                                                            />
                                                        ) : (
                                                            <span
                                                                aria-hidden="true"
                                                                className="h-0.5 w-2 rounded-full bg-line"
                                                            />
                                                        )}
                                                        <span className="sr-only">
                                                            {has
                                                                ? t(
                                                                      'pages.allergens.matrix.contains',
                                                                      {
                                                                          allergen:
                                                                              name,
                                                                      },
                                                                  )
                                                                : t(
                                                                      'pages.allergens.matrix.free',
                                                                      {
                                                                          allergen:
                                                                              name,
                                                                      },
                                                                  )}
                                                        </span>
                                                    </span>
                                                </td>
                                            );
                                        })}
                                    </tr>
                                </Fragment>
                            ))}
                        </tbody>
                    ))}
                </table>
            </div>

            <div
                aria-label={t('pages.allergens.matrix.key')}
                className="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-ink-muted"
            >
                <span className="inline-flex items-center gap-2">
                    <AllergenIcon code="milk" className="size-5 text-ember" />
                    {t('pages.allergens.matrix.key_contains')}
                </span>
                <span className="inline-flex items-center gap-2">
                    <span
                        aria-hidden="true"
                        className="h-0.5 w-2 rounded-full bg-line-strong"
                    />
                    {t('pages.allergens.matrix.key_free')}
                </span>
            </div>
        </div>
    );
}
