import { cn } from '@/lib/utils';

/**
 * A miniature of the app in a theme: the frame with the sidebar, the panel with a field and a button. Drawn with the
 * design-system tokens, so the dark one sits inside a `.dark` scope.
 */
export default function ThemeMiniature({ dark }: { dark: boolean }) {
    return (
        <span
            className={cn(
                'flex h-[104px] gap-1.5 overflow-hidden rounded-lg bg-sidebar p-2',
                dark && 'dark',
            )}
        >
            <span className="flex w-[28%] flex-col gap-[5px] pt-1">
                <span className="h-1.5 w-[70%] rounded-[3px] bg-primary" />
                <span className="h-[5px] rounded-[3px] bg-border" />
                <span className="h-[5px] rounded-[3px] bg-border" />
            </span>
            <span className="flex grow flex-col gap-1.5 rounded-md bg-background p-2">
                <span className="h-[7px] w-1/2 rounded-[3px] bg-border" />
                <span className="h-[22px] rounded border-[1.5px] border-border" />
                <span className="h-[7px] w-[36%] rounded-[3px] bg-primary" />
            </span>
        </span>
    );
}
