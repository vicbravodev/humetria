import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { guidesText, staffText } from '@/lib/work-centers';
import type { WorkCenterEvaluation } from '@/types';

/** The work centers that can be evaluated now, with their people and the guides that apply. */
export default function WorkCenterSelect({
    workCenters,
    value,
    onChange,
    error,
}: {
    workCenters: WorkCenterEvaluation[];
    value: string;
    onChange: (id: string) => void;
    error: string | undefined;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor="evaluation-work-center">Centro de trabajo</Label>
            <Select
                name="work_center_id"
                value={value}
                onValueChange={onChange}
            >
                <SelectTrigger
                    id="evaluation-work-center"
                    className="w-full"
                    aria-invalid={error === undefined ? undefined : true}
                    aria-describedby={
                        error === undefined
                            ? undefined
                            : 'evaluation-work-center-error'
                    }
                >
                    <SelectValue placeholder="Elige un centro" />
                </SelectTrigger>
                <SelectContent>
                    {workCenters.map((workCenter) => (
                        <SelectItem key={workCenter.id} value={workCenter.id}>
                            {workCenter.name} · {staffText(workCenter.workers)}{' '}
                            · {guidesText(workCenter.guides)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError
                id="evaluation-work-center-error"
                role="alert"
                message={error}
            />
        </div>
    );
}
