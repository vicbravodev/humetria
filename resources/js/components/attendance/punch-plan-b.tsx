import { Smartphone } from 'lucide-react';
import OfflineFrame from '@/components/attendance/offline-frame';
import StatusMessage from '@/components/humetria/status-message';
import { Button } from '@/components/ui/button';

/**
 * The person's key did not sign without a network (plan B). With the phone's own key and a pass that is still good, it offers to
 * sign with the phone, and says RH will see it; otherwise it says what to do: enter once with internet to prepare the phone.
 */
export default function PunchPlanB({
    company,
    deviceKey,
    busy,
    onMarkWithPhone,
    onRetry,
    onClose,
}: {
    company: string;
    /** This phone has a key and a pass that is still good. */
    deviceKey: boolean;
    busy: boolean;
    onMarkWithPhone: () => void;
    onRetry: () => void;
    onClose: () => void;
}) {
    return (
        <OfflineFrame
            company={company}
            centered
            actions={
                deviceKey ? (
                    <>
                        <Button
                            className="h-14 w-full text-[17px] font-semibold"
                            disabled={busy}
                            onClick={onMarkWithPhone}
                        >
                            Marcar con este teléfono
                        </Button>
                        <Button
                            variant="outline"
                            className="h-12 w-full text-base"
                            disabled={busy}
                            onClick={onRetry}
                        >
                            Intentar con mi llave otra vez
                        </Button>
                    </>
                ) : (
                    <Button
                        variant="outline"
                        className="h-14 w-full text-[17px]"
                        onClick={onClose}
                    >
                        Entendido
                    </Button>
                )
            }
        >
            <StatusMessage
                icon={Smartphone}
                title={
                    deviceKey
                        ? 'Su llave no respondió sin internet'
                        : 'Falta preparar este teléfono'
                }
            >
                {deviceKey
                    ? 'Puede marcar con este teléfono. Recursos Humanos verá que se firmó con el teléfono.'
                    : 'Para marcar sin internet con este teléfono, entre una vez a Humetría con internet.'}
            </StatusMessage>
        </OfflineFrame>
    );
}
