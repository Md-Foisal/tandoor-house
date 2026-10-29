import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { Field } from '@/components/form/field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/lib/i18n';
import type { CategoryRow, Period } from './types';

type FormData = {
    name: string;
    description: string;
    service_periods: Period[];
};

export function CategoryDialog({
    open,
    category,
    periods,
    online,
    onOpenChange,
}: {
    open: boolean;
    category: CategoryRow | null;
    periods: Period[];
    online: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const t = useT();
    const form = useForm<FormData>({
        name: '',
        description: '',
        service_periods: [],
    });
    const { data, setData, errors, processing } = form;

    useEffect(() => {
        if (open) {
            form.clearErrors();
            form.setData({
                name: category?.name ?? '',
                description: category?.description ?? '',
                service_periods: category?.service_periods ?? [],
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, category]);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (category) {
            form.patch(`/staff/menu/categories/${category.id}`, options);
        } else {
            form.post('/staff/menu/categories', options);
        }
    };

    const toggle = (period: Period, on: boolean) =>
        setData(
            'service_periods',
            on
                ? [...data.service_periods, period]
                : data.service_periods.filter((p) => p !== period),
        );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {t(
                                category
                                    ? 'staff_menu.categories.form_edit'
                                    : 'staff_menu.categories.form_new',
                            )}
                        </DialogTitle>
                    </DialogHeader>
                    <Field
                        name="category-name"
                        label={t('staff_menu.categories.name')}
                        error={errors.name}
                        required
                    >
                        {(props) => (
                            <Input
                                {...props}
                                className="h-11"
                                value={data.name}
                                maxLength={80}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Field
                        name="category-description"
                        label={t('staff_menu.categories.description')}
                        hint={t('staff_menu.categories.description_hint')}
                        error={errors.description}
                    >
                        {(props) => (
                            <Textarea
                                {...props}
                                rows={2}
                                value={data.description}
                                maxLength={255}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                            />
                        )}
                    </Field>
                    <fieldset className="grid gap-2">
                        <legend className="mb-1 text-sm font-medium">
                            {t('staff_menu.categories.periods')}
                        </legend>
                        <div className="flex flex-wrap gap-4">
                            {periods.map((period) => (
                                <Label
                                    key={period}
                                    className="flex min-h-11 items-center gap-2"
                                >
                                    <Checkbox
                                        checked={data.service_periods.includes(
                                            period,
                                        )}
                                        onCheckedChange={(on) =>
                                            toggle(period, on === true)
                                        }
                                    />
                                    {t(`staff_menu.periods.${period}`)}
                                </Label>
                            ))}
                        </div>
                        <p className="text-sm text-ink-muted">
                            {t('staff_menu.categories.periods_hint')}
                        </p>
                    </fieldset>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            size="lg"
                            onClick={() => onOpenChange(false)}
                        >
                            {t('staff_menu.categories.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            size="lg"
                            disabled={processing || !online}
                            title={
                                online
                                    ? undefined
                                    : t('staff_menu.editor.offline')
                            }
                        >
                            {t('staff_menu.categories.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
