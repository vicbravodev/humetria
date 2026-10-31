import { PartyPopper } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PortalChecklistCard from '@/components/telework/portal/portal-checklist-card';
import type { PortalPending } from '@/types';

/** What the person has to do and what they already sent, in plain words ("usted"). */
export default function PortalHome({
    name,
    pending,
}: {
    name: string;
    pending: PortalPending;
}) {
    const nothing =
        pending.checklists.length === 0 && pending.sent.length === 0;

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
                                pending={false}
                            />
                        ))}
                    </ul>
                </section>
            )}
        </div>
    );
}
