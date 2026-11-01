import { Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { show } from '@/routes/portal/notices';

/** The way into the person's written notices (numerals 6.3, 6.7, 6.9). */
export default function PortalNoticesLink({ token }: { token: string }) {
    return (
        <section aria-labelledby="portal-notices" className="grid gap-3">
            <h2 id="portal-notices" className="text-xl font-semibold">
                Avisos
            </h2>
            <Link
                href={show.url({ token })}
                className="flex items-start gap-3.5 rounded-xl border bg-card p-4 hover:bg-accent/50"
            >
                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
                    <FileText aria-hidden className="size-5" />
                </span>
                <span className="flex flex-col gap-1">
                    <span className="text-base font-semibold">
                        Escribir un aviso
                    </span>
                    <span className="text-base text-muted-foreground">
                        Cambio de domicilio, riesgo de trabajo o una alteración
                        de sus condiciones.
                    </span>
                </span>
            </Link>
        </section>
    );
}
