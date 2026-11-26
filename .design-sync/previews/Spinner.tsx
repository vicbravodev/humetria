import { Button, Spinner } from 'humetria';

export const InButton = () => (
    <Button disabled>
        <Spinner />
        Entrar
    </Button>
);

export const Sizes = () => (
    <div className="flex items-center gap-4">
        <Spinner />
        <Spinner className="size-5" />
        <Spinner className="size-6 text-primary" />
    </div>
);

export const WithText = () => (
    <div className="flex items-center gap-2 text-sm text-muted-foreground">
        <Spinner />
        Guardando tu semana…
    </div>
);
