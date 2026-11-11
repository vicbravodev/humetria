import { m } from 'motion/react';
import { ENTER, ONCE } from '@/components/landing/landing-motion';

const steps = [
    'Te escribimos para agendar la llamada, por WhatsApp o por correo.',
    'Te mostramos Humetría con un caso como el tuyo.',
    'Si te convence, arrancas la prueba con tu gente.',
] as const;

/** What happens after the form, so nobody sends it wondering: three steps joined by a line. */
export default function DemoNextSteps() {
    return (
        <div data-test="demo-next-steps" className="mt-3 flex flex-col gap-4">
            <h3 className="text-xl leading-tight font-semibold tracking-[-0.02em]">
                Qué sigue
            </h3>
            <ol className="flex flex-col">
                {steps.map((step, index) => (
                    <m.li
                        key={step}
                        initial={{ opacity: 0, transform: 'translateX(-8px)' }}
                        whileInView={{
                            opacity: 1,
                            transform: 'translateX(0px)',
                        }}
                        viewport={ONCE}
                        transition={{ ...ENTER, delay: 0.1 + index * 0.08 }}
                        className="relative grid grid-cols-[36px_minmax(0,1fr)] gap-3.5 pb-[22px] last:pb-0"
                    >
                        <span
                            aria-hidden
                            className="relative z-1 flex size-9 items-center justify-center rounded-full bg-accent font-semibold text-primary tabular-nums"
                        >
                            {index + 1}
                        </span>
                        {index < steps.length - 1 && (
                            <span
                                aria-hidden
                                className="absolute top-9 bottom-0 left-[17px] w-0.5 bg-border"
                            />
                        )}
                        <span className="pt-1.5 leading-snug">{step}</span>
                    </m.li>
                ))}
            </ol>
            <p className="mt-1 max-w-[52ch] border-l-2 pl-3.5 text-meta text-muted-foreground">
                Humetría no tiene registro abierto: se entra por demo.
            </p>
        </div>
    );
}
