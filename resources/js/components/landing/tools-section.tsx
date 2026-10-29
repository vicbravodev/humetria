import SectionHeading from '@/components/landing/section-heading';
import ToolCard from '@/components/landing/tool-card';
import { TOOLS } from '@/components/landing/landing-tools';

export default function ToolsSection() {
    return (
        <section
            id="herramientas"
            aria-labelledby="herramientas-title"
            className="scroll-mt-16 border-y bg-muted"
        >
            <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
                <SectionHeading id="herramientas-title" title="Herramientas">
                    Tres obligaciones de la STPS, cada una con lo que la norma o
                    la ley te pide.
                </SectionHeading>

                <div className="mt-10 grid gap-6 lg:grid-cols-3">
                    {TOOLS.map((tool) => (
                        <ToolCard key={tool.key} tool={tool} />
                    ))}
                </div>

                <p className="mt-10 text-muted-foreground">
                    Humetría reúne en un solo lugar las obligaciones de RH ante
                    la STPS.
                </p>
            </div>
        </section>
    );
}
