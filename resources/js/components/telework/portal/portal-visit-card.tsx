import { useForm } from '@inertiajs/react';
import { House } from 'lucide-react';
import PortalEvidenceController from '@/actions/App/Modules/Telework/Http/Controllers/PortalEvidenceController';
import { Button } from '@/components/ui/button';
import type { PortalVisit } from '@/types';

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
        <li className="flex items-start gap-3.5 rounded-xl border bg-card p-4">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
                <House aria-hidden className="size-5" />
            </span>
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <p className="text-base font-semibold">
                    La Comisión pide visitar su lugar de trabajo
                </p>
                <p className="text-base text-muted-foreground">
                    {visit.workplace}
                </p>
                <p className="mt-1 text-base">
                    Solo irán con su permiso. Al autorizar, queda constancia de
                    la fecha, la hora y la dirección IP desde la que lo
                    confirma.
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
            </div>
        </li>
    );
}
