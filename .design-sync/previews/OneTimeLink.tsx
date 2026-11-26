import { OneTimeLink } from 'humetria';

export const PersonAccess = () => (
    <div className="max-w-xl">
        <OneTimeLink
            id="access-link"
            label="Enlace de acceso de María Fernanda López"
            link="https://app.humetria.mx/me/planta-apodaca/entrar/7Kq2xV9mPz"
        >
            Entrégaselo en persona o por un medio que solo use esa persona: con él entra y crea su llave.
            Funciona una sola vez, vence en 48 h y no se vuelve a mostrar.
        </OneTimeLink>
    </div>
);

export const KioskScreen = () => (
    <div className="max-w-xl">
        <OneTimeLink
            id="kiosk-link"
            label="Enlace de la pantalla"
            link="https://app.humetria.mx/a/planta-apodaca?t=Qm4sT8wLr2"
        >
            Ábrelo en la tableta que mostrará el código: funciona una sola vez y no se vuelve a mostrar. Si un
            chat o un correo lo abre antes (al mostrar su vista previa), vuelve a emitirlo.
        </OneTimeLink>
    </div>
);
