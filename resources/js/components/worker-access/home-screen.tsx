import { Link } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import HomeSections from '@/components/worker-access/home-sections';
import LeaveButton from '@/components/worker-access/leave-button';
import WorkerFrame from '@/components/worker-access/worker-frame';
import PersonHeading from '@/components/humetria/person-heading';
import ToneTile from '@/components/humetria/tone-tile';
import { Button } from '@/components/ui/button';
import type { WorkerSection } from '@/types';

/** The person's start: who they are, the tools their company has for them, a key for this device when they may add one, and «Salir». */
export default function HomeScreen({
    company,
    name,
    sections,
    keyUrl,
    leaveUrl,
}: {
    company: string;
    name: string;
    sections: WorkerSection[];
    keyUrl: string | null;
    leaveUrl: string;
}) {
    return (
        <WorkerFrame company={company} actions={<LeaveButton url={leaveUrl} />}>
            <PersonHeading title={`Hola, ${name}`}>
                Este es su portal de {company}. Al terminar, toque «Salir»,
                sobre todo en un dispositivo compartido.
            </PersonHeading>
            <HomeSections sections={sections} />
            {keyUrl !== null && (
                <section className="flex flex-col items-start gap-3 card p-5">
                    <ToneTile icon={KeyRound} />
                    <p className="text-xl leading-7 font-semibold">
                        Entre más rápido la próxima vez
                    </p>
                    <p className="text-base text-muted-foreground">
                        Cree una llave de acceso en este dispositivo y entre con
                        su huella, sin esperar un correo.
                    </p>
                    <Button asChild variant="outline" size="lg">
                        <Link href={keyUrl}>Crear una llave aquí</Link>
                    </Button>
                </section>
            )}
        </WorkerFrame>
    );
}
