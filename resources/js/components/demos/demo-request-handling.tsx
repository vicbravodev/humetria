import DemoRequestForm from '@/components/demos/demo-request-form';
import DemoRequestStanding from '@/components/demos/demo-request-standing';
import type { DemoRequestRow } from '@/types';

/**
 * The follow-up under the panel's actions: where the request stands, and its stage, owner, next step and internal note
 * nobody else sees. Only the superadmin changes them; support reads.
 */
export default function DemoRequestHandling({
    request,
    canManage,
}: {
    request: DemoRequestRow;
    canManage: boolean;
}) {
    return (
        <section className="flex flex-col gap-4 border-t px-5 py-4 md:px-6">
            <DemoRequestStanding request={request} />
            {canManage ? (
                <DemoRequestForm request={request} />
            ) : (
                <>
                    {request.next_step !== null && (
                        <p className="text-sm">Sigue: {request.next_step}</p>
                    )}
                    <p className="text-sm whitespace-pre-line">
                        {request.note ?? (
                            <span className="text-muted-foreground">
                                Sin nota interna
                            </span>
                        )}
                    </p>
                </>
            )}
        </section>
    );
}
