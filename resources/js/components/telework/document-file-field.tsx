import { Upload } from 'lucide-react';
import FileDropZone from '@/components/humetria/file-drop-zone';
import SettingRow from '@/components/humetria/setting-row';
import InputError from '@/components/humetria/input-error';

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
            <FileDropZone
                name="document"
                accept="application/pdf"
                title="Arrastra el PDF aquí o elígelo"
                hint="Un documento en PDF"
                invalid={error !== undefined}
                describedBy="document-file-error"
            />
            <InputError id="document-file-error" message={error} />
        </SettingRow>
    );
}
