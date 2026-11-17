import { Form } from '@inertiajs/react';
import { Info, ShieldCheck } from 'lucide-react';
import RiskLevel from '@/components/humetria/risk-level';
import PortalFrame from '@/components/seating/portal-frame';
import PortalHeading from '@/components/seating/portal-heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { shortDate } from '@/lib/calendar-date';
import { show } from '@/routes/portal/seating';
import { store } from '@/routes/portal/seating/acknowledgments';
import type { PortalAcknowledgment as Pending } from '@/types';

/**
 * The result of the person's assessment (finding 34, board SillaAcuse): the level big, as the one thing that matters, the
 * measures, what confirming means (that they were informed, Quinta H; not that they agree), and «Confirmo que me informaron»
 * stuck to the bottom at full width. The proof was given on the way in, so it sends with no dialog.
 */
export default function PortalAcknowledgment({
    slug,
    company,
    pending,
}: {
    slug: string;
    company: string;
    pending: Pending;
}) {
    return (
        <Form {...store.form(slug)}>
            {({ processing }) => (
                <PortalFrame
                    company={company}
                    privacy="identified"
                    back={show.url(slug)}
                    actions={
                        <Button
                            className="h-14 w-full text-lg font-semibold"
                            disabled={processing}
                        >
                            {processing ? (
                                <Spinner />
                            ) : (
                                <ShieldCheck aria-hidden className="size-5" />
                            )}
                            Confirmo que me informaron
                        </Button>
                    }
                >
                    <PortalHeading
                        eyebrow="Ley Silla"
                        title="Resultado de su evaluación"
                    >
                        La Comisión de Seguridad e Higiene evaluó su trabajo de
                        pie
                        {pending.signedOn !== null &&
                            ` el ${shortDate(pending.signedOn)}`}
                        .
                    </PortalHeading>
                    {pending.level !== null && pending.levelLabel !== null && (
                        <section className="flex flex-col gap-2.5 card p-4">
                            <h2 className="text-caption font-medium tracking-wide text-muted-foreground uppercase">
                                Su nivel de riesgo
                            </h2>
                            <RiskLevel
                                level={pending.level}
                                label={pending.levelLabel}
                                size="lg"
                            />
                        </section>
                    )}
                    <section className="flex flex-col gap-2">
                        <h2 className="text-caption font-medium tracking-wide text-muted-foreground uppercase">
                            Medidas para cuidarle
                        </h2>
                        <div className="card p-4">
                            {pending.measures.length === 0 ? (
                                <p className="text-muted-foreground">
                                    Su empresa le dirá qué medidas toma para
                                    cuidarle.
                                </p>
                            ) : (
                                <ul className="list-disc pl-5">
                                    {pending.measures.map((measure) => (
                                        <li key={measure}>{measure}</li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </section>
                    <p className="flex gap-2.5 rounded-xl bg-accent px-3.5 py-3 text-base text-accent-foreground">
                        <Info aria-hidden className="mt-0.5 size-5 shrink-0" />
                        Al confirmar, queda registrado que le informaron de este
                        resultado.
                    </p>
                </PortalFrame>
            )}
        </Form>
    );
}
