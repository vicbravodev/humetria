const year = new Date().getFullYear();

export default function LandingFooter() {
    return (
        <footer className="border-t">
            <div className="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-8 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <p>© Humetría {year}</p>
                <ul className="flex flex-wrap gap-x-6 gap-y-2">
                    <li>Aviso de privacidad (pendiente)</li>
                    <li>Términos (pendientes)</li>
                </ul>
            </div>
        </footer>
    );
}
