import { Link } from '@inertiajs/react';
import { ChevronRight, KeyRound } from 'lucide-react';
import LeaveButton from '@/components/worker-access/leave-button';
import IconTile from '@/components/humetria/icon-tile';
import PersonHeading from '@/components/humetria/person-heading';
import { initials } from '@/lib/worker-access/portal';

const CAPTION =
    'text-caption font-medium tracking-wide text-muted-foreground uppercase';

/** «Cuenta»: who entered, a key for this device when they may add one, and «Salir». */
export default function HomeAccount({
    company,
    name,
    slug,
    keyUrl,
    hasKey,
    leaveUrl,
}: {
    company: string;
    name: string;
    slug: string;
    keyUrl: string | null;
    hasKey: boolean;
    leaveUrl: string;
}) {
    return (
        <>
            <PersonHeading title="Su cuenta" />
            <div className="flex items-center gap-3.5 card p-4">
                <span
                    aria-hidden
                    className="flex size-12 shrink-0 items-center justify-center rounded-full bg-accent font-semibold text-primary"
                >
                    {initials(name)}
                </span>
                <span className="flex min-w-0 flex-col">
                    <span className="text-base font-semibold">{name}</span>
                    <span className="text-base text-muted-foreground">
                        {company}
                    </span>
                </span>
            </div>
            {keyUrl !== null && (
                <section className="flex flex-col gap-2">
                    <h2 className={CAPTION}>Entrar más rápido</h2>
                    <Link
                        href={keyUrl}
                        className="flex min-h-16 items-center gap-3.5 card p-4 transition-[background-color,scale] duration-150 ease-out hover:bg-accent/50 active:scale-[0.99]"
                    >
                        <IconTile icon={KeyRound} />
                        <span className="flex min-w-0 flex-1 flex-col">
                            <span className="text-base font-semibold">
                                {hasKey
                                    ? 'Agregar una llave aquí'
                                    : 'Crear una llave aquí'}
                            </span>
                            <span className="text-base text-muted-foreground">
                                Entre con su huella, sin esperar un correo
                            </span>
                        </span>
                        <ChevronRight
                            aria-hidden="true"
                            className="size-5 shrink-0 text-muted-foreground"
                        />
                    </Link>
                </section>
            )}
            <section className="flex flex-col gap-2">
                <h2 className={CAPTION}>Al terminar</h2>
                <LeaveButton url={leaveUrl} slug={slug} />
                <p className="text-base text-muted-foreground">
                    Sobre todo si usa un dispositivo compartido.
                </p>
            </section>
        </>
    );
}
