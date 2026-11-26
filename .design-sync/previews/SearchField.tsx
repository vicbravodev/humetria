import { SearchField } from 'humetria';

export const Empty = () => (
    <div className="max-w-sm">
        <SearchField label="Buscar por nombre o número de empleado" value="" onChange={() => {}} />
    </div>
);

export const WithQuery = () => (
    <div className="max-w-sm">
        <SearchField label="Buscar centro de trabajo" value="Apodaca" onChange={() => {}} />
    </div>
);
