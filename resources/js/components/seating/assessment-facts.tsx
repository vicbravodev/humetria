import { FileText } from 'lucide-react';
import SettingRow from '@/components/humetria/setting-row';
import type { AssessmentFact } from '@/types';

/** The eleven items of the registry of Quinta C, as the Comisión records them; frozen once the assessment is signed. */
export default function AssessmentFacts({
    facts,
}: {
    facts: AssessmentFact[];
}) {
    return (
        <SettingRow
            icon={FileText}
            title="Registro de la Comisión"
            description="Los datos que pide la Quinta C de las Disposiciones. Salen del puesto, del centro y de la persona; al firmar quedan fijos."
        >
            <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                {facts.map((fact) => (
                    <div key={fact.numeral} className="flex flex-col gap-0.5">
                        <dt className="text-caption text-muted-foreground">
                            {fact.numeral}. {fact.label}
                        </dt>
                        <dd className="text-base">{fact.value}</dd>
                    </div>
                ))}
            </dl>
        </SettingRow>
    );
}
