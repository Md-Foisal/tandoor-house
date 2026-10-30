import { Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type Props = ComponentProps<typeof Link>;

export default function TextLink({
    className = '',
    children,
    ...props
}: Props) {
    return (
        <Link
            className={cn(
                'inline-flex min-h-11 items-center font-semibold text-ember underline decoration-1 underline-offset-4 outline-hidden hover:decoration-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember',
                className,
            )}
            {...props}
        >
            {children}
        </Link>
    );
}
