import { Upload } from 'lucide-react';
import SettingRow from '@/components/humetria/setting-row';
import InputError from '@/components/input-error';

/** Step 2: the PDF itself. Its content is checked by the server. */
export default function DocumentFileField({
    error,
}: {
    error: string | undefined;
}) {
    return (
        <SettingRow
            icon={Upload}
            title="Paso 2 · Archivo PDF"
            description="Cada archivo que subas queda como una versión nueva; las anteriores se conservan."
        >
            <input
                name="document"
                type="file"
                accept="application/pdf"
                aria-label="Documento en PDF"
                aria-invalid={error === undefined ? undefined : true}
                className="w-full rounded-md border border-input bg-card px-4 py-2.5 text-base file:mr-3 file:font-medium"
            />
            <InputError role="alert" message={error} />
        </SettingRow>
    );
}
