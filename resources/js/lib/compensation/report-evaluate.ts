export type EvaluationPoint = { key: string; title: string; detail: string };

/** What a raise has to be weighed against besides its cost: guidance to decide with, no figure of ours. */
export const EVALUATION_POINTS: EvaluationPoint[] = [
    {
        key: 'compression',
        title: 'Compresión salarial',
        detail: 'Un mismo porcentaje separa más a quien gana más y un monto fijo acerca a las categorías. Mira cuánto queda entre un nivel y el siguiente después del aumento.',
    },
    {
        key: 'market',
        title: 'Competitividad de mercado',
        detail: 'Compara los salarios nuevos con lo que paga tu sector y tu región por los mismos puestos. Un aumento puede ser alto en costo y aun así quedar debajo del mercado.',
    },
    {
        key: 'overtime',
        title: 'Horas extra y días festivos',
        detail: 'Este reporte no calcula horas extra ni días festivos: si el salario sube, también sube lo que pagas por cada hora y cada día trabajado en descanso.',
    },
    {
        key: 'benefits',
        title: 'Prestaciones superiores a la ley',
        detail: 'Vales, bonos y fondo de ahorro suman al costo si el contrato los liga al salario. Revisa cuáles se pagan sobre el sueldo y cuáles son un monto fijo.',
    },
    {
        key: 'capacity',
        title: 'Capacidad financiera',
        detail: 'Pon el costo del primer año y el de los siguientes frente a tu flujo y tus márgenes, y decide si lo absorbes, lo trasladas o lo escalonas.',
    },
    {
        key: 'productivity',
        title: 'Productividad',
        detail: 'Un aumento puede ir de la mano de metas de asistencia, calidad o rotación. Defínelas antes de negociar, no después.',
    },
];
