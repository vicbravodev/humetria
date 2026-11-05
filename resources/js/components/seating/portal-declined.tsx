import { Button } from '@/components/ui/button';

/** When the person prefers not to answer (board LS-Portal): it has no consequence, and they can change their mind. */
export default function PortalDeclined({ onAnswer }: { onAnswer: () => void }) {
    return (
        <section className="flex flex-col items-start gap-3">
            <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                Está bien
            </h1>
            <p>
                No guardamos nada: su evaluación se hace sin estos datos y no
                tiene ninguna consecuencia. Si cambia de opinión, puede
                contestar cuando quiera desde este enlace.
            </p>
            <Button variant="outline" onClick={onAnswer}>
                Contestar ahora
            </Button>
        </section>
    );
}
