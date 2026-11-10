import ReviewStep from '@/components/engagement/review-step';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { weekChoices } from '@/lib/evaluation';

/** The last day to answer: one, two or three weeks at a click, or any day within the limits in the calendar. */
export default function DeadlineStep({
    value,
    onChange,
    limits,
    error,
}: {
    value: string;
    onChange: (value: string) => void;
    limits: { min: string; suggested: string; max: string };
    error: string | undefined;
}) {
    return (
        <ReviewStep number={3} title="Último día para contestar">
            <div
                className="flex flex-wrap gap-2"
                role="group"
                aria-label="Plazo"
            >
                {weekChoices(limits.suggested).map((choice) => (
                    <Button
                        key={choice.date}
                        type="button"
                        size="sm"
                        variant={choice.date === value ? 'default' : 'outline'}
                        aria-pressed={choice.date === value}
                        onClick={() => onChange(choice.date)}
                    >
                        {choice.label}
                    </Button>
                ))}
            </div>
            <div className="grid max-w-80 gap-2">
                <Label htmlFor="survey-closes_on">Fecha</Label>
                <DatePicker
                    id="survey-closes_on"
                    name="closes_on"
                    min={limits.min}
                    max={limits.max}
                    value={value}
                    onChange={onChange}
                    invalid={error !== undefined}
                    describedBy="survey-closes_on-help survey-closes_on-error"
                />
                <p
                    id="survey-closes_on-help"
                    className="text-sm text-muted-foreground"
                >
                    Cada enlace funciona hasta las 23:59 de ese día.
                </p>
                <InputError id="survey-closes_on-error" message={error} />
            </div>
        </ReviewStep>
    );
}
