import type { LocationError } from '@/lib/attendance/geolocation';

/** A reading coarser than this (meters) cannot place a small zone well: RH is told before saving. */
const COARSE_METERS = 50;

/** A degree with six decimals: about 10 cm, finer than any phone reads. */
export function coordinate(degrees: number): string {
    return degrees.toFixed(6);
}

/** «19.432712, -99.133298»: where a zone's circle is centered. */
export function placeText(latitude: number, longitude: number): string {
    return `${coordinate(latitude)}, ${coordinate(longitude)}`;
}

/** «80 m». */
export function radiusText(meters: number): string {
    return `${meters.toLocaleString('es-MX')} m`;
}

/** How far off the browser's reading may be, and what to do when it is too coarse for a zone. */
export function accuracyText(meters: number): string {
    const text = `Tu ubicación tiene una precisión de ±${Math.ceil(meters)} m`;

    return meters > COARSE_METERS
        ? `${text}: muévete al aire libre o revisa las coordenadas antes de guardar.`
        : `${text}.`;
}

const ERRORS: Record<LocationError, string> = {
    denied: 'Tu navegador no dio permiso de usar tu ubicación. Permítelo en la configuración del sitio o escribe las coordenadas.',
    unavailable:
        'No se pudo saber dónde estás. Intenta al aire libre o escribe las coordenadas.',
    timeout:
        'Tardó demasiado en encontrar tu ubicación. Intenta de nuevo o escribe las coordenadas.',
    unsupported:
        'Este navegador no puede dar tu ubicación. Escribe las coordenadas.',
};

/** Why the browser gave no position, and how RH can still place the zone. */
export function locationErrorText(error: LocationError): string {
    return ERRORS[error];
}
