import type { ScanStatus } from '@/lib/pwa/qr-scan';

/** What the window of the reader says: it is looking, it read the code, or something needs the person's hand. */
export type ViewfinderState = 'looking' | 'found' | 'problem';

const TEXT: Record<ScanStatus, string> = {
    idle: '',
    opening: 'Abriendo la cámara…',
    scanning: 'Apunte al código de la pantalla de su centro.',
    unreadable:
        'No logro leer el código. Acérquese o aléjese un poco, sin reflejos, y mantenga el teléfono quieto.',
    unrecognized:
        'Ese código no es el de la pantalla de su centro. Apunte al de la pantalla.',
    'other-company': 'Ese código es de otra empresa.',
    denied: 'Permita el uso de la cámara para leer el código.',
    unavailable: 'Este teléfono no pudo abrir la cámara.',
};

const SEES: ScanStatus[] = [
    'opening',
    'scanning',
    'unreadable',
    'unrecognized',
    'other-company',
];

const PROBLEMS: ScanStatus[] = ['unreadable', 'unrecognized', 'other-company'];

/**
 * What the screen of the reader shows for the state of the camera: whether the camera and its window are shown, which state
 * the window is in, whether something needs the person's hand, and the one line of text. Once the code was read the
 * check wins over whatever the camera says after (it is turned off at that moment).
 */
export function scanView(
    status: ScanStatus,
    read: boolean,
): {
    seeing: boolean;
    state: ViewfinderState;
    problem: boolean;
    text: string;
} {
    const problem = PROBLEMS.includes(status) && !read;

    return {
        seeing: SEES.includes(status),
        state: read ? 'found' : problem ? 'problem' : 'looking',
        problem,
        text: read ? 'Código leído.' : TEXT[status],
    };
}
