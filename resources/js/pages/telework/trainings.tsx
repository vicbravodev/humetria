import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import PageHeader from '@/components/page-header';
import TrainingCoverage from '@/components/telework/training-coverage';
import TrainingDialog from '@/components/telework/training-dialog';
import TrainingList from '@/components/telework/training-list';
import TrainingPeople from '@/components/telework/training-people';
import TrainingToolbar from '@/components/telework/training-toolbar';
import type {
    Paginated,
    TrainingChoice,
    TrainingFilters,
    TrainingPerson,
    TrainingRow as Row,
} from '@/types';

export default function Trainings({
    trainings,
    people,
    filters,
    uncovered,
    today,
    topics,
    modalities,
}: {
    trainings: Paginated<Row>;
    people: Paginated<TrainingPerson>;
    filters: TrainingFilters;
    uncovered: TrainingChoice[] | null;
    today: string;
    topics: TrainingChoice[];
    modalities: TrainingChoice[];
}) {
    const tenantSlug = usePage().props.tenant?.slug ?? '';
    setLayoutProps({
        breadcrumbs: [{ title: 'NOM-037' }, { title: 'Capacitación' }],
    });

    return (
        <>
            <Head title="Capacitación de NOM-037" />
            <div className="flex w-full max-w-[880px] flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Capacitación de NOM-037"
                    description="Cada persona del padrón se capacita al menos una vez al año, con los temas del numeral 8.1. Aquí registras lo que se impartió; Humetría no imparte la capacitación."
                >
                    <TrainingDialog
                        tenantSlug={tenantSlug}
                        filters={filters}
                        today={today}
                        topics={topics}
                        modalities={modalities}
                    />
                </PageHeader>
                <TrainingToolbar tenantSlug={tenantSlug} filters={filters} />
                <TrainingCoverage uncovered={uncovered} />
                <TrainingPeople people={people} />
                <TrainingList tenantSlug={tenantSlug} trainings={trainings} />
            </div>
        </>
    );
}
