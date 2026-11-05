# Rediseño de Humetría: rutas guiadas para NOM-035, NOM-037 y Ley Silla

Lienzo de diseño: https://claude.ai/artifact/Mabe2mH9pPgE8FKA2BvY6Q (claro y oscuro con el tweak `tema`).
- Páginas **v2 · ruta guiada**, **v2 · navegación, roles y cierres**, **v2 · cierre de gaps** y **v2 · quejas, difusión y compensación**: dirección vigente.
- Páginas **v1 (referencia)**: cada pantalla dice en su título si "Se queda" o fue "Reemplazada por v2".

## Por qué v2
La v1 hablaba el idioma de la norma (renglones, 5.1.1, R1–R13). Daba una calificación sin decir por dónde empezar, tenía unos 15 botones por pantalla, rompía el orden natural de la norma y suponía que RH ya tiene los documentos.

En la competencia (Integratec, Factorial, Buk, Runa, EdiFact), la encuesta de NOM-035 está bien resuelta. Para NOM-037 y Ley Silla solo ofrecen checklists en PDF, gestores de documentos o listan el módulo sin flujo. Nadie guía dentro del producto.

Patrones tomados: la entrevista de TurboTax, el checklist de Gusto, los flujos guiados de Vanta y la lista de tareas de GOV.UK.

## Principios
1. Cada norma es una **ruta de 6 etapas** en el orden de la norma. Los títulos van en lenguaje llano y los numerales quedan como referencia secundaria.
2. Hay **un siguiente paso** claro. Lo urgente (accidentes, canalización, quejas por acusar) va antes, en "Antes de seguir" o "Atiende primero".
3. Los **documentos se escriben con preguntas**, no con "Subir". "Ya lo tengo" es la opción secundaria.
4. **Etapas de empresa y etapas de centro**:
   - Políticas y documentos se hacen una vez para todos los centros.
   - Personas, lugares, Comisión, evaluaciones y avisos van por centro.
5. **"¿Te aplica?" va primero.** Si no aplica, se emite una constancia con fecha.
6. **"Si hoy llegara una inspección":** muestra lo que puedes enseñar y lo que falta, cada cosa con su botón.
7. **Cada rol entra solo a lo suyo.** Voz: a RH y a la Comisión se les habla de tú; a las personas trabajadoras, de usted.

## Roles y permisos
| Puede | Administra todo | Ayuda con las rutas | Solo consulta | Comisión (enlace) | Persona (portal) |
|---|---|---|---|---|---|
| Ver rutas y expedientes | Sí | Sí | Sí | Solo su cola | No |
| Avanzar tareas y documentos | Sí | Sí | No | Validar y firmar | Solo lo suyo |
| Resultados NOM-035 agregados | Sí | Sí | Sí | No | El resumen compartido |
| Canalización y datos de salud | Sí | No | No | No | No |
| Avisos de accidentes | Sí | Sí | Solo ver | Si afecta un lugar | Los suyos |
| Contenido de quejas y denuncias | Solo si se le designa | Solo si se le designa | No | Solo si se le designa | Las suyas, con referencia y clave |
| Canal de quejas: quién atiende y conteos | Sí | No | No | No | No |
| Compensación: salarios y escenarios | Sí | No | Reportes compartidos | No | No |
| Usuarios y configuración | Sí | No | No | No | No |

- El consultor entra a cada cliente con permisos de "Administra todo". Ve un aviso fijo: "Estás dentro de X como Y". Lo que hace queda registrado a su nombre.
- Abrir datos confidenciales deja registro de quién y cuándo.
- Los enlaces personales (Comisión, portal, persona externa que atiende quejas) no piden contraseña. El de la Comisión vence a los 7 días y se pide de nuevo con un clic.
- Nadie ve una queja en su contra. El sindicato es otra cuenta, con sus propios datos.

## Ciclo de vida de cada ruta
- **NOM-035:**
  - Nueva evaluación al menos cada 2 años, con aviso 60 días antes.
  - Al iniciar un ciclo se copian la política y el programa como punto de partida.
  - Entre ciclos siguen vivos:
    - las acciones del programa;
    - la política para nuevos ingresos (se entrega automáticamente);
    - la Guía I ante un acontecimiento traumático severo;
    - las quejas.
- **NOM-037:**
  - Revisión de cada lugar según la periodicidad de ajustes (12 meses por defecto).
  - Capacitación anual.
  - Nueva versión de cada documento cuando cambia.
  - Altas y bajas de teletrabajadores en cualquier momento.
- **Ley Silla:**
  - Evaluación vigente por persona; se hace una nueva al cambiar de puesto o de condiciones.
  - El reglamento se actualiza cuando cambia.
  - Asientos y reportes en cualquier momento.
- **Estado "Todo al día":** muestra la fecha de término, la próxima obligación, lo que sigue vivo y la descarga del expediente.

## Aviso de accidente (NOM-037, 6.9)
Pasos:
1. Registrar qué se hizo: canalización a atención médica, ajuste del lugar o del equipo, cambio de tareas, permiso u otro. La nota va sin detalle clínico.
2. Avisar a la persona en su portal.
3. Cerrar con evidencia.

Se puede pedir a la Comisión que revise el lugar. RH recibe un correo inmediato y un recordatorio si pasan 3 días sin respuesta. El IMSS y los exámenes médicos quedan fuera de Humetría.

## Correos (NOM-037, Ley Silla, Comisión, RH)
| Para | Cuándo | Asunto | Botón |
|---|---|---|---|
| Comisión | Hay pendientes nuevos (resumen diario como máximo) | Tienes N pendientes como integrante de la Comisión | Ver mis pendientes |
| Persona NOM-037 | Alta como teletrabajador; recordatorio a los 3 y 7 días | Su espacio de trabajo en casa: una lista de 10 minutos | Contestar la lista |
| Persona NOM-037 | Documento publicado o equipo entregado | Tiene algo por confirmar | Ver y confirmar |
| Persona Ley Silla | Al iniciar evaluaciones; recordatorio semanal | 2 preguntas sobre su trabajo de pie | Contestar ahora |
| Persona NOM-035 | Resultados compartidos | Resultados de la evaluación de su centro | Leer y confirmar |
| RH | Aviso de accidente (inmediato); sin respuesta a los 3 días | X avisó un accidente de trabajo | Revisar aviso |
| RH | Correos rebotados | N personas no recibieron… | Corregir correos |
| RH | 60 días antes de un nuevo ciclo | Se acerca tu próxima evaluación | Programar |

Todos llevan el logo de la empresa o del consultor, un solo botón y el sello "hecho con humetría". Los correos de quejas, difusión y compensación están en el board 41.

## Documentos con preguntas (el contenido debe validarlo un abogado)
- **Riesgos de trabajar desde casa (5.3):**
  - tipo de trabajo;
  - riesgos a explicar: postura y pantalla, instalación eléctrica, aislamiento, jornadas sin fin, caídas con cables;
  - a quién avisar;
  - cómo se entrega: portal con acuse o inducción.
- **Cómo se empieza a teletrabajar (5.6):**
  - quién lo solicita;
  - quién aprueba y en cuánto tiempo;
  - qué se firma: anexo al contrato y lista del lugar;
  - qué equipo se entrega.
- **Mantenimiento del equipo (5.8):**
  - qué equipo entrega la empresa;
  - cada cuánto se revisa;
  - quién lo hace: TI o un proveedor;
  - cómo se reporta una falla.
- **Regreso a la oficina (5.10):**
  - quién puede pedirlo: la persona, la empresa o ambos;
  - con cuánta anticipación;
  - cómo se acuerda y se documenta;
  - qué pasa con el equipo.
- **Apoyo si vive violencia en casa (5.13):**
  - a quién puede acudir dentro de la empresa;
  - a dónde se canaliza fuera: líneas e instituciones públicas;
  - qué ajustes temporales ofrece la empresa;
  - confidencialidad.
- **Participación en comisiones (5.14):**
  - cómo se elige o invita;
  - cómo participa a distancia;
  - tiempo dentro de la jornada.

## Primera vez de una empresa
1. Datos de la empresa y centros. Este paso ya existe.
2. Por cada centro: ¿alguien trabaja desde casa más del 40 % del tiempo? ¿Alguien trabaja de pie? Y el sector.
3. Con las personas registradas se deduce qué guías de NOM-035 aplican:
   - hasta 15 personas: Guía I;
   - de 16 a 50: Guías I y II;
   - más de 50: Guías I y III.

El resultado son las rutas creadas y las constancias de "no aplica".

## Estados del sistema
- Empresa sin centros.
- Correos rebotados.
- Enlace vencido.
- Portal sin conexión: guarda en el teléfono y envía al volver.
- Consultor dentro de un cliente: barra fija.
- Error al guardar: conserva el texto y ofrece reintentar.

Regla para todos: decir qué pasó y qué hacer, sin disculpas y sin perder lo escrito.

## A validar con la implementación
- La lógica A/B/C de la Figura 1 de Ley Silla. En el prototipo es ilustrativa.
- Los textos de la lista de verificación de NOM-037. Son de ejemplo.
- Los tiempos estimados por tarea. Son supuestos; hay que medirlos con usuarios.
- La redacción legal de plantillas, textos base, sugerencias de acciones y documentos de apoyo.
- El contenido exacto de NOM-035 para el programa de intervención, la difusión, la muestra de la Guía III y la periodicidad.
- Las reglas de aplicabilidad de Ley Silla por sector y de NOM-037 (40 % del tiempo).
- El nivel de riesgo de Ley Silla: se calcula al firmar, con las tablas del DOF.
- La vigencia de los enlaces (Comisión: 7 días; portal) y la frecuencia de los recordatorios.
