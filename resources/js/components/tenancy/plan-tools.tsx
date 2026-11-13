import { Check, Minus } from 'lucide-react';
import { toolState } from '@/lib/plan';
import type { PlanTool } from '@/types';

/** The tools of Humetría and which ones the company has: on, on until a day, or not included. */
export default function PlanTools({ tools }: { tools: PlanTool[] }) {
    return (
        <section aria-labelledby="plan-tools" className="grid gap-4">
            <h2
                id="plan-tools"
                className="text-xl font-semibold tracking-[-0.01em]"
            >
                Tus herramientas
            </h2>
            <ul className="grid gap-px overflow-hidden card">
                {tools.map((tool) => (
                    <li
                        key={tool.label}
                        className="flex items-center justify-between gap-3 px-5 py-3.5"
                    >
                        <span className="flex items-center gap-3">
                            {tool.active ? (
                                <Check
                                    aria-hidden
                                    className="size-5 text-primary"
                                />
                            ) : (
                                <Minus
                                    aria-hidden
                                    className="size-5 text-muted-foreground"
                                />
                            )}
                            <span
                                className={
                                    tool.active
                                        ? 'font-medium'
                                        : 'text-muted-foreground'
                                }
                            >
                                {tool.label}
                            </span>
                        </span>
                        <span className="text-sm text-muted-foreground">
                            {toolState(tool)}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}
