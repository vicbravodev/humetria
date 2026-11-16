import { Link } from '@inertiajs/react';
import { ArrowLeft, PartyPopper } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PortalNoticesLink from '@/components/telework/portal/portal-notices-link';
import PortalEquipmentSection from '@/components/telework/portal/portal-equipment-section';
import PortalDocuments from '@/components/telework/portal/portal-documents';
import PortalChecklistCard from '@/components/telework/portal/portal-checklist-card';
import PortalAsked from '@/components/telework/portal/portal-asked';
import { nothingPending } from '@/lib/telework/portal';
import { home } from '@/routes/worker';
import type { PortalPending } from '@/types';

/** The telework section of the person: what they have to do and what they already sent, in plain words ("usted"). */
export default function PortalHome({
    slug,
    canNotify,
    pending,
}: {
    slug: string;
    canNotify: boolean;
    pending: PortalPending;
}) {
    const nothing = nothingPending(pending);

    return (
        <div className="flex w-full max-w-2xl flex-col gap-6">
            <Link
                href={home.url(slug)}
                className="touch-hit flex w-fit items-center gap-1.5 text-base font-medium text-primary"
            >
                <ArrowLeft aria-hidden className="size-4" />
                Volver a mi portal
            </Link>
            <div className="flex flex-col gap-2">
                <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                    Su teletrabajo
                </h1>
                <p className="text-muted-foreground">
                    Lo que su empresa y la Comisión de Seguridad e Higiene le
                    piden, y lo que ya envió.
                </p>
            </div>
            {canNotify && <PortalNoticesLink slug={slug} />}
            {nothing && (
                <EmptyState icon={PartyPopper} title="Nada pendiente por ahora">
                    Cuando su empresa le pida algo, lo verá aquí y le avisaremos
                    por correo. Si necesita algo antes, pida a Recursos Humanos
                    de su empresa.
                </EmptyState>
            )}
            <PortalDocuments documents={pending.documents} slug={slug} />
            <PortalEquipmentSection equipment={pending.equipment} slug={slug} />
            <PortalAsked pending={pending} slug={slug} />
            {pending.checklists.length > 0 && (
                <section
                    aria-labelledby="portal-pending"
                    className="grid gap-3"
                >
                    <h2 id="portal-pending" className="text-xl font-semibold">
                        Por contestar
                    </h2>
                    <ul className="grid gap-3">
                        {pending.checklists.map((checklist) => (
                            <PortalChecklistCard
                                key={checklist.id}
                                checklist={checklist}
                                slug={slug}
                                pending
                            />
                        ))}
                    </ul>
                </section>
            )}
            {pending.sent.length > 0 && (
                <section aria-labelledby="portal-sent" className="grid gap-3">
                    <h2 id="portal-sent" className="text-xl font-semibold">
                        Ya enviado
                    </h2>
                    <ul className="grid gap-3">
                        {pending.sent.map((checklist) => (
                            <PortalChecklistCard
                                key={checklist.id}
                                checklist={checklist}
                                slug={slug}
                                pending={false}
                            />
                        ))}
                    </ul>
                </section>
            )}
        </div>
    );
}
