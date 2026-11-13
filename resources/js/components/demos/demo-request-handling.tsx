import { Link } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import DemoRequestForm from '@/components/demos/demo-request-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index as companies, show } from '@/routes/companies';
import type { DemoRequestRow } from '@/types';

/**
 * What the team does with a request: where it stands, an internal note nobody else sees, and the way to a company: create
 * it from the request, or open the one it became. Only the superadmin changes things; support reads.
 */
export default function DemoRequestHandling({
    request,
    canManage,
}: {
    request: DemoRequestRow;
    canManage: boolean;
}) {
    const converted = request.status === 'converted';

    return (
        <section className="flex flex-col gap-4 rounded-xl bg-muted p-4 md:p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h3 className="flex items-center gap-2 text-caption text-muted-foreground">
                    Seguimiento
                    <Badge variant={converted ? 'default' : 'outline'}>
                        {request.status_label}
                    </Badge>
                </h3>
                {converted && request.tenant_id !== null ? (
                    <Button asChild size="sm">
                        <Link href={show(request.tenant_id)}>
                            <Building2 aria-hidden />
                            Ver empresa
                        </Link>
                    </Button>
                ) : (
                    canManage && (
                        <Button asChild size="sm">
                            <Link
                                href={companies.url({
                                    query: { demo: request.id },
                                })}
                            >
                                <Building2 aria-hidden />
                                Crear empresa
                            </Link>
                        </Button>
                    )
                )}
            </div>
            {canManage ? (
                <DemoRequestForm request={request} />
            ) : (
                <p className="text-sm whitespace-pre-line">
                    {request.note ?? (
                        <span className="text-muted-foreground">
                            Sin nota interna
                        </span>
                    )}
                </p>
            )}
        </section>
    );
}
