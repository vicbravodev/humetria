import { Gauge } from 'lucide-react';
import ModePicker from '@/components/engagement/pulse/mode-picker';
import EmptyState from '@/components/humetria/empty-state';
import type { PulseModeOption } from '@/types';

/** No pulse at all and none scheduled: what a pulse is and, for who manages it, the first one. */
export default function PulseFirstTime({
    modes,
}: {
    /** The modes to create it in; `null` for who only reads. */
    modes: PulseModeOption[] | null;
}) {
    return (
        <EmptyState
            icon={Gauge}
            title="Todavía no hay un pulso"
            {...(modes === null
                ? {}
                : {
                      action: (
                          <ModePicker
                              modes={modes}
                              label="Crear el primer pulso"
                          />
                      ),
                  })}
        >
            Una encuesta corta, mensual o trimestral, para ver cómo cambia el
            clima entre una encuesta y otra. Nadie recibe nada hasta que lo
            lances.
        </EmptyState>
    );
}
