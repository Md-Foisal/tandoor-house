import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

/**
 * Toasts are styled as order slips: paper, a torn top edge, ink text.
 */
function Toaster({ ...props }: ToasterProps) {
    const { resolvedAppearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={resolvedAppearance}
            className="toaster group"
            position="bottom-center"
            offset={{ bottom: 88 }}
            mobileOffset={{ bottom: 88 }}
            toastOptions={{
                classNames: {
                    toast: 'order-slip !rounded-md !border-line !bg-surface !text-ink !shadow-lift !font-sans',
                    description: '!text-ink-muted',
                    actionButton: '!bg-ember !text-ember-ink !font-semibold',
                    success: '[&_[data-icon]]:!text-leaf',
                    error: '[&_[data-icon]]:!text-chilli',
                },
            }}
            {...props}
        />
    );
}

export { Toaster };
