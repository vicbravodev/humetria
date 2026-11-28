import { CircleAlert, Megaphone } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * «Así lo verá la empresa» (tablero Avisos): the banner of the form's message over a sketch of the panel, with the tones the
 * panel gives it. A person can close it; it comes back only if the announcement changes.
 */
export default function AnnouncementPreview({
    kind,
    message,
}: {
    kind: string;
    message: string;
}) {
    const alarm = kind === 'maintenance' || kind === 'incident';
    const Icon = alarm ? CircleAlert : Megaphone;

    return (
        <section className="card">
            <header className="border-b px-6 py-4">
                <h2 className="text-base font-semibold">
                    Así lo verá la empresa
                </h2>
            </header>
            <div className="grid gap-3.5 px-6 py-5">
                <div
                    data-slot="announcement-preview"
                    className="rounded-xl border bg-background p-3.5"
                >
                    <div
                        className={cn(
                            'flex items-start gap-3 rounded-xl px-4 py-3',
                            alarm
                                ? 'bg-warning text-warning-foreground'
                                : 'bg-accent text-foreground',
                        )}
                    >
                        <Icon aria-hidden className="mt-0.5 size-5 shrink-0" />
                        <p className="min-w-0 flex-1 text-meta break-words">
                            {message.trim() === ''
                                ? 'Aquí aparece el mensaje que escribas.'
                                : message}
                        </p>
                    </div>
                    <div
                        aria-hidden
                        className="mt-3 h-2.5 w-3/5 rounded-md bg-muted"
                    />
                    <div
                        aria-hidden
                        className="mt-2 h-2.5 w-2/5 rounded-md bg-muted"
                    />
                </div>
                <p className="text-meta text-muted-foreground">
                    Cada persona puede cerrar el banner; vuelve a aparecer solo
                    si el aviso cambia.
                </p>
            </div>
        </section>
    );
}
