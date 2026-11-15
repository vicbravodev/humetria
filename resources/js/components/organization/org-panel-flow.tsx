/** «Qué sale de aquí»: what the reporting line feeds, in two lines. */
export default function OrgPanelFlow() {
    return (
        <section
            aria-labelledby="org-panel-flow"
            className="grid gap-1 rounded-lg bg-muted p-3"
        >
            <h3 id="org-panel-flow" className="text-sm font-semibold">
                Qué sale de aquí
            </h3>
            <p className="text-sm text-muted-foreground">
                Recibe las solicitudes de vacaciones de su equipo directo y
                tiene su propia lectura de Clima cuando su equipo llega al
                mínimo de respondientes (5).
            </p>
        </section>
    );
}
