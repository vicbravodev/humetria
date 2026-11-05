import { Form, usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';
import ImportProblems from '@/components/compensation/import-problems';
import FileDropZone from '@/components/humetria/file-drop-zone';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/**
 * Loading a file of salaries (board V4-Comp-Datos, C4): the CSV is dropped on the zone or chosen from it, read whole and written
 * only when every row is valid, so a file with a problem imports nothing and lists the rows to fix.
 */
export default function CsvImportCard({
    form,
    templateUrl,
    columns,
    children,
}: {
    form: { action: string; method: 'post' };
    templateUrl: string;
    /** The columns of the file, as it names them. */
    columns: string;
    /** What else to know before loading it. */
    children: string;
}) {
    const summary = usePage().flash.salary_import;

    return (
        <section className="grid gap-5">
            <Form
                {...form}
                options={{ preserveScroll: true }}
                resetOnSuccess
                className="grid gap-3"
            >
                {({ processing, errors }) => (
                    <>
                        <FileDropZone
                            id="salary-file"
                            name="file"
                            accept=".csv,text/csv"
                            title="Arrastra aquí tu CSV o elige un archivo"
                            hint={`Columnas: ${columns}. ${children}`}
                            invalid={errors['file'] !== undefined}
                            describedBy="salary-file-error"
                        />
                        <InputError
                            id="salary-file-error"
                            message={errors['file']}
                        />
                        <div className="flex flex-wrap items-center gap-3">
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Importar
                            </Button>
                            <Button variant="ghost" asChild>
                                <a href={templateUrl} download>
                                    <Download />
                                    Descargar plantilla
                                </a>
                            </Button>
                        </div>
                    </>
                )}
            </Form>
            {summary?.status === 'invalid' && (
                <ImportProblems summary={summary} />
            )}
        </section>
    );
}
