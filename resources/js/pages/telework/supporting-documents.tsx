import { Head, setLayoutProps } from '@inertiajs/react';
import FlowProgress from '@/components/humetria/flow-progress';
import SupportingRow from '@/components/telework/supporting-row';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { teleworkCrumbs } from '@/lib/telework/crumbs';
import { supportingProgress } from '@/lib/telework/writing';
import type { SupportingDocument } from '@/types';

/** Board V2-N037-Docs: the six documents NOM-037 asks for besides the policy, each written with questions or uploaded. */
export default function TeleworkSupportingDocuments({
    documents,
    centers,
    canManage,
}: {
    documents: SupportingDocument[];
    centers: number;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const progress = supportingProgress(documents);
    const firstMissing = documents.find(({ ready }) => !ready)?.kind;
    const yours = centers === 1 ? 'tu centro' : `tus ${centers} centros`;
    setLayoutProps({
        breadcrumbs: teleworkCrumbs(tenantSlug, null, {
            title: 'Documentos de apoyo',
        }),
    });

    return (
        <>
            <Head title="Documentos de apoyo" />
            <div className="mx-auto flex w-full max-w-[1080px] flex-col gap-7 px-4 py-6 md:px-12 md:py-10">
                <header className="flex flex-wrap items-end gap-5">
                    <div className="flex min-w-0 flex-[1_1_520px] flex-col gap-2">
                        <h1 className="text-3xl leading-10 font-semibold tracking-[-0.015em]">
                            Documentos de apoyo
                        </h1>
                        <p className="text-lg text-muted-foreground">
                            Además de la política, la norma pide estos 6. Cada
                            uno se arma con 3 o 4 preguntas, o subes el que ya
                            tengas. Cubren a {yours}.
                        </p>
                    </div>
                    <div className="w-full min-w-[220px] sm:w-auto">
                        <FlowProgress
                            label={`${progress.ready} de ${documents.length} ${progress.ready === 1 ? 'listo' : 'listos'}`}
                            left={
                                progress.minutes > 0
                                    ? `≈ ${progress.minutes} min los demás`
                                    : null
                            }
                            value={progress.ready}
                            max={documents.length}
                        />
                    </div>
                </header>
                <section aria-label="Documentos" className="@container">
                    <ul className="overflow-hidden card">
                        {documents.map((document) => (
                            <SupportingRow
                                key={document.kind}
                                document={document}
                                primary={document.kind === firstMissing}
                                canManage={canManage}
                            />
                        ))}
                    </ul>
                </section>
                <p className="text-sm text-muted-foreground">
                    Al publicar cada uno, queda vigente en {yours} y se suma a
                    tu expediente; el de riesgos les llega a las personas para
                    que confirmen que lo leyeron. Las versiones anteriores no se
                    borran.
                </p>
            </div>
        </>
    );
}
