import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import TrainingFields from '@/components/telework/training-fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import type { Option } from '@/lib/option-search';
import { store } from '@/routes/tenant/telework/trainings';
import type { TrainingChoice, TrainingFilters } from '@/types';

/** Registers a training that was given: Humetría keeps the record, it does not give the training. */
export default function TrainingDialog({
    tenantSlug,
    filters,
    today,
    topics,
    modalities,
}: {
    tenantSlug: string;
    filters: TrainingFilters;
    today: string;
    topics: TrainingChoice[];
    modalities: TrainingChoice[];
}) {
    const [open, setOpen] = useState(false);
    const [center, setCenter] = useState<Option | null>(filters.center);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus aria-hidden />
                    Registrar capacitación
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                <DialogTitle>Registrar una capacitación</DialogTitle>
                <DialogDescription>
                    Lo que se impartió y a quién: cada persona vuelve a
                    capacitarse 12 meses después.
                </DialogDescription>
                <Form
                    {...store.form(tenantSlug)}
                    noValidate
                    onSuccess={() => setOpen(false)}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <TrainingFields
                                tenantSlug={tenantSlug}
                                center={center}
                                onCenter={setCenter}
                                today={today}
                                topics={topics}
                                modalities={modalities}
                                errors={errors}
                            />
                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-fit"
                            >
                                {processing && <Spinner />}
                                Registrar capacitación
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
