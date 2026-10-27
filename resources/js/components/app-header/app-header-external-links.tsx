import { rightNavItems } from '@/components/app-header/app-header-nav-items';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { toUrl } from '@/lib/utils';

export function AppHeaderExternalLinks() {
    return (
        <div className="ml-1 hidden gap-1 lg:flex">
            {rightNavItems.map((item) => (
                <Tooltip key={item.title}>
                    <TooltipTrigger>
                        <a
                            href={toUrl(item.href)}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="group inline-flex size-11 items-center justify-center rounded-md bg-transparent p-0 text-sm font-medium text-accent-foreground ring-offset-background transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
                        >
                            <span className="sr-only">{item.title}</span>
                            {item.icon && (
                                <item.icon className="size-5 opacity-80 group-hover:opacity-100" />
                            )}
                        </a>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>{item.title}</p>
                    </TooltipContent>
                </Tooltip>
            ))}
        </div>
    );
}
