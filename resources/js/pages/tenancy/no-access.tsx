import { Head } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import StatusMessage from '@/components/humetria/status-message';

type Props = {
    /** Who can give access: the owner of the company, by name; null in a company that has none yet. */
    ownerName: string | null;
};

/**
 * Where a person lands who may open nothing in the company yet (pantallas: Error-403): the empty arch with a key, and
 * whom to ask for access.
 */
export default function NoAccess({ ownerName }: Props) {
    return (
        <>
            <Head title="Aún no tienes acceso" />
            <div className="flex justify-center px-4 py-16 md:py-24">
                <StatusMessage icon={KeyRound} title="Aún no tienes acceso">
                    {ownerName === null
                        ? 'Pide a quien administra la empresa que te dé acceso a lo que necesitas.'
                        : `Pide a ${ownerName}, quien administra la empresa, que te dé acceso a lo que necesitas.`}
                </StatusMessage>
            </div>
        </>
    );
}
