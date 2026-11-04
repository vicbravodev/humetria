import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

/** For the notice of a traumatic event: the names of the people involved, if there are any (num. 6.5). */
export default function InvolvedField({
    value,
    onChange,
}: {
    value: string;
    onChange: (text: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor="complaint-involved">
                Nombres de las personas involucradas, si las hay (opcional)
            </Label>
            <Textarea
                id="complaint-involved"
                rows={3}
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
        </div>
    );
}
