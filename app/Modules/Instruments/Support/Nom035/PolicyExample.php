<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Support\Nom035;

use App\Modules\Instruments\Contracts\Nom035Policy;
use App\Modules\Instruments\Data\PolicySection;
use App\Modules\Instruments\Data\PolicyTemplate;

/**
 * Guía de referencia IV, transcribed literal from the DOF of 23/10/2018 (docs/normas/nom-035/texto-de-referencia.md): nine
 * commitments and eight principles. The only change is that "(Razón Social)" is the placeholder `{legal_name}`.
 */
final readonly class PolicyExample implements Nom035Policy
{
    public function version(): string
    {
        return 'dof-2018-10-23';
    }

    public function template(): PolicyTemplate
    {
        return new PolicyTemplate($this->version(), [
            new PolicySection('commitments', 'Compromisos', 'En este centro de trabajo {legal_name} en relación con la prevención de los factores de riesgo psicosocial; la prevención de la violencia laboral, y la promoción de un entorno organizacional favorable, se asumen los compromisos siguientes:', [
                'Es obligación de supervisores, gerentes y directores aplicar esta política y predicar con el ejemplo;',
                'Los actos de violencia laboral no son tolerados, así como ningún incidente que propicie factores de riesgo psicosocial o acciones en contra del entorno organizacional favorable;',
                'Se aplican medidas encaminadas a la prevención de los factores de riesgo psicosocial; la prevención de la violencia laboral, y la promoción de un entorno organizacional favorable, para prevenir sus consecuencias adversas;',
                'Se cuenta con un procedimiento de atención justo, que no permite represalias y evita reclamaciones abusivas o carentes de fundamento, y que garantiza la confidencialidad de los casos;',
                'Se realizan acciones de sensibilización, programas de información y capacitación;',
                'Se divulgan de forma eficaz las políticas de prevención y las medidas de prevención;',
                'Todos los trabajadores participan para establecer y poner en práctica esta política en el lugar de trabajo;',
                'Se respeta al ejercicio de los derechos del personal para observar sus creencias o prácticas o para satisfacer sus necesidades relacionadas con la raza, sexo, religión, etnia o edad o cualquier otra condición que pueda dar origen a la discriminación, y',
                'Se crean espacios de participación y consulta, teniendo en cuenta las ideas de los trabajadores y empleados.',
            ], 'numbers', removable: false),
            new PolicySection('responsibles', 'Responsables', '', removable: false, required: true),
            new PolicySection('resources', 'Recursos', '', removable: false, required: true),
            new PolicySection('principles', 'Principios', 'La política se basa en los principios siguientes:'),
            new PolicySection('principle_1', 'Principio 1', 'Para favorecer un entorno organizacional favorable, en el centro de trabajo se:', [
                'Realizan reuniones con los trabajadores para que todos tengan la oportunidad de exponer sus dudas, inquietudes, quejas relacionadas con su trabajo;',
                'Mantienen limpias las áreas de trabajo; así como las áreas comunes: comedor, baños, sanitarios, accesos;',
                'Trata con amabilidad y cortesía a los compañeros de trabajo, jefes, subalternos, visitantes, proveedores, clientes;',
                'Atienden todos los casos relacionados con burlas, malos tratos, discriminación, etc., y en su caso, se sancionan éstos;',
                'Realizan exámenes médicos al personal de nuevo ingreso para procurar y mantener su salud;',
                'Llevan a cabo exámenes psicológicos y/o psicométricos para situar a cada trabajador en el puesto para el cual cuente con capacidades y preparación acordes;',
                'Realizan planes de promoción interna, y',
                'Capacita a todo el personal para el desempeño de sus funciones, su seguridad y desarrollo.',
            ], 'letters'),
            new PolicySection('principle_2', 'Principio 2', 'En este centro de trabajo, para promover el sentido de pertenencia de los trabajadores a la organización, se hace hincapié en que todas y todos los trabajadores y empleados son importantes para el centro de trabajo, ya que el mismo no puede funcionar si no se cuenta con personal desde el de la limpieza, pasando por el operativo, administrativo y hasta el directivo.'),
            new PolicySection('principle_3', 'Principio 3', 'Este centro de trabajo, para lograr la adecuada realización de las tareas encomendadas, imparte a sus trabajadores y empleados la capacitación establecida en la Ley Federal del Trabajo, acorde a las actividades que desempeñan.'),
            new PolicySection('principle_4', 'Principio 4', 'En este centro de trabajo, para lograr la definición precisa de responsabilidades para los trabajadores, se difunde y promueve el seguimiento a los manuales de organización y las descripciones de puesto que indican las responsabilidades específicas de cada puesto de trabajo.'),
            new PolicySection('principle_5', 'Principio 5', 'Para lograr la participación proactiva y comunicación entre sus integrantes, este centro de trabajo publica en tableros anuncios, noticias y avisos de interés para sus trabajadores y empleados relativos a las labores y operaciones de la misma, y éstos, a su vez, pueden publicar en dichos tableros el mismo tipo de anuncios, noticias y avisos. En estos tableros no se incluyen avisos que no están estrictamente relacionados con las labores del centro de trabajo.'),
            new PolicySection('principle_6', 'Principio 6', 'Para promover un trabajo digno y lograr un entorno organizacional favorable, este centro de trabajo realiza una distribución adecuada de cargas de trabajo, con jornadas de trabajo conforme a la Ley Federal de Trabajo, para tal efecto, define en los manuales de organización y en las descripciones de puesto que se emiten, así como en el Reglamento Interior de Trabajo, en función de la naturaleza especifica de la labor de que se trate.'),
            new PolicySection('principle_7', 'Principio 7', 'Para reconocer el desempeño de los trabajadores en el centro de trabajo, se evalúa por lo menos una vez al año el desempeño de cada uno de sus trabajadores y empleados, lo cual se lleva a cabo personalmente por conducto del supervisor o jefe inmediato del trabajador o empleado de que se trate y los resultados de la misma sirven de base para la determinar el reconocimiento o beneficios a los trabajadores sobresalientes.'),
            new PolicySection('principle_8', 'Principio 8', 'Este centro de trabajo se compromete a vigilar el cumplimiento de las normas dirigidas a prevenir cualquier conducta o comportamiento que implique violencia laboral, a salvaguardar la información que sea recolectada, dar trámite e intervenir, de forma oportuna, en las quejas que se reciban por los medios establecidos.'),
        ], 'La política deberá indicar a los responsables (con capacidad para su aplicación) y los recursos que se disponen para cumplir con las políticas en todos los planos de la organización; la asignación de responsabilidades tanto a las personas como a los equipos de trabajo, los cuales reciben la capacitación para la aplicación de las políticas.');
    }
}
