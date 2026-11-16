import { Presentation } from 'lucide-react';
import RoomRunButton from '@/components/demos/room-run-button';
import EmptyState from '@/components/humetria/empty-state';

/** No demo company yet: the next step is to prepare it. */
export default function RoomEmpty({ running }: { running: boolean }) {
    return (
        <EmptyState
            icon={Presentation}
            title="Aún no hay demo"
            action={<RoomRunButton kind="prepare" running={running} />}
        >
            Prepárala para tener una empresa creíble con su historia en todos
            los módulos, lista para enseñar en vivo.
        </EmptyState>
    );
}
