import { TOOLS } from '@/components/landing/landing-tools';
import ToolStage from '@/components/landing/tool-stage';
import ToolTabs from '@/components/landing/tool-tabs';
import { useHashTab } from '@/hooks/use-hash-tab';

const KEYS = TOOLS.map((tool) => tool.key);

export default function ToolsSection() {
    const [selected, select] = useHashTab('nom_035', KEYS, 'herramienta-');
    const tool = TOOLS.find((candidate) => candidate.key === selected);

    return (
        <section
            id="herramientas"
            aria-labelledby="herramientas-title"
            className="scroll-mt-16"
        >
            <div className="mx-auto flex max-w-[1240px] flex-col gap-6 px-4 py-12 sm:px-6 lg:pt-8 lg:pb-12">
                <div className="flex flex-col gap-2">
                    <h2
                        id="herramientas-title"
                        className="text-[32px] leading-[1.1] font-semibold tracking-[-0.025em] text-balance sm:text-[40px]"
                    >
                        Nueve herramientas, un solo lugar
                    </h2>
                    <p className="max-w-[75ch] text-base leading-6 text-muted-foreground">
                        Elige una herramienta y mira cómo funciona. Humi te
                        acompaña en todas.
                    </p>
                </div>
                <div className="grid items-start gap-6 lg:grid-cols-[240px_minmax(0,1fr)]">
                    <ToolTabs selected={selected} onSelect={select} />
                    {tool !== undefined && <ToolStage tool={tool} />}
                </div>
            </div>
        </section>
    );
}
