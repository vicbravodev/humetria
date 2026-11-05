import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import PortalShell from '@/components/telework/portal/portal-shell';
import WrittenSheet from '@/components/telework/written-sheet';
import { show } from '@/routes/portal';
import type { WrittenDocument } from '@/types';

/** A document of the company written with questions, as the person reads it from their portal before confirming it. */
export default function PortalDocument({
    token,
    document,
}: {
    token: string;
    document: WrittenDocument;
}) {
    return (
        <PortalShell company={document.company}>
            <Head title={document.title} />
            <div className="flex w-full max-w-2xl flex-col gap-6">
                <Link
                    href={show.url({ token })}
                    className="touch-hit flex w-fit items-center gap-1.5 text-base font-medium text-primary"
                >
                    <ArrowLeft aria-hidden className="size-4" />
                    Volver a su portal
                </Link>
                <WrittenSheet document={document} />
            </div>
        </PortalShell>
    );
}
