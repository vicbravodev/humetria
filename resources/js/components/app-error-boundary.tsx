import { Component, type ReactNode } from 'react';
import AppErrorFallback from '@/components/app-error-fallback';
import { errorContext, reportError } from '@/lib/error-tracking';

type State = { failed: boolean };

/**
 * Around the whole app: a page that breaks while it renders shows AppErrorFallback instead of a blank screen, and the
 * error goes to PostHog (error-tracking.ts). React only catches render errors in a class component.
 */
export default class AppErrorBoundary extends Component<
    { children: ReactNode },
    State
> {
    override state: State = { failed: false };

    static getDerivedStateFromError(): State {
        return { failed: true };
    }

    override componentDidCatch(error: Error): void {
        reportError(error);
    }

    override render() {
        return this.state.failed ? (
            <AppErrorFallback requestId={errorContext().requestId} />
        ) : (
            this.props.children
        );
    }
}
