<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Support\Nom037;

use App\Modules\Instruments\Contracts\Nom037Checklist;
use App\Modules\Instruments\Data\ChecklistItem;
use App\Modules\Instruments\Data\Favorable;
use App\Modules\Instruments\Data\Requirement;

/**
 * The base NOM-037 checklist: the 109 questions of Apéndice 3 as the DOF words them (with the favorable answer approved in
 * criterios C-10), plus 13 of Humetría's for what chapter 7 and numeral 5.1.1 require and the Apéndice does not ask (C-08).
 * Each work center starts from it and may edit its copy.
 */
final class BaseChecklist implements Nom037Checklist
{
    private const string ADDED_HEADING = 'Condiciones de la norma';

    public function version(): string
    {
        return Nom037::VERSION;
    }

    public function items(): array
    {
        return [
            self::dof('S1-01', 1, 'Conceptos elementales para seleccionar a las personas trabajadoras bajo la modalidad de Teletrabajo', '¿Usted trabajaría en la modalidad de Teletrabajo de forma voluntaria?', Favorable::Yes, null),
            self::dof('S1-02', 1, 'Conceptos elementales para seleccionar a las personas trabajadoras bajo la modalidad de Teletrabajo', '¿Usted se incorporaría a la modalidad de Teletrabajo por imposición del patrón?', Favorable::No, null),
            self::dof('S1-03', 1, 'Conceptos elementales para seleccionar a las personas trabajadoras bajo la modalidad de Teletrabajo', '¿Ha pensado Usted en algún lugar que pudiera seleccionar como lugar de trabajo para realizar Teletrabajo?', Favorable::Yes, null),
            self::dof('S1-04', 1, 'Conceptos elementales para seleccionar a las personas trabajadoras bajo la modalidad de Teletrabajo', '¿El lugar que usted pudiera tener en mente como lugar de trabajo para realizar Teletrabajo es privado?', Favorable::Yes, Requirement::Privacy),
            self::dof('S1-05', 1, 'Conceptos elementales para seleccionar a las personas trabajadoras bajo la modalidad de Teletrabajo', '¿En el lugar de trabajo que usted ocuparía para realizar el Teletrabajo, existen menores de edad, o adultos mayores, o personas con alguna discapacidad que requieran de su atención?', Favorable::Informative, null),
            self::dof('S1-06', 1, 'Conceptos elementales para seleccionar a las personas trabajadoras bajo la modalidad de Teletrabajo', '¿Para realizar su trabajo solamente utiliza las TIC´s [Tecnologías de la Información y Comunicación, como por ejemplo computadoras o tabletas electrónicas]?', Favorable::Yes, null),
            self::dof('S1-07', 1, 'Conceptos elementales para seleccionar a las personas trabajadoras bajo la modalidad de Teletrabajo', '¿El porcentaje de tiempo de su jornada laboral contenida en su contrato de trabajo, que desearía trabajar en la modalidad Teletrabajo, es mayor a 40%?', Favorable::Yes, null),
            self::dof('S1-08', 1, 'Conceptos elementales para seleccionar a las personas trabajadoras bajo la modalidad de Teletrabajo', 'En el supuesto de que se incorpore a la lista de personas trabajadoras bajo la modalidad de Teletrabajo ¿estaría disponible a asistir al centro de trabajo en los días considerados en la modalidad de Teletrabajo?', Favorable::Yes, null),
            self::dof('S1-09', 1, 'Conceptos elementales para seleccionar a las personas trabajadoras bajo la modalidad de Teletrabajo', '¿Considera que podría encontrarse laborando en la modalidad de Teletrabajo con supervisión mínima de su trabajo y tiempo de la jornada laboral?', Favorable::Yes, null),
            self::dof('S1-10', 1, 'Conceptos elementales para seleccionar a las personas trabajadoras bajo la modalidad de Teletrabajo', '¿Para incursionar en la modalidad de Teletrabajo requiere necesariamente que el patrón le proporcione mobiliario que establece la Ley?', Favorable::Informative, null),
            self::dof('S1-11', 1, 'Conceptos elementales para seleccionar a las personas trabajadoras bajo la modalidad de Teletrabajo', '¿Para incursionar en la modalidad de Teletrabajo requiere necesariamente que el patrón le proporcione equipo (software) que establece la Ley?', Favorable::Informative, null),
            self::dof('S1-12', 1, 'Conceptos elementales para seleccionar a las personas trabajadoras bajo la modalidad de Teletrabajo', '¿Para incursionar en la modalidad de Teletrabajo requiere necesariamente que el patrón le proporcione equipo (hardware) que establece la Ley?', Favorable::Informative, null),
            self::dof('S1-13', 1, 'Organización para el trabajo', '¿Al estar en la modalidad de trabajo, considera que podría cumplir con sus metas y proyectos de trabajo al estar expuesto durante su jornada laboral a interrupciones por familiares o por otras personas ajenas a su trabajo?', Favorable::Yes, Requirement::Privacy),
            self::dof('S1-14', 1, 'Espacio físico (general)', '¿El lugar de trabajo seleccionado para realizar Teletrabajo dispone de un espacio físico de al menos 2 metros cuadrados para ser utilizarlo como plano de trabajo, y una altura de al menos 2,5 metros?', Favorable::Yes, null),
            self::dof('S1-15', 1, 'Espacio físico (general)', '¿El lugar de trabajo seleccionado para realizar Teletrabajo dispone de un espacio físico de una altura de al menos 2,5 metros?', Favorable::Yes, null),
            self::dof('S1-16', 1, 'Iluminación', '¿El lugar de trabajo que usted destinaría para el Teletrabajo cuenta con iluminación natural (luz del sol) o artificial (lámparas, luminarias o focos) para la jornada de trabajo?', Favorable::Yes, Requirement::Lighting),
            self::dof('S1-17', 1, 'Iluminación', '¿Considera que la luz natural en el lugar de trabajo, con la que realizaría Teletrabajo, podría ser molesta para su visión?', Favorable::No, Requirement::Lighting),
            self::dof('S1-18', 1, 'Iluminación', '¿La posición de las lámparas, focos o luminarias del lugar de trabajo en las que realizaría Teletrabajo podrían producirle reflejos molestos para su visión?', Favorable::No, Requirement::Lighting),
            self::dof('S1-19', 1, 'Iluminación', '¿El lugar de trabajo que usted utilizaría para realizar el Teletrabajo cuenta con lámparas de LED?', Favorable::Informative, null),
            self::dof('S1-20', 1, 'Iluminación', '¿En el lugar de trabajo, donde colocaría los equipos de las TIC, se evitaría el deslumbramiento de la luz que entra por la ventana u por otra fuente de iluminación?', Favorable::Yes, Requirement::Lighting),
            self::dof('S1-21', 1, 'Ventilación', '¿El lugar de trabajo en donde realizaría el Teletrabajo cuenta con ventanas cerca del plano de trabajo (escritorio o mesa de trabajo)?', Favorable::Informative, null),
            self::dof('S1-22', 1, 'Ventilación', '¿En caso de que cuente con ventanas el lugar de trabajo en donde realizaría el Teletrabajo, éstas se encuentran normalmente cerradas?', Favorable::Informative, null),
            self::dof('S1-23', 1, 'Ventilación', '¿En el lugar de trabajo en el que realizaría Teletrabajo cuenta con aire acondicionado?', Favorable::Informative, null),
            self::dof('S1-24', 1, 'Temperatura', '¿La temperatura del lugar de trabajo que utilizaría para realizar Teletrabajo le parece muy fría como para requerir del uso de ropa de abrigo?', Favorable::No, Requirement::TemperatureVentilation),
            self::dof('S1-25', 1, 'Temperatura', '¿Considera que la temperatura de lugar de trabajo que destinaría para realizar Teletrabajo es alta como para requerir de ventilación adicional con un ventilador o aire acondicionado?', Favorable::No, Requirement::TemperatureVentilation),
            self::dof('S1-26', 1, 'Temperatura', '¿La altura del lugar de trabajo que utilizaría para el Teletrabajo es de más de dos metros y medio?', Favorable::Yes, null),
            self::dof('S1-27', 1, 'Ruido', '¿En el lugar de trabajo que ocuparía para realizar Teletrabajo se percibe ruido de la calle, de patio o de otro lugar cercano que le impida concentrarse en el Teletrabajo?', Favorable::No, Requirement::Noise),
            self::dof('S1-28', 1, 'Ruido', '¿En el lugar de trabajo que utilizaría para el Teletrabajo se percibe un volumen alto de los aparatos de sonido como la televisión, la radio, o la música de tal manera que no pudiera concentrarse en sus tareas?', Favorable::No, Requirement::Noise),
            self::dof('S1-29', 1, 'Para agentes mecánicos', '¿El área de trabajo que destinaría como lugar de trabajo está separada de otras áreas por elementos físicos como paredes, puertas, ventanas, canceles o elementos similares?', Favorable::Yes, Requirement::Privacy),
            self::dof('S1-30', 1, 'Para agentes mecánicos', '¿Los pisos del lugar de trabajo que destinaría al Teletrabajo están despejados y libres de elementos (sillas, bancos, cajas u otro tipo de artículos) que le pudieran generar tropiezo o caída?', Favorable::Yes, Requirement::CleanTidy),
            self::dof('S1-31', 1, 'Para agentes mecánicos', '¿Dispondría usted de un botiquín de primeros auxilios cerca al lugar de trabajo donde realizaría el Teletrabajo?', Favorable::Yes, null),
            self::dof('S1-32', 1, 'Para agentes mecánicos', '¿En el lugar de trabajo que usaría para realizar Teletrabajo existe mobiliario con esquinas o bordes afilados o salientes que le pudieran provocar que se golpeara, raspara o cortara en manos o pies?', Favorable::No, null),
            self::dof('S1-33', 1, 'Para agentes mecánicos', '¿Dispone cerca del lugar de trabajo de un directorio telefónico de números de emergencia?', Favorable::Yes, null),
            self::dof('S1-34', 1, 'Para agentes mecánicos', '¿Dispone de instalaciones eléctricas (contactos eléctricos) en el lugar de trabajo que utilizaría para conectar los equipos de las TIC?', Favorable::Yes, Requirement::Electrical),
            self::dof('S1-35', 1, 'Para agentes mecánicos', '¿En el lugar de trabajo que destinaría para el Teletrabajo utilizaría multicontactos para conectar el equipo TIC?', Favorable::No, Requirement::Electrical),
            self::dof('S1-36', 1, 'Para agentes mecánicos', '¿En el lugar de trabajo que utilizaría para realizar Teletrabajo las instalaciones eléctricas tiene cables expuestos, es decir los cables se encuentran por ejemplo fuera de la caja de los contactos eléctricos?', Favorable::No, Requirement::Electrical),
            self::dof('S1-37', 1, 'Para agentes mecánicos', '¿En el lugar de trabajo que ocuparía para el Teletrabajo, el equipo de las TIC se conectaría a un contacto eléctrico a una distancia mayor de un metro?', Favorable::No, Requirement::Electrical),
            self::dof('S1-38', 1, 'Para agentes mecánicos', '¿En el lugar de trabajo que destinaría para realizar el Teletrabajo, sabe si el contacto eléctrico que utilizaría para alimentar el equipo de las TIC cuenta con conexión a tierra física?', Favorable::Yes, Requirement::Electrical),
            self::dof('S1-39', 1, 'Para agentes mecánicos', '¿Dispone de un extintor disponible cerca de su lugar de trabajo que destinaría para realizar el Teletrabajo?', Favorable::Yes, null),
            self::dof('S1-40', 1, 'Para agentes químicos', '¿En el espacio físico que destinaría como lugar de trabajo, se perciben olores de sustancias químicas (solventes, pinturas, humo de cigarro o polvos de aserrín) que le parezcan desagradables?', Favorable::No, null),
            self::dof('S1-41', 1, 'Para agentes químicos', '¿Usted o alguien más fuma dentro del lugar de trabajo que utilizaría para realizar el Teletrabajo?', Favorable::No, null),
            self::dof('S1-42', 1, 'Para agentes químicos', '¿En el lugar de trabajo que usted ocuparía para realizar el Teletrabajo, se concentran los olores de la cocción de alimentos como asados de carne, chiles toreados, entre otros?', Favorable::No, null),
            self::dof('S1-43', 1, 'Para factores de riesgo ergonómico', '¿Cuenta en el lugar de trabajo que destinaría para el Teletrabajo con alguna silla o asiento disponible para realizar estas actividades?', Favorable::Yes, Requirement::Chair),
            self::dof('S1-44', 1, 'Para factores de riesgo ergonómico', '¿La mesa, escritorio o superficie del lugar de trabajo que ocuparía para el Teletrabajo tiene una altura entre 72 cm y 76 cm', Favorable::Yes, null),
            self::dof('S1-45', 1, 'Para factores de riesgo psicosocial', '¿Usted estaría dispuesto a realizar Teletrabajo para su patrón?', Favorable::Yes, null),
            self::dof('S1-46', 1, 'Para factores de riesgo psicosocial', '¿En caso de que usted realizara Teletrabajo, tendría inconvenientes para llevarlo a cabo sin que para ello la familia fuese un obstáculo?', Favorable::No, Requirement::WorkLifeBalance),
            self::dof('S1-47', 1, 'Para factores de riesgo psicosocial', '¿En el supuesto de que usted realizara Teletrabajo, considera que se sentiría apartado y degradado de su centro de trabajo?', Favorable::No, null),
            self::dof('S1-48', 1, 'Para factores de riesgo psicosocial', '¿Usted considera que si, incursiona en la modalidad de Teletrabajo, éste podría interferir con los tiempos para la atención de las actividades del trabajo y los tiempos para la familia?', Favorable::No, Requirement::WorkLifeBalance),
            self::added('H1-01', 1, '¿El lugar de trabajo que usted destinaría para el Teletrabajo cuenta con conexión a internet suficiente para trabajar?', Favorable::Yes, Requirement::Connectivity),
            self::added('H1-02', 1, '¿El área de trabajo que usted destinaría para el Teletrabajo estaría limpia y ordenada, sin objetos innecesarios?', Favorable::Yes, Requirement::CleanTidy),
            self::added('H1-03', 1, '¿Tendría usted espacio en su lugar de trabajo para apoyar cómodamente los brazos, sin objetos que estorben debajo de la mesa o escritorio?', Favorable::Yes, Requirement::WorkSurface),
            self::added('H1-04', 1, '¿Necesitaría usted algún aditamento ergonómico o postural para usar el equipo de cómputo (por ejemplo, soporte para laptop, reposapiés o apoyo lumbar)?', Favorable::Informative, Requirement::ErgonomicAids),
            self::added('H1-05', 1, '¿Considera que podría conciliar el Teletrabajo con su vida familiar y personal, por ejemplo con una jornada flexible?', Favorable::Yes, Requirement::WorkLifeBalance),
            self::added('H1-06', 1, '¿Conoce usted el mecanismo de la empresa para avisar de un caso de violencia familiar que le afecte?', Favorable::Yes, Requirement::FamilyViolenceChannel),
            self::added('H1-07', 1, '¿Podría usted tomar pausas y tiempos de descanso adecuados durante su jornada de Teletrabajo?', Favorable::Yes, Requirement::Breaks),
            self::added('H1-08', 1, '¿Podría usted desconectarse de las TIC al terminar su jornada y en sus horarios no laborables, vacaciones, permisos y licencias?', Favorable::Yes, Requirement::Disconnection),
            self::dof('S2-01', 2, 'Iluminación', '¿El lugar de trabajo que usted determinó para el Teletrabajo tiene iluminación natural? (luz del sol)', Favorable::Informative, null),
            self::dof('S2-02', 2, 'Iluminación', '¿El lugar de trabajo que usted utiliza para realizar el Teletrabajo cuenta con lámparas incandescentes? (focos de filamento)', Favorable::Informative, null),
            self::dof('S2-03', 2, 'Iluminación', '¿El lugar de trabajo que usted destinó para realizar el Teletrabajo cuenta con lámparas de fluorescentes? (luminarias)', Favorable::Informative, null),
            self::dof('S2-04', 2, 'Iluminación', '¿Considera que la luz con la que realiza Teletrabajo en su lugar de trabajo es molesta para su visión?', Favorable::No, Requirement::Lighting),
            self::dof('S2-05', 2, 'Iluminación', '¿La posición de las lámparas, focos o luminarias del lugar de trabajo en las que realiza Teletrabajo le producen reflejos molestos para su visión?', Favorable::No, Requirement::Lighting),
            self::dof('S2-06', 2, 'Iluminación', '¿El lugar de trabajo que usted utiliza para el Teletrabajo tiene lámparas de LED?', Favorable::Informative, null),
            self::dof('S2-07', 2, 'Iluminación', '¿En el lugar de trabajo, donde coloca los equipos de las TIC, evita el deslumbramiento de la luz que entra por la ventana u por otra fuente de iluminación?', Favorable::Yes, Requirement::Lighting),
            self::dof('S2-08', 2, 'Ventilación', '¿El lugar de trabajo en donde lleva a cabo el Teletrabajo cuenta con ventanas cerca?', Favorable::Informative, null),
            self::dof('S2-09', 2, 'Ventilación', '¿En caso de que cuente con ventanas el lugar de trabajo en donde realiza Teletrabajo, éstas se encuentran normalmente cerradas?', Favorable::Informative, null),
            self::dof('S2-10', 2, 'Ventilación', '¿En el lugar de trabajo en el que desarrolla Teletrabajo cuenta con aire acondicionado?', Favorable::Informative, null),
            self::dof('S2-11', 2, 'Temperatura', '¿La temperatura del lugar de trabajo que emplea para el Teletrabajo le parece muy fría como para requerir del uso de ropa de abrigo?', Favorable::No, Requirement::TemperatureVentilation),
            self::dof('S2-12', 2, 'Temperatura', '¿Considera que la temperatura de lugar de trabajo que utiliza para el Teletrabajo es alta como para requerir de ventilación adicional con un ventilador o aire acondicionado?', Favorable::No, Requirement::TemperatureVentilation),
            self::dof('S2-13', 2, 'Temperatura', '¿La altura del lugar de trabajo que emplea para el Teletrabajo es de más de dos metros y medio?', Favorable::Yes, null),
            self::dof('S2-14', 2, 'Ruido', '¿En el lugar de trabajo que ocupa para realizar Teletrabajo se percibe ruido de la calle, de patio o de otro lugar cercano que le impida concentrarse en el Teletrabajo?', Favorable::No, Requirement::Noise),
            self::dof('S2-15', 2, 'Ruido', '¿El espacio destinado al Teletrabajo está alejado del ruido o de otras distracciones que pudieran interferir con las actividades del Teletrabajo?', Favorable::Yes, Requirement::Noise),
            self::dof('S2-16', 2, 'Ruido', '¿En el lugar de trabajo que escogió para realizar el Teletrabajo se percibe un volumen alto de los aparatos de sonido como la televisión, la radio, o la música de ese lugar o de predios o lugares cercanos, de tal manera que no le permiten concentrarse en sus tareas?', Favorable::No, Requirement::Noise),
            self::dof('S2-17', 2, 'Para agentes mecánicos', '¿El área que destinó como lugar de trabajo está separada de otras áreas por elementos físicos como paredes, puertas, ventanas, canceles o elementos similares?', Favorable::Yes, Requirement::Privacy),
            self::dof('S2-18', 2, 'Para agentes mecánicos', '¿La superficie de su plano de trabajo (mesa, escritorio, barra u otro elemento similar) le permite realizar sus actividades de Teletrabajo de manera similar al realizado en el centro de trabajo?', Favorable::Yes, Requirement::WorkSurface),
            self::dof('S2-19', 2, 'Para agentes mecánicos', '¿En caso de que sus tareas de Teletrabajo, requiera de transcripción de documentos impresos a los equipos de las TIC dispone de un soporte para documentos que le faciliten estas tareas en el lugar de trabajo?', Favorable::Yes, Requirement::ErgonomicAids),
            self::dof('S2-20', 2, 'Para agentes mecánicos', '¿En el lugar de trabajo los elementos de uso más frecuente como teléfono, documentos o accesorios de escritorio (engrapadora, caja de lápices o clips entre otros) están al alcance de las actividades del Teletrabajo que realiza?', Favorable::Yes, Requirement::CleanTidy),
            self::dof('S2-21', 2, 'Para agentes mecánicos', '¿Los pasillos y las puertas de su lugar de trabajo se encuentran permanentemente despejados de objetos que interfieran a su libre desplazamiento?', Favorable::Yes, Requirement::CleanTidy),
            self::dof('S2-22', 2, 'Para agentes mecánicos', '¿El espacio de trabajo se mantiene generalmente libre de basura, desorden y líquidos inflamables?', Favorable::Yes, Requirement::CleanTidy),
            self::dof('S2-23', 2, 'Para agentes mecánicos', '¿Los pisos del lugar de trabajo que determinó para el Teletrabajo están despejados y libres de elementos (sillas, bancos, cajas u otro tipo de artículos) que le pudieran generar tropiezo o caída?', Favorable::Yes, Requirement::CleanTidy),
            self::dof('S2-24', 2, 'Para agentes mecánicos', '¿En su caso, los cables que alimentan los equipos de las TIC se encuentran en el camino de acceso como pasillos al lugar de trabajo, que puedan convierten en obstrucciones para el libre acceso de entrada o salida?', Favorable::No, Requirement::CleanTidy),
            self::dof('S2-25', 2, 'Para agentes mecánicos', '¿En el lugar de trabajo que ocupa para realizar Teletrabajo existe mobiliario con esquinas o bordes afilados o salientes que le pudieran provocar que se golpeara, raspara o cortara en manos o pies?', Favorable::No, null),
            self::dof('S2-26', 2, 'Para agentes mecánicos', '¿Cuenta con un directorio telefónico de números de emergencia cerca de su lugar de trabajo?', Favorable::Yes, null),
            self::dof('S2-27', 2, 'Para agentes mecánicos', '¿Dispone en su lugar de trabajo de instalaciones eléctricas (contactos eléctricos) para conectar los equipos de las TIC?', Favorable::Yes, Requirement::Electrical),
            self::dof('S2-28', 2, 'Para agentes mecánicos', '¿En su lugar de trabajo para el Teletrabajo utiliza multicontactos para conectar el equipo TIC?', Favorable::No, Requirement::Electrical),
            self::dof('S2-29', 2, 'Para agentes mecánicos', '¿Las instalaciones eléctricas de su lugar de trabajo para realizar Teletrabajo en tiene cables expuestos, es decir los cables se encuentran por ejemplo fuera de la caja de los contactos eléctricos?', Favorable::No, Requirement::Electrical),
            self::dof('S2-30', 2, 'Para agentes mecánicos', '¿Los equipos utilizados para el Teletrabajo se conectan a un contacto eléctrico a una distancia mayor de un metro?', Favorable::No, Requirement::Electrical),
            self::dof('S2-31', 2, 'Para agentes mecánicos', '¿Sabe usted si el contacto eléctrico que emplea para alimentar el equipo de las TIC en su lugar de trabajo para realizar el Teletrabajo cuenta con conexión a tierra física?', Favorable::Yes, Requirement::Electrical),
            self::dof('S2-32', 2, 'Para agentes mecánicos', '¿Los contactos eléctricos de su lugar de trabajo se encuentran dañados, en malas condiciones a simple vista, como sucios rotos, flojos, o sin cubierta?', Favorable::No, Requirement::Electrical),
            self::dof('S2-33', 2, 'Para agentes mecánicos', '¿En el lugar de trabajo donde realiza el Teletrabajo, requiere del uso de cables conocidos como extensión para alimentar a los equipos de las TIC?', Favorable::No, Requirement::Electrical),
            self::dof('S2-34', 2, 'Para agentes mecánicos', '¿Cuando no está en uso el equipo que usa para realizar el Teletrabajo, lo mantiene apagado o en modo de espera (suspender)?', Favorable::Yes, null),
            self::dof('S2-35', 2, 'Para agentes mecánicos', '¿Dispone en el lugar de trabajo de un regulador contra sobretensiones eléctricas para proteger el equipo de TIC equipo con el que realiza Teletrabajo?', Favorable::Yes, Requirement::Electrical),
            self::dof('S2-36', 2, 'Para agentes mecánicos', '¿En el espacio físico que dispone como lugar de trabajo para realizar Teletrabajo se perciben olores de sustancias químicas (solventes, pinturas, humo de cigarro o polvos de aserrín) que le parezcan desagradables?', Favorable::No, null),
            self::dof('S2-37', 2, 'Para agentes mecánicos', '¿Usted o alguien más fuma dentro del lugar de trabajo que tiene destinado como lugar de trabajo para el Teletrabajo?', Favorable::No, null),
            self::dof('S2-38', 2, 'Para agentes mecánicos', '¿El lugar de trabajo está ubicado en un ambiente libre de humo?', Favorable::Yes, null),
            self::dof('S2-39', 2, 'Para factores de riesgo ergonómico', '¿La mesa, escritorio o superficie del lugar de trabajo que utiliza para el Teletrabajo tiene una altura entre 72 cm y 76 cm', Favorable::Yes, null),
            self::dof('S2-40', 2, 'Para factores de riesgo ergonómico', '¿La silla que utiliza en su lugar de trabajo cuenta con cinco ruedas?', Favorable::Yes, Requirement::Chair),
            self::dof('S2-41', 2, 'Para factores de riesgo ergonómico', '¿Es posible ajustar la altura del asiento de su silla con respecto al piso?', Favorable::Yes, Requirement::Chair),
            self::dof('S2-42', 2, 'Para factores de riesgo ergonómico', '¿Si es posible ajustar la altura del asiento de su silla con respecto al piso, al hacerlo las plantas del píe le permiten descansarlos completamente en el piso?', Favorable::Yes, Requirement::Chair),
            self::dof('S2-43', 2, 'Para factores de riesgo ergonómico', '¿Su espalda queda completamente apoyada en el respaldo de la silla?', Favorable::Yes, Requirement::Chair),
            self::dof('S2-44', 2, 'Para factores de riesgo ergonómico', '¿Es posible ajustar el respaldo de su silla para apoyar completamente la espalda?', Favorable::Yes, Requirement::Chair),
            self::dof('S2-45', 2, 'Para factores de riesgo ergonómico', '¿El respaldo de la silla es capaz de soportar la curva lumbar de la espalda? (curva lumbar o espalda baja)', Favorable::Yes, Requirement::Chair),
            self::dof('S2-46', 2, 'Para factores de riesgo ergonómico', '¿La silla del lugar de trabajo en donde realiza el Teletrabajo cuenta con descansabrazos?', Favorable::Yes, Requirement::Chair),
            self::dof('S2-47', 2, 'Para factores de riesgo ergonómico', '¿En caso de que la silla del lugar de trabajo en donde realiza el Teletrabajo tenga descansabrazos, éstos son ajustables?', Favorable::Yes, Requirement::Chair),
            self::dof('S2-48', 2, 'Para factores de riesgo ergonómico', '¿Cuando está sentado la silla, en su lugar de trabajo, requiere de un reposapiés para apoyar las plantas de los pies?', Favorable::No, Requirement::ErgonomicAids),
            self::dof('S2-49', 2, 'Para factores de riesgo ergonómico', '¿Considera usted que requiere de un apoyo lumbar en la silla del lugar de trabajo, es decir que requiere de algún elemento de soporte como un cojín en la espalda?', Favorable::No, Requirement::ErgonomicAids),
            self::dof('S2-50', 2, 'Para factores de riesgo ergonómico', '¿Cuando realiza trabajos con equipos de TIC tiene posturas prolongadas en la misma posición, por ejemplo, permanece sentado en el mismo lugar, sin movimiento, por más de 60 minutos de forma continua?', Favorable::No, null),
            self::dof('S2-51', 2, 'Para factores de riesgo ergonómico', '¿El lugar de trabajo se adapta al espacio físico, al equipo y al material relacionado con el Teletrabajo?', Favorable::Yes, null),
            self::dof('S2-52', 2, 'Para factores de riesgo ergonómico', '¿La brillantez que emiten los equipos para el manejo de las TIC le fatiga la vista al realizar las tareas de Teletrabajo?', Favorable::No, null),
            self::dof('S2-53', 2, 'Para factores de riesgo ergonómico', '¿Al momento de teletrabajar en el lugar de trabajo, la distancia entre el monitor del equipo de las TIC y su cara (cuando tiene computadora de escritorio) se encuentra a una distancia de entre 30 y 50 cm.?', Favorable::Yes, null),
            self::dof('S2-54', 2, 'Para factores de riesgo ergonómico', '¿De acuerdo con las tareas a realizar en el lugar de trabajo, cuenta con un ratón o mouse indicador en el equipo de las TIC?', Favorable::Yes, Requirement::ErgonomicAids),
            self::dof('S2-55', 2, 'Para factores de riesgo ergonómico', '¿En el lugar de trabajo la distancia entre el teclado y los codos es insuficiente, de tal manera que le incomoda al teclear?', Favorable::No, Requirement::WorkSurface),
            self::dof('S2-56', 2, 'Para factores de riesgo ergonómico', '¿El lugar de trabajo es suficientemente amplio para que realice movimientos en el Teletrabajo sin que pueda tener una postura forzada que le produzca tensión o lesión musculares?', Favorable::Yes, Requirement::WorkSurface),
            self::dof('S2-57', 2, 'Para factores de riesgo ergonómico', '¿Tiene suficiente espacio debajo de la mesa de trabajo para evitar que las piernas se golpeen?', Favorable::Yes, Requirement::WorkSurface),
            self::dof('S2-58', 2, 'Para factores de riesgo ergonómico', '¿Hay espacio en su lugar de trabajo para descansar los brazos cuando no teclea?', Favorable::Yes, Requirement::WorkSurface),
            self::dof('S2-59', 2, 'Para factores de riesgo psicosocial', '¿Al realizar Teletrabajo, tiene inconvenientes para desarrollarlo sin que ello afecte o interfiera con las actividades de la familia?', Favorable::No, Requirement::WorkLifeBalance),
            self::dof('S2-60', 2, 'Para factores de riesgo psicosocial', '¿Conoce algo sobre los ciclos de descanso de acuerdo con su actividad en el Teletrabajo?', Favorable::Yes, Requirement::Breaks),
            self::dof('S2-61', 2, 'Para factores de riesgo psicosocial', 'Considera usted que el Teletrabajo le genera ansiedad, irritabilidad, estados depresivos, o fatiga mental', Favorable::No, null, sensitive: true),
            self::added('H2-01', 2, '¿El lugar de trabajo que usted utiliza para el Teletrabajo cuenta con conexión a internet suficiente para trabajar?', Favorable::Yes, Requirement::Connectivity),
            self::added('H2-02', 2, '¿Logra usted conciliar el Teletrabajo con su vida familiar y personal, por ejemplo con una jornada flexible?', Favorable::Yes, Requirement::WorkLifeBalance),
            self::added('H2-03', 2, '¿Conoce usted el mecanismo de la empresa para avisar de un caso de violencia familiar que le afecte?', Favorable::Yes, Requirement::FamilyViolenceChannel),
            self::added('H2-04', 2, '¿Toma usted pausas y tiempos de descanso adecuados durante su jornada de Teletrabajo?', Favorable::Yes, Requirement::Breaks),
            self::added('H2-05', 2, '¿Se respeta su desconexión de las TIC al terminar su jornada y en sus horarios no laborables, vacaciones, permisos y licencias?', Favorable::Yes, Requirement::Disconnection),
        ];
    }

    public function instructions(): string
    {
        return '1.- Para cada uno de los conceptos de la presente lista de verificación de las condiciones de seguridad y salud (en ambas secciones) se encontrará preguntas que servirán para conocer si el lugar de trabajo seleccionado cuenta o no con las condiciones de seguridad y salud en el trabajo para el desarrollo de tareas de Teletrabajo.

2.- De cada pregunta, la persona trabajadora en la modalidad de Teletrabajo (incluido el aspirante) realizará una anotación, con una "X", en el recuadro (casilla o celda) que corresponda a la fila en donde se formula la pregunta.

3.- La persona trabajadora en la modalidad de Teletrabajo solamente puede optar por una anotación ("X") en la celda ("SI", "NO" o "NO APLICA)".

En caso de que la respuesta infiera una condición desfavorable para realizar actividades de Teletrabajo (condiciones que puedan provocar algún tipo de afectación a su salud o lesión física) deberá documentar el tipo de riesgo identificado y, en su caso, el patrón podrá acordar un plan de acción para poder eliminarla. Lo mínimo que debe tener esta notificación es evidencia documental (de ser posible evidencia fotográfica) de la evaluación realizada.

En caso de ser necesario, la persona trabajadora en la modalidad de Teletrabajo podrá consultar, si es el caso, el manual, la guía o instructivo elaborado, establecido o implementado por el patrón, con el apoyo de la comisión de seguridad e higiene del centro de trabajo, tanto para responder a las preguntas, como para realizar acciones que le permitan contar con condiciones favorables para realizar el Teletrabajo.

4.- Una vez concluido el llenado de la lista de verificación de las condiciones de seguridad y salud en el lugar de trabajo, la persona trabajadora bajo la modalidad de Teletrabajo (o la persona aspirante que se vaya a contratar en esta modalidad) entregará la lista de verificación de las condiciones de seguridad y salud en el lugar de trabajo, al patrón (ver definición de lugar de trabajo).';
    }

    public function responsibilityNotice(): string
    {
        return 'Contestar esta lista no significa que usted sea responsable de las condiciones de su lugar de trabajo ni de pagar las adecuaciones con su patrimonio: según la NOM-037, la autoaplicación de la lista «no significa el traslado de la responsabilidad a las personas trabajadoras en la comprobación de las condiciones de seguridad y salud del lugar de trabajo, ni de la adecuación a cargo de su patrimonio». Usted solo describe su lugar; la Comisión de Seguridad e Higiene es quien lo valida.';
    }

    public function baseConsent(): string
    {
        return 'Algunas respuestas de esta lista, y las fotografías de su lugar de trabajo que decida adjuntar, pueden revelar datos de su salud, que son datos personales sensibles. Al aceptar, usted autoriza de forma expresa que se recaben y se traten para verificar las condiciones de seguridad y salud de su Teletrabajo. La empresa es la responsable de estos datos y Humetría los trata por cuenta de ella. Usted puede no aceptar: en ese caso se omitirán las preguntas sensibles y las fotografías, y podrá contestar el resto de la lista.';
    }

    private static function dof(string $code, int $section, string $heading, string $text, Favorable $favorable, ?Requirement $requirement, bool $sensitive = false): ChecklistItem
    {
        return new ChecklistItem($code, $section, $heading, $text, $favorable, $requirement, $sensitive, true);
    }

    private static function added(string $code, int $section, string $text, Favorable $favorable, Requirement $requirement): ChecklistItem
    {
        return new ChecklistItem($code, $section, self::ADDED_HEADING, $text, $favorable, $requirement, false, false);
    }
}
