import { ReadOnlyNotice } from 'humetria';

export const DefaultReason = () => <ReadOnlyNotice />;

export const StaffAsOwner = () => (
    <ReadOnlyNotice>
        Entraste como soporte de Humetría a Grupo Industrial Norteño: puedes revisar los horarios, pero no cambiarlos.
    </ReadOnlyNotice>
);
