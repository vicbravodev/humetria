import { FileUp } from 'lucide-react';
import FileDropZone from '@/components/humetria/file-drop-zone';
import InputError from '@/components/humetria/input-error';

/** The CSV to upload (C4): dropped on the zone or chosen from it, which then names the file. */
export default function ImportFileField({
    error,
}: {
    error: string | undefined;
}) {
    return (
        <div className="grid gap-2">
            <FileDropZone
                id="employees-file"
                name="file"
                accept=".csv,text/csv"
                icon={FileUp}
                title="Arrastra aquí tu CSV o elígelo"
                hint="Con las columnas de la plantilla, una fila por persona."
                invalid={error !== undefined}
                describedBy="employees-file-error"
            />
            <InputError id="employees-file-error" message={error} />
        </div>
    );
}
