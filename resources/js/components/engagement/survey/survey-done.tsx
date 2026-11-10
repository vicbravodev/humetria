import { CircleCheck } from 'lucide-react';
import StatusMessage from '@/components/humetria/status-message';

/** The survey was sent: thanks, and that the link no longer opens anything. */
export default function SurveyDone() {
    return (
        <div className="flex flex-1 items-center justify-center py-8">
            <StatusMessage
                icon={CircleCheck}
                caption="Encuesta enviada"
                title="Gracias. Sus respuestas ya se enviaron."
            >
                Ya puede cerrar esta página. El enlace dejó de funcionar para
                que nadie más lo use.
            </StatusMessage>
        </div>
    );
}
