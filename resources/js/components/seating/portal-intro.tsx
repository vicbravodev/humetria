import { Button } from '@/components/ui/button';

/**
 * The first screen of the person's Ley Silla page (board LS-Portal): what the two questions are for, that answering is
 * voluntary, who sees the answers (the privacy notice), and the choice to answer or not. Nothing is asked before it.
 */
export default function PortalIntro({
    name,
    notice,
    onAccept,
    onDecline,
}: {
    name: string;
    notice: string;
    onAccept: () => void;
    onDecline: () => void;
}) {
    return (
        <section className="flex flex-1 flex-col gap-6">
            <div className="flex flex-col gap-3">
                <p className="text-base font-medium text-primary">
                    Ley Silla · 2 preguntas
                </p>
                <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                    Su trabajo de pie, {name}
                </h1>
                <p>
                    Nos ayuda a decidir qué asiento y qué descansos le
                    corresponden. Contestar es voluntario.
                </p>
            </div>
            <div className="flex flex-col gap-2 rounded-xl bg-accent p-4 text-base">
                <h2 className="font-semibold">Quién ve sus respuestas</h2>
                <p>{notice}</p>
            </div>
            <div className="mt-auto flex flex-col gap-2 pt-6">
                <Button
                    size="lg"
                    className="h-[52px] w-full"
                    data-test="portal-accept"
                    onClick={onAccept}
                >
                    Acepto, quiero contestar
                </Button>
                <Button
                    size="lg"
                    variant="ghost"
                    className="h-[52px] w-full"
                    onClick={onDecline}
                >
                    Prefiero no contestar
                </Button>
            </div>
        </section>
    );
}
