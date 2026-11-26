/** How everyone on a climate draft gets their link, when nobody is left out: by email, by WhatsApp, or some of each. */
export function climateReached({
    people,
    whatsapp,
}: {
    people: number;
    whatsapp: number;
}): string {
    if (whatsapp === 0) {
        return 'Todas tienen correo: a cada una le llega su enlace.';
    }

    if (whatsapp === people) {
        return 'Todas tienen celular: a cada una le llega su enlace por WhatsApp.';
    }

    const emails = people - whatsapp;
    const verb = emails === 1 ? 'recibe' : 'reciben';

    return `Todas tienen correo o celular: ${emails} ${verb} su enlace por correo y ${whatsapp} por WhatsApp.`;
}

/** Who on a climate draft has no way to be written to. */
export function climateUnreached(n: number): string {
    return n === 1
        ? '1 persona no tiene correo ni celular.'
        : `${n} personas no tienen correo ni celular.`;
}
