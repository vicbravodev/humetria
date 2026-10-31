const LIST = new Intl.ListFormat('es-MX', { type: 'conjunction' });

/** The tools a demo request asks about, as a sentence names them: "NOM-035, Ley Silla y NOM-037". */
export function toolsText(tools: string[]): string {
    return tools.length === 0 ? 'Sin indicar' : LIST.format(tools);
}
