import { Badge } from '@/components/ui/badge';
import ReviewAttachForm from '@/components/telework/review-attach-form';
import ReviewVisitDialog from '@/components/telework/review-visit-dialog';
import { describeDate } from '@/lib/calendar-date';
import { show } from '@/routes/tenant/telework/visit-authorizations/document';
import type { ReviewAuthorization as Authorization } from '@/types';

function viaLabel(authorization: Authorization): string {
    return authorization.viaPortal ? 'Confirmada en el portal' : 'PDF firmado';
}

/** One request to visit: waiting for the person, given (and ready to record a visit) or already used. */
export default function ReviewAuthorization({
    tenantSlug,
    workplaceId,
    authorization,
}: {
    tenantSlug: string;
    workplaceId: string;
    authorization: Authorization;
}) {
    return (
        <li className="flex flex-col gap-2 px-5 py-4">
            <div className="flex flex-wrap items-center gap-2">
                <p className="font-medium">
                    Visita pedida el {describeDate(authorization.requestedOn)}
                </p>
                <Badge
                    variant={authorization.authorized ? 'success' : 'warning'}
                >
                    {authorization.used
                        ? 'Usada'
                        : authorization.authorized
                          ? 'Autorizada'
                          : 'Esperando a la persona'}
                </Badge>
            </div>
            {authorization.authorized && (
                <p className="text-[15px] text-muted-foreground">
                    {viaLabel(authorization)}
                    {authorization.hasDocument && (
                        <>
                            {' · '}
                            <a
                                className="underline"
                                href={
                                    show({
                                        tenant: tenantSlug,
                                        authorization: authorization.id,
                                    }).url
                                }
                                target="_blank"
                                rel="noreferrer"
                            >
                                Ver el PDF
                            </a>
                        </>
                    )}
                </p>
            )}
            {!authorization.authorized && (
                <ReviewAttachForm
                    tenantSlug={tenantSlug}
                    authorizationId={authorization.id}
                />
            )}
            {authorization.authorized && !authorization.used && (
                <ReviewVisitDialog
                    tenantSlug={tenantSlug}
                    workplaceId={workplaceId}
                    authorizationId={authorization.id}
                />
            )}
        </li>
    );
}
