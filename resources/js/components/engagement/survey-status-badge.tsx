import { Badge } from '@/components/ui/badge';
import type { ClimateSurveyStatus } from '@/types';

const VARIANTS: Record<ClimateSurveyStatus, 'default' | 'secondary'> = {
    active: 'default',
    draft: 'secondary',
    closed: 'secondary',
};

/** Borrador, En curso or Cerrada: a dot and the word, never the color alone. */
export default function SurveyStatusBadge({
    status,
    label,
}: {
    status: ClimateSurveyStatus;
    label: string;
}) {
    return <Badge variant={VARIANTS[status]}>{label}</Badge>;
}
