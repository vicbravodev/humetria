import { useForm } from '@inertiajs/react';
import { Info, Send } from 'lucide-react';
import { useState } from 'react';
import AckCenterBars from '@/components/disclosure/ack-center-bars';
import SignaturesDialog from '@/components/disclosure/signatures-dialog';
import Pagination from '@/components/humetria/pagination';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store } from '@/routes/tenant/disclosure/dissemination/reminders';
import type { DisseminationProps, EvidenceCenter, Paginated } from '@/types';

/**
 * «Quién confirmó que leyó la política» (board V4-Difusion-Registro): how far the confirmations of the version in force go, by
 * center, the reminder to who is pending and the paper signatures of who has no email. Confirming is reading the policy, never
 * a survey answer; the names are in the list below, for whoever manages.
 */
export default function AckCentersCard({
    version,
    progress,
    centers,
    today,
}: {
    version: NonNullable<DisseminationProps['version']>;
    progress: NonNullable<DisseminationProps['progress']>;
    centers: Paginated<EvidenceCenter>;
    today: string;
}) {
    const tenantSlug = useTenantSlug();
    const remind = useForm({});
    const [signing, setSigning] = useState(false);

    return (
        <section
            aria-labelledby="acks"
            className="flex flex-col gap-5 card p-5 md:p-7"
        >
            <div className="flex flex-col gap-1">
                <h2 id="acks" className="text-xl leading-7 font-semibold">
                    Quién confirmó que leyó la política
                </h2>
                <p className="text-sm text-muted-foreground">
                    Versión {version.version} del {version.publishedOn}.
                    Confirman desde el enlace que les llega por correo.
                </p>
            </div>
            <p className="flex flex-wrap items-baseline gap-x-3">
                <span className="text-5xl leading-none font-semibold tabular-nums">
                    {progress.percent}&nbsp;%
                </span>
                <span className="text-muted-foreground">
                    {progress.confirmed} de {progress.active} personas
                </span>
            </p>
            <AckCenterBars centers={centers.data} />
            <Pagination
                page={centers}
                label="Páginas de centros"
                pinned={false}
            />
            <p className="flex gap-2.5 rounded-lg bg-muted px-4 py-3 text-sm">
                <Info
                    aria-hidden
                    className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                />
                ¿Hay personas sin correo? Imprime la lista, que firmen en papel
                y registra cuántas firmaron. Guarda la hoja escaneada como
                evidencia.
            </p>
            <div className="flex flex-wrap items-center gap-3">
                <Button
                    disabled={remind.processing || progress.pending === 0}
                    onClick={() =>
                        remind.post(
                            store.url({
                                tenant: tenantSlug,
                                document: version.id,
                            }),
                            { preserveScroll: true },
                        )
                    }
                >
                    {remind.processing ? <Spinner /> : <Send aria-hidden />}
                    {progress.pending === 1
                        ? 'Recordar a 1 persona'
                        : `Recordar a ${progress.pending} personas`}
                </Button>
                <Button variant="ghost" onClick={() => setSigning(true)}>
                    Registrar firmas en papel
                </Button>
            </div>
            {signing && (
                <SignaturesDialog
                    open={signing}
                    onOpenChange={setSigning}
                    today={today}
                />
            )}
        </section>
    );
}
