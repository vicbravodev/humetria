import { markName } from '@/lib/attendance/next-step';
import type { ReportMark } from '@/types';

/** Which mark of the day RH corrects: one option per mark in force, with its local time. */
export default function ReportMarkField({
    marks,
    value,
    onChange,
}: {
    marks: ReportMark[];
    value: string;
    onChange: (id: string) => void;
}) {
    return (
        <fieldset className="grid grid-cols-2 gap-2">
            <legend className="col-span-2 mb-2 font-medium">
                ¿Qué marca corriges?
            </legend>
            {marks.map((mark) => (
                <label key={mark.id} className="relative block">
                    <input
                        type="radio"
                        name="mark"
                        value={mark.id}
                        checked={value === mark.id}
                        onChange={() => onChange(mark.id)}
                        className="peer absolute inset-0 m-0 cursor-pointer opacity-0"
                    />
                    <span className="flex min-h-12 items-center justify-center gap-1.5 rounded-lg border border-input bg-background px-2 text-center transition-colors duration-150 ease-out peer-checked:border-2 peer-checked:border-primary peer-checked:bg-accent peer-checked:font-semibold peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring">
                        {markName(mark.type)}
                        <span className="tabular-nums">{mark.time}</span>
                    </span>
                </label>
            ))}
        </fieldset>
    );
}
