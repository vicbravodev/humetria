import { Head, setLayoutProps } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import CommitteeSeats from '@/components/telework/committee-seats';
import CommitteeTask from '@/components/telework/committee-task';
import { greeting, pendingLine } from '@/lib/telework/queue';
import type { CommitteeSeat, CommitteeTask as Task } from '@/types';

/** Board V2-Comision (decisión A5, with its account): what waits for a member of the Comisión in its centers, and only that. */
export default function TeleworkQueue({
    name,
    tasks,
    centers,
}: {
    name: string;
    tasks: Task[];
    centers: CommitteeSeat[];
}) {
    setLayoutProps({
        breadcrumbs: [{ title: 'NOM-037' }, { title: 'Mis pendientes' }],
    });

    return (
        <>
            <Head title="Mis pendientes de la Comisión" />
            <div className="mx-auto grid w-full max-w-[1180px] gap-8 px-4 py-6 min-[1240px]:grid-cols-[minmax(0,1fr)_320px] md:px-12 md:py-10">
                <div className="flex min-w-0 flex-col gap-6">
                    <header className="flex flex-col gap-2">
                        <h1 className="text-3xl leading-10 font-semibold tracking-[-0.015em]">
                            {greeting(name)}
                        </h1>
                        <p className="text-lg text-muted-foreground">
                            {pendingLine(tasks)}
                        </p>
                    </header>
                    {tasks.length === 0 ? (
                        <EmptyState
                            icon={ClipboardCheck}
                            title="Nada te espera por ahora"
                        >
                            Cuando una persona conteste la lista de su lugar o
                            envíe un aviso, aparecerá aquí.
                        </EmptyState>
                    ) : (
                        <ol
                            aria-label="Pendientes de la Comisión"
                            className="@container overflow-hidden card"
                        >
                            {tasks.map((task, index) => (
                                <CommitteeTask
                                    key={`${task.kind}-${task.href}`}
                                    task={task}
                                    number={index + 1}
                                />
                            ))}
                        </ol>
                    )}
                    <p className="text-sm text-muted-foreground">
                        Si algo no te toca a ti, avísale a Recursos Humanos.
                    </p>
                </div>
                <CommitteeSeats seats={centers} />
            </div>
        </>
    );
}
