import { m, useInView } from 'motion/react';
import { Check, Lock } from 'lucide-react';
import { useRef } from 'react';
import HumiChatHeader from '@/components/landing/humi-chat-header';
import { ENTER } from '@/components/landing/landing-motion';
import { Button } from '@/components/ui/button';
import { useTypewriter } from '@/hooks/use-typewriter';

const ANSWER =
    'Te faltan 31 respuestas de NOM-035 y 3 listas de verificación por validar en NOM-037.';
const WORDS = ANSWER.split(' ');

/** An example conversation: the question, Humi looking it up, its answer written out and where it comes from. */
export default function HumiChatDemo() {
    const chat = useRef<HTMLDivElement>(null);
    const seen = useInView(chat, { once: true, amount: 0.5 });
    const shown = useTypewriter(WORDS.length, seen);
    const done = shown === WORDS.length;

    return (
        <div
            ref={chat}
            data-test="humi-chat"
            className="overflow-hidden card rounded-2xl shadow-float"
        >
            <HumiChatHeader />
            <div className="flex min-h-[300px] flex-col gap-5 p-5">
                <p className="max-w-[88%] self-end rounded-xl bg-secondary px-4 py-3">
                    ¿Qué me falta para estar al día en Planta Querétaro?
                </p>
                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Check aria-hidden className="size-4 text-primary" />
                    Consultó el avance de Planta Querétaro
                </p>
                <p className="text-lg leading-7 font-medium">
                    <span className="sr-only">{ANSWER}</span>
                    <span aria-hidden>{WORDS.slice(0, shown).join(' ')}</span>
                </p>
                <m.div
                    initial={{ opacity: 0, transform: 'translateY(8px)' }}
                    animate={
                        done ? { opacity: 1, transform: 'translateY(0px)' } : {}
                    }
                    transition={ENTER}
                    inert={!done}
                    className="flex flex-col gap-4"
                >
                    <p className="flex flex-wrap items-center gap-2 text-sm">
                        <span className="text-muted-foreground">Fuentes</span>
                        <span className="rounded-md border bg-muted px-2 py-0.5">
                            NOM-035-STPS-2018
                        </span>
                        <span className="rounded-md border bg-muted px-2 py-0.5">
                            NOM-037-STPS-2023
                        </span>
                    </p>
                    <Button asChild className="self-start">
                        <a href="#agenda-una-demo">Ver pendientes</a>
                    </Button>
                </m.div>
            </div>
            <p className="flex items-start gap-2 border-t px-5 py-3 text-caption text-muted-foreground">
                <Lock aria-hidden className="mt-0.5 size-3.5 shrink-0" />
                Humi puede equivocarse. Verifica las cifras antes de
                reportarlas. Conversación de ejemplo.
            </p>
        </div>
    );
}
