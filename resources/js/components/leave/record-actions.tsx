import { FilePen } from 'lucide-react';
import { useState } from 'react';
import RowMenu from '@/components/humetria/row-menu';
import RecordLeaveForm from '@/components/leave/record-leave-form';
import { Button } from '@/components/ui/button';
import type {
    LeaveRecordPeriod,
    LeaveRecordPerson,
    LeaveTypeOption,
} from '@/types';

/** What RH does from a record: «Registrar ausencia» (the primary) and, in the menu, adjust the days of the current period. */
export default function RecordActions({
    person,
    types,
    current,
    onAdjust,
}: {
    person: LeaveRecordPerson;
    types: LeaveTypeOption[];
    current: LeaveRecordPeriod | undefined;
    onAdjust: (period: LeaveRecordPeriod) => void;
}) {
    const [recording, setRecording] = useState(false);

    return (
        <>
            {current !== undefined && (
                <RowMenu
                    label="Más acciones del expediente"
                    items={[
                        {
                            label: 'Ajustar días',
                            icon: FilePen,
                            onSelect: () => onAdjust(current),
                        },
                    ]}
                />
            )}
            <Button
                type="button"
                onClick={() => setRecording(true)}
                className="max-sm:h-11 max-sm:flex-1"
            >
                Registrar ausencia
            </Button>
            <RecordLeaveForm
                open={recording}
                onOpenChange={setRecording}
                types={types}
                person={{ value: person.id, label: person.name }}
            />
        </>
    );
}
