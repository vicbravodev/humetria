/** Under the main button: how the center's code gets to the mark, said by whether this page came from a scan. */
export default function PunchHint({ scanned }: { scanned: boolean }) {
    return (
        <p className="text-center text-base text-muted-foreground">
            {scanned
                ? 'Código de la pantalla leído. Al marcar, el teléfono le pedirá su huella, su rostro o su PIN.'
                : 'Si su centro tiene una pantalla QR, escanee su código con la cámara de su teléfono antes de marcar.'}
        </p>
    );
}
