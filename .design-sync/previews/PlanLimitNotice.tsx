import { PlanLimitNotice } from 'humetria';

// What the app's pages share through Inertia (usePage().props); outside the app the bundle reads it from here.
Object.assign(globalThis, {
    HumetriaPage: {
        props: {
            support: { email: 'soporte@humetria.mx' },
            tenant: { trade_name: 'Grupo Norte', can: { use_assistant: true } },
        },
    },
});

export const WithRoom = () => (
    <PlanLimitNotice
        limit={{ used: 142, amount: 200, remaining: 58, over: false }}
        what="personas"
        noun={{ singular: 'persona', plural: 'personas' }}
    />
);

export const AtTheLimit = () => (
    <PlanLimitNotice
        limit={{ used: 3, amount: 3, remaining: 0, over: false }}
        what="centros"
        noun={{ singular: 'centro de trabajo', plural: 'centros de trabajo' }}
    />
);

export const OverTheLimit = () => (
    <PlanLimitNotice
        limit={{ used: 212, amount: 200, remaining: 0, over: true }}
        what="personas"
        noun={{ singular: 'persona', plural: 'personas' }}
    />
);
