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

/** What a task asks, its line and its estimate (board V2-Comision). */
export function taskText(task: CommitteeTask): {
    title: string;
    text: string;
    estimate: string;
    action: string;
} {
    if (task.kind === 'lists') {
        const verb = task.people.length === 1 ? 'contestó' : 'contestaron';

        return {
            title:
                task.count === 1
                    ? `Valida 1 lugar de teletrabajo · ${task.workCenter}`
                    : `Valida ${task.count} lugares de teletrabajo · ${task.workCenter}`,
            text: `${namesLine(task.people)} ${verb} la lista de su lugar. Revisa y decide.`,
            estimate: task.count === 1 ? '≈ 5 min' : '≈ 5 min cada uno',
            action: 'Empezar',
        };
    }

    return {
        title:
            task.count === 1
                ? `Revisa 1 aviso · ${task.workCenter}`
                : `Revisa ${task.count} avisos · ${task.workCenter}`,
        text: 'Cambios de domicilio, accidentes o casos de fuerza mayor que avisaron las personas del centro.',
        estimate: '≈ 3 min cada uno',
        action: 'Ver los avisos',
    };
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
