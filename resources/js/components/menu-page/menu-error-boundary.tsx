import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Component } from 'react';
import { ErrorState } from '@/components/brand/states';

type Props = { children: ReactNode; title: string };

/**
 * Keeps a failed render of the menu from taking the whole page with it.
 */
export class MenuErrorBoundary extends Component<Props, { failed: boolean }> {
    state = { failed: false };

    static getDerivedStateFromError() {
        return { failed: true };
    }

    retry = () => {
        this.setState({ failed: false });
        router.reload({ only: ['categories'] });
    };

    render() {
        if (this.state.failed) {
            return (
                <div className="container-page py-10">
                    <ErrorState title={this.props.title} onRetry={this.retry} />
                </div>
            );
        }

        return this.props.children;
    }
}
