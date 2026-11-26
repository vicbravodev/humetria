import InstallGuide from '@/components/humetria/install-guide';
import HomeGreeting from '@/components/worker-access/home-greeting';
import HomeDay from '@/components/worker-access/home-day';
import HomeSections from '@/components/worker-access/home-sections';
import PushOffer from '@/components/worker-access/push-offer';
import type { WorkerDay, WorkerSection } from '@/types';

/** «Inicio»: who the person is, what waits for their answer first, the rest of their tools and, last, how to install the app. */
export default function HomeStart({
    company,
    name,
    slug,
    waiting,
    tools,
    day,
    punchUrl,
    weekUrl,
    serverNow,
}: {
    company: string;
    name: string;
    slug: string;
    waiting: WorkerSection[];
    tools: WorkerSection[];
    /** Their day, where the company keeps one (and where Marcar and their week are). */
    day: WorkerDay | null;
    punchUrl: string | null;
    weekUrl: string | null;
    serverNow: number;
}) {
    return (
        <>
            {day !== null && punchUrl !== null && weekUrl !== null ? (
                <HomeDay
                    name={name}
                    day={day}
                    punchUrl={punchUrl}
                    weekUrl={weekUrl}
                    serverNow={serverNow}
                />
            ) : (
                <HomeGreeting
                    name={name}
                    where={company}
                    serverNow={serverNow}
                />
            )}
            <HomeSections sections={waiting} label="Esperan su respuesta" />
            <HomeSections
                sections={tools.filter(
                    (tool) => day === null || tool.key !== 'attendance',
                )}
                label="Sus herramientas"
            />
            <PushOffer slug={slug} />
            <InstallGuide />
        </>
    );
}
