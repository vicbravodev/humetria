import { PillChoice } from 'humetria';

const YES_NO = [
    { value: 'yes', label: 'Sí' },
    { value: 'no', label: 'No' },
];

export const YesNo = () => (
    <PillChoice
        name="telework"
        legend="¿Alguien trabaja desde su casa más del 40 % de su tiempo?"
        options={YES_NO}
        value="yes"
        onChange={() => {}}
    />
);

export const Sector = () => (
    <div className="max-w-xl">
        <PillChoice
            name="sector"
            legend="¿A qué se dedica el centro?"
            options={[
                { value: 'manufacturing', label: 'Manufactura' },
                { value: 'retail', label: 'Comercio' },
                { value: 'services', label: 'Servicios' },
                { value: 'logistics', label: 'Logística' },
                { value: 'health', label: 'Salud' },
                { value: 'other', label: 'Otro' },
            ]}
            value="logistics"
            onChange={() => {}}
        />
    </div>
);

export const Unanswered = () => (
    <PillChoice
        name="standing"
        legend="¿Alguien hace su trabajo de pie, aunque sea parte de su jornada?"
        options={YES_NO}
        value={null}
        onChange={() => {}}
    />
);
