import { Inbox } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PortalSections from '@/components/telework/portal/portal-sections';
import type { PortalSection } from '@/types';

/** The start of the portal opened by link: whom it greets and the tools their company has for them ("usted"). */
export default function PortalStart({
    name,
    sections,
}: {
    name: string;
    sections: PortalSection[];
}) {
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
            {sections.length === 0 && (
                <EmptyState icon={Inbox} title="Nada pendiente por ahora">
                    Cuando su empresa le pida algo, lo verá aquí y le avisaremos
                    por correo. Si necesita algo antes, pida a Recursos Humanos
                    de su empresa.
                </EmptyState>
            )}
        </div>
    );
}
