import { PartyPopper } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PortalSections from '@/components/telework/portal/portal-sections';
import PortalEquipmentSection from '@/components/telework/portal/portal-equipment-section';
import PortalDocuments from '@/components/telework/portal/portal-documents';
import PortalChecklistCard from '@/components/telework/portal/portal-checklist-card';
import PortalAsked from '@/components/telework/portal/portal-asked';
import { nothingPending } from '@/lib/telework/portal';
import type { PortalPending, PortalSection } from '@/types';

/** What the person has to do and what they already sent, in plain words ("usted"). */
export default function PortalHome({
    name,
    token,
    sections,
    pending,
}: {
    name: string;
    token: string;
    sections: PortalSection[];
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
                    Aquí encuentra lo que su empresa tiene para usted. Este
                    enlace es solo suyo: no lo comparta.
                </p>
            </div>
            <PortalSections sections={sections} />
            {nothing && (
                <EmptyState icon={PartyPopper} title="Nada pendiente por ahora">
                    Cuando su empresa le pida algo, lo verá aquí y le avisaremos
                    por correo. Si necesita algo antes, pida a Recursos Humanos
                    de su empresa.
                </EmptyState>
            )}
            <PortalDocuments documents={pending.documents} token={token} />
            <PortalEquipmentSection
                equipment={pending.equipment}
                token={token}
            />
            <PortalAsked pending={pending} token={token} />
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
