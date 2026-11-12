import { PartyPopper } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';
import { Button } from '@/components/ui/button';

/** What the panel says once every step is done: shown once, then the button is gone. */
export default function FirstStepsDone({ onClose }: { onClose: () => void }) {
    return (
        <div className="flex flex-col items-start gap-3">
            <IconTile icon={PartyPopper} />
            <p className="text-base font-semibold">
                Listo: Humetria ya está trabajando para ti
            </p>
            <Button size="sm" onClick={onClose}>
                Cerrar
            </Button>
        </div>
    );
}
