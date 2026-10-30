import SectionHeading from '@/components/landing/section-heading';
import ToolCard from '@/components/landing/tool-card';
import { TOOLS } from '@/components/landing/landing-tools';

export default function ToolsSection() {
    return (
        <section
            id="herramientas"
            aria-labelledby="herramientas-title"
            className="scroll-mt-16 border-y bg-sidebar"
        >
            <div className="mx-auto flex max-w-[1200px] flex-col gap-10 px-4 py-16 sm:px-6 lg:py-[88px]">
                <SectionHeading
                    id="herramientas-title"
                    title="Tres obligaciones, un solo lugar"
                >
                    Cada herramienta hace exactamente lo que pide la norma o la
                    ley. Nada más, nada menos.
                </SectionHeading>

                <div className="grid grid-cols-[repeat(auto-fit,minmax(min(320px,100%),1fr))] gap-5">
                    {TOOLS.map((tool) => (
                        <ToolCard key={tool.key} tool={tool} />
                    ))}
                </div>
            </div>
        </section>
    );
}
