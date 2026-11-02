import { useForm } from '@inertiajs/react';
import { House } from 'lucide-react';
import PortalEvidenceController from '@/actions/App/Modules/Telework/Http/Controllers/PortalEvidenceController';
import { Button } from '@/components/ui/button';
import type { PortalVisit } from '@/types';
import PortalCard from '@/components/telework/portal/portal-card';

/** A visit to authorize: the person confirms in writing, here, that the Comisión may go to their workplace. */
export default function PortalVisitCard({
    visit,
    token,
}: {
    visit: PortalVisit;
    token: string;
}) {
    const form = useForm({});

    return (
        <PortalCard
            icon={House}
            title={<>La Comisión pide visitar su lugar de trabajo</>}
            detail={visit.workplace}
        >
            <p className="mt-1 text-base">
                Solo irán con su permiso. Al autorizar, queda constancia de la
                fecha, la hora y la dirección IP desde la que lo confirma.
            </p>
            <Button
                className="mt-2 h-12 self-start text-base"
                disabled={form.processing}
                onClick={() =>
                    form.post(
                        PortalEvidenceController.confirmVisit.url({
                            token,
                            authorization: visit.id,
                        }),
                    )
                }
            >
                Autorizo la visita
            </Button>
        </PortalCard>
    );
}
