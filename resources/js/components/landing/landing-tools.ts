/** The tools Humetría offers, keyed as the demo form sends them (App\Modules\Demos\Enums\Tool). */
export type ToolKey =
    | 'nom_035'
    | 'nom_037'
    | 'ley_silla'
    | 'complaints'
    | 'compensation'
    | 'attendance'
    | 'leave'
    | 'org_chart'
    | 'engagement';

/** A tool's film in public/video (`{name}.webm`, `.mp4`, `.jpg`): four chapters of CHAPTER_SECONDS, one per label. */
export type ToolFilm = {
    name: string;
    chapters: readonly string[];
    /** What the film shows, for screen readers. */
    description: string;
};

export type LandingTool = {
    key: ToolKey;
    film: ToolFilm;
    name: string;
    /** What the tool is about, in two or three words. */
    hint: string;
    appliesTo: string;
    points: readonly string[];
};

export const TOOLS: readonly LandingTool[] = [
    {
        key: 'nom_035',
        film: {
            name: 'humetria-nom-035',
            chapters: ['Guías', 'Muestra', 'Invitaciones', 'Informe'],
            description:
                'RH elige un centro y la plataforma marca qué guías le tocan según su tamaño, sortea la muestra por género, envía un recordatorio a quien falta sin ver qué contestó nadie y genera el informe del numeral 7.7, con la canalización de la Guía I solo para personas autorizadas.',
        },
        name: 'NOM-035',
        hint: 'Factores de riesgo psicosocial',
        appliesTo: 'Todo centro de trabajo',
        points: [
            'Elige las guías según el tamaño de cada centro',
            'Calcula y sortea la muestra por género',
            'Invita a cada persona y recuerda a quien falta',
            'Canaliza la Guía I solo con personas autorizadas',
            'Genera el informe del numeral 7.7',
        ],
    },
    {
        key: 'nom_037',
        film: {
            name: 'humetria-nom-037',
            chapters: ['Listado', 'Lista', 'Política', 'Verificación'],
            description:
                'RH agrega a una persona al listado de teletrabajo según su porcentaje de jornada, la persona contesta desde el celular la lista de verificación de su lugar de trabajo, RH arma y publica la política contestando preguntas y elige cada cuánto se verifica, con un aviso cuando un lugar está por vencer.',
        },
        name: 'NOM-037',
        hint: 'Teletrabajo',
        appliesTo: 'Quien teletrabaja más del 40 % de su jornada',
        points: [
            'Lleva el listado de personas en teletrabajo',
            'Cada persona contesta la lista de verificación de su lugar de trabajo desde el celular',
            'Arma la política y sus documentos contestando preguntas',
            'Avisa cuando toca una verificación periódica',
        ],
    },
    {
        key: 'ley_silla',
        film: {
            name: 'humetria-ley-silla',
            chapters: ['Puestos', 'Encuesta', 'Reglamento', 'Plan'],
            description:
                'RH registra un puesto que se hace de pie y dónde va su asiento, la persona contesta la encuesta confidencial desde su celular mientras RH solo ve porcentajes por grupo, un botón arma el texto propuesto del reglamento interior y RH sube la evidencia de una medida del plan de acción.',
        },
        name: 'Ley Silla',
        hint: 'Asientos y descansos',
        appliesTo: 'Todo patrón, en todo centro de trabajo',
        points: [
            'Registra qué puestos se hacen de pie',
            'Pregunta al personal, de forma confidencial',
            'Propone el texto del reglamento interior',
            'Arma el plan de acción con la evidencia',
        ],
    },
    {
        key: 'complaints',
        film: {
            name: 'humetria-complaints',
            chapters: [
                'QR y anónima',
                'A quien toca',
                'Aviso discreto',
                'Seguimiento',
            ],
            description:
                'La persona escanea el cartel del centro y presenta su queja anónima desde el celular, la queja llega solo a quien le toca y nunca a RH si es contra RH, el aviso por correo trae solo conteos y la encargada pasa el caso a revisión mientras la persona ve el avance con su referencia.',
        },
        name: 'Buzón de quejas',
        hint: 'Quejas y denuncias',
        appliesTo: 'Toda tu gente, sin crear una cuenta',
        points: [
            'Cada queja llega a quien le toca, nunca a la persona señalada',
            'Se presenta desde el celular y puede ser anónima',
            'Enlace y cartel con código QR para cada centro',
            'Los avisos por correo nunca llevan el contenido de la queja',
        ],
    },
    {
        key: 'compensation',
        film: {
            name: 'humetria-compensation',
            chapters: ['Costea', 'Compara', 'Proyecta', 'Reporte'],
            description:
                'La persona elige un aumento del 6 % y lo costea con salario, prestaciones, IMSS, Infonavit e impuesto sobre nómina, compara la propuesta de la empresa con la del sindicato y la distancia entre ellas, proyecta el costo del primer año a cinco años y comparte el reporte con un enlace de solo lectura, todo con cifras de ejemplo.',
        },
        name: 'Compensación',
        hint: 'Revisión salarial y contractual',
        appliesTo: 'La empresa o el sindicato, cada uno con sus datos aparte',
        points: [
            'Costea propuestas de aumento con cargas e IMSS',
            'Compara escenarios y la distancia entre propuestas',
            'Proyecta el primer año y los siguientes',
            'Arma un reporte para compartir en la mesa',
        ],
    },
    {
        key: 'attendance',
        film: {
            name: 'humetria-attendance',
            chapters: ['Marca', 'Registro', 'Semana', 'Reporte'],
            description:
                'la persona escanea el código QR del centro o llega a la zona, marca desde su celular y confirma con su huella; el registro no se puede alterar y las correcciones se suman con su motivo; confirma su semana con los topes a la vista y RH genera con un botón el reporte para la autoridad.',
        },
        name: 'Registro de jornada',
        hint: 'Reloj checador y registro de jornada',
        appliesTo: 'Toda persona empleadora, desde el 1 de enero de 2027',
        points: [
            'Cada persona marca entrada, pausas y salida desde su celular, sin correo ni contraseña',
            'Valida el lugar con geocerca o con el código QR del centro',
            'El registro no se puede alterar: las correcciones dejan rastro y cada persona confirma su semana',
            'Avisa los topes de jornada y de horas extra antes de rebasarlos',
            'Arma con un botón el reporte para la autoridad',
        ],
    },
    {
        key: 'leave',
        film: {
            name: 'humetria-leave',
            chapters: [
                'Saldo por ley',
                'Solicitar',
                'Jefe aprueba',
                'Evidencia',
            ],
            description:
                'La persona ve su saldo de vacaciones conforme a la ley y a la política de su empresa, elige sus días en el calendario de su portal y toca Solicitar; su jefe aprueba, la ausencia aparece en el calendario del equipo y el expediente guarda la solicitud y la constancia anual.',
        },
        name: 'Vacaciones y permisos',
        hint: 'Vacaciones, permisos y saldos',
        appliesTo: 'Toda la plantilla, con la política de cada empresa',
        points: [
            'Calcula el saldo de cada persona conforme a la LFT o a tu política superior',
            'La persona solicita desde su portal y su jefe aprueba',
            'Muestra quién falta y cuándo, para planear la cobertura',
            'Guarda la evidencia que el patrón debe poder mostrar',
        ],
    },
    {
        key: 'org_chart',
        film: {
            name: 'humetria-org-chart',
            chapters: [
                'Organigrama',
                'Cargar archivo',
                'Baja de jefe',
                'Solo su gente',
            ],
            description:
                'RH abre el organigrama, que se dibuja a partir de quién reporta a quién, importa la línea de reporte desde el archivo de personal, da de baja a un jefe y reasigna a su equipo, y el jefe ve en su portal solo a quienes le reportan y aprueba sus solicitudes.',
        },
        name: 'Organigrama',
        hint: 'Quién reporta a quién',
        appliesTo: 'Empresas de cualquier tamaño, sin límite de niveles',
        points: [
            'Dibuja el organigrama a partir de quién reporta a quién',
            'Carga la línea de reporte desde tu archivo de personal',
            'Al dar de baja a un jefe, te pide reasignar a su equipo',
            'Cada jefe aprueba y ve solo a su gente',
        ],
    },
    {
        key: 'engagement',
        film: {
            name: 'humetria-engagement',
            chapters: ['Encuesta', 'Pulsos', 'Grupos ocultos', 'Comparar'],
            description:
                'La persona contesta la encuesta de clima y un pulso corto desde su enlace, y RH ve qué temas atender primero, los resultados por área con los grupos de menos de 5 respuestas ocultos y la comparación de una ola con la anterior y de un centro con otro, sin ninguna respuesta individual.',
        },
        name: 'Clima y pulso',
        hint: 'Clima laboral y pulso',
        appliesTo:
            'Toda la plantilla, con una encuesta de clima y pulsos cortos',
        points: [
            'Encuesta de clima con preguntas probadas: qué impulsa el compromiso y qué atender primero',
            'Pulsos cortos para saber si las acciones están funcionando',
            'Nadie ve una respuesta individual: los grupos pequeños se ocultan',
            'Compara una ola con la anterior y un centro con otro',
        ],
    },
];
