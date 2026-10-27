export default function DemoRequestsEmpty() {
    return (
        <div className="rounded-lg border bg-card p-6">
            <h2 className="text-xl leading-7 font-semibold tracking-[-0.01em]">
                Aún no hay solicitudes
            </h2>
            <p className="mt-1 text-muted-foreground">
                Cuando alguien llene el formulario de la landing, su solicitud
                aparecerá aquí.
            </p>
        </div>
    );
}
