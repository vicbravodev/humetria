import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

/** Why the person asks for the correction: at least 10 characters, as the server asks. */
export default function CorrectionReasonField({
    value,
    onChange,
}: {
    value: string;
    onChange: (reason: string) => void;
}) {
    return (
        <div className="flex flex-col gap-2">
            <Label htmlFor="correction-reason" className="text-base">
                ¿Por qué pide la corrección?
            </Label>
            <Textarea
                id="correction-reason"
                name="reason"
                required
                minLength={10}
                maxLength={500}
                rows={3}
                className="text-base"
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
        </div>
    );
}
