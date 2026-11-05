import { Badge } from '@/components/ui/badge';
import type { CaseDetail } from '@/types';

/** Where a case stands in words (board V4-Quejas-Caso): waiting for its acknowledgement, acknowledged, in review or closed. */
export default function CaseStatus({
    detail,
    acknowledged,
}: {
    detail: CaseDetail;
    acknowledged: boolean;
}) {
    if (detail.status === 'closed') {
        return (
            <Badge variant="secondary">
                Cerrada
                {detail.resolution === null ? '' : `: ${detail.resolution}`}
            </Badge>
        );
    }

    if (detail.status === 'in_review') {
        return <Badge>En revisión</Badge>;
    }

    return acknowledged ? (
        <Badge>Acuse enviado</Badge>
    ) : (
        <Badge variant="destructive">Espera acuse</Badge>
    );
}
