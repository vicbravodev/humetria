# Vacaciones, organigrama, clima y pulso: investigación y diseño

Fecha: 2026-11-09 · Estado: **aprobado por Victor (sección 8); planes en `docs/superpowers/plans/2026-11-09-*`**. Nada construido.

Este documento cubre cuatro herramientas. Cada una es un PR propio y tendrá su plan detallado
(`docs/superpowers/plans/`). Las decisiones de Victor están en la sección 8; lo marcado **(mi propuesta)** es mío.
El banco de preguntas de clima y pulso está en `2026-11-09-engagement-item-bank.md`.

## 1. Para qué

Hasta hoy Humetria cubre cumplimiento (NOM-035, NOM-037, Ley Silla, jornada). Estas cuatro herramientas son de uso
diario o recurrente y dan a RH una razón para entrar cada semana:

- **Organigrama**: quién reporta a quién. Su valor real no es el dibujo sino la línea de reporte como dato, que
  usan las otras tres.
- **Vacaciones**: saldos conforme a la LFT, políticas superiores a la ley, solicitudes y la evidencia que el patrón
  debe poder mostrar (la carga de la prueba es suya, art. 784 X y XI).
- **Clima**: encuesta anual o semestral que dice qué impulsa el compromiso y qué priorizar.
- **Pulso**: encuesta corta y frecuente que mide si las acciones del clima están funcionando.

## 2. Lo que ya existe y se reutiliza

| Necesidad | Lo que hay | Qué falta |
|---|---|---|
| Empleados, áreas, centros | `Organization` (`Roster`, `Areas`, `JobGrades`), CSV de hasta 2000 filas | `manager_id` no existe (asistencia lo planeó y lo difirió). `hired_on` existe pero es opcional y `RosterEntry` no lo expone |
| Autoservicio del empleado | Portal personal por token (`Telework\Contracts\PortalAccess`, `portal/{token}`); los empleados **no tienen `User`** | Secciones nuevas en el portal |
| Ausencias en la jornada | El spec de asistencia ya espera un módulo `Leave` con un contrato `Absences` ("Leave no conoce a Attendance") | El módulo |
| Encuesta anónima | Patrón de `Responses` (UUID v4, sin llave al empleado, solo inserción), enlaces de un solo uso de `Campaigns` | El motor actual no sirve tal cual: una escala por instrumento, sin 0–10 ni texto abierto, definiciones fijas, una campaña por centro, `CampaignKind` y correos redactados para NOM-035 |
| Mínimo por grupo | `Results\Contracts\SmallGroups` (`GroupMinimum`, con supresión complementaria, `config/results.php`) | Umbral mayor para comentarios |
| Permisos y navegación | Un permiso `view-x`/`manage-x` por herramienta sembrado por migración; `tenant.can.*` en `ResolveTenant`; `tool-navigation.ts` (grupo "Herramientas") | Cuatro entradas |
| Activación por plan | Solo en el spec de monetización (rama `feature/monetization-admin`, sin construir): ya lista `vacations`, `org_chart`, `pulse` y `climate` | Cuando Billing exista, cada herramienta entra a su catálogo por nombre de ruta |
| Humi | `ToolRegistry` + contratos de lectura por módulo | Un contrato de lectura por herramienta (fase posterior) |
| Recordatorios | Comandos programados en `routes/console.php`; notificaciones por correo en cada módulo | Los de cada herramienta |

## 3. Cimiento compartido: la línea de reporte (dentro de `Organization`)

**(mi propuesta)** No se crea un módulo nuevo para el organigrama: vive en `Organization`, que ya es dueño del
empleado.

- `employees.manager_id`: llave foránea a la misma tabla (compuesta con `tenant_id`), opcional.
  `CHECK (manager_id <> id)` y un trigger de Postgres que sube por la cadena y rechaza ciclos (una validación solo
  en la app deja pasar dos cambios simultáneos).
- `employees.seniority_on` (opcional): antigüedad reconocida distinta de la fecha de alta, para sustitución
  patronal (art. 41 LFT). Si está vacía se usa `hired_on`.
- CSV: columna `jefe` (número de empleado o correo del jefe) y `fecha_de_ingreso` también en la variante de empresa.
- Contrato nuevo `Organization\Contracts\ReportingLines`: `managerOf`, `chainOf`, `directReportIds`, `subtreeIds`.
  Las demás herramientas nunca leen la tabla.
- **Profundidad ilimitada**: director → senior manager → manager → senior analyst → analyst, o los niveles que
  la empresa tenga. No hay un número fijo de niveles ni un catálogo de niveles que configurar: la jerarquía sale
  de quién reporta a quién.
- Lista de adyacencia + `WITH RECURSIVE`. A menos de 10 mil personas por empresa, `ltree` o closure table solo
  añaden costo de escritura.
- Sin historia con vigencia en v1: cada cambio de jefe va a la bitácora (log y bitácora `employee.manager_changed`; `EmployeeMoved` es el cambio de centro, no sirve para esto).
- Al dar de baja a alguien con reportes directos, la pantalla exige reasignarlos.

## 4. Organigrama

### Investigación (resumen)
- Los HRIS para pymes (BambooHR, Factorial) usan el modelo por persona ("reporta a"); el modelo por plaza
  (Workday, Personio de pago, Buk) es para planeación de plantilla.
- Los fallos reales con cargas por CSV son empleados sin jefe, varias raíces, jefe dado de baja y ciclos.
- Bain: empresa promedio 6–7 reportes directos; McKinsey rechaza un número único. Sirven como señal, no como regla.
- **Ninguna norma mexicana exige organigrama** a un patrón privado (no REPSE, no RIT, no NOM-035). ISO 9001 5.3
  pide responsabilidades y autoridades; el organigrama es la evidencia habitual. Se vende como útil, no obligatorio.

### Diseño
- **Árbol propio, sin dependencia nueva**: `<ul>` anidado con `role="tree"`, líneas en CSS, hijos cargados por nodo
  desde el servidor (nunca hay miles de nodos en el DOM; cumple la regla de listas paginadas y la CSP por nonce).
  La misma estructura es la vista de esquema en móvil.
- Búsqueda en servidor que abre la cadena hasta la persona; migas hasta la dirección; filtros por centro y área.
- Cada nodo: nombre, puesto, área, reportes directos y totales. Con Vacaciones activa, insignia "ausente hoy".
- **Calidad de datos** como pantalla propia (es lo que más valor da): conteos de sin jefe, varias raíces, jefe
  inactivo; detalle paginado con corrección en línea.
- Indicadores: niveles, mediana de reportes directos, y solo los extremos (1–2 o más de 15) marcados "revisar".
- Exportar: hoja de impresión CSS (PDF desde el navegador).
- Portal del empleado: "Mi equipo" (mi jefe, mis compañeros, mis reportes).

### No metas (v1)
Vacantes y plazas, línea punteada o matricial, organigrama a una fecha, arrastrar y soltar, exportar a PNG.

## 5. Vacaciones (módulo `Leave`)

### Investigación legal (LFT, última reforma DOF 14-05-2026; decreto de vacaciones DOF 27-12-2022)

| Años | 1 | 2 | 3 | 4 | 5 | 6–10 | 11–15 | 16–20 | 21–25 | 26–30 | 31–35 |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Días (art. 76) | 12 | 14 | 16 | 18 | 20 | 22 | 24 | 26 | 28 | 30 | 32 |

- **Art. 78**: al menos 12 días continuos; fraccionar es potestad **del trabajador**, no del patrón.
- **Art. 79**: no se pagan en lugar de disfrutarse; solo el proporcional al terminar la relación.
- **Art. 80**: prima no menor al 25 %.
- **Art. 81**: se conceden dentro de los 6 meses siguientes al aniversario; **constancia anual** con antigüedad,
  periodo y fecha de disfrute.
- **Art. 516** y jurisprudencia 2a./J. 1/97 *(no verificada en el SJF)*: prescriben un año después de terminados
  esos 6 meses, unos 18 meses tras el aniversario. Es una excepción que el patrón opone en juicio.
- **Arts. 784 y 804–805**: el patrón prueba ingreso, antigüedad, disfrute y pago de vacaciones y prima; conserva
  comprobantes el último año y un año tras terminar la relación; si faltan, se presume cierto lo que diga el trabajador.
- El derecho **nace completo en el aniversario**; no hay devengo mensual exigible.
- **Prestaciones superiores**: la ley es piso (art. 56; segundo transitorio de 2022). Se dan por contrato
  individual, colectivo, reglamento o política. Los días sobre el mínimo no los regula la LFT, así que la empresa
  puede ponerles caducidad, pago o tope de arrastre si consta por escrito *(defendible; no verificado en
  jurisprudencia)*. Esquemas comunes: tabla propia, días por nivel, días personales, cumpleaños, prima de 50–100 %.
- Casos: discontinuos proporcional (art. 77); menores 18 días (art. 179); "días laborables" son los que esa
  persona trabaja; festivos (art. 74) e incapacidad dentro del periodo no consumen saldo *(práctica dominante, no
  verificada en tesis)*; aniversarios anteriores a 2023 conservan la tabla vieja.
- Otras licencias con goce: paternidad 5 días (132 XXVII Bis), maternidad 6+6 semanas (170), adopción 6 semanas,
  cuidados de hijos con cáncer (170 Bis / 140 Bis LSS). **Luto no está vigente** (minuta pendiente en el Senado).
- La reforma de 40 horas (DOF 01-05-2026) no cambia las vacaciones.

Al construir, esto se vuelve `docs/normas/vacaciones/` (`criterios.md` estilo `C-NN`, que es el que Humi puede
buscar, + `texto-de-referencia.md` + `fuentes/`), y cada punto "no verificado" se verifica o queda como criterio
nuestro con su justificación.

### Diseño

**Políticas (los "overrides")**
- `leave_policies`: nombre, tabla de días por año de antigüedad, prima %, semana laboral (L–V, L–S), y reglas de
  los días adicionales (caducidad en meses, tope de arrastre). La base valida que ningún renglón quede bajo el
  art. 76 ni la prima bajo 25 %: una política solo puede mejorar la ley.
- Siempre existe la política "Ley" (la tabla del art. 76, no editable). La empresa crea las suyas y las asigna por
  defecto, por grupo (`staff_group`: sindicalizado/confianza) o por nivel (`job_grade`). Gana la más específica.
- Días fijos extra (cumpleaños, personales) son renglones de la política con su propia caducidad.

**Saldos por periodo, no un contador**
- `leave_entitlements`: uno por empleado y aniversario, con `statutory_days`, `extra_days`, `granted_on`,
  `enjoy_by` (+6 meses) y `prescribes_on` (+18 meses) y la política aplicada. Un comando diario los crea al
  cumplirse el aniversario.
- `leave_entries`: libro de movimientos de solo inserción (otorgado, disfrutado, ajuste, saldo inicial, pago al
  terminar). El saldo es la suma. Se consume primero el periodo más antiguo, y dentro de él primero el tramo legal.
- Lo prescribible se **marca**, no se borra: decidir oponer la prescripción es del patrón.
- Saldos iniciales por CSV (empleado, periodo, días pendientes) para empresas que ya venían operando.
- Vacaciones adelantadas: apagadas por defecto; si la política las permite, quedan como saldo negativo explícito
  con quién las autorizó.

**Solicitudes**
- El empleado pide desde su portal: ve su saldo por periodo, elige fechas y el sistema cuenta días laborables
  (semana de su política, menos festivos del art. 74 y los que la empresa agregue).
- **Aprueba su jefe** (`ReportingLines`) desde su propio portal, con aviso por correo. **RH es solo respaldo**:
  si el jefe directo está inactivo aprueba el siguiente jefe activo hacia arriba; la solicitud le llega a RH
  en el panel si no hay ningún jefe activo en la cadena, o si el jefe no responde en
  el plazo que fije la empresa (3 días hábiles por defecto, **mi propuesta**). RH siempre puede ver y corregir.
- La solicitud muestra al jefe y a RH si la persona **tenía o no días disponibles** y de qué periodo salen.
- Art. 78: si la persona nunca toma 12 días seguidos en el periodo, se le avisa; no se bloquea (es su potestad).
- Estados: solicitada, aprobada, rechazada (con motivo), cancelada, disfrutada.
- **Permisos y licencias**: un catálogo corto de tipos sin saldo, con el mismo flujo de solicitud y aprobación.
  Vienen sembradas las licencias de ley (paternidad, maternidad, adopción) y "permiso especial" (con y sin goce);
  la empresa puede agregar los suyos. Sin reglas por tipo en v1: solo quién, cuándo, cuántos días y quién aprobó.

**Evidencia**
- Constancia anual del art. 81 como hoja imprimible (el repo no usa librería de PDF; el PDF sale del navegador,
  igual que el certificado de Ley Silla), con acuse del empleado en el portal (mismo patrón que `Disclosure`).
- Expediente por empleado: periodos, solicitudes con quién aprobó y cuándo, constancias.
- Cálculo de días proporcionales al dar de baja, solo en días. **Ningún importe en dinero** en todo el módulo:
  la prima se guarda como porcentaje de la política, sin calcular cuánto es.
- Conservación: toda la relación más un año.

**Panel de RH**
- Resumen: quién está fuera hoy y esta semana, solicitudes pendientes, periodos por vencer la ventana de 6 meses,
  días prescribibles, pasivo total en días. El detalle siempre paginado.
- Calendario del equipo por área o centro.

**Hacia afuera**
- Contrato `Leave\Contracts\Absences` (quién está ausente en un rango): lo consumen Asistencia (un día de
  vacaciones no es falta) y el organigrama.

### No metas (v1)
Importes en dinero y nómina, timbrado, regímenes especiales (buques, tripulaciones, plataformas), menores de edad
(no guardamos fecha de nacimiento), compra y venta de días, incapacidades del IMSS.

## 6. Clima y pulso (módulo `Engagement`, dos herramientas)

### Investigación (resumen)
- **Clima** es descriptivo y grupal (qué se vive); **compromiso** es el resultado; **NOM-035** mide exposición a
  lo que daña. El clima complementa la evidencia del numeral 8.1, **no sustituye** a la Guía III. No se repiten
  ítems de la guía; violencia, jornada y carga se quedan en NOM-035.
- **Licencias**: Gallup Q12, UWES, JSS, GPTW y Litwin-Stringer no se pueden usar en un SaaS comercial sin
  permiso. "NPS/eNPS" son marcas de Bain, NICE y Reichheld. EMCO (UNAM) está validada en México pero sin licencia
  publicada. Los constructos no tienen derechos; la redacción sí. Culture Amp, Peakon y Qualtrics usan banco propio.
- **Consenso de drivers** en la investigación (12; el banco v1 quedó en 10 e índice de 3 ítems tras la revisión): liderazgo directo, reconocimiento, crecimiento, carga y equilibrio, autonomía,
  propósito y alineación, relaciones, compensación percibida, bienestar, comunicación y confianza en la dirección,
  recursos, inclusión y voz. **Índice de compromiso** (4 ítems fijos): orgullo, recomendación, permanencia, motivación.
- **La fatiga la causa la falta de acción**, no la frecuencia (McKinsey; Culture Amp).
- **Estadística**: con n=5 el margen de error es ±44 puntos; con n=30, ±18. El índice de recomendación es aún más
  inestable. El análisis de impacto necesita n≥25–50.
- **Ataque de diferenciación**: restar subgrupos u olas para deducir a una persona. Viva Glint usa mínimo 5 para
  puntajes y 10 para comentarios, con supresión complementaria.
- **Privacidad**: hay nueva LFPDPPP (DOF 20-03-2025). Las respuestas anónimas no son dato personal; la invitación
  y un comentario libre sí pueden serlo. *El aviso de privacidad lo debe revisar un abogado.*

### Diseño

**Banco de ítems propio** (decisión derivada de las licencias)
- 41 ítems cerrados y 4 abiertas en es-MX (banco v1, tras revisión externa): 5 indicadores de resultado, 10
  drivers de empresa y 8 sobre el jefe. Likert de acuerdo de 5 puntos; toda pregunta se puede omitir, y el mínimo
  por grupo se aplica a las respuestas de cada ítem. Definido en código y versionado, como los instrumentos de las normas.
- El índice de compromiso son 3 ítems; la intención de permanencia se reporta aparte.
- No repite preguntas de la Guía III (el banco lista cuáles se quitaron por eso). Se lanza como herramienta de
  diagnóstico; "validado" solo cuando exista la evidencia que describe el banco.
- La métrica se llama **"índice de recomendación"**; no se usa "eNPS" ni "Net Promoter" en la UI ni en ventas.
- Sin constructor de cuestionarios (el spec de posicionamiento ya lo descartó como YAGNI).

**Motor: tablas propias, mismas garantías** **(mi propuesta)**
- No se fuerza el motor de NOM-035 (una campaña por centro, una escala, redacción normativa): generalizarlo pone
  en riesgo lo que ya cumple la norma. `Engagement` tiene sus encuestas por empresa, sus participantes con enlace
  de un solo uso y su tabla de respuestas con las mismas reglas: UUID v4, ninguna llave a empleado ni invitación,
  solo inserción por trigger, sin hora.
- Todo agregado pasa por `Results\Contracts\SmallGroups`. No hay segundo servicio de mínimos.
- En el plan se revisa qué de enlaces e invitaciones conviene extraer a algo común en vez de copiar.

**Cortes bajo anonimato real**
- Al invitar se copia a la invitación una instantánea gruesa: centro, área, antigüedad en 4 rangos y jefe solo si
  su equipo tiene 5 o más. La respuesta guarda esos valores, no la llave.
- Antes de lanzar, toda combinación con menos de 5 invitados se generaliza hacia arriba (área → centro → empresa).
- Al reportar: mínimo 5 para puntajes y **10 para comentarios**; un solo atributo de corte a la vez; supresión
  complementaria; nunca el conteo exacto de un grupo oculto. Comentarios sin atributos.
- Índice de recomendación y "sube/baja" solo con n≥30; debajo se muestra el dato sin flecha.

**Clima** (anual o semestral)
- Banco completo, 10–12 minutos. Resultados: participación, índice de compromiso, % favorable por driver con
  distribución, 3 fortalezas y 3 oportunidades por grupo, prioridades (impacto calculado a nivel empresa,
  mostrado como alta/media, sin coeficientes), comparación con la ola anterior y con el promedio de la empresa.

**Retroalimentación al jefe** (decisión de Victor, estilo Glint)
- La encuesta tiene dos bloques: preguntas sobre la empresa y preguntas sobre "su jefe directo" (8 ítems + 2
  abiertas, ver el banco). Cada persona contesta sobre el jefe que tenía al enviarse la invitación.
- El jefe ve en su portal sus resultados agregados y anónimos: % favorable por ítem, comparado con el promedio de
  la empresa, y sus 2 fortalezas y 2 oportunidades. Nunca quién contestó qué, ni cuántos faltaron por contestar.
- Reglas de anonimato **(mi propuesta)**:
  - Ve resultados solo con **5 o más respuestas** de sus reportes directos. Con menos, se usa su línea completa
    (directos e indirectos) si llega a 5; si tampoco, no ve nada y sus respuestas cuentan en el nivel de arriba.
  - Se le muestra un solo grupo (directos o línea completa), nunca los dos, para que no pueda restar.
  - Los grupos de jefes **no se enciman**: cada persona cuenta en un solo grupo. Si un jefe intermedio ya tiene
    grupo propio visible, su gente no vuelve a contar en el de su director; de otro modo el director podría
    restar. (Lo detectó el plan de clima; ver su tarea de grupos de jefe.)
  - **El jefe no recibe los textos originales** de los comentarios sobre él: un comentario puede delatar a su
    autor por lo que cuenta, sin importar cuántas respuestas haya. Ve un **resumen hecho con IA** (decisión de
    Victor, 2026-11-09), que entra en v1. Reglas del resumen:
    - Solo con **10 o más comentarios**; con menos no hay resumen y los comentarios cuentan en el nivel de arriba.
    - Entrega temas ("qué valoran", "qué piden cambiar"), cada uno con cuántos comentarios lo respaldan. Parafrasea;
      nunca cita textual, ni nombres, fechas o sucesos que señalen a alguien.
    - Se genera **una sola vez al cerrar la encuesta** y se guarda. El jefe no puede preguntarle a Humi sobre los
      comentarios ni regenerarlo: preguntar varias veces es una forma de sacar el texto original.
    - Los comentarios se pasan al modelo como datos, nunca como instrucciones, y sin ningún atributo del autor.
    - Un comentario que describa acoso o violencia no se resume para el jefe: RH recibe un aviso de que hubo
      menciones de ese tipo, con la recomendación de difundir el canal de quejas.
    - Usa la infraestructura de IA que ya tiene Humi (sin dependencia nueva). El aviso de privacidad de la encuesta
      declara que los comentarios los procesa un proveedor de IA.
    - RH (owner, hr) sí puede leer los comentarios originales con 10 o más; `viewer` ve el resumen.
    - Pruebas: el resumen no se genera bajo el umbral, las props del jefe no contienen ningún comentario original
      y ningún comentario llega a los logs.
  - La pantalla de la encuesta no promete "nadie sabrá quién lo escribió"; dice lo que el sistema garantiza: no
    guarda quién contestó y solo muestra resultados de grupo.
- RH (owner, hr, viewer) ve los resultados por jefe con las mismas reglas.
- Una prueba afirma que las props de la página del jefe no contienen respuestas individuales.

**Pulso** (mensual o trimestral, programado)
- Máximo 11 ítems: 5 fijos (3 del índice, recomendación y el de acciones vistas) + hasta 6 rotativos en una de
  tres modalidades por pulso: seguimiento de los drivers priorizados, recorrido de anclas, o jefe (trimestral).
  La tendencia solo se calcula con ítems idénticos.
- No hay modo siempre abierto: con anonimato real no se controlan duplicados.

**Cerrar el ciclo** (lo que lo hace útil y no un tablero de vanidad)
- 2–3 acciones sugeridas por driver; hasta 3 compromisos por grupo con responsable, fecha y estado.
- Comunicado "Nos dijeron / Haremos" publicado en el portal del empleado (también cubre el espíritu del 7.8).
- La siguiente encuesta muestra a RH qué compromisos quedaron abiertos antes de lanzar.

### No metas (v1)
Preguntas personalizadas (el banco lo definimos nosotros), benchmark entre empresas, preguntarle a Humi
en el chat sobre los comentarios (el resumen con IA sí entra en v1, generado una vez; ver "Retroalimentación al jefe").

### Anti-metas
- Que un cruce de filtros u olas permita deducir la respuesta de una persona.
- Encuestar sin que quede ninguna acción registrada.

## 7. Orden de construcción (un PR por trabajo)

1. **Línea de reporte + organigrama** (`Organization`). Es el cimiento de los demás y el más chico.
2. **Vacaciones** (`Leave`), con `docs/normas/vacaciones/`. Conecta con Asistencia y el organigrama.
3. **Clima** (`Engagement`): banco, motor, cortes, resultados y compromisos.
4. **Pulso**: programación, rotación y tendencia sobre lo anterior.

Cada PR trae permisos, navegación, pantallas según `docs/design-system/pantallas/` (claro, oscuro y 375 px),
pruebas de aislamiento de empresa, y en 3 y 4 la prueba de que ninguna prop de Inertia ni línea de log contiene
respuestas individuales. Cuando Billing llegue a `main`, cada herramienta se agrega a su catálogo.

## 8. Decisiones de Victor (2026-11-09)

1. **El jefe aprueba vacaciones desde su portal.** RH solo es respaldo cuando el jefe no lo hace.
2. **La semana laboral la define la política de vacaciones**, porque no es igual para todos: cada política trae
   la suya y se asigna por grupo o nivel.
3. **`Leave` cubre vacaciones y permisos o licencias específicas** (un permiso especial, por ejemplo). Nada más.
4. **Sin importes en dinero.** Solo control: quién pide, quién aprueba, cuántos días y si los tenía disponibles.
5. **Organigrama ligero y sin vacantes**: quien no está contratado no aparece. Jerarquía de N niveles.
6. **Clima y pulso en un módulo `Engagement`** con dos herramientas.
7. **El banco de preguntas lo definimos nosotros**, con objetivos específicos; borrador en
   `2026-11-09-engagement-item-bank.md`, pendiente de su revisión.
8. **Sin preguntas personalizadas** de inicio.
9. **El jefe sí ve su retroalimentación**, anónima y agregada (ver "Retroalimentación al jefe").
10. **`viewer` ve saldos de vacaciones y resultados de clima.**

11. **Solo resúmenes con IA para el jefe**, nunca los textos originales de los comentarios.
12. **Lo demás lo decide Claude** (delegado por Victor): valen los tres umbrales propuestos (3 días hábiles, 5
    respuestas, 10 comentarios) y la propuesta de cada plan en sus "Preguntas abiertas". Resumen: organigrama
    oculto para sindicatos; `viewer` ve nombre y puesto; política «Ley» de lunes a viernes por defecto, a
    confirmar al activar; solicitud sin saldo solo se aprueba si la política permite adelantar; sin periodos
    anteriores a la fecha de arranque; antigüedad en <1, 1–3, 3–6 y 6+ años; RH ve cuántos contestaron, no
    quiénes; el pulso se salta mientras haya un clima abierto.

Fuera del código y aún pendiente: revisión del aviso de privacidad por un abogado.

## 8 bis. Nombres compartidos entre los cuatro planes

Cada plan usa estos nombres tal cual; el plan dueño los construye y los demás solo los consumen.

| Qué | Dueño | Nombre |
|---|---|---|
| Línea de reporte (`directReportIds` y `subtreeIds` devuelven solo personas activas; `subtreeIds` atraviesa a un jefe intermedio inactivo) | plan 1 | `App\Modules\Organization\Contracts\ReportingLines`: `managerOf(string $employeeId): ?string`, `chainOf(string $employeeId): list<string>` (del jefe directo hacia arriba, incluye inactivos), `directReportIds(string $employeeId): list<string>`, `subtreeIds(string $employeeId): list<string>` (sin incluirse) |
| Antigüedad | plan 1 | `App\Modules\Organization\Contracts\Seniority`: `startDatesFor(list<string> $employeeIds): array<string, CarbonImmutable>` (usa `seniority_on` y si no `hired_on`; omite a quien no tiene ninguna) |
| Ausencias | plan 2 | `App\Modules\Leave\Contracts\Absences`: `absentOn(CarbonImmutable $day, list<string> $employeeIds): list<string>`, `between(CarbonImmutable $from, CarbonImmutable $to, list<string> $employeeIds): list<AbsenceData>` |
| Insignia "ausente hoy" en el organigrama y día de vacaciones en Asistencia | plan 2 | lo conecta el plan de vacaciones, no el del organigrama |
| Módulo de encuestas | plan 3 | `App\Modules\Engagement`; el motor acepta `SurveyKind::Climate` y `SurveyKind::Pulse` desde el plan 3; el plan 4 solo agrega programación, rotación y tendencia |
| Portal del empleado | existente | las secciones nuevas cuelgan de `portal/{token}` igual que Seating y Disclosure |

Permisos (sembrados por migración, como los de asistencia): `view-org-chart`/`manage-org-chart`,
`view-vacations`/`manage-vacations`, `view-climate`/`manage-climate`, `view-pulse`/`manage-pulse`. `view-*` para
owner, hr y viewer; `manage-*` para owner y hr. Prefijos de ruta, con la convención del repo: `tenant.org-chart.*`, `tenant.vacations.*`,
`tenant.climate.*`, `tenant.pulse.*` en el panel y `portal.*` en el portal (la parte del medio es la clave que usará Billing).

Configuración: `leave.escalation_business_days` = 3, `engagement.min_comments` = 10, `engagement.min_trend` = 30;
el mínimo de 5 sigue siendo `results.min_group`.

## 9. Fuentes principales

- LFT (Cámara de Diputados): https://www.diputados.gob.mx/LeyesBiblio/pdf/LFT.pdf
- Decreto de vacaciones, DOF 27-12-2022: https://dof.gob.mx/nota_detalle.php?codigo=5675889&fecha=27/12/2022
- Prescripción de vacaciones y prima: https://idconline.mx/laboral/2017/11/29/prescripcion-de-la-prima-vacacional
- BambooHR, organigrama y "Reports To": https://bamboohr.screenstepslive.com/m/53907/l/1994529-faq-company-directory-and-org-chart
- Personio, vacantes en el organigrama: https://support.personio.de/hc/en-us/articles/18704769097629
- Bain, tramos y niveles: https://www.bain.com/insights/streamlining-spans-and-layers/
- PostgreSQL, `WITH RECURSIVE` y `CYCLE`: https://www.postgresql.org/docs/current/queries-with.html
- Schneider, Ehrhart y Macey (2013), clima organizacional: https://www.annualreviews.org/doi/10.1146/annurev-psych-113011-143809
- Gallup, términos de uso del Q12: https://www.gallup.com/shared/242291/product-terms-use.aspx
- Bain, marcas NPS: https://nps.bain.com/resources/trademarks-and-licensing
- UWES, condiciones de uso: https://www.wilmarschaufeli.nl/publications/Schaufeli/Tests/UWES_ES_9.pdf
- Culture Amp, ciencia del índice: https://support.cultureamp.com/en/articles/7048329-the-science-behind-our-engagement-surveys
- Culture Amp, confidencialidad en reportes: https://support.cultureamp.com/en/articles/7048386-confidentiality-protections-in-reporting
- Viva Glint, umbrales y supresión: https://learn.microsoft.com/en-us/viva/glint/setup/manage-confidentiality-thresholds
- McKinsey, fatiga de encuestas: https://www.mckinsey.com/capabilities/people-and-organization/our-insights/the-organization-blog/survey-fatigue-blame-the-leader-not-the-question
- Nueva LFPDPPP (2025): https://www.garrigues.com/es_ES/noticia/mexico-nueva-ley-federal-proteccion-datos-personales-posesion-particulares-introduce

Lo marcado *no verificado* viene de fuentes secundarias y se confirma contra la fuente primaria al escribir
`docs/normas/vacaciones/`.
