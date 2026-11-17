import { Head, usePage } from '@inertiajs/react';
import {
    CircleAlert,
    Clock,
    Lock,
    Search,
    Wrench,
    type LucideIcon,
} from 'lucide-react';
import ErrorPageAction from '@/components/error-page-action';
import Logo from '@/components/humetria/logo';
import StatusMessage from '@/components/humetria/status-message';
import RequestIdNote from '@/components/request-id-note';
import { errorMessage, roleLimit } from '@/lib/error-page';
import { home } from '@/routes';

const ICONS: Record<number, LucideIcon> = {
    403: Lock,
    404: Search,
    419: Clock,
    429: Clock,
    503: Wrench,
};

/**
 * The app's own page for an error status (pantallas: Error-403, Error-404); the status code stays the server's. On the
 * survey it speaks "usted" and Humetría is only the logo, as in the survey's own frame.
 */
export default function ErrorPage({
    status,
    survey,
    screen,
    requestId,
}: {
    status: number;
    survey: boolean;
    /** The page of a time clock screen (kiosk): not found means not activated or revoked. */
    screen: boolean;
    /** The id of this request (X-Request-Id, and every log line of it): what a person quotes to support. */
    requestId: string | null;
}) {
    const { tenant } = usePage().props;
    const message = errorMessage(status, survey, screen);
    const limit = roleLimit(status, tenant);

    return (
        <div className="flex min-h-dvh flex-col bg-background">
            <Head title={message.title} />
            <header className="px-4 py-7 sm:px-10">
                {survey ? (
                    <Logo className="h-[26px]" />
                ) : (
                    <a
                        href={home().url}
                        className="touch-hit inline-block rounded-md"
                    >
                        <Logo className="h-[26px]" />
                    </a>
                )}
            </header>
            <main className="flex flex-1 items-center justify-center px-6 pt-6 pb-30">
                <StatusMessage
                    icon={ICONS[status] ?? CircleAlert}
                    caption={message.caption}
                    title={
                        limit === null
                            ? message.title
                            : 'Esta sección no está en tu rol'
                    }
                    actions={<ErrorPageAction survey={survey} />}
                >
                    {limit === null ? (
                        message.body
                    ) : (
                        <>
                            Tu rol en {limit.company} es{' '}
                            <strong className="font-semibold text-foreground">
                                {limit.role}
                            </strong>
                            , que {limit.summary}. Para entrar aquí, pídele a
                            quien administra la cuenta que cambie tu rol.
                        </>
                    )}
                    {status >= 500 && <RequestIdNote requestId={requestId} />}
                </StatusMessage>
            </main>
        </div>
    );
}
