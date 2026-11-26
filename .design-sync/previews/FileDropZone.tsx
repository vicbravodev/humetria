import { FileDropZone, InputError } from 'humetria';
import { Camera, FileUp } from 'lucide-react';

export const EmployeesCsv = () => (
    <div className="grid gap-2 max-w-xl">
        <FileDropZone
            id="employees-file"
            name="file"
            accept=".csv,text/csv"
            icon={FileUp}
            title="Arrastra aquí tu CSV o elígelo"
            hint="Con las columnas de la plantilla, una fila por persona."
        />
    </div>
);

export const Evidence = () => (
    <div className="grid gap-2 max-w-xl">
        <p className="text-sm font-medium">Foto del periódico mural</p>
        <FileDropZone
            id="mural-evidence"
            name="evidence"
            accept="application/pdf,image/jpeg,image/png"
            icon={Camera}
            title="Sube la foto o el PDF"
            hint="JPG, PNG o PDF de hasta 10 MB."
        />
    </div>
);

export const WithError = () => (
    <div className="grid gap-2 max-w-xl">
        <FileDropZone
            id="employees-file-bad"
            name="file"
            accept=".csv,text/csv"
            icon={FileUp}
            title="Arrastra aquí tu CSV o elígelo"
            hint="Con las columnas de la plantilla, una fila por persona."
            invalid
            describedBy="employees-file-bad-error"
        />
        <InputError id="employees-file-bad-error" message="El archivo debe ser un CSV." />
    </div>
);
