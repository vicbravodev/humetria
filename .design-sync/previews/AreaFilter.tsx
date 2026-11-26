import { AreaFilter } from 'humetria';

const AREAS = [
    { id: 'a1', name: 'Producción' },
    { id: 'a2', name: 'Almacén' },
    { id: 'a3', name: 'Administración' },
];

export const WholeCenter = () => (
    <AreaFilter url="/admin/acme/campaigns/1/results" areas={AREAS} selected={null} />
);

export const OneArea = () => (
    <AreaFilter url="/admin/acme/campaigns/1/results" areas={AREAS} selected={{ value: 'a1' }} />
);
