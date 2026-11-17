import HomeSections from '@/components/worker-access/home-sections';
import PersonHeading from '@/components/humetria/person-heading';
import type { WorkerSection } from '@/types';

/** «Inicio»: who the person is, what waits for their answer first, and the rest of their tools. */
export default function HomeStart({
    company,
    name,
    waiting,
    tools,
}: {
    company: string;
    name: string;
    waiting: WorkerSection[];
    tools: WorkerSection[];
}) {
    return (
        <>
            <PersonHeading title={`Hola, ${name}`}>
                Este es su portal de {company}.
            </PersonHeading>
            <HomeSections sections={waiting} label="Esperan su respuesta" />
            <HomeSections sections={tools} label="Sus herramientas" />
        </>
    );
}
