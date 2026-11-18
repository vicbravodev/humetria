import type { LandingTool, ToolKey } from '@/components/landing/landing-tools';
import { TOOLS } from '@/components/landing/landing-tools';
import tools from '@/routes/tools';

/** The address of each tool's own page. */
export function toolPageUrl(key: ToolKey): string {
    return tools[key].url();
}

/** The tool of a page, by the key the server sends. */
export function toolByKey(key: string): LandingTool | undefined {
    return TOOLS.find((tool) => tool.key === key);
}
