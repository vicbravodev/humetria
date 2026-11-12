import type { CommitteeTask } from '@/types';

/** "Hugo Saiz", "Hugo Saiz y Ana Ruiz", "Hugo Saiz, Ana Ruiz y 2 más". */
export function namesLine(names: string[]): string {
    if (names.length <= 2) {
        return names.join(' y ');
    }

    return names.length === 3
        ? `${names[0]}, ${names[1]} y ${names[2]}`
        : `${names[0]}, ${names[1]} y ${names.length - 2} más`;
}

/** "Hola, Rubén": the first name. */
export function greeting(name: string): string {
    const first = name.trim().split(/\s+/)[0] ?? '';

    return first === '' ? 'Hola' : `Hola, ${first}`;
}

/** The standard a task belongs to, for its badge. */
export function taskNorm(task: CommitteeTask): string {
    return ['signatures', 'minutes', 'unassessed'].includes(task.kind)
        ? 'Ley Silla'
        : 'NOM-037';
}

type TaskText = {
    title: string;
    text: string;
    estimate: string;
    action: string;
};

/** What each kind of task asks: NOM-037's lists and notices, Ley Silla's signatures and minutes. */
const TEXTS: Record<CommitteeTask['kind'], (task: CommitteeTask) => TaskText> =
    {
        checklist: (task) => ({
            title: `Publica la lista de verificación · ${task.workCenter}`,
            text: `El centro ${task.workCenter} no tiene lista de verificación publicada: sin ella no se puede revisar ningún lugar de teletrabajo.`,
            estimate: '≈ 10 min',
            action: 'Ir a la lista',
        }),
        unassessed: (task) => ({
            title:
                task.count === 1
                    ? `1 persona de pie sin evaluación en ${task.workCenter}`
                    : `${task.count} personas de pie sin evaluación en ${task.workCenter}`,
            text: 'Todavía no tienen una evaluación firmada de su puesto de trabajo.',
            estimate: '≈ 3 min cada una',
            action: 'Ir a Evaluaciones',
        }),
        lists: (task) => ({
            title:
                task.count === 1
                    ? `Valida 1 lugar de teletrabajo · ${task.workCenter}`
                    : `Valida ${task.count} lugares de teletrabajo · ${task.workCenter}`,
            text: `${namesLine(task.people)} ${task.people.length === 1 ? 'contestó' : 'contestaron'} la lista de su lugar. Revisa y decide.`,
            estimate: task.count === 1 ? '≈ 5 min' : '≈ 5 min cada uno',
            action: 'Empezar',
        }),
        notices: (task) => ({
            title:
                task.count === 1
                    ? `Revisa 1 aviso · ${task.workCenter}`
                    : `Revisa ${task.count} avisos · ${task.workCenter}`,
            text: 'Cambios de domicilio, accidentes o casos de fuerza mayor que avisaron las personas del centro.',
            estimate: '≈ 3 min cada uno',
            action: 'Ver los avisos',
        }),
        signatures: (task) => ({
            title:
                task.count === 1
                    ? `Firma 1 evaluación de trabajo de pie · ${task.workCenter}`
                    : `Firma ${task.count} evaluaciones de trabajo de pie · ${task.workCenter}`,
            text: `${namesLine(task.people)}. Ya tienen los datos: solo confirmas y firmas.`,
            estimate: task.count === 1 ? '≈ 3 min' : '≈ 3 min cada una',
            action: 'Revisar y firmar',
        }),
        minutes: (task) => ({
            title:
                task.count === 1
                    ? `Asienta 1 evaluación en el acta de recorrido · ${task.workCenter}`
                    : `Asienta ${task.count} evaluaciones en el acta de recorrido · ${task.workCenter}`,
            text: 'Las evaluaciones firmadas que aún no están en un acta de la Comisión.',
            estimate: '≈ 5 min',
            action: 'Abrir acta',
        }),
    };

/** What a task asks, its line and its estimate (board V2-Comision). */
export function taskText(task: CommitteeTask): TaskText {
    return TEXTS[task.kind](task);
}

/** "La Comisión tiene 3 cosas pendientes." */
export function pendingLine(tasks: CommitteeTask[]): string {
    const count = tasks.reduce((sum, task) => sum + task.count, 0);

    if (count === 0) {
        return 'No tienes pendientes como integrante de la Comisión.';
    }

    return count === 1
        ? 'La Comisión tiene 1 cosa pendiente.'
        : `La Comisión tiene ${count} cosas pendientes.`;
}
