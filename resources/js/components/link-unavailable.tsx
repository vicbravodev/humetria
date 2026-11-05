import { Clock } from 'lucide-react';
import type { ReactNode } from 'react';
import StatusMessage from '@/components/humetria/status-message';

/**
 * A personal or shared link that opens nothing (board V3-Estados, «Enlace vencido»): the same words wherever it happens —
 * the survey, the portal, a shared report, the people's page, the complaints channel —, never why nor whose it was. What
 * to do goes in the voice of whoever reads it.
 */
export default function LinkUnavailable({ children }: { children: ReactNode }) {
    return (
        <StatusMessage icon={Clock} title="Este enlace ya no está disponible">
            {children}
        </StatusMessage>
    );
}
