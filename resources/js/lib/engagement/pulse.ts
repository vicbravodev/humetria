/** What each mode of the pulse looks at besides its fixed questions, in a sentence. */
export function modeHint(mode: string): string {
    switch (mode) {
        case 'follow_up':
            return 'Pregunta de nuevo por las prioridades que dejó el último clima, para ver si mejoran.';
        case 'tour':
            return 'Un par de preguntas de cada tema, para cubrir todos en dos pulsos.';
        case 'manager':
            return 'Dos preguntas sobre el jefe directo y algunas de los temas más importantes.';
        default:
            return '';
    }
}
