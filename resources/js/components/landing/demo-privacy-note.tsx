import { privacyNotice } from '@/routes';

/** What the demo form does with the data, and the way to the notice that says it in full. */
export default function DemoPrivacyNote() {
    return (
        <p className="text-center text-caption text-muted-foreground">
            Usamos tus datos para contactarte y para saber cómo llegaste a
            Humetría.{' '}
            <a
                href={privacyNotice().url}
                data-test="demo-privacy-link"
                className="font-medium text-foreground underline underline-offset-4"
            >
                Consulta el aviso de privacidad
            </a>
            .
        </p>
    );
}
