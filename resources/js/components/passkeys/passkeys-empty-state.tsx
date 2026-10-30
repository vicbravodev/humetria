/** What the list of passkeys says before there is one. */
export default function PasskeysEmptyState() {
    return (
        <div className="px-4 py-6 text-center">
            <p className="text-sm font-medium">
                Aún no tienes llaves de acceso
            </p>
            <p className="mt-0.5 text-[13px] leading-[18px] text-muted-foreground">
                Agrega una para entrar sin escribir tu contraseña.
            </p>
        </div>
    );
}
