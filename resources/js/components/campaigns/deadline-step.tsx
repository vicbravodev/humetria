import ReviewStep from '@/components/campaigns/review-step';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { weekChoices } from '@/lib/evaluation';

/** The last day to answer: one, two or three weeks at a click, or any day from tomorrow in the calendar. */
export default function DeadlineStep({
    value,
    onChange,
    limits,
    error,
}: {
    value: string;
    onChange: (value: string) => void;
    limits: { min: string; suggested: string };
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
                <Label htmlFor="campaign-closes_on">Fecha</Label>
                <DatePicker
                    id="campaign-closes_on"
                    name="closes_on"
                    min={limits.min}
                    value={value}
                    onChange={onChange}
                    invalid={error !== undefined}
                    describedBy="campaign-closes_on-help campaign-closes_on-error"
                />
                <p
                    id="campaign-closes_on-help"
                    className="text-sm text-muted-foreground"
                >
                    Cada enlace funciona hasta las 23:59 de ese día.
                </p>
                <InputError
                    id="campaign-closes_on-error"
                    role="alert"
                    message={error}
                />
            </div>
        </ReviewStep>
    );
}
