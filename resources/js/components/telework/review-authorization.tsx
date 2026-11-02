import { Badge } from '@/components/ui/badge';
import ReviewAttachForm from '@/components/telework/review-attach-form';
import ReviewCancelButton from '@/components/telework/review-cancel-button';
import ReviewVisitDialog from '@/components/telework/review-visit-dialog';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { describeDate } from '@/lib/calendar-date';
import {
    AUTHORIZATION_BADGES,
    authorizationState,
} from '@/lib/telework/review';
import { show } from '@/routes/tenant/telework/visit-authorizations/document';
import type { ReviewAuthorization as Authorization } from '@/types';

function viaLabel(authorization: Authorization): string {
    return authorization.viaPortal ? 'Confirmada en el portal' : 'PDF firmado';
}

/** One request to visit: waiting for the person, given (and ready to record a visit) or already used. */
export default function ReviewAuthorization({
    workplaceId,
    authorization,
}: {
    workplaceId: string;
    authorization: Authorization;
}) {
    const tenantSlug = useTenantSlug();
    const state = authorizationState(authorization);

    return (
        <li className="flex flex-col gap-2 px-5 py-4">
            <div className="flex flex-wrap items-center gap-2">
                <p className="font-medium">
                    Visita pedida el {describeDate(authorization.requestedOn)}
                </p>
                <Badge variant={AUTHORIZATION_BADGES[state].tone}>
                    {AUTHORIZATION_BADGES[state].label}
                </Badge>
            </div>
            {authorization.authorized && (
                <p className="text-meta text-muted-foreground">
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
            {state === 'waiting' && (
                <ReviewAttachForm authorizationId={authorization.id} />
            )}
            {state === 'given' && (
                <ReviewVisitDialog
                    workplaceId={workplaceId}
                    authorizationId={authorization.id}
                />
            )}
            {(state === 'waiting' || state === 'given') && (
                <ReviewCancelButton authorizationId={authorization.id} />
            )}
        </li>
    );
}
