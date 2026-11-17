import { Link } from '@inertiajs/react';
import PortalFrame from '@/components/seating/portal-frame';
import PortalHeading from '@/components/seating/portal-heading';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/portal/seating';

/**
 * Before the two health questions (finding 13, board SillaConsent): what they are for, that answering is voluntary, and who
 * sees the answers, in the company's own words (criterio C-18), whole. Saying no costs the same as saying yes: two buttons
 * of the same size, and «Prefiero no contestar» goes back to the tasks with nothing kept.
 */
export default function PortalConsent({
    slug,
    company,
    notice,
    onAccept,
}: {
    slug: string;
    company: string;
    notice: string;
    onAccept: () => void;
}) {
    return (
        <PortalFrame
            company={company}
            privacy="identified"
            back={show.url(slug)}
            actions={
                <>
                    <Button
                        className="h-14 w-full text-lg font-semibold"
                        data-test="portal-accept"
                        onClick={onAccept}
                    >
                        Acepto, quiero contestar
                    </Button>
                    <Button
                        variant="outline"
                        className="h-14 w-full text-lg font-medium"
                        asChild
                    >
                        <Link href={show.url(slug)}>Prefiero no contestar</Link>
                    </Button>
                </>
            }
        >
            <PortalHeading
                eyebrow="Ley Silla · 2 preguntas"
                title="Su trabajo de pie"
            >
                Nos ayuda a decidir qué asiento y qué descansos le corresponden.
                Contestar es voluntario.
            </PortalHeading>
            <section className="flex flex-col gap-2 card p-4">
                <h2 className="text-caption font-medium tracking-wide text-muted-foreground uppercase">
                    Quién ve sus respuestas
                </h2>
                <p className="text-[17px] leading-[26px]">{notice}</p>
            </section>
        </PortalFrame>
    );
}
