import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import ComplaintsHeader from '@/components/complaints/complaints-header';
import ProcedureFacts from '@/components/complaints/procedure-facts';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/complaints';

const RULES = [
    'Solo ves los casos que te asignaron, y nunca una queja contra tu propio rol.',
    'Primero confirma que la recibiste: el acuse ya viene redactado con el día de la respuesta.',
    'Lo que escribes a la persona lo lee con su referencia y su clave; las notas internas no las ve.',
    'Si conoces a quien está involucrada o tienes un conflicto, excúsate: el caso sigue con las demás personas designadas.',
    'Cada vez que abres un caso queda en la bitácora. Ningún correo lleva lo que dice una queja.',
];

/** «Cómo se atiende» (board V4-Menu-Roles): the procedure the people read, the deadlines and how a handler works a case. */
export default function Procedure({
    procedure,
    acknowledgeWithinDays,
    resolveWithinDays,
    retentionMonths,
}: {
    procedure: string;
    acknowledgeWithinDays: number;
    resolveWithinDays: number;
    retentionMonths: number;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Quejas', href: index(tenantSlug) },
            { title: 'Cómo se atiende' },
        ],
    });

    return (
        <>
            <Head title="Cómo se atiende" />
            <div className="mx-auto flex w-full max-w-[880px] flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <ComplaintsHeader
                    eyebrow="NOM-035 · numeral 8.1 b"
                    title="Cómo se atiende"
                    lead="Lo que la empresa promete a quien presenta una queja, y cómo la atiendes tú."
                >
                    <Button variant="outline" asChild>
                        <Link href={index(tenantSlug)}>
                            Ver mis casos
                            <ArrowRight aria-hidden />
                        </Link>
                    </Button>
                </ComplaintsHeader>
                <section
                    aria-labelledby="procedure"
                    className="flex flex-col gap-5 card p-6"
                >
                    <div className="flex flex-col gap-1">
                        <h2
                            id="procedure"
                            className="text-xl leading-7 font-semibold"
                        >
                            Lo que leen las personas
                        </h2>
                        <p className="text-muted-foreground">
                            El procedimiento que la empresa escribió. Lo leen
                            antes de presentar una queja.
                        </p>
                    </div>
                    <p className="whitespace-pre-line">{procedure}</p>
                    <ProcedureFacts
                        acknowledgeWithinDays={acknowledgeWithinDays}
                        resolveWithinDays={resolveWithinDays}
                        retentionMonths={retentionMonths}
                    />
                    <p className="text-caption text-muted-foreground">
                        Estos plazos los fija la empresa; la norma no los marca.
                    </p>
                </section>
                <section
                    aria-labelledby="how"
                    className="flex flex-col gap-4 card p-6"
                >
                    <h2 id="how" className="text-xl leading-7 font-semibold">
                        Cómo atiendes un caso
                    </h2>
                    <ol className="flex flex-col gap-3">
                        {RULES.map((rule, position) => (
                            <li key={rule} className="flex items-start gap-3">
                                <span
                                    aria-hidden
                                    className="inline-flex size-7 shrink-0 items-center justify-center rounded-full border-2 border-primary text-sm font-semibold text-primary"
                                >
                                    {position + 1}
                                </span>
                                <span className="pt-0.5">{rule}</span>
                            </li>
                        ))}
                    </ol>
                </section>
            </div>
        </>
    );
}
