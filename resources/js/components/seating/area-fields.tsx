import { Wrench } from 'lucide-react';
import InputError from '@/components/humetria/input-error';
import SettingRow from '@/components/humetria/setting-row';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AreaConditions, StandingPosition } from '@/types';

type Errors = Partial<Record<string, string>>;

const AGENTS: { key: keyof AreaConditions; label: string }[] = [
    { key: 'noise', label: 'Ruido' },
    { key: 'lighting', label: 'Iluminación' },
    { key: 'ventilation', label: 'Ventilación' },
    { key: 'temperature', label: 'Temperaturas' },
    { key: 'vibration', label: 'Vibraciones' },
    { key: 'chemicals', label: 'Contaminación por productos químicos' },
];

const OTHERS: {
    name: 'tools' | 'physical_load' | 'attention_level';
    field: 'tools' | 'physicalLoad' | 'attentionLevel';
    label: string;
}[] = [
    {
        name: 'tools',
        field: 'tools',
        label: 'Herramientas, maquinaria o equipos fijos',
    },
    {
        name: 'physical_load',
        field: 'physicalLoad',
        label: 'Carga física manual (empujar, jalar, levantar…)',
    },
    {
        name: 'attention_level',
        field: 'attentionLevel',
        label: 'Nivel de atención (visual, auditiva, motora…)',
    },
];

/** What the registry of Quinta C asks of the area and the work: the six agents, the tools, the load and the attention. */
export default function AreaFields({
    position,
    errors,
}: {
    position: StandingPosition | null;
    errors: Errors;
}) {
    return (
        <SettingRow
            icon={Wrench}
            title="Condiciones del área"
            description="El registro de la Comisión las pide por puesto: descríbelas con una frase corta cada una."
        >
            <div className="grid gap-4 sm:grid-cols-2">
                {AGENTS.map((agent) => (
                    <div key={agent.key} className="grid gap-2">
                        <Label htmlFor={`area-${agent.key}`}>
                            {agent.label}
                        </Label>
                        <Input
                            id={`area-${agent.key}`}
                            name={`area_conditions[${agent.key}]`}
                            defaultValue={
                                position?.areaConditions[agent.key] ?? ''
                            }
                        />
                        <InputError
                            message={errors[`area_conditions.${agent.key}`]}
                        />
                    </div>
                ))}
                {OTHERS.map((other) => (
                    <div key={other.name} className="grid gap-2">
                        <Label htmlFor={`position-${other.name}`}>
                            {other.label}
                        </Label>
                        <Input
                            id={`position-${other.name}`}
                            name={other.name}
                            defaultValue={position?.[other.field] ?? ''}
                        />
                        <InputError message={errors[other.name]} />
                    </div>
                ))}
            </div>
        </SettingRow>
    );
}
