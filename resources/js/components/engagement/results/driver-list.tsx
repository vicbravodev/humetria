import { ChevronDown } from 'lucide-react';
import DriverRow from '@/components/engagement/results/driver-row';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { topicOrder } from '@/lib/engagement/results';
import type { EngagementDriver, EngagementPriority } from '@/types';

/**
 * «Dónde actuar primero» (pantalla Clima · Resultados): one list of themes, the prioritized first, each against the
 * engagement of the whole company; the rest folded under «Otros N temas». Priority is association, not cause.
 */
export default function DriverList({
    drivers,
    priorities,
    reference,
}: {
    drivers: EngagementDriver[];
    priorities: EngagementPriority[];
    /** The company's engagement index: the vertical mark of every bar. */
    reference: number | null;
}) {
    const { main, rest } = topicOrder(drivers, priorities);

    return (
        <section aria-labelledby="act-first" className="flex flex-col gap-3">
            <div className="flex flex-col gap-1">
                <h2 id="act-first" className="text-xl font-semibold">
                    Dónde actuar primero
                </h2>
                <p className="text-sm text-muted-foreground">
                    {priorities.length > 0
                        ? 'Temas ordenados por prioridad.'
                        : 'Los temas de esta encuesta.'}
                    {reference !== null &&
                        ` La marca vertical es el compromiso de toda la empresa: ${reference} %.`}
                </p>
            </div>
            <div className="flex flex-col gap-5 card p-5 sm:p-6">
                <ol className="flex flex-col divide-y">
                    {main.map((topic, index) => (
                        <DriverRow
                            key={topic.driver.key}
                            topic={topic}
                            reference={reference}
                            index={index}
                        />
                    ))}
                </ol>
                {rest.length > 0 && (
                    <Collapsible className="border-t pt-4">
                        <CollapsibleTrigger className="group touch-hit inline-flex items-center gap-2 font-medium text-primary">
                            <ChevronDown
                                aria-hidden
                                className="size-4 transition-transform group-data-[state=open]:rotate-180"
                            />
                            Otros {rest.length} temas
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <ol className="mt-5 flex flex-col divide-y">
                                {rest.map((topic, index) => (
                                    <DriverRow
                                        key={topic.driver.key}
                                        topic={topic}
                                        reference={reference}
                                        index={index}
                                    />
                                ))}
                            </ol>
                        </CollapsibleContent>
                    </Collapsible>
                )}
                {priorities.length > 0 && (
                    <p className="text-sm text-muted-foreground">
                        Prioridad es asociación con el compromiso, no causa.
                    </p>
                )}
            </div>
        </section>
    );
}
