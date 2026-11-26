import { FlowProgress, OptionRows, QuestionHeading } from 'humetria';

export const PolicyQuestion = () => (
    <QuestionHeading
        topic="Violencia laboral"
        title="¿A quién puede acudir una persona que sufre acoso en el trabajo?"
        hint="Nombra el puesto, no a la persona: así la política sigue vigente si alguien se va."
    />
);

export const SurveyQuestion = () => (
    <QuestionHeading topic="Liderazgo" title="Mi jefe me trata con respeto." hint={null} />
);

export const InTheFlow = () => (
    <div className="flex flex-col gap-6 max-w-xl">
        <FlowProgress label="Pregunta 3 de 8" left="Faltan unos 9 min" value={2} max={8} />
        <QuestionHeading
            topic="Canal de denuncias"
            title="¿Por qué medios pueden presentar una queja?"
            hint="Marca todos los que ya tiene la empresa."
        />
        <OptionRows
            name="channels"
            legend="Medios para presentar una queja"
            shown={false}
            multiple
            columns={1}
            value={['portal', 'rh']}
            onChange={() => {}}
            options={[
                { value: 'portal', label: 'Canal de denuncias en línea' },
                { value: 'rh', label: 'Con Recursos Humanos, en persona' },
                { value: 'buzon', label: 'Buzón físico en el comedor' },
            ]}
        />
    </div>
);
