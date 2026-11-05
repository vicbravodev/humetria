import { Form, usePage } from '@inertiajs/react';
import { Download, FileUp } from 'lucide-react';
import ImportProblems from '@/components/compensation/import-problems';
import IconTile from '@/components/humetria/icon-tile';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

/**
 * Loading a file of salaries: the CSV is read whole and written only when every row is valid, so a file with a problem
 * imports nothing and lists the rows to fix.
 */
export default function CsvImportCard({
    form,
    templateUrl,
    title,
    columns,
    children,
}: {
    form: { action: string; method: 'post' };
    templateUrl: string;
    title: string;
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
                className="grid gap-4 card p-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="flex items-start gap-3">
                            <IconTile icon={FileUp} />
                            <div className="min-w-0 flex-1">
                                <h2 className="text-lg leading-[26px] font-semibold">
                                    {title}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Columnas: <code>{columns}</code>. {children}
                                </p>
                            </div>
                            <Button variant="ghost" asChild>
                                <a href={templateUrl} download>
                                    <Download />
                                    Plantilla
                                </a>
                            </Button>
                        </div>
                        <div className="grid items-start gap-3 sm:grid-cols-[1fr_auto]">
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="salary-file"
                                    className="sr-only"
                                >
                                    Archivo CSV
                                </Label>
                                <Input
                                    id="salary-file"
                                    name="file"
                                    type="file"
                                    accept=".csv,text/csv"
                                    aria-invalid={
                                        errors['file'] === undefined
                                            ? undefined
                                            : true
                                    }
                                    aria-describedby={
                                        errors['file'] === undefined
                                            ? undefined
                                            : 'salary-file-error'
                                    }
                                />
                                <InputError
                                    id="salary-file-error"
                                    message={errors['file']}
                                />
                            </div>
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Importar
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
