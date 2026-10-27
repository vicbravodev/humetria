export default function TenantsEmpty() {
    return (
        <div className="rounded-lg border bg-card p-6">
            <h2 className="text-xl leading-7 font-semibold tracking-[-0.01em]">
                Aún no hay empresas
            </h2>
            <p className="mt-1 text-muted-foreground">
                Cuando des de alta una empresa, aparecerá aquí con su estado.
            </p>
        </div>
    );
}
