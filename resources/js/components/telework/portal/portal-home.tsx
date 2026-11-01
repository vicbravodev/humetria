import { PartyPopper } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PortalNoticesLink from '@/components/telework/portal/portal-notices-link';
import PortalEquipmentSection from '@/components/telework/portal/portal-equipment-section';
import PortalDocuments from '@/components/telework/portal/portal-documents';
import PortalChecklistCard from '@/components/telework/portal/portal-checklist-card';
import PortalPhotoRequestCard from '@/components/telework/portal/portal-photo-request-card';
import PortalVisitCard from '@/components/telework/portal/portal-visit-card';
import { nothingPending } from '@/lib/telework/portal';
import type { PortalPending } from '@/types';

/** What the person has to do and what they already sent, in plain words ("usted"). */
export default function PortalHome({
    name,
    token,
    canNotify,
    pending,
}: {
    name: string;
    token: string;
    canNotify: boolean;
    pending: PortalPending;
}) {
    const nothing = nothingPending(pending);

    return (
        <div className="flex w-full max-w-2xl flex-col gap-6">
            <div className="flex flex-col gap-2">
                <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                    Hola, {name}
                </h1>
                <p className="text-muted-foreground">
                    Aquí verá lo que su empresa le pide para su teletrabajo.
                    Este enlace es solo suyo: no lo comparta.
                </p>
            </div>
            {nothing && (
                <EmptyState icon={PartyPopper} title="Nada pendiente por ahora">
                    Cuando su empresa le pida algo, lo verá aquí y le avisaremos
                    por correo.
                </EmptyState>
            )}
            <PortalDocuments documents={pending.documents} token={token} />
            <PortalEquipmentSection
                equipment={pending.equipment}
                token={token}
            />
            {(pending.photos.length > 0 || pending.visits.length > 0) && (
                <section aria-labelledby="portal-asked" className="grid gap-3">
                    <h2 id="portal-asked" className="text-xl font-semibold">
                        La Comisión le pide
                    </h2>
                    <ul className="grid gap-3">
                        {pending.photos.map((request) => (
                            <PortalPhotoRequestCard
                                key={request.id}
                                request={request}
                                token={token}
                            />
                        ))}
                        {pending.visits.map((visit) => (
                            <PortalVisitCard
                                key={visit.id}
                                visit={visit}
                                token={token}
                            />
                        ))}
                    </ul>
                </section>
            )}
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
                                token={token}
                                pending
                            />
                        ))}
                    </ul>
                </section>
            )}
            {canNotify && <PortalNoticesLink token={token} />}
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
                                token={token}
                                pending={false}
                            />
                        ))}
                    </ul>
                </section>
            )}
        </div>
    );
}
