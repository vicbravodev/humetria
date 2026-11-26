import { Button, NextStepCard } from 'humetria';

export const Onboarding = () => (
    <NextStepCard
        title="Dos preguntas por centro y armamos todo"
        meta="3 centros sin contestar · ≈ 1 min cada uno"
        action={<Button>Empezar</Button>}
    >
        Con esto sabemos qué normas te tocan en cada centro y creamos tus rutas.
    </NextStepCard>
);

export const NormStage = () => (
    <NextStepCard
        title="Lanza la evaluación de Planta Apodaca"
        meta="NOM-035 · etapa 3 de 6 · 2 centros"
        action={<Button>Continuar</Button>}
    >
        Ya tienes a las 186 personas cargadas. Al lanzarla les llega su enlace por correo o WhatsApp.
    </NextStepCard>
);

export const Disclosure = () => (
    <NextStepCard
        title="Publica tu política de prevención"
        meta="Numeral 5.1"
        action={<Button>Redactar política</Button>}
    >
        La norma pide que todas las personas del centro la conozcan. La publicas una vez y cada quien la acusa de leída.
    </NextStepCard>
);
