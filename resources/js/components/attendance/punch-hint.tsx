/**
 * Under the buttons, in a line: how this person marks. A remote one is asked for no code nor zone; one who opened the page
 * from the center's screen has a few minutes to mark with that scan; anyone else is told how the code gets to the mark.
 */
export default function PunchHint({
    mode,
    scannedAt,
    readInApp,
}: {
    mode: 'remote' | 'onsite' | null;
    /** When the code of the screen was read, at its center's clock; null when the page did not open from one that counted. */
    scannedAt: string | null;
    /** The code was read inside the app: its time is the server's and there is none to say (nor a network to ask it of). */
    readInApp: boolean;
}) {
    return (
        <p className="text-center text-sm text-muted-foreground">
            {hintText(mode, scannedAt, readInApp)}
        </p>
    );
}

function hintText(
    mode: 'remote' | 'onsite' | null,
    scannedAt: string | null,
    readInApp: boolean,
): string {
    if (mode === 'remote') {
        return 'Marque desde donde esté: no se le pedirá un QR ni estar en una zona.';
    }

    if (readInApp) {
        return 'Leyó el código de la pantalla. Marque ahora.';
    }

    return scannedAt !== null
        ? `Escaneó a las ${scannedAt}, tiene 3 minutos para marcar.`
        : 'Si su centro tiene una pantalla QR, escanee su código con la cámara antes de marcar.';
}
