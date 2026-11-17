/**
 * Under the buttons, in a line: how this person marks. A remote one is asked for no code nor zone; one who opened the page
 * from the center's screen has a few minutes to mark with that scan; anyone else is told how the code gets to the mark.
 */
export default function PunchHint({
    mode,
    scannedAt,
}: {
    mode: 'remote' | 'onsite' | null;
    /** When the code of the screen was read, at its center's clock; null when the page did not open from one that counted. */
    scannedAt: string | null;
}) {
    const hint =
        mode === 'remote'
            ? 'Marque desde donde esté: no se le pedirá un QR ni estar en una zona.'
            : scannedAt !== null
              ? `Escaneó a las ${scannedAt}, tiene 3 minutos para marcar.`
              : 'Si su centro tiene una pantalla QR, escanee su código con la cámara antes de marcar.';

    return <p className="text-center text-sm text-muted-foreground">{hint}</p>;
}
