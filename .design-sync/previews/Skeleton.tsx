import { Card, CardContent, CardHeader, Skeleton } from 'humetria';

export const HumiThinking = () => (
    <div className="flex gap-3 max-w-md" role="status">
        <Skeleton className="size-10 rounded-full shrink-0" />
        <div className="flex flex-1 flex-col gap-2">
            <span className="text-sm text-muted-foreground">Pensando</span>
            <Skeleton className="h-3 w-full" />
            <Skeleton className="h-3 w-2/3" />
        </div>
    </div>
);

export const SettingsLoading = () => (
    <div className="grid gap-3 max-w-md" role="status" aria-label="Cargando">
        <Skeleton className="h-6 w-2/3" />
        <Skeleton className="h-11 w-full" />
    </div>
);

export const CardLoading = () => (
    <Card className="max-w-sm">
        <CardHeader className="gap-2">
            <Skeleton className="h-5 w-1/2" />
            <Skeleton className="h-4 w-3/4" />
        </CardHeader>
        <CardContent className="grid gap-3">
            <Skeleton className="h-12 w-full" />
            <Skeleton className="h-12 w-full" />
            <Skeleton className="h-12 w-11/12" />
        </CardContent>
    </Card>
);
