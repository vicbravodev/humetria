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
        'Hoy esa información viene solo de tu sesión: es la primera visita con etiquetas desde que abriste el sitio, y se pierde cuando pasa un tiempo sin que lo uses. Si llegaste sin etiquetas ni otro sitio de por medio, tu solicitud queda sin dato de procedencia, y eso no significa que hayas llegado de forma directa.',
    ],
};

const OWN_COOKIES: PrivacySection = {
    id: 'cookies-propias',
    title: 'Cookies propias',
    paragraphs: [
        'Para que el sitio funcione usamos cookies propias, necesarias y sin fines publicitarios. Se escriben al abrir el sitio:',
    ],
    items: [
        'humetria-session es la cookie de sesión. Mantiene tu visita y, si entras a la plataforma, tu inicio de sesión. Va cifrada y caduca a los 120 minutos sin que uses el sitio.',
        'XSRF-TOKEN es la cookie de protección de formularios: evita que otro sitio envíe una solicitud en tu nombre. Dura lo mismo que la de sesión.',
        'Tu preferencia de tema (claro, oscuro o el de tu dispositivo) se guarda en una cookie por un año y en el almacenamiento local de tu navegador.',
    ],
};

const ANALYTICS: PrivacySection = {
    id: 'cookies-de-analitica',
    title: 'Analítica y cookie de atribución',
    paragraphs: [
        'Medimos las visitas de la página de inicio con PostHog, y solo ahí y solo para quien no ha iniciado sesión: en las encuestas, el buzón de quejas, los portales de las personas trabajadoras y el panel no medimos visitas.',
        'No grabamos tu sesión, no registramos lo que escribes y no creamos un perfil tuyo. Sí contamos qué botones y enlaces de la página tocas (por ejemplo, "Agendar demo"), qué secciones ves, hasta dónde bajas y en qué campo del formulario hubo un error, sin guardar nada de lo que escribiste.',
        'Como con cualquier servicio de un tercero, tu navegador se conecta con PostHog (us.i.posthog.com, en Estados Unidos) y le llegan, como mínimo, los datos técnicos de tu navegador y de tu conexión. Además, le decimos qué página de inicio abriste, con solo las etiquetas de campaña de la dirección, el sitio del que vienes si es una página nuestra y que enviaste una solicitud de demo, con el tipo de empresa y el rango de personas que elegiste. A PostHog no le enviamos tu nombre, correo, teléfono ni mensaje, ni ningún dato de la solicitud. No guardamos tu dirección IP en PostHog. PostHog no escribe cookies en tu navegador: guarda un identificador anónimo en su almacenamiento local.',
        'También registramos los errores técnicos para poder arreglarlos. Si algo falla en tu navegador en esta página, PostHog recibe el tipo de error y el lugar del código donde ocurrió, con los correos y los números largos de su texto ocultos. Si algo falla en nuestro servidor, recibe la clase del error y la ruta del código por donde pasó, nunca su texto ni datos de ninguna persona.',
        'La cookie de atribución humetria_acq solo se escribe si llegas con etiquetas de campaña o desde otro sitio. Recuerda la primera y la última de esas visitas, hasta por 90 días, para ligar tu solicitud con la campaña que te trajo. Va cifrada, solo se envía por conexión segura y solo la lee nuestro servidor.',
        `El texto de esta sección está ${REVIEW}.`,
    ],
};

const PLATFORM: PrivacySection = {
    id: 'humi-y-registros',
    title: 'Humi y los registros técnicos de la plataforma',
    paragraphs: [
        'Si usas Humi, el asistente de la plataforma, enviamos a PostHog (us.i.posthog.com, en Estados Unidos) lo que le escribes y lo que te responde, con las instrucciones que recibe, el modelo que contestó, cuánto tardó y si hubo un error, para mejorar el asistente. Antes de salir, tu mensaje pasa por el mismo filtro que oculta correos, teléfonos, CURP, RFC y NSS. A PostHog no le decimos quién eres: te identifica con un código que no se puede convertir en tu nombre ni en tu correo, y no crea un perfil tuyo.',
        'Cuando la plataforma resume los comentarios de una encuesta de clima, a PostHog solo le llegan el modelo, la cantidad de texto procesado, el tiempo y si hubo un error: nunca el texto de los comentarios ni el resumen.',
        'Los registros técnicos de nuestro servidor también se guardan en PostHog para detectar fallas y avisarnos: qué pasó, cuándo y con qué identificadores internos, nunca respuestas de encuestas ni contraseñas.',
        'En el resto de la plataforma (el panel, las encuestas, el buzón de quejas y los portales), si algo falla en tu navegador PostHog recibe el tipo de error, el lugar del código donde ocurrió, el nombre de la pantalla y un código de la petición para encontrarla en nuestros registros, con los correos, los números largos y las direcciones de su texto ocultos: nunca la dirección de la página (puede llevar tu enlace personal), tu nombre ni tus respuestas. Ahí PostHog no guarda nada en tu navegador ni crea un perfil tuyo.',
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

/** The nine sections of the notice, in the order the page shows them. */
export function privacySections(parties: PrivacyParties): PrivacySection[] {
    return [
        controller(parties),
        DEMO_DATA,
        ORIGIN,
        OWN_COOKIES,
        ANALYTICS,
        PLATFORM,
        CONTROL,
        RETENTION,
        RIGHTS,
    ];
}
