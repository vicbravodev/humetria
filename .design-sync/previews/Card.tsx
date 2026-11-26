import { Badge, Button, Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from 'humetria';

export const LiveCampaign = () => (
    <div className="max-w-md">
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between gap-3">
                    <CardTitle>Evaluación 2026 · Planta Apodaca</CardTitle>
                    <Badge>En curso</Badge>
                </div>
                <CardDescription>Guías I y III · cierra el 14 de diciembre</CardDescription>
            </CardHeader>
            <CardContent>
                <p className="text-sm text-muted-foreground">
                    142 de 186 personas contestaron (76 %). Faltan 44.
                </p>
            </CardContent>
            <CardFooter className="gap-2">
                <Button variant="outline">Enviar recordatorio</Button>
                <Button>Ver avance</Button>
            </CardFooter>
        </Card>
    </div>
);

export const HeaderAndContent = () => (
    <div className="max-w-md">
        <Card>
            <CardHeader>
                <CardTitle>Vacaciones de este año</CardTitle>
                <CardDescription>Según tu antigüedad de 3 años</CardDescription>
            </CardHeader>
            <CardContent>
                <p className="text-sm">Te quedan 9 de 16 días. Vencen el 31 de marzo de 2027.</p>
            </CardContent>
        </Card>
    </div>
);

export const EmptyNextStep = () => (
    <div className="max-w-md">
        <Card>
            <CardHeader>
                <CardTitle>Aún no hay centros de trabajo</CardTitle>
                <CardDescription>
                    La NOM-035 se evalúa por centro. Agrega el primero para saber qué guías aplican.
                </CardDescription>
            </CardHeader>
            <CardFooter>
                <Button>Agregar centro</Button>
            </CardFooter>
        </Card>
    </div>
);
