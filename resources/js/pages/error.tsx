import { Head, Link, usePage } from '@inertiajs/react';
import {
    CircleAlert,
    Clock,
    Lock,
    Search,
    Wrench,
    type LucideIcon,
} from 'lucide-react';
import Logo from '@/components/humetria/logo';
import StatusMessage from '@/components/humetria/status-message';
import { Button } from '@/components/ui/button';
import { errorMessage, roleLimit } from '@/lib/error-page';
import { dashboard, home } from '@/routes';

const ICONS: Record<number, LucideIcon> = {
    403: Lock,
    404: Search,
    419: Clock,
    503: Wrench,
};

/** The app's own page for an error status (pantallas: Error-403, Error-404); the status code stays the server's. */
export default function ErrorPage({ status }: { status: number }) {
    const { auth, tenant } = usePage().props;
    const message = errorMessage(status);
    const limit = roleLimit(status, tenant);

    return (
        <div className="flex min-h-dvh flex-col bg-background">
            <Head title={message.title} />
            <header className="px-4 py-7 sm:px-10">
                <Link
                    href={home()}
                    className="touch-hit inline-block rounded-md"
                >
                    <Logo className="h-[26px]" />
                </Link>
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
                    actions={
                        <Button asChild>
                            {auth.user === null ? (
                                <Link href={home()}>Ir al inicio</Link>
                            ) : (
                                <Link href={dashboard()}>Volver al panel</Link>
                            )}
                        </Button>
                    }
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
                </StatusMessage>
            </main>
        </div>
    );
}
