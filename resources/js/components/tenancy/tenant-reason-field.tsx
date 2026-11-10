import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

/** Why the company's access changes: always asked, because it goes to the audit trail with whoever decided. */
export default function TenantReasonField({
    error,
}: {
    error: string | undefined;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor="tenant-reason">Motivo</Label>
            <Textarea
                id="tenant-reason"
                name="reason"
                required
                maxLength={500}
                rows={3}
                aria-invalid={error !== undefined}
                aria-describedby="tenant-reason-hint tenant-reason-error"
            />
            <p
                id="tenant-reason-hint"
                className="text-caption text-muted-foreground"
            >
                Queda en la bitácora, con tu nombre y la fecha.
            </p>
            <InputError id="tenant-reason-error" message={error} />
        </div>
    );
}
