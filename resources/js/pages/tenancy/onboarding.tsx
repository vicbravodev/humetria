import { Head, Link } from '@inertiajs/react';
import Pagination from '@/components/humetria/pagination';
import FirstStepsTour from '@/components/first-steps/first-steps-tour';
import SaveError from '@/components/humetria/save-error';
import SetupFrame from '@/components/setup-frame';
import OnboardingCenter from '@/components/tenancy/onboarding-center';
import OnboardingSummary from '@/components/tenancy/onboarding-summary';
import { useOnboarding } from '@/hooks/use-onboarding';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { OnboardingCenter as Center } from '@/lib/onboarding';
import { index as workCenters } from '@/routes/tenant/work-centers';
import type { Paginated } from '@/types';

type Props = { company: string; centers: Paginated<Center> };

/** The first time of a company (board V3-Onboarding): two questions per center, and its routes. */
export default function TenancyOnboarding({ company, centers }: Props) {
    const tenantSlug = useTenantSlug();
    const setup = useOnboarding(tenantSlug, centers.data);

    return (
        <SetupFrame
            caption={`${company} · primera vez`}
            aside={
                <span className="text-sm text-muted-foreground">
                    Paso 2 de 2
                </span>
            }
        >
            <Head title="Primera vez" />
            <FirstStepsTour />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-7">
                <header className="flex max-w-[760px] flex-col gap-2.5">
                    <h1 className="text-3xl leading-10 font-semibold tracking-[-0.015em] text-balance">
                        Dos preguntas por centro y armamos todo
                    </h1>
                    <p className="text-lg leading-7 text-muted-foreground">
                        Con esto sabemos qué normas te tocan en cada centro y
                        creamos tus rutas. Puedes cambiarlo después.
                    </p>
                </header>
                <div className="grid items-start gap-8 min-[1100px]:grid-cols-[minmax(0,1fr)_400px]">
                    <div
                        data-tour="applicability-questions"
                        className="flex min-w-0 flex-col gap-4"
                    >
                        {centers.data.map((center) => (
                            <OnboardingCenter
                                key={center.id}
                                center={center}
                                answers={setup.answers[center.id] ?? center}
                                onAnswer={(change) =>
                                    setup.answer(center.id, change)
                                }
                            />
                        ))}
                        {centers.last_page > 1 && (
                            <Pagination
                                page={centers}
                                label="Páginas de centros de trabajo"
                                pinned={false}
                            />
                        )}
                        <Link
                            href={workCenters(tenantSlug)}
                            className="inline-flex min-h-11 items-center self-start rounded-lg border border-dashed border-input bg-card px-5 font-medium"
                        >
                            Agregar otro centro
                        </Link>
                    </div>
                    <div className="flex flex-col gap-4">
                        <OnboardingSummary
                            centers={centers.data}
                            answers={setup.answers}
                            processing={setup.saving.processing}
                            onCreate={setup.create}
                        />
                        {setup.saving.failed && (
                            <SaveError
                                onRetry={setup.saving.retry}
                                processing={setup.saving.processing}
                            />
                        )}
                    </div>
                </div>
            </div>
        </SetupFrame>
    );
}
