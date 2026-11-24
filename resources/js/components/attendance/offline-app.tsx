import { RotateCw, WifiOff } from 'lucide-react';
import Logo from '@/components/humetria/logo';
import StatusMessage from '@/components/humetria/status-message';
import { Button } from '@/components/ui/button';

/**
 * The shell of the installed app (`/me/{slug}/offline`): what the phone opens when there is no network and the page asked
 * for is not there. It carries no data of anyone: the company's name and the way back to the person's start, nothing else
 * (it is saved in the phone's cache and shown to whoever holds the phone). Not an Inertia page: no frame, no props.
 */
export default function OfflineApp({
    company,
    homeUrl,
}: {
    company: string;
    homeUrl: string;
}) {
    return (
        <div
            id="offline-shell"
            className="flex min-h-dvh flex-col bg-background text-lg leading-7"
        >
            <header className="flex h-[60px] shrink-0 items-center border-b bg-card px-4">
                <p className="min-w-0 truncate text-base font-semibold">
                    {company}
                </p>
            </header>
            <main className="flex w-full flex-1 items-center justify-center px-4 pb-[12dvh]">
                <StatusMessage
                    icon={WifiOff}
                    title="No hay internet"
                    actions={
                        <Button asChild size="lg">
                            <a href={homeUrl}>
                                <RotateCw aria-hidden />
                                Reintentar
                            </a>
                        </Button>
                    }
                >
                    Esta pantalla necesita conexión. Cuando vuelva a tener
                    internet, ábrala de nuevo.
                </StatusMessage>
            </main>
            <footer className="flex justify-center px-4 py-3.5">
                <Logo kind="seal" className="h-4" />
            </footer>
        </div>
    );
}
