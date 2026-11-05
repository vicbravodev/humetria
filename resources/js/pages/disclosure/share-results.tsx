import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import ShareDelivery from '@/components/disclosure/share-delivery';
import ShareIncludes from '@/components/disclosure/share-includes';
import SharePreview from '@/components/disclosure/share-preview';
import Notice from '@/components/humetria/notice';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { nom035Crumbs } from '@/lib/nom035/crumbs';
import { policy as policyRoute } from '@/routes/tenant/disclosure';
import { publish } from '@/routes/tenant/disclosure/results';
import type { ShareResultsProps } from '@/types';

/**
 * Board V2-N035-Compartir: a short summary of the results for the people of the center (num. 7.8), with what each one will
 * read beside it. It is published to their page, with its poster to print; never a name, an answer or a small area.
 */
export default function ShareResults(props: ShareResultsProps) {
    const tenantSlug = useTenantSlug();
    const [withMeasures, setWithMeasures] = useState(props.showMeasures);
    setLayoutProps({
        breadcrumbs: nom035Crumbs(tenantSlug, props.workCenter, {
            title: 'Compartir los resultados',
        }),
    });

    return (
        <>
            <Head
                title={`Compartir los resultados · ${props.workCenter.name}`}
            />
            <div className="mx-auto grid w-full max-w-[1180px] gap-10 px-4 py-6 min-[1100px]:grid-cols-[minmax(0,1fr)_360px] md:px-12 md:py-10">
                <div className="flex min-w-0 flex-col gap-7">
                    <header className="flex flex-col gap-2">
                        <h1 className="text-3xl leading-10 font-semibold tracking-[-0.015em]">
                            Comparte los resultados con tu equipo
                        </h1>
                        <p className="text-lg text-muted-foreground">
                            Armamos un resumen corto y claro con lo que ya
                            capturaste. A la derecha ves lo que leerá cada
                            persona.
                        </p>
                    </header>
                    <Form
                        {...publish.form([tenantSlug, props.campaign.id])}
                        options={{ preserveScroll: true }}
                        className="flex flex-col gap-7"
                    >
                        {({ processing }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="back"
                                    value="share"
                                />
                                <section
                                    aria-labelledby="share-includes"
                                    className="flex flex-col gap-3"
                                >
                                    <h2
                                        id="share-includes"
                                        className="text-lg font-semibold"
                                    >
                                        Qué incluye
                                    </h2>
                                    <ShareIncludes
                                        props={props}
                                        withMeasures={withMeasures}
                                        onMeasures={setWithMeasures}
                                    />
                                    {!props.policy && (
                                        <Notice
                                            action={
                                                <Link
                                                    href={policyRoute(
                                                        tenantSlug,
                                                    )}
                                                    className="touch-hit text-sm font-semibold underline underline-offset-4"
                                                >
                                                    Terminar política
                                                </Link>
                                            }
                                        >
                                            Tu política aún no está publicada en
                                            este centro. El resumen sale igual;
                                            la política llega al publicarla.
                                        </Notice>
                                    )}
                                    <p className="text-sm text-muted-foreground">
                                        Nunca incluye nombres, respuestas de
                                        nadie ni resultados de áreas con pocas
                                        personas.
                                    </p>
                                </section>
                                <ShareDelivery
                                    published={props.published}
                                    canPublish={props.canPublish}
                                    center={props.workCenter.name}
                                    processing={processing}
                                />
                            </>
                        )}
                    </Form>
                </div>
                <SharePreview props={props} withMeasures={withMeasures} />
            </div>
        </>
    );
}
