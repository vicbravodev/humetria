import { Button, Notice } from 'humetria';

export const WithAction = () => (
    <Notice action={<Button variant="outline" size="sm">Completar datos</Button>}>
        <strong>3 personas no tienen correo ni celular.</strong> Sin uno de los dos no les llega la evaluación.
    </Notice>
);

export const WithLinkAction = () => (
    <Notice
        action={
            <a href="#politica" className="text-sm font-semibold underline underline-offset-4">
                Terminar política
            </a>
        }
    >
        Tu política aún no está publicada en este centro. El resumen sale igual; la política llega al publicarla.
    </Notice>
);

export const TextOnly = () => (
    <Notice>
        <strong>La página no está activa.</strong> Quien escanee este cartel verá que el enlace no abre. Actívala antes
        de colgarlo.
    </Notice>
);
