import { WRAP } from '@/components/landing/landing-layout';
import { cn } from '@/lib/utils';

/** How the tool runs, in three steps with large numbers: the same rhythm on every page. */
export default function ToolPageSteps({
    steps,
}: {
    steps: { title: string; text: string }[];
}) {
    return (
        <section
            id="como-funciona"
            aria-labelledby="como-funciona-title"
            className={cn(WRAP, 'grid scroll-mt-16 gap-9 pt-16 lg:pt-22')}
        >
            <h2
                id="como-funciona-title"
                className="text-[30px] leading-tight font-semibold tracking-[-0.028em] sm:text-[40px]"
            >
                Cómo funciona
            </h2>
            <ol className="grid gap-8 md:grid-cols-3">
                {steps.map((step, index) => (
                    <li key={step.title} className="grid content-start gap-2.5">
                        <span
                            aria-hidden
                            className="text-5xl leading-none font-semibold tracking-[-0.03em] text-primary"
                        >
                            {index + 1}
                        </span>
                        <b className="text-xl tracking-[-0.01em]">
                            {step.title}
                        </b>
                        <span className="leading-relaxed text-muted-foreground">
                            {step.text}
                        </span>
                    </li>
                ))}
            </ol>
        </section>
    );
}
