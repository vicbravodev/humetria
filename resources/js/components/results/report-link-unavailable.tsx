import LinkUnavailable from '@/components/link-unavailable';

/** A shared report whose link expired or was revoked (pantallas: Enlace-Vencido): what happened and what to do. */
export default function ReportLinkUnavailable() {
    return (
        <main className="flex flex-1 items-center justify-center px-6 pb-24">
            <LinkUnavailable>
                Venció o quien lo compartió lo desactivó. Pídele un enlace nuevo
                a quien te lo envió.
            </LinkUnavailable>
        </main>
    );
}
