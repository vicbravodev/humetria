import { Badge } from '@/components/ui/badge';
import type { EngagementPriority } from '@/types';

/**
 * The drivers to act on first. It says only how urgent each is, never a figure: the statistics behind the order stay
 * on the server.
 */
export default function PriorityList({
    priorities,
}: {
    priorities: EngagementPriority[];
}) {
    return (
        <section className="grid gap-6 card p-5 sm:p-6 lg:grid-cols-3 lg:gap-x-10">
            <div className="flex flex-col gap-2">
                <h2 className="text-lg font-semibold">Dónde actuar primero</h2>
                <p className="text-sm text-muted-foreground">
                    Los temas que más pesan en el compromiso, de mayor a menor.
                    «Alta» es donde además la empresa sale por debajo.
                </p>
            </div>
            <div className="lg:col-span-2">
                {priorities.length === 0 ? (
                    <p className="text-muted-foreground">
                        Todavía no hay prioridades: se calculan desde 30
                        respuestas y cuando algún tema se mueve con el
                        compromiso.
                    </p>
                ) : (
                    <ol className="flex flex-col divide-y">
                        {priorities.map((priority) => (
                            <li
                                key={priority.driver}
                                className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"
                            >
                                <span className="font-medium">
                                    {priority.label}
                                </span>
                                <Badge
                                    variant={
                                        priority.level === 'high'
                                            ? 'destructive'
                                            : 'warning'
                                    }
                                >
                                    {priority.levelLabel}
                                </Badge>
                            </li>
                        ))}
                    </ol>
                )}
            </div>
        </section>
    );
}
