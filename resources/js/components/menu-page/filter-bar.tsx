import { ChevronDown, Search, SlidersHorizontal, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { FilterFields } from '@/components/menu-page/filter-fields';
import type { MenuFilters } from '@/components/menu-page/filters';
import {
    activeFilterCount,
    hasAnyFilter,
} from '@/components/menu-page/filters';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { choice, useT } from '@/lib/i18n';

type Props = {
    filters: MenuFilters;
    update: (patch: Partial<MenuFilters>) => void;
    clear: () => void;
    resultCount: number;
    disabled?: boolean;
};

const DEBOUNCE_MS = 200;

export function FilterBar({
    filters,
    update,
    clear,
    resultCount,
    disabled = false,
}: Props) {
    const t = useT();
    const count = activeFilterCount(filters);
    const [text, setText] = useState(filters.query);
    const [panelOpen, setPanelOpen] = useState(count > 0);
    const input = useRef<HTMLInputElement>(null);
    const timer = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => {
        setText((previous) =>
            previous.trim() === filters.query ? previous : filters.query,
        );
    }, [filters.query]);

    useEffect(() => () => clearTimeout(timer.current), []);

    const onType = (value: string) => {
        setText(value);
        clearTimeout(timer.current);
        timer.current = setTimeout(
            () => update({ query: value.trim() }),
            DEBOUNCE_MS,
        );
    };

    const clearSearch = () => {
        clearTimeout(timer.current);
        setText('');
        update({ query: '' });
        input.current?.focus();
    };

    const filterLabel =
        count > 0
            ? t('menu.filters.button_count', { count })
            : t('menu.filters.button');

    const trigger = (
        <>
            <SlidersHorizontal aria-hidden="true" />
            {t('menu.filters.button')}
            {count > 0 && (
                <Badge variant="solid" aria-hidden="true">
                    {count}
                </Badge>
            )}
        </>
    );

    return (
        <Collapsible
            open={panelOpen}
            onOpenChange={setPanelOpen}
            className="flex flex-col gap-3"
        >
            <div className="flex items-stretch gap-2">
                <div className="relative flex-1">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-ink-muted"
                        aria-hidden="true"
                    />
                    <Input
                        ref={input}
                        type="search"
                        role="searchbox"
                        value={text}
                        onChange={(event) => onType(event.target.value)}
                        disabled={disabled}
                        aria-label={t('menu.search.label')}
                        placeholder={t('menu.search.placeholder')}
                        autoComplete="off"
                        enterKeyHint="search"
                        className="h-12 [appearance:none] pr-12 pl-11 [&::-webkit-search-cancel-button]:hidden"
                    />
                    {text !== '' && (
                        <button
                            type="button"
                            onClick={clearSearch}
                            aria-label={t('menu.search.clear')}
                            className="absolute top-1/2 right-1 grid size-11 -translate-y-1/2 place-items-center rounded-full text-ink-muted outline-hidden hover:text-ink focus-visible:outline-2 focus-visible:outline-ember"
                        >
                            <X className="size-5" aria-hidden="true" />
                        </button>
                    )}
                </div>

                <div className="md:hidden">
                    <Sheet>
                        <SheetTrigger asChild>
                            <Button
                                variant="outline"
                                className="h-12"
                                disabled={disabled}
                                aria-label={filterLabel}
                            >
                                {trigger}
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="bottom"
                            className="max-h-[90dvh] gap-0 rounded-t-lg"
                        >
                            <SheetHeader className="pr-16">
                                <SheetTitle>
                                    {t('menu.filters.title')}
                                </SheetTitle>
                                <SheetDescription>
                                    {t('menu.filters.description')}
                                </SheetDescription>
                            </SheetHeader>
                            <div className="overflow-y-auto px-4 pb-6">
                                <FilterFields
                                    filters={filters}
                                    update={update}
                                    idPrefix="sheet"
                                />
                            </div>
                            <SheetFooter className="flex-row gap-3 border-t border-line pb-[calc(1rem+env(safe-area-inset-bottom))]">
                                <Button
                                    variant="ghost"
                                    onClick={clear}
                                    disabled={!hasAnyFilter(filters)}
                                    className="h-12"
                                >
                                    {t('menu.filters.clear_all')}
                                </Button>
                                <SheetClose asChild>
                                    <Button className="h-12 flex-1">
                                        {choice(
                                            t('menu.filters.show_results', {
                                                count: resultCount,
                                            }),
                                            resultCount,
                                        )}
                                    </Button>
                                </SheetClose>
                            </SheetFooter>
                        </SheetContent>
                    </Sheet>
                </div>

                <CollapsibleTrigger asChild>
                    <Button
                        variant="outline"
                        className="hidden h-12 md:inline-flex"
                        disabled={disabled}
                        aria-label={filterLabel}
                    >
                        {trigger}
                        <ChevronDown
                            aria-hidden="true"
                            className={panelOpen ? 'rotate-180' : ''}
                        />
                    </Button>
                </CollapsibleTrigger>
            </div>

            <div className="hidden md:block">
                <CollapsibleContent>
                    <div className="rounded-lg border border-line bg-surface p-6">
                        <FilterFields
                            filters={filters}
                            update={update}
                            idPrefix="inline"
                            className="lg:grid-cols-2"
                        />
                    </div>
                </CollapsibleContent>
            </div>

            <div className="flex min-h-6 flex-wrap items-center gap-x-4 gap-y-1 text-sm text-ink-muted">
                <p role="status" aria-live="polite">
                    {choice(
                        t('menu.results', { count: resultCount }),
                        resultCount,
                    )}
                    {count > 0 &&
                        ` · ${choice(t('menu.filters.active', { count }), count)}`}
                </p>
                {hasAnyFilter(filters) && (
                    <button
                        type="button"
                        onClick={clear}
                        className="-my-2 inline-flex min-h-11 items-center font-semibold text-ember underline underline-offset-4 outline-hidden focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember"
                    >
                        {t('menu.filters.clear_all')}
                    </button>
                )}
            </div>
        </Collapsible>
    );
}
