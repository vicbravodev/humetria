import { TOOLS } from '@/components/landing/landing-tools';
import { useActiveSection } from '@/hooks/use-active-section';
import { cn } from '@/lib/utils';

const ids = TOOLS.map((tool) => `herramienta-${tool.key}`);

/** The five tools as a list that stays in view beside them, marking the one being read. Wide screens only. */
export default function ToolsIndex() {
    const active = useActiveSection(ids);

    return (
        <nav aria-label="Herramientas" className="hidden lg:block">
            <ol className="sticky top-24 flex flex-col">
                {TOOLS.map((tool, index) => {
                    const current = active === `herramienta-${tool.key}`;

                    return (
                        <li key={tool.key}>
                            <a
                                href={`#herramienta-${tool.key}`}
                                data-test={`tool-link-${tool.key}`}
                                aria-current={current ? 'true' : undefined}
                                className={cn(
                                    'group flex min-h-11 items-center gap-3 rounded-md py-2 text-lg font-medium transition-colors duration-200 ease-out',
                                    current
                                        ? 'text-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <span
                                    aria-hidden
                                    className={cn(
                                        'w-6 text-sm tabular-nums transition-colors duration-200',
                                        current && 'text-primary',
                                    )}
                                >
                                    {String(index + 1).padStart(2, '0')}
                                </span>
                                {tool.name}
                                <span
                                    aria-hidden
                                    className={cn(
                                        'ml-auto size-1.5 rounded-full bg-primary transition-[opacity,transform] duration-300 ease-out',
                                        current
                                            ? 'scale-100 opacity-100'
                                            : 'scale-50 opacity-0',
                                    )}
                                />
                            </a>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
