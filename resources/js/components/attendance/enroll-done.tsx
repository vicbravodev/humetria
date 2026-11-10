import { Link } from '@inertiajs/react';
import { CircleCheck, Smartphone } from 'lucide-react';
import EnrollProgress from '@/components/attendance/enroll-progress';
import PersonFrame from '@/components/attendance/person-frame';
import ToneTile from '@/components/attendance/tone-tile';
import { Button } from '@/components/ui/button';

/** The phone is linked (board P1, step 4): what the person can do now, and that the key stays in their phone. */
export default function EnrollDone({
    company,
    device,
    punchUrl,
}: {
    company: string;
    device: string;
    punchUrl: string;
}) {
    return (
        <PersonFrame
            company={company}
            actions={
                <Button asChild className="h-14 w-full text-lg font-semibold">
                    <Link href={punchUrl}>Ir a marcar</Link>
                </Button>
            }
        >
            <div className="flex w-full max-w-md flex-col gap-5">
                <EnrollProgress step="done" />
                <section
                    role="status"
                    className="flex flex-col items-start gap-3"
                >
                    <ToneTile icon={CircleCheck} tone="ok" large />
                    <h1 className="text-2xl leading-8 font-semibold text-balance">
                        Listo, su teléfono está vinculado
                    </h1>
                    <p className="text-muted-foreground">
                        Desde ahora puede marcar su entrada y su salida. Tiene
                        la llave en este teléfono, no en el servidor.
                    </p>
                </section>
                <section className="flex items-center gap-4 card p-5">
                    <Smartphone aria-hidden className="size-6 text-primary" />
                    <div className="flex flex-col">
                        <p className="text-lg font-semibold">{device}</p>
                        <p className="text-base text-muted-foreground">
                            Llave de acceso de este teléfono
                        </p>
                    </div>
                </section>
                <p className="text-base text-muted-foreground">
                    El enlace que recibió ya no sirve: si cambia de teléfono,
                    pida a Recursos Humanos uno nuevo.
                </p>
            </div>
        </PersonFrame>
    );
}
