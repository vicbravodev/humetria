import InstallGuide from '@/components/humetria/install-guide';
import PersonHeading from '@/components/humetria/person-heading';
import HomeSections from '@/components/worker-access/home-sections';
import PushOffer from '@/components/worker-access/push-offer';
import type { WorkerSection } from '@/types';

/** «Inicio»: who the person is, what waits for their answer first, the rest of their tools and, last, how to install the app. */
export default function HomeStart({
    company,
    name,
    slug,
    waiting,
    tools,
}: {
    company: string;
    name: string;
    slug: string;
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
            <PushOffer slug={slug} />
            <InstallGuide />
        </>
    );
}
