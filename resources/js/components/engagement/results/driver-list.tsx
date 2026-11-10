import DriverRow from '@/components/engagement/results/driver-row';
import type { EngagementDriver } from '@/types';

/** The drivers of the company, in the order of the questionnaire, each as a bar of its favorable share. */
export default function DriverList({
    drivers,
}: {
    drivers: EngagementDriver[];
}) {
    return (
        <section className="grid gap-6 card p-5 sm:p-6 lg:grid-cols-3 lg:gap-x-10">
            <div className="flex flex-col gap-2">
                <h2 className="text-lg font-semibold">
                    Qué mueve el compromiso
                </h2>
                <p className="text-sm text-muted-foreground">
                    El porcentaje es de respuestas favorables (de acuerdo y
                    totalmente de acuerdo) a las preguntas de cada tema.
                </p>
            </div>
            <ol className="flex flex-col gap-5 lg:col-span-2">
                {drivers.map((driver, index) => (
                    <DriverRow key={driver.key} driver={driver} index={index} />
                ))}
            </ol>
        </section>
    );
}
