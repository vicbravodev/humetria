import { TOOLS, type ToolKey } from '@/components/landing/landing-tools';
import ToolPanel from '@/components/landing/tool-panel';

/** The nine tools as tab panels in the page, the chosen one visible: what each does is there for anyone to read. */
export default function ToolPanels({ selected }: { selected: ToolKey }) {
    return TOOLS.map((tool) => (
        <ToolPanel
            key={tool.key}
            tool={tool}
            selected={tool.key === selected}
        />
    ));
}
