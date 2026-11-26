import { CertificateSheet } from 'humetria';

const apodaca = {
    name: 'Planta Apodaca',
    legalName: 'Aceros del Norte, S.A. de C.V.',
    rfc: 'XAXX010101000',
    address: 'Av. Ejemplo 100, Parque Industrial, 66600 Apodaca, N.L.',
};

export const Nom037 = () => (
    <CertificateSheet standard="NOM-037-STPS-2023 · Teletrabajo" center={apodaca} declaredOn="3 de febrero de 2026" declaredBy="Ana Lucía Villarreal">
        Se declaró que en este centro de trabajo nadie labora fuera de sus instalaciones más del 40 % de su tiempo. Por eso no tiene
        personas en teletrabajo en los términos del artículo 330-A de la Ley Federal del Trabajo, y la NOM-037-STPS-2023 no le aplica
        mientras eso no cambie.
    </CertificateSheet>
);

export const LeySillaFormerUser = () => (
    <CertificateSheet
        standard="Ley Silla · trabajo de pie"
        center={{
            name: 'Oficinas Monterrey',
            legalName: 'Aceros del Norte, S.A. de C.V.',
            rfc: 'XAXX010101000',
            address: 'Calle Ejemplo 200, Col. Centro, 64000 Monterrey, N.L.',
        }}
        declaredOn="18 de agosto de 2025"
        declaredBy={null}
    >
        Se declaró que en este centro de trabajo nadie hace su trabajo de pie durante su jornada laboral. Por eso no tiene puestos que
        requieran los asientos que pide el artículo 132, fracción V, de la Ley Federal del Trabajo mientras eso no cambie.
    </CertificateSheet>
);
