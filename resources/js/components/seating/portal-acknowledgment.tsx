import { Form } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import RiskLevel from '@/components/humetria/risk-level';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/portal/seating/acknowledgments';
import type { PortalAcknowledgment as Pending } from '@/types';

/** The result of the person's assessment and the button to confirm they were informed of it (Quinta H). */
export default function PortalAcknowledgment({
    token,
    pending,
}: {
    token: string;
    pending: Pending;
}) {
    return (
        <section
            aria-label="Resultado de su evaluación"
            className="flex flex-col gap-4 card p-5"
        >
            <div className="flex items-start gap-3">
                <ShieldCheck aria-hidden className="mt-1 size-6 text-primary" />
                <div className="flex flex-col gap-1">
                    <h2 className="text-lg font-semibold">
                        Evaluamos su trabajo de pie
                    </h2>
                    <p className="text-muted-foreground">
                        La Comisión de Seguridad e Higiene la evaluó
                        {pending.signedOn !== null && ` el ${pending.signedOn}`}
                        . Este es su nivel de riesgo:
                    </p>
                    {pending.level !== null && pending.levelLabel !== null && (
                        <p>
                            <RiskLevel
                                level={pending.level}
                                label={pending.levelLabel}
                            />
                        </p>
                    )}
                </div>
            </div>
            <p className="text-base">
                {pending.measures.length === 0
                    ? 'Su empresa le dirá qué medidas toma para cuidarle.'
                    : 'Las medidas para cuidarle:'}
            </p>
            {pending.measures.length > 0 && (
                <ul className="list-disc pl-5">
                    {pending.measures.map((measure) => (
                        <li key={measure}>{measure}</li>
                    ))}
                </ul>
            )}
            <Form {...store.form({ token })} options={{ preserveScroll: true }}>
                {({ processing }) => (
                    <Button size="lg" disabled={processing}>
                        {processing && <Spinner />}
                        Confirmo que me informaron
                    </Button>
                )}
            </Form>
        </section>
    );
}
