import SectionHeading from '@/components/landing/section-heading';
import ToolCard from '@/components/landing/tool-card';
import ToolsIndex from '@/components/landing/tools-index';
import { TOOLS } from '@/components/landing/landing-tools';

export default function ToolsSection() {
    return (
        <section
            id="herramientas"
            aria-labelledby="herramientas-title"
            className="scroll-mt-16"
        >
            <div className="mx-auto flex max-w-[1240px] flex-col gap-12 px-4 py-20 sm:px-6 lg:py-28">
                <SectionHeading
                    id="herramientas-title"
                    title="Cinco herramientas, un solo lugar"
                >
                    Cada una hace lo que pide la norma o la ley, numeral por
                    numeral. Y Humi te acompaña en todas.
                </SectionHeading>
                <div className="grid gap-10 lg:grid-cols-[220px_minmax(0,1fr)] lg:gap-16">
                    <ToolsIndex />
                    <div className="flex flex-col gap-6">
                        {TOOLS.map((tool) => (
                            <ToolCard key={tool.key} tool={tool} />
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
