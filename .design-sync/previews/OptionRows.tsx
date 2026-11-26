import { OptionRows } from 'humetria';

export const SingleChoice = () => (
    <div className="max-w-xl">
        <OptionRows
            name="frequency"
            legend="¿Cada cuánto revisan la política?"
            shown
            multiple={false}
            columns={1}
            options={[
                { value: 'yearly', label: 'Una vez al año' },
                { value: 'two_years', label: 'Cada dos años' },
                { value: 'on_change', label: 'Cuando cambia la operación' },
            ]}
            value="yearly"
            onChange={() => {}}
        />
    </div>
);

export const SeveralInTwoColumns = () => (
    <div className="max-w-2xl">
        <OptionRows
            name="channels"
            legend="¿Por dónde pueden reportar un acto de violencia laboral?"
            shown
            multiple
            columns={2}
            options={[
                { value: 'complaints', label: 'Canal de denuncias' },
                { value: 'hr', label: 'Con Recursos Humanos' },
                { value: 'boss', label: 'Con su jefe directo' },
                { value: 'committee', label: 'Con el comité' },
            ]}
            value={['complaints', 'committee']}
            onChange={() => {}}
        />
    </div>
);

export const Unanswered = () => (
    <div className="max-w-xl">
        <OptionRows
            name="agreement"
            legend="Mi trabajo me permite desarrollar nuevas habilidades"
            shown={false}
            multiple={false}
            columns={1}
            options={[
                { value: '4', label: 'Siempre' },
                { value: '3', label: 'Casi siempre' },
                { value: '2', label: 'Algunas veces' },
                { value: '1', label: 'Casi nunca' },
                { value: '0', label: 'Nunca' },
            ]}
            value={null}
            onChange={() => {}}
        />
    </div>
);
