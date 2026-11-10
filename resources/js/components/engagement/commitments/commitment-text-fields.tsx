import CommitmentTextField from '@/components/engagement/commitments/commitment-text-field';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';

type Errors = Partial<Record<string, string>>;

type Values = {
    heard: string;
    will_do: string;
    owner_name: string;
    due_on: string;
};

const BLANK: Values = { heard: '', will_do: '', owner_name: '', due_on: '' };

/** What was heard, what will be done, who answers for it and by when. */
export default function CommitmentTextFields({
    editing,
    errors,
}: {
    editing: Values | null;
    errors: Errors;
}) {
    const values = editing ?? BLANK;

    return (
        <>
            <CommitmentTextField
                id="commitment-heard"
                name="heard"
                label="Nos dijeron"
                multiline
                maxLength={500}
                defaultValue={values.heard}
                error={errors['heard']}
            />
            <CommitmentTextField
                id="commitment-will-do"
                name="will_do"
                label="Haremos"
                multiline
                maxLength={500}
                defaultValue={values.will_do}
                error={errors['will_do']}
            />
            <div className="grid gap-4 sm:grid-cols-2">
                <CommitmentTextField
                    id="commitment-owner"
                    name="owner_name"
                    label="Responsable"
                    maxLength={120}
                    defaultValue={values.owner_name}
                    error={errors['owner_name']}
                />
                <div className="grid gap-2">
                    <Label htmlFor="commitment-due">Fecha compromiso</Label>
                    <DatePicker
                        id="commitment-due"
                        name="due_on"
                        defaultValue={values.due_on}
                        invalid={errors['due_on'] !== undefined}
                    />
                    <InputError message={errors['due_on']} />
                </div>
            </div>
        </>
    );
}
