import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function ImportFileField({
    error,
}: {
    error: string | undefined;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor="employees-file">Archivo CSV</Label>
            <Input
                id="employees-file"
                name="file"
                type="file"
                accept=".csv,text/csv"
                aria-invalid={error === undefined ? undefined : true}
                aria-describedby={
                    error === undefined ? undefined : 'employees-file-error'
                }
            />
            <InputError
                id="employees-file-error"
                role="alert"
                message={error}
            />
        </div>
    );
}
