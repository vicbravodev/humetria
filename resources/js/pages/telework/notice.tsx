import { Head, setLayoutProps } from '@inertiajs/react';
import AccidentAnswer from '@/components/telework/accident-answer';
import AccidentClose from '@/components/telework/accident-close';
import AccidentHeader from '@/components/telework/accident-header';
import AccidentPerson from '@/components/telework/accident-person';
import AccidentPlaceReview from '@/components/telework/accident-place-review';
import AccidentTimeline from '@/components/telework/accident-timeline';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { dashboard } from '@/routes/tenant';
import { route } from '@/routes/tenant/telework';
import type { AccidentNotice, Choice } from '@/types';

/** Board V3-Aviso: an accident notice (6.9), followed up in three steps, with who sent it, its timeline and the place. */
export default function Notice({
    notice,
    choices,
}: {
    notice: AccidentNotice;
    choices: Choice[];
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Panel', href: dashboard(tenantSlug) },
            {
                title: `NOM-037 · ${notice.workCenter.name}`,
                href: route([tenantSlug, notice.workCenter.id]),
            },
            { title: `Aviso de ${notice.person}` },
        ],
    });

    return (
        <>
            <Head title={`Aviso de ${notice.person}`} />
            <div className="mx-auto grid w-full max-w-[1180px] gap-8 px-4 py-6 min-[1240px]:grid-cols-[minmax(0,1fr)_340px] md:px-12 md:py-10">
                <div className="flex min-w-0 flex-col gap-6">
                    <AccidentHeader notice={notice} />
                    <section
                        aria-label="Pasos para atender el aviso"
                        className="grid overflow-hidden card [&>*:first-child]:border-t-0 [&>form>*:first-child]:border-t-0"
                    >
                        <AccidentAnswer notice={notice} choices={choices} />
                        <AccidentClose notice={notice} />
                    </section>
                    <p className="text-sm text-muted-foreground">
                        Los trámites con el IMSS y los exámenes médicos se
                        llevan fuera de Humetría.
                    </p>
                </div>
                <aside className="flex flex-col gap-4">
                    <AccidentPerson notice={notice} />
                    <AccidentTimeline notice={notice} />
                    <AccidentPlaceReview notice={notice} />
                </aside>
            </div>
        </>
    );
}
