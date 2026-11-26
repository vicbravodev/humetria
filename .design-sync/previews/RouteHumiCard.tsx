import { RouteHumiCard } from 'humetria';

// What the app's pages share through Inertia (usePage().props); outside the app the bundle reads it from here.
Object.assign(globalThis, {
    HumetriaPage: {
        props: {
            support: { email: 'soporte@humetria.mx' },
            tenant: { trade_name: 'Grupo Norte', can: { use_assistant: true } },
        },
    },
});

export const Nom037 = () => (
    <div className="w-[360px]">
        <RouteHumiCard example="qué es la reversibilidad o qué multa hay" />
    </div>
);
