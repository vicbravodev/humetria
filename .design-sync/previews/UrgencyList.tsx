import { UrgencyList } from 'humetria';

export const AttendFirst = () => (
    <UrgencyList
        items={[
            {
                key: 'guia-1',
                tag: 'NOM-035',
                title: '4 personas requieren valoración clínica',
                detail: 'Guía I de Planta Apodaca · canalízalas esta semana',
                action: 'Ver canalización',
                href: '#canalizacion',
            },
            {
                key: 'denuncia',
                tag: 'Denuncias',
                title: 'Una denuncia lleva 6 días sin respuesta',
                detail: 'Caso DEN-0214 · el plazo de atención vence el viernes',
                action: 'Abrir caso',
                href: '#caso',
            },
            {
                key: 'nom-037',
                tag: 'NOM-037',
                title: 'Faltan 3 listas de verificación',
                detail: 'Teletrabajadores de Oficinas Monterrey',
                action: 'Revisar',
                href: '#teletrabajo',
            },
        ]}
    />
);

export const SingleItem = () => (
    <UrgencyList
        items={[
            {
                key: 'vacaciones',
                tag: 'Vacaciones',
                title: '7 solicitudes esperan tu aprobación',
                detail: 'La más antigua es de hace 5 días',
                action: 'Revisar solicitudes',
                href: '#vacaciones',
            },
        ]}
    />
);

export const Narrow = () => (
    <div style={{ maxWidth: 380 }}>
        <UrgencyList
            items={[
                {
                    key: 'asistencia',
                    tag: 'Asistencia',
                    title: '12 correcciones pendientes',
                    detail: 'Centro Querétaro · quincena en curso',
                    action: 'Revisar',
                    href: '#correcciones',
                },
                {
                    key: 'silla',
                    tag: 'Ley Silla',
                    title: 'Evaluación de riesgo sin cerrar',
                    detail: 'Tienda Centro · 2 puestos',
                    action: 'Continuar',
                    href: '#silla',
                },
            ]}
        />
    </div>
);
