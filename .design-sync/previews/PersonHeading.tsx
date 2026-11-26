import { PersonHeading } from 'humetria';

export const Greeting = () => (
    <div className="max-w-md">
        <PersonHeading title="Buenos días, Rosa">Miércoles 25 de noviembre</PersonHeading>
    </div>
);

export const BeforeIdentifying = () => (
    <div className="max-w-md">
        <PersonHeading title="Marcar su jornada">Marque con su huella, su rostro o su PIN.</PersonHeading>
    </div>
);

export const TitleOnly = () => (
    <div className="max-w-md">
        <PersonHeading title="Marcar sin internet" />
    </div>
);
