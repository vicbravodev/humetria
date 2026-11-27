import { Link, router } from '@inertiajs/react';
import { LogIn } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { impersonate } from '@/routes/companies';

/**
 * «Entrar como dueño» or «como RH»: the demo company is not on the companies list, so the room enters it itself. HR is
 * offered only here: a real company never lets the staff enter as its HR. While a run rebuilds the company nobody enters:
 * it is half made. During a session the guide shows either way, so the card says only that.
 */
export default function RoomEnter({
    companyId,
    running,
    session,
}: {
    companyId: string;
    running: boolean;
    session: boolean;
}) {
    return (
        <section
            aria-labelledby="room-enter"
            className="flex flex-col gap-3 card p-5"
        >
            <h3 id="room-enter" className="text-base font-semibold">
                Entrar a la demo
            </h3>
            <p className="text-sm text-pretty text-muted-foreground">
                {session
                    ? 'Como dueño o como RH. El guía aparece igual.'
                    : 'Como dueño ves la empresa completa; como RH, lo que ve quien opera el día a día. Para volver a la consola, usa «Salir».'}
            </p>
            <div className="flex flex-wrap gap-2">
                {/* Another person's session: nothing prefetched as the staff member carries over. */}
                <Button asChild>
                    <Link
                        href={impersonate(companyId)}
                        data={{ as: 'owner' }}
                        as="button"
                        disabled={running}
                        onClick={() => router.flushAll()}
                    >
                        <LogIn aria-hidden />
                        Entrar como dueño
                    </Link>
                </Button>
                <Button asChild variant="outline">
                    <Link
                        href={impersonate(companyId)}
                        data={{ as: 'hr' }}
                        as="button"
                        disabled={running}
                        onClick={() => router.flushAll()}
                    >
                        <LogIn aria-hidden />
                        Entrar como RH
                    </Link>
                </Button>
            </div>
        </section>
    );
}
