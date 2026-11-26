import { CountTabs } from 'humetria';

export const CorrectionsStatus = () => (
    <CountTabs
        label="Estado de las solicitudes"
        tabs={[
            { label: 'Pendientes', count: 12, href: '#pendientes', active: true },
            { label: 'Aprobadas', count: 48, href: '#aprobadas', active: false },
            { label: 'Rechazadas', count: 5, href: '#rechazadas', active: false },
            { label: 'Todas', count: 65, href: '#todas', active: false },
        ]}
    />
);

export const SecondTabActive = () => (
    <CountTabs
        label="Estado de las personas"
        tabs={[
            { label: 'Pendientes', count: 42, href: '#pendientes', active: false },
            { label: 'Contestaron', count: 18, href: '#contestaron', active: true },
            { label: 'Todas', count: 60, href: '#todas', active: false },
        ]}
    />
);

export const WithoutCounts = () => (
    <CountTabs
        label="Roles"
        tabs={[
            { label: 'Roles de tu empresa', count: 4, href: '#empresa', active: true },
            { label: 'Permisos', href: '#permisos', active: false },
        ]}
    />
);

export const ByActionWithOtherFilters = () => (
    <CountTabs
        label="Estado de las solicitudes de vacaciones"
        byAction
        tabs={[
            { label: 'Todas', count: 31, href: '#todas', active: true },
            { label: 'Por aprobar', count: 7, href: '#por-aprobar', active: false, needsAction: true },
            { label: 'Canceladas', count: 0, href: '#canceladas', active: false },
        ]}
    />
);
