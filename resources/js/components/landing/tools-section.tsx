import FamilyTabs from '@/components/landing/family-tabs';
import { familyOf } from '@/components/landing/landing-families';
import {
    LEAD,
    SECTION,
    TITLE,
    WRAP,
} from '@/components/landing/landing-layout';
import { TOOLS } from '@/components/landing/landing-tools';
import Reveal from '@/components/landing/reveal';
import ToolStage from '@/components/landing/tool-stage';
import ToolTabs from '@/components/landing/tool-tabs';
import { useHashTab } from '@/hooks/use-hash-tab';
import { cn } from '@/lib/utils';

const KEYS = TOOLS.map((tool) => tool.key);

/** Nine tools as three families: pick a family, then one of its tools, and its film plays on the stage below. */
export default function ToolsSection() {
    const [selected, select] = useHashTab('nom_035', KEYS, 'herramienta-');
    const family = familyOf(selected);
    const tool = TOOLS.find((candidate) => candidate.key === selected);

    return (
        <section
            id="herramientas"
            aria-labelledby="herramientas-title"
            className={cn('scroll-mt-16 bg-sidebar', SECTION)}
        >
            <div className={cn(WRAP, 'flex flex-col gap-5')}>
                <Reveal className="mb-3 flex max-w-[760px] flex-col gap-4 lg:mb-9">
                    <h2 id="herramientas-title" className={TITLE}>
                        Nueve herramientas, un solo lugar
                    </h2>
                    <p className={LEAD}>
                        Elige una familia y luego una herramienta para ver cómo
                        funciona. Humi te acompaña en todas.
                    </p>
                </Reveal>
                <FamilyTabs
                    selected={family}
                    onSelect={(next) => select(next.tools[0])}
                />
                <ToolTabs
                    family={family}
                    selected={selected}
                    onSelect={select}
                />
                {tool !== undefined && <ToolStage tool={tool} />}
            </div>
        </section>
    );
}
