import { Form } from '@inertiajs/react';
import { Shuffle } from 'lucide-react';
import SampleTable from '@/components/campaigns/sample-table';
import ConsolePagination from '@/components/console-pagination';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { redraw } from '@/routes/tenant/campaigns';
import type { Paginated, SampledPerson } from '@/types';

/** Who the draw put in the Guía III sample, a page at a time, and the way to draw it again before launching. */
export default function SampleSection({
    route,
    sample,
}: {
    /** The company's slug and the campaign's id. */
    route: [string, string];
    sample: Paginated<SampledPerson>;
}) {
    return (
        <section
            className="flex flex-col gap-3.5"
            aria-labelledby="sample-title"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2
                    id="sample-title"
                    className="text-lg leading-7 font-semibold"
                >
                    Muestra sorteada ({sample.total})
                </h2>
                <Form
                    {...redraw.form(route)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <Button
                            type="submit"
                            variant="outline"
                            size="sm"
                            disabled={processing}
                        >
                            {processing ? (
                                <Spinner />
                            ) : (
                                <Shuffle aria-hidden="true" />
                            )}
                            Volver a sortear
                        </Button>
                    )}
                </Form>
            </div>
            <SampleTable people={sample.data} />
            <ConsolePagination
                page={sample}
                label="Páginas de la muestra"
                previous="Anteriores"
                next="Siguientes"
            />
        </section>
    );
}
