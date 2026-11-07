import { m } from 'motion/react';
import { ENTER, ONCE } from '@/components/landing/landing-motion';

const steps = [
    'Te escribimos para agendar la llamada, por WhatsApp o por correo.',
    'Te mostramos Humetría con un caso como el tuyo.',
    'Si te convence, arrancas la prueba con tu gente.',
] as const;

/** What happens after the form, so nobody sends it wondering. */
export default function DemoNextSteps() {
    return (
        <div data-test="demo-next-steps" className="flex flex-col gap-4">
            <h3 className="font-semibold">Qué sigue</h3>
            <ol className="flex flex-col">
                {steps.map((step, index) => (
                    <m.li
                        key={step}
                        initial={{ opacity: 0, transform: 'translateY(8px)' }}
                        whileInView={{
                            opacity: 1,
                            transform: 'translateY(0px)',
                        }}
                        viewport={ONCE}
                        transition={{ ...ENTER, delay: 0.1 + index * 0.1 }}
                        className="flex gap-4 border-t py-4"
                    >
                        <span
                            aria-hidden
                            className="flex size-8 shrink-0 items-center justify-center rounded-full border-[1.5px] border-primary text-sm font-semibold text-primary tabular-nums"
                        >
                            {index + 1}
                        </span>
                        <span className="pt-1">{step}</span>
                    </m.li>
                ))}
            </ol>
            <p className="text-sm text-muted-foreground">
                Humetría no tiene registro abierto: se entra por demo.
            </p>
        </div>
    );
}
