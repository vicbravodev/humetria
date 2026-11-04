import DatePicker from '@/components/humetria/date-picker';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store } from '@/routes/tenant/disclosure/dissemination/acknowledgments';

/**
 * Records the confirmations people gave on paper (or when they have no email), in bulk: the day they signed, never before
 * the publication nor in the future, and a note about the sheet. `choice` is who was ticked, or every pending person a search
 * found.
 */
export default function PaperDialog({
    open,
    onOpenChange,
    documentId,
    choice,
    days,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    documentId: string;
    choice: { ids: string[]; allMatching: string | null };
    days: { first: string; today: string };
}) {
    const tenantSlug = useTenantSlug();

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Registrar acuses en papel"
            description="Quienes firmaron una hoja quedan como confirmados. No se avisa a nadie."
            form={store.form({ tenant: tenantSlug, document: documentId })}
            submit="Registrar"
        >
            {(errors) => (
                <>
                    {choice.allMatching === null ? (
                        choice.ids.map((id) => (
                            <input
                                key={id}
                                type="hidden"
                                name="employee_ids[]"
                                value={id}
                            />
                        ))
                    ) : (
                        <>
                            <input
                                type="hidden"
                                name="all_matching"
                                value="1"
                            />
                            <input
                                type="hidden"
                                name="q"
                                value={choice.allMatching}
                            />
                        </>
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="paper-date">Día en que firmaron</Label>
                        <DatePicker
                            id="paper-date"
                            name="date"
                            defaultValue={days.today}
                            min={days.first}
                            max={days.today}
                            invalid={errors['date'] !== undefined}
                        />
                        <InputError message={errors['date']} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="paper-note">
                            Nota (por ejemplo, la hoja de firmas)
                        </Label>
                        <Textarea
                            id="paper-note"
                            name="note"
                            aria-invalid={
                                errors['note'] === undefined ? undefined : true
                            }
                        />
                        <InputError message={errors['note']} />
                    </div>
                    <InputError
                        message={
                            errors['employee_ids'] ?? errors['employee_ids.0']
                        }
                    />
                </>
            )}
        </FormDialog>
    );
}
