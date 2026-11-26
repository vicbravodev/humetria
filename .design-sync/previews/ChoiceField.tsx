import { ChoiceField } from 'humetria';

const MODES = [
    { value: 'company', label: 'Igual que la empresa' },
    { value: 'field', label: 'En campo' },
];

export const HowTheyMark = () => (
    <div className="max-w-md">
        <ChoiceField
            name="mode"
            legend="Cómo marcan en Planta Apodaca"
            options={MODES}
            value="company"
            onChange={() => {}}
        />
    </div>
);

export const ThreeColumns = () => (
    <ChoiceField
        name="shift"
        legend="Turno"
        columns={3}
        options={[
            { value: 'morning', label: 'Matutino' },
            { value: 'evening', label: 'Vespertino' },
            { value: 'night', label: 'Nocturno' },
        ]}
        value="evening"
        onChange={() => {}}
    />
);

export const LargeQuestion = () => (
    <div className="max-w-xl">
        <ChoiceField
            name="workers"
            size="lg"
            legend="¿Cuántas personas trabajan en este centro?"
            hint="Cuenta a todas, de planta y eventuales. Con eso sabemos qué guías aplican."
            options={[
                { value: 'small', label: 'Hasta 15' },
                { value: 'medium', label: 'De 16 a 50' },
                { value: 'large', label: 'Más de 50' },
            ]}
            value="medium"
            onChange={() => {}}
        />
    </div>
);

export const WithError = () => (
    <div className="max-w-md">
        <ChoiceField
            name="mode-error"
            legend="Cómo marcan en Bodega Escobedo"
            options={MODES}
            value={null}
            onChange={() => {}}
            error="Elige cómo marcan en este centro."
        />
    </div>
);
