/**
 * Under the buttons, in a line: how this person marks. A remote one is asked for no code nor zone; one who opened the page
 * from the center's screen has a few minutes to mark with that scan; anyone else is told how the code gets to the mark.
 */
export default function PunchHint({
    mode,
    scannedAt,
    readInApp,
    qr = null,
}: {
    mode: 'remote' | 'onsite' | null;
    /** When the code of the screen was read, at its center's clock; null when the page did not open from one that counted. */
    scannedAt: string | null;
    /** The code was read inside the app: its time is the server's and there is none to say (nor a network to ask it of). */
    readInApp: boolean;
    /** The next mark asks for the code (the policy's: it is read first) or not (its location is enough); null: not known yet. */
    qr?: boolean | null;
}) {
    return (
        <p className="text-center text-sm text-muted-foreground">
            {hintText({ mode, scannedAt, readInApp, qr })}
        </p>
    );
}

function hintText({
    mode,
    scannedAt,
    readInApp,
    qr,
}: {
    mode: 'remote' | 'onsite' | null;
    scannedAt: string | null;
    readInApp: boolean;
    qr: boolean | null;
}): string {
    if (mode === 'remote') {
        return 'Marque desde donde esté: no se le pedirá un QR ni estar en una zona.';
    }

    if (readInApp) {
        return 'Leyó el código de la pantalla. Marque ahora.';
    }

    if (qr === false && scannedAt === null) {
        return 'Para esta marca basta con estar en su centro: se toma con su ubicación.';
    }

    if (qr === true && scannedAt === null) {
        return 'Al marcar, primero lea el código de la pantalla de su centro.';
    }

    return scannedAt !== null
        ? `Escaneó a las ${scannedAt}, tiene 3 minutos para marcar.`
        : 'Si su centro tiene una pantalla QR, escanee su código con la cámara antes de marcar.';
}
