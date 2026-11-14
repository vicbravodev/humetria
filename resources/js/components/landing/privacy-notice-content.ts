export type PrivacySection = {
    id: string;
    title: string;
    paragraphs: readonly string[];
    /** A list shown after the paragraphs. */
    items?: readonly string[];
    /** The paragraphs that close the section, after its list. */
    closing?: readonly string[];
};

export type PrivacyParties = {
    /** The legal name of whoever controls the data; null while none is configured. */
    legalName: string | null;
    /** Its legal address; null while none is configured. */
    legalAddress: string | null;
    /** Where to write to Humetría; null while none is configured. */
    contact: string | null;
};

const REVIEW = 'pendiente de revisión legal';

function controller({
    legalName,
    legalAddress,
    contact,
}: PrivacyParties): PrivacySection {
    return {
        id: 'responsable',
        title: 'Quién es el responsable',
        paragraphs: [
            `${legalName ?? 'Humetría'} es el responsable del tratamiento de los datos personales que recibe por este sitio.`,
            legalAddress === null
                ? 'Su domicilio está por confirmarse.'
                : `Su domicilio es ${legalAddress}.`,
            contact === null
                ? 'Para ejercer tus derechos o resolver una duda sobre este aviso, usa el formulario de demo de la página de inicio.'
                : `Para ejercer tus derechos o resolver una duda sobre este aviso, escribe a ${contact}.`,
        ],
    };
}

const DEMO_DATA: PrivacySection = {
    id: 'datos-del-formulario',
    title: 'Qué datos recabamos en el formulario de demo y para qué',
    paragraphs: ['Cuando pides una demo, nos das estos datos:'],
    closing: [
        'Los usamos para contactarte por WhatsApp o por correo electrónico, agendar la llamada, mostrarte Humetría con un caso parecido al tuyo y acordar la prueba gratis. También nos sirven para saber qué herramientas interesan más y atender mejor a cada tipo de empresa.',
        'No pedimos datos sensibles ni usamos tus datos para algo distinto de lo que dice este aviso. Este formulario no incluye respuestas de ninguna evaluación: esas viven en la plataforma y nunca se ligan con una persona.',
    ],
    items: [
        'Nombre.',
        'Correo electrónico.',
        'Número de WhatsApp.',
        'Empresa.',
        'Para quién es Humetría: tu empresa, tu despacho de consultoría o tu sindicato.',
        'Las herramientas que te interesan.',
        'El tamaño de la plantilla de la empresa.',
        'El mensaje que decidas escribir, si lo escribes.',
    ],
};

const ORIGIN: PrivacySection = {
    id: 'de-donde-llegaste',
    title: 'De dónde llegaste',
    paragraphs: [
        'Cuando llegas desde un anuncio o desde un enlace de campaña, la dirección trae etiquetas que dicen cuál fue (fuente, medio y campaña). Si pides una demo, guardamos esas etiquetas y el sitio desde el que llegaste junto con tu solicitud, para saber qué campañas funcionan.',
        'De la página de la que vienes guardamos solo su dominio, nunca la dirección completa. No guardamos identificadores de clic de anuncios.',
    ],
};

const OWN_COOKIES: PrivacySection = {
    id: 'cookies-propias',
    title: 'Cookies propias',
    paragraphs: [
        'Para que el sitio funcione usamos cookies propias, necesarias y sin fines publicitarios:',
    ],
    items: [
        'La cookie de sesión mantiene tu visita y, si entras a la plataforma, tu inicio de sesión. Caduca cuando pasa un tiempo sin que uses el sitio.',
        'La cookie de protección de formularios evita que otro sitio envíe una solicitud en tu nombre.',
        'Tu preferencia de tema (claro, oscuro o el de tu dispositivo) se guarda en tu navegador por un año.',
    ],
};

const ANALYTICS: PrivacySection = {
    id: 'cookies-de-analitica',
    title: 'Cookies de analítica y de atribución',
    paragraphs: [
        'Para medir cuántas personas visitan la página de inicio y qué anuncios traen solicitudes de demo, podemos usar Google Analytics y una cookie propia de atribución. Solo se cargan si la medición está activa y con tu consentimiento; mientras no lo esté, no se cargan ni se escriben.',
        'Google Analytics escribe las cookies _ga y _ga_ seguida del identificador de la propiedad, para distinguir visitas. Tenemos desactivadas las señales de Google y la personalización de anuncios. A Google no le enviamos tu nombre, correo, teléfono ni mensaje, y solo mide la página de inicio: las encuestas, el buzón de quejas y los portales de las personas trabajadoras no lo cargan.',
        'La cookie de atribución recuerda de dónde llegaste la primera vez y la última, hasta por 90 días, para ligar tu solicitud con la campaña que te trajo. Va cifrada y solo la lee nuestro servidor.',
        `El texto de esta sección está ${REVIEW}.`,
    ],
};

const CONTROL: PrivacySection = {
    id: 'borrar-o-rechazar-cookies',
    title: 'Cómo borrar o rechazar las cookies',
    paragraphs: [
        'Puedes borrar las cookies o bloquearlas desde la configuración de tu navegador, para todos los sitios o solo para este. Las cookies necesarias seguirán haciendo falta para iniciar sesión: sin ellas, la plataforma no te dejará entrar.',
        'Si tu navegador envía una señal para no ser rastreado o bloqueas la medición, el sitio funciona igual.',
    ],
};

const RETENTION: PrivacySection = {
    id: 'conservacion',
    title: 'Cuánto tiempo conservamos los datos',
    paragraphs: [
        `El plazo de conservación de las solicitudes de demo está ${REVIEW}.`,
        'Mientras tanto, conservamos tus datos solo para lo que dice este aviso y los borramos si nos lo pides.',
    ],
};

const RIGHTS: PrivacySection = {
    id: 'tus-derechos',
    title: 'Cómo ejercer tus derechos',
    paragraphs: [
        'Puedes acceder a tus datos, corregirlos, pedir que los cancelemos u oponerte a que los usemos (derechos ARCO), y retirar tu consentimiento.',
        'Escríbenos con tu nombre, el correo o el teléfono con el que pediste la demo y lo que quieres hacer. Te respondemos por el mismo medio.',
        `El procedimiento, los plazos de respuesta y la autoridad ante la que puedes presentar una queja están ${REVIEW}.`,
    ],
};

/** The eight sections of the notice, in the order the page shows them. */
export function privacySections(parties: PrivacyParties): PrivacySection[] {
    return [
        controller(parties),
        DEMO_DATA,
        ORIGIN,
        OWN_COOKIES,
        ANALYTICS,
        CONTROL,
        RETENTION,
        RIGHTS,
    ];
}
