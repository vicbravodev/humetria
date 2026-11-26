import { CalendarDays, KeyRound } from 'lucide-react';
import AccountLink from '@/components/worker-access/account-link';
import DeviceKeyCard from '@/components/worker-access/device-key-card';
import LeaveButton from '@/components/worker-access/leave-button';
import PersonHeading from '@/components/humetria/person-heading';
import { initials } from '@/lib/worker-access/portal';
import type { ProofStep } from '@/types';

const CAPTION =
    'text-caption font-medium tracking-wide text-muted-foreground uppercase';

/**
 * «Cuenta»: who entered, a key for this device when they may add one, the phone's own key to mark without a connection
 * (plan B) and «Salir».
 */
export default function HomeAccount({
    company,
    name,
    slug,
    keyUrl,
    hasKey,
    deviceKey,
    weekUrl,
    leaveUrl,
}: {
    company: string;
    name: string;
    slug: string;
    keyUrl: string | null;
    hasKey: boolean;
    deviceKey: {
        optionsUrl: string;
        storeUrl: string;
        proof: ProofStep;
    } | null;
    /** Where the person's weeks are read (with their key); null where the company has no attendance. */
    weekUrl: string | null;
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
            {weekUrl !== null && (
                <section className="flex flex-col gap-2">
                    <h2 className={CAPTION}>Su asistencia</h2>
                    <AccountLink
                        href={weekUrl}
                        icon={CalendarDays}
                        title="Mis marcas"
                        detail="Semana por semana, con su huella"
                    />
                </section>
            )}
            {keyUrl !== null && (
                <section className="flex flex-col gap-2">
                    <h2 className={CAPTION}>Entrar más rápido</h2>
                    <AccountLink
                        href={keyUrl}
                        icon={KeyRound}
                        title={
                            hasKey
                                ? 'Agregar una llave aquí'
                                : 'Crear una llave aquí'
                        }
                        detail="Entre con su huella, sin esperar un correo"
                    />
                </section>
            )}
            {deviceKey !== null && (
                <section className="flex flex-col gap-2">
                    <h2 className={CAPTION}>Marcar sin internet</h2>
                    <DeviceKeyCard slug={slug} {...deviceKey} />
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
