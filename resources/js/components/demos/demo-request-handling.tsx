import DemoRequestActions from '@/components/demos/demo-request-actions';
import DemoRequestForm from '@/components/demos/demo-request-form';
import DemoRequestStanding from '@/components/demos/demo-request-standing';
import type { DemoRequestRow } from '@/types';

/**
 * What the team does with a request: where it stands, what comes next, an internal note nobody else sees, and the way
 * to a company. Only the superadmin changes things; support reads.
 */
export default function DemoRequestHandling({
    request,
    canManage,
}: {
    request: DemoRequestRow;
    canManage: boolean;
}) {
    return (
        <section className="flex flex-col gap-4 rounded-xl bg-muted p-4 md:p-5">
            <DemoRequestStanding request={request} />
            <DemoRequestActions request={request} canManage={canManage} />
            {canManage ? (
                <DemoRequestForm request={request} />
            ) : (
                <>
                    <p className="text-sm">
                        {request.owner?.name ?? 'Sin responsable'}
                        {request.next_step !== null &&
                            ` · Sigue: ${request.next_step}`}
                    </p>
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
