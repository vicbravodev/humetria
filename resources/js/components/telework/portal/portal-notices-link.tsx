import { Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';
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
                className="flex items-start gap-3.5 card p-4 hover:bg-accent/50"
            >
                <IconTile icon={FileText} />
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
