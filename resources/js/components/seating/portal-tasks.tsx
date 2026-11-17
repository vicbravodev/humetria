import {
    Armchair,
    CircleCheck,
    HeartPulse,
    Lock,
    ShieldCheck,
    TriangleAlert,
} from 'lucide-react';
import PersonFrame from '@/components/person-frame';
import PortalHeading from '@/components/seating/portal-heading';
import PortalTaskCard from '@/components/seating/portal-task-card';
import { shortDate } from '@/lib/calendar-date';
import { acknowledgment, answers, report } from '@/routes/portal/seating';

const AREA = { icon: Armchair, label: 'Ley Silla' };

/**
 * The person's Ley Silla page (finding 13, board SillaHub): a list of tasks, each opening its own screen. What waits for the
 * person first (the result to confirm), then what they decide: the two health questions (voluntary) and a damaged seat
 * (anonymous). Nothing of health is on this page.
 */
export default function PortalTasks({
    page,
}: {
    page: {
        slug: string;
        company: string;
        homeUrl: string;
        pending: boolean;
        acknowledgedOn: string | null;
        sentOn: string | null;
    };
}) {
    return (
        <PersonFrame company={page.company} area={AREA} back={page.homeUrl}>
            <div className="flex w-full max-w-2xl flex-col gap-6">
                <PortalHeading eyebrow="Ley Silla" title="Su trabajo de pie">
                    Nos ayuda a decidir qué asiento y qué descansos le
                    corresponden.
                </PortalHeading>
                {(page.pending || page.acknowledgedOn !== null) && (
                    <section className="flex flex-col gap-2">
                        <h2 className="text-caption font-medium tracking-wide text-muted-foreground uppercase">
                            {page.pending
                                ? 'Espera su confirmación'
                                : 'Su evaluación'}
                        </h2>
                        <ul className="flex flex-col gap-3">
                            <PortalTaskCard
                                task={{
                                    href: acknowledgment.url(page.slug),
                                    icon: ShieldCheck,
                                    title: page.pending
                                        ? 'Confirme las medidas de su puesto'
                                        : 'Resultado de su evaluación',
                                    description:
                                        page.acknowledgedOn === null
                                            ? 'Su evaluación ya está firmada'
                                            : `Confirmó el ${shortDate(page.acknowledgedOn)}`,
                                    waiting: page.pending,
                                }}
                            />
                        </ul>
                    </section>
                )}
                <section className="flex flex-col gap-2">
                    <h2 className="text-caption font-medium tracking-wide text-muted-foreground uppercase">
                        Usted decide
                    </h2>
                    <ul className="flex flex-col gap-3">
                        <PortalTaskCard
                            task={{
                                href: answers.url(page.slug),
                                icon: HeartPulse,
                                title: 'Cuéntenos de su trabajo de pie',
                                description:
                                    page.sentOn === null
                                        ? '2 preguntas de salud'
                                        : `Contestó el ${shortDate(page.sentOn)}`,
                                tag: {
                                    icon: CircleCheck,
                                    label: 'Es voluntario',
                                },
                            }}
                        />
                        <PortalTaskCard
                            task={{
                                href: report.url(page.slug),
                                icon: TriangleAlert,
                                title: 'Avisar de un asiento dañado',
                                description:
                                    'Llega a la empresa y a la Comisión',
                                tag: { icon: Lock, label: 'Es anónimo' },
                            }}
                        />
                    </ul>
                </section>
            </div>
        </PersonFrame>
    );
}
