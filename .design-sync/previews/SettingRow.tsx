import { Badge, Button, SettingRow } from 'humetria';
import { History, KeyRound, ShieldCheck, Smartphone } from 'lucide-react';

export const WithStatusAndContent = () => (
    <SettingRow
        icon={ShieldCheck}
        title="Mínimo de personas por grupo"
        status={
            <span className="rounded-full bg-muted px-2.5 py-0.5 text-caption text-muted-foreground">
                Valor recomendado
            </span>
        }
        description="Protege a quien responde: con menos personas, el resultado de un grupo puede dejar adivinar quién respondió."
    >
        <p className="text-sm">
            Hoy: <strong>5 personas</strong> por área, turno o centro.
        </p>
    </SettingRow>
);

export const WithAction = () => (
    <SettingRow
        icon={Smartphone}
        title="Verificación en dos pasos"
        status={<Badge variant="secondary">Desactivada</Badge>}
        description="Además de tu contraseña, te pedimos un código de tu app de autenticación al entrar."
        action={<Button>Activar</Button>}
    />
);

export const DescriptionOnly = () => (
    <SettingRow
        icon={History}
        title="Lo ya lanzado no cambia"
        description="El cambio vale para las campañas y encuestas que lances desde ahora. Las ya lanzadas conservan el mínimo con el que salieron."
    />
);

export const StackedInCard = () => (
    <div className="card flex flex-col gap-7 p-6 [&>section:first-child]:border-t-0 [&>section:first-child]:pt-0">
        <SettingRow
            icon={KeyRound}
            title="Contraseña"
            description="La cambiaste hace 3 meses."
            action={<Button variant="outline">Cambiar</Button>}
        />
        <SettingRow
            icon={Smartphone}
            title="Verificación en dos pasos"
            status={<Badge>Activada</Badge>}
            description="Te pedimos un código de tu app de autenticación al entrar."
            action={<Button variant="outline">Desactivar</Button>}
        />
    </div>
);
