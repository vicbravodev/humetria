import { SupportLink } from 'humetria';

// What the app's pages share through Inertia (usePage().props); outside the app the bundle reads it from here.
Object.assign(globalThis, {
    HumetriaPage: {
        props: {
            support: { email: 'soporte@humetria.mx' },
            tenant: { trade_name: 'Grupo Norte', can: { use_assistant: true } },
        },
    },
});

export const InASentence = () => (
    <p className="text-sm">
        ¿Necesitas más centros de trabajo?{' '}
        <SupportLink subject="Quiero ampliar mi plan">Escríbenos</SupportLink> y te
        ayudamos a ampliar tu plan.
    </p>
);

export const ReactivateCompany = () => (
    <p className="text-sm text-muted-foreground">
        La cuenta de Grupo Industrial del Norte está en solo lectura.{' '}
        <SupportLink subject="Reactivar Grupo Industrial del Norte">
            Escribe a Humetría para reactivarla
        </SupportLink>
        .
    </p>
);
