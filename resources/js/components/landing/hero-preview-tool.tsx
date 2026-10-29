import type { LucideIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

export type PreviewTool = {
    key: string;
    icon: LucideIcon;
    name: string;
    status: string;
    variant: 'default' | 'secondary' | 'outline';
    detail: string;
    /** A Tailwind width class for the progress bar, written whole so Tailwind sees it. */
    progress?: string;
};

export default function HeroPreviewTool({ tool }: { tool: PreviewTool }) {
    const { icon: Icon } = tool;

    return (
        <li data-test={`preview-${tool.key}`} className="flex gap-4 py-4">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-accent">
                <Icon aria-hidden className="size-5 text-primary" />
            </span>
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="font-medium">{tool.name}</p>
                    <Badge variant={tool.variant}>{tool.status}</Badge>
                </div>
                <p className="mt-1 text-sm text-muted-foreground tabular-nums">
                    {tool.detail}
                </p>
                {tool.progress === undefined ? null : (
                    <div className="mt-2 h-1.5 rounded-full bg-muted">
                        <div
                            className={`h-1.5 rounded-full bg-primary ${tool.progress}`}
                        />
                    </div>
                )}
            </div>
        </li>
    );
}
