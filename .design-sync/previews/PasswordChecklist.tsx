import { Label, PasswordChecklist, PasswordInput } from 'humetria';

const RULES = 'minlength: 12; required: lower; required: upper; required: digit; required: special;';

const Field = ({ value }: { value: string }) => (
    <div className="grid gap-1.5 max-w-xs">
        <Label htmlFor="new-password">Contraseña nueva</Label>
        <PasswordInput id="new-password" name="password" autoComplete="new-password" defaultValue={value} aria-describedby="password-checks" />
        <PasswordChecklist id="password-checks" rules={RULES} value={value} />
    </div>
);

export const Empty = () => <Field value="" />;

export const Halfway = () => <Field value="apodaca2026" />;

export const AllMet = () => <Field value="Apodaca-2026!" />;
