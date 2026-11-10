import { Badge } from '@/components/ui/badge';
import type { PulseModeOption } from '@/types';

/** What this pulse looks at; nothing for a climate survey, which has no mode. */
export default function ModeBadge({ mode }: { mode: PulseModeOption | null }) {
    return mode === null ? null : <Badge variant="outline">{mode.label}</Badge>;
}
