/** A wa.me chat link for a number stored in E.164 (+525512345678). */
export function whatsappUrl(e164: string): string {
    return `https://wa.me/${e164.replace('+', '')}`;
}
