import { Head } from '@inertiajs/react';
import { History, ShieldCheck } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import SettingRow from '@/components/humetria/setting-row';
import GroupMinimumForm from '@/components/tenancy/group-minimum-form';

/** «Privacidad de los resultados» (the owner's): the minimum of people a group needs before its result is shown. */
export default function Privacy({
    minimum,
    chosen,
    recommended,
    lowest,
    highest,
}: {
    minimum: number;
    chosen: number | null;
    recommended: number;
    lowest: number;
    highest: number;
}) {
    return (
        <>
            <Head title="Privacidad de los resultados" />
            <div className="flex w-full max-w-[880px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Privacidad de los resultados"
                    description="Cuántas personas necesita un grupo para que veas su resultado en NOM-035, Ley Silla, clima y pulso y el canal de denuncias."
                />
                <div className="flex flex-col gap-7 card p-6 [&>section:first-child]:border-t-0 [&>section:first-child]:pt-0">
                    <SettingRow
                        icon={ShieldCheck}
                        title="Mínimo de personas por grupo"
                        status={
                            <span className="rounded-full bg-muted px-2.5 py-0.5 text-caption text-muted-foreground">
                                {chosen === null
                                    ? 'Valor recomendado'
                                    : 'Elegido por tu empresa'}
                            </span>
                        }
                        description="Protege a quien responde: con menos personas, el resultado de un grupo puede dejar adivinar quién respondió. Un área, turno o centro con menos personas que este mínimo no muestra su resultado."
                    >
                        <GroupMinimumForm
                            minimum={minimum}
                            chosen={chosen}
                            recommended={recommended}
                            lowest={lowest}
                            highest={highest}
                        />
                    </SettingRow>
                    <SettingRow
                        icon={History}
                        title="Lo ya lanzado no cambia"
                        description="El cambio vale para las campañas y encuestas que lances desde ahora. Las ya lanzadas conservan el mínimo con el que salieron: a quienes ya respondieron se les prometió ese número."
                    />
                </div>
            </div>
        </>
    );
}
