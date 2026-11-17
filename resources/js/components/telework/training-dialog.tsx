import { Plus } from 'lucide-react';
import { useState } from 'react';
import FormSheet from '@/components/humetria/form-sheet';
import TrainingFields from '@/components/telework/training-fields';
import { Button } from '@/components/ui/button';
import { SheetTrigger } from '@/components/ui/sheet';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { Option } from '@/lib/option-search';
import { store } from '@/routes/tenant/telework/trainings';
import type { TrainingChoice, TrainingFilters } from '@/types';

/** Registers a training that was given: Humetría keeps the record, it does not give the training. */
export default function TrainingDialog({
    filters,
    today,
    topics,
    modalities,
}: {
    filters: TrainingFilters;
    today: string;
    topics: TrainingChoice[];
    modalities: TrainingChoice[];
}) {
    const tenantSlug = useTenantSlug();
    const [open, setOpen] = useState(false);
    const [center, setCenter] = useState<Option | null>(filters.center);

    return (
        <FormSheet
            open={open}
            onOpenChange={setOpen}
            title="Registrar una capacitación"
            description="Lo que se impartió y a quién: cada persona vuelve a capacitarse 12 meses después."
            form={store.form(tenantSlug)}
            submit="Registrar capacitación"
            trigger={
                <SheetTrigger asChild>
                    <Button>
                        <Plus aria-hidden />
                        Registrar capacitación
                    </Button>
                </SheetTrigger>
            }
        >
            {(errors) => (
                <>
                    <input
                        type="hidden"
                        name="filter_center"
                        value={filters.center?.value ?? ''}
                    />
                    <TrainingFields
                        center={center}
                        onCenter={setCenter}
                        today={today}
                        topics={topics}
                        modalities={modalities}
                        errors={errors}
                    />
                </>
            )}
        </FormSheet>
    );
}
