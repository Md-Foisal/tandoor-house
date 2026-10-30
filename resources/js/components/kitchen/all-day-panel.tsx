import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useT } from '@/lib/i18n';
import type { AllDayRow } from './types';

export function AllDayPanel({
    open,
    onOpenChange,
    rows,
    disabled,
    onSoldOut,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    rows: AllDayRow[];
    disabled: boolean;
    onSoldOut: (row: AllDayRow) => void;
}) {
    const t = useT();

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="w-full sm:max-w-md">
                <SheetHeader>
                    <SheetTitle className="font-display text-display-md">
                        {t('staff_kitchen.all_day.title')}
                    </SheetTitle>
                    <SheetDescription>
                        {t('staff_kitchen.all_day.description')}
                    </SheetDescription>
                </SheetHeader>
                {rows.length === 0 ? (
                    <p className="px-4 text-ink-muted">
                        {t('staff_kitchen.all_day.empty')}
                    </p>
                ) : (
                    <ul className="flex flex-col divide-y divide-line overflow-y-auto px-4 pb-6">
                        {rows.map((row) => (
                            <li
                                key={row.menu_item_id ?? row.name}
                                className="flex min-h-14 items-center justify-between gap-3 py-2"
                            >
                                <span className="text-lg font-semibold">
                                    {t('staff_kitchen.all_day.item', {
                                        quantity: row.quantity,
                                        dish: row.name,
                                    })}
                                </span>
                                {row.menu_item_id !== null &&
                                    (row.available ? (
                                        <Button
                                            variant="outline"
                                            disabled={disabled}
                                            onClick={() => onSoldOut(row)}
                                        >
                                            {t(
                                                'staff_kitchen.all_day.sold_out',
                                            )}
                                        </Button>
                                    ) : (
                                        <span className="text-sm text-chilli">
                                            {t(
                                                'staff_kitchen.all_day.already_sold_out',
                                            )}
                                        </span>
                                    ))}
                            </li>
                        ))}
                    </ul>
                )}
            </SheetContent>
        </Sheet>
    );
}
