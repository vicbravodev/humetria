import { Info } from 'lucide-react';
import { Button } from '@/components/ui/button';

/** The start of «¿Te aplica la NOM-037?» (board V2-Aplica): what it asks, how long it takes and what the 40 % means. */
export default function AppliesIntro({
    center,
    onStart,
}: {
    center: string;
    onStart: () => void;
}) {
    return (
        <div className="flex flex-col gap-5">
            <h1 className="text-3xl leading-[38px] font-semibold tracking-[-0.015em] text-balance md:text-[34px] md:leading-[42px]">
                Primero, veamos si la NOM-037 te aplica en {center}
            </h1>
            <p className="text-lg leading-7 text-muted-foreground">
                Son 2 preguntas y tarda un minuto. Con tus respuestas armamos tu
                ruta, o te dejamos constancia de que no te aplica.
            </p>
            <div className="flex gap-3 rounded-xl bg-accent px-4 py-4 md:px-5">
                <Info
                    aria-hidden
                    className="mt-0.5 size-5 shrink-0 text-primary"
                />
                <p>
                    La NOM-037 no es para el home office de vez en cuando.
                    Aplica a quien trabaja fuera del centro{' '}
                    <strong className="font-semibold">
                        más del 40 % de su tiempo
                    </strong>
                    .
                </p>
            </div>
            <div>
                <Button size="lg" onClick={onStart}>
                    Empezar
                </Button>
            </div>
        </div>
    );
}
