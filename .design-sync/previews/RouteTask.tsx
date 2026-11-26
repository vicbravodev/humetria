import { RouteTask } from 'humetria';

export const StageOne = () => (
    <ul className="@container overflow-hidden card">
        <RouteTask
            title="Termina tu Política de Teletrabajo"
            detail="Te faltan 2 de 9 temas: desconexión y regreso a la oficina."
            refs="numeral 5.2 · cubre a todo el centro"
            done={false}
            href="#politica"
            action="Continuar"
            primary
        />
        <RouteTask
            title="Prepara 6 documentos de apoyo"
            detail="2 de 6 listos. Riesgos, implementación, mantenimiento de equipo, regreso a la oficina, violencia familiar y apoyo a comisiones."
            refs="5.3, 5.6, 5.8, 5.10, 5.13, 5.14"
            done={false}
            href="#documentos"
            action="Ver los 6"
            primary={false}
        />
    </ul>
);

export const DoneAndOpen = () => (
    <ul className="@container overflow-hidden card">
        <RouteTask
            title="Equipo entregado a cada persona"
            detail="9 de 12 personas con equipo confirmado o con la justificación de no recibirlo."
            refs="numeral 5.7"
            done={false}
            href="#padron"
            action="Ver el padrón"
            primary
        />
        <RouteTask
            title="Capacitación del último año"
            detail={null}
            refs="numeral 5.9 y 8"
            done
            href="#capacitaciones"
            action="Ver capacitaciones"
            primary={false}
        />
        <RouteTask
            title="Mantenimiento del equipo"
            detail="Versión 2."
            refs="numeral 5.8"
            done
            href="#documentos"
            action="Ver documentos"
            primary={false}
        />
    </ul>
);

export const ViewerNoButtons = () => (
    <ul className="@container overflow-hidden card">
        <RouteTask
            title="Cada lugar revisado y validado por la Comisión"
            detail="7 de 12 lugares con validación vigente de la Comisión."
            refs="numeral 5.5"
            done={false}
            href={null}
            action={null}
            primary={false}
        />
        <RouteTask
            title="Conexión y condiciones de cada lugar"
            detail={null}
            refs="numeral 5.1.1"
            done
            href={null}
            action={null}
            primary={false}
        />
    </ul>
);
