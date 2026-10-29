import type { LandingTool } from '@/components/landing/landing-tools';
import { Badge } from '@/components/ui/badge';

export type PreviewState = {
    status: 'En curso' | 'Por revisar';
    detail: string;
    /** A Tailwind width class for the progress bar, written whole so Tailwind sees it. */
    progress?: string;
};

/** One look per status, so the same state never reads as two. */
const variants = {
    'En curso': 'default',
    'Por revisar': 'outline',
} as const;

type Props = {
    tool: LandingTool;
    state: PreviewState;
};

export default function HeroPreviewTool({ tool, state }: Props) {
    const { icon: Icon } = tool;

    return (
        <li data-test={`preview-${tool.key}`} className="flex gap-4 py-4">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-accent">
                <Icon aria-hidden className="size-5 text-primary" />
            </span>
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="font-medium">{tool.name}</p>
                    <Badge variant={variants[state.status]}>
                        {state.status}
                    </Badge>
                </div>
                <p className="mt-1 text-sm text-muted-foreground tabular-nums">
                    {state.detail}
                </p>
                {state.progress === undefined ? null : (
                    <div className="mt-2 h-1.5 rounded-full bg-muted">
                        <div
                            className={`h-1.5 rounded-full bg-primary ${state.progress}`}
                        />
                    </div>
                )}
            </div>
        </li>
    );
}
