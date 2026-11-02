# Ley Silla: expediente de asientos y descansos, evaluación por persona y encuesta confidencial

Fecha: 2026-11-02. Estado: propuesta (pendiente de aprobación). Es la tercera y última herramienta del MVP; el plan de
implementación se escribe al aprobarse este documento. El diseño de la calculadora de contrato colectivo ya está en `main`
(#63) y su plan espera a que Ley Silla esté en `main` (su decisión 13).

Anexos obligatorios:

- [`docs/normas/ley-silla/criterios.md`](../../normas/ley-silla/criterios.md) (versión 1): **cómo y por qué** se aplica
  cada obligación, con lo que es decisión nuestra rotulado. Manda sobre este documento; aquí se cita como C-nn.
- [`docs/normas/ley-silla/texto-de-referencia.md`](../../normas/ley-silla/texto-de-referencia.md): texto literal del
  decreto, de las Disposiciones de la STPS (con la Figura 1 y las siete tablas de puntaje) y del boletín 59/25.
- [`docs/normas/ley-silla/fuentes/`](../../normas/ley-silla/fuentes/README.md): copias de las fuentes oficiales con su
  huella, bajadas el 2026-11-02.

Este documento dice **qué se construye**. Los anexos dicen qué pide la ley y cómo se interpreta.

## Intención

Con esta herramienta, una empresa puede demostrar ante la STPS o ante la autoridad laboral de su estado que cumple la
«Ley Silla»: asientos con respaldo suficientes y a disposición de todas las personas, un análisis de riesgo por cada
persona que trabaja de pie, medidas y pausas documentadas, y un reglamento interior que lo recoge. La STPS anunció la
verificación en su Programa Anual de Inspección 2026 y ya es todo exigible (C-04, C-16).

A diferencia de la NOM-035, no es un cuestionario calificado, y a diferencia de la NOM-037, no es un expediente por
persona teletrabajadora. Es un **expediente por centro de trabajo** con tres piezas:

1. el **inventario** de puestos de pie y de asientos, con la decisión de la Figura 1 por puesto;
2. la **evaluación individual** de las siete condicionantes (7 a 21 puntos), que hace la Comisión de Seguridad e Higiene;
3. la **evidencia**: medidas, pausas acordadas, reglamento interior, acuses y la encuesta confidencial.

Principios, los mismos que en NOM-037:

- **Reutilizar el catálogo central.** Las personas son los empleados de `Organization`; los centros, los de `Tenancy`.
- **No meter la Ley Silla en lo compartido.** Todo lo de la ley vive en un módulo nuevo, `Seating`.
- **Precisión.** Los textos y tablas del DOF están transcritos literalmente y las pruebas se escriben contra ellos. Lo que
  la fuente calla se rotula «criterio de Humetría» y nunca se presenta como DOF.

## Decisiones del usuario (2026-11-02)

Respondió «usa los defaults» a las ocho preguntas del cuestionario. Quedaron así, con lo que cambió al leer el DOF:

1. **Alcance:** la reforma a la LFT **y** las Disposiciones de la STPS del 17-07-2025.
2. **Unidad del análisis:** inventario por puesto con las personas asignadas, y evaluación individual. **Cambio por la
   investigación:** el default era evaluar solo donde el puesto saliera en riesgo, pero las Disposiciones exigen
   determinar el nivel de riesgo «de cada persona trabajadora que labora en bipedestación» y registrar a cada una
   (Quinta B, C VII y D). Se resuelve con una evaluación **por persona que hereda las condiciones del puesto**, de modo
   que contestarla sea confirmar, no capturar de cero (C-07). Ver «Decisiones del diseñador», punto 2.
3. **Encuesta confidencial:** sobre Campaigns y Responses, con enlace de un solo uso y mínimo de 5 por grupo; unas 10
   preguntas (C-19).
4. **Comisión de Seguridad e Higiene:** se reutiliza el rol `committee` de NOM-037, con sus centros asignados, para
   evaluar y firmar. Ve también la Ley Silla de sus centros.
5. **Texto del reglamento interior:** cláusulas modelo editables, con aviso de que no son asesoría legal. **Cambio por la
   investigación:** el RIT lo formula una comisión mixta y surte efectos al depositarse (LFT 424 y 425), así que el
   producto lleva también su firma, depósito y reparto (C-13).
6. **Portal de la persona:** acuse de que le informaron de los riesgos y reporte de un asiento dañado.
7. **Entregables:** expediente imprimible por centro y plan de acción con evidencia; sin borrado de evidencia (C-17).
8. **Fuentes y rama:** descargadas con huella (`docs/normas/ley-silla/fuentes/`); este spec va en la rama
   `claude/ley-silla-design`, en un solo documento, sin código ni plan hasta aprobarse.

## Objetivos

1. RH declara por centro su **sector** (servicios, comercio, análogo o industrial) y registra sus **puestos en
   bipedestación**. Por puesto responde la Figura 1 y la herramienta le asigna A (asiento en el puesto), B (cerca) o C
   (dentro del centro) (C-03, C-05, C-06).
2. RH liga a las personas del padrón a su puesto y registra el **inventario de asientos**: tipo, ubicación, las nueve
   características, estado, señalización y foto (C-08, C-11).
3. Un integrante de la Comisión hace la **evaluación individual** de cada persona de pie: 11 datos del registro, siete
   condicionantes y nivel Bajo, Medio o Alto, calculado por la plataforma (C-07).
4. RH lleva el **plan de acción**: medidas preventivas con responsable, fecha y evidencia, y los acuerdos de pausas con las
   personas; la Comisión propone medidas al firmar una evaluación (C-10, C-14).
5. RH lleva el **reglamento interior**: borrador de cláusulas, firma de la comisión mixta, depósito y reparto (C-13).
6. Cada persona recibe un **enlace personal** donde confirma que le informaron de los riesgos, contesta lo que es suyo
   (malestares y datos de salud, de forma voluntaria) y reporta un asiento dañado (C-11, C-12, C-18).
7. RH lanza la **encuesta confidencial** del centro y ve resultados agregados con mínimo de respondentes (C-19).
8. RH ve un **tablero por centro** con el estado de cada requisito, recibe avisos de lo que vence o falta, e imprime el
   **expediente** para la inspección.

## No objetivos

- Evaluaciones de la NOM-030 o exámenes médicos, ergonomía industrial más allá de las siete condicionantes (p. ej. ROSA,
  RULA, REBA), el diagnóstico y programa de seguridad y salud de la NOM-030, el acta de constitución y el programa anual
  de recorridos de la NOM-019, el depósito del reglamento ante el Centro Federal de Conciliación y Registro Laboral,
  comprar o dar mantenimiento a los asientos, y asesoría legal.
- SMS y WhatsApp: solo correo, como en las demás herramientas.
- Firma electrónica avanzada: el acuse es una confirmación dentro del portal, con fecha y hora.
- Decidir qué fracción de multa aplicará la autoridad (C-15) o cuál autoridad inspeccionará (C-16).
- Un estándar de «asientos por persona»: la ley no lo fija; la razón que muestra la plataforma es una ayuda (C-01).
- Que la persona edite sus datos del padrón desde el portal.

## Anti-objetivos (fracasaría aunque funcione)

- **Calificar de «apto» o «aprobado» a un centro, a un puesto o a una persona.** La plataforma calcula el nivel de riesgo
  de las tablas del DOF y sugiere medidas rotuladas como sugerencia; no certifica cumplimiento.
- **Presentar como texto del DOF** una pregunta de la encuesta, una medida sugerida o una cláusula del reglamento.
- **Que la negativa de una persona a contestar un dato de salud bloquee el cumplimiento del patrón** o, al revés, que se
  presione a la persona (C-18).
- **Reidentificar a quien contestó la encuesta**: con un filtro, con una resta de subtotales o con el orden en que llegan
  las respuestas (CLAUDE.md, «Privacidad»).
- **Borrar evidencia** (C-17): no hay botón de borrar para evaluaciones, acuses, medidas, acuerdos, documentos ni reportes.
- **Exponer datos de salud o nombres** a `viewer`, a un enlace público, a logs o a la bitácora.
- **Un segundo catálogo de personas, de centros o de comisiones.**
- **Afirmar una multa o un inspector** que la fuente no sostiene (C-15, C-16).

## Reglas de la ley que el sistema hace cumplir

| Regla | Fuente | Cómo se cumple |
|---|---|---|
| Asientos suficientes a disposición de todas las personas | C-01 | Inventario contra personas por turno; aviso de faltante (ayuda, no estándar) |
| Áreas designadas para los descansos periódicos, en las mismas instalaciones | C-01 | Cada asiento de tipo «descanso» exige una ubicación designada |
| No impedir sentarse | C-02 | Pregunta de la encuesta; resultado agregado por centro |
| Sector declarado por centro; industrial solo si la naturaleza lo permite | C-03 | Enum `sector`; razón por escrito en industrial |
| Figura 1 por puesto, con resultado A, B o C | C-06 | Preguntas del puesto; resultado calculado, no capturado |
| Evaluación por persona, siete condicionantes de 1 a 3 | C-07 | `check (1..3)`; total y nivel calculados; 7 / 8-14 / 15-21 probados en sus bordes |
| Evaluador integrante de la Comisión | C-07, C-09 | Solo un integrante asignado al centro puede firmar |
| Registro de 11 datos | C-07 | Campos del registro; no se firma incompleto |
| Tipo de asiento y nueve características | C-08 | Enum y casillas |
| Medidas preventivas y pausas | C-10 | Plan de acción con estado y evidencia |
| Informar, señalizar, canalizar | C-11 | Acuse por persona, casilla y foto, registro de canalización |
| El reglamento incluye factores de riesgo, pausas y ubicación | C-13 | Tres partes obligatorias en el borrador; estado de firma, depósito y reparto |
| Pausas acordadas con las personas | C-14 | Acuerdo con su evidencia |
| Evidencia sin borrado | C-17 | Sin botón de borrar; archivos en disco privado |

## Módulo `Seating`

`app/Modules/Seating/` sigue la estructura de CLAUDE.md. **API pública:**

- `Contracts/SeatingHeadcount`: cuántas personas de un centro trabajan de pie. Opcional: se agrega solo si una pantalla
  de otro módulo lo usa.
- `Events/RiskAssessmentSigned` (employee_id, work_center_id, level). Por ahora nadie lo escucha.

`Seating` lee de otros módulos solo por contratos: `Organization\Contracts\Roster` (personas y áreas),
`Tenancy\Contracts\Companies` y `WorkCenters` (empresa y centros, incluido el domicilio para el registro) y lo que haga
falta de los contratos existentes. Un dato que falte se agrega al contrato del módulo dueño, no se hace un join. La
Comisión (rol `committee` y asignaciones a centros) hoy la guarda `Telework`: **no se importa de `Telework`**; el
constructor decide si la asignación sube a `Tenancy` (lo más limpio, porque la Comisión es de la empresa y no de una
herramienta) o si `Seating` se alimenta de un contrato público de `Telework`. En ningún caso se duplica el catálogo.

### Instrumento

En código, versionado como las demás: `seating.assessment`, versión `dof-2025-07-17`:

- las **siete condicionantes** con sus opciones y puntos, literales de las tablas del DOF, y los tres niveles de riesgo.
  Una prueba compara cada texto contra `texto-de-referencia.md`;
- el **flujograma de la Figura 1** como cinco preguntas y tres resultados;
- las **cinco medidas** de la Quinta G y los **cuatro tipos de asiento** y **nueve características** de E y F;
- las **preguntas de Humetría** de la encuesta (`seating.survey`, códigos `SS-nn`), dicotómicas o de escala corta, de
  «usted», sin parecer del DOF y con su requisito (C-19). Ninguna suma al nivel de riesgo.

Si el DOF cambia las tablas, se agrega una versión nueva; las evaluaciones guardadas conservan la suya y nunca se
recalculan con otra edición.

## Modelo de datos

Todas las tablas de negocio llevan `tenant_id`, aplican `BelongsToCurrentTenant` y traen su prueba de aislamiento (la
empresa A no lee ni modifica lo de B, por URL 404). Los nombres exactos los fija el constructor; la estructura es:

- **`seating_profiles`**: una fila por centro. `sector` (`services|commerce|analogous|industrial`), fecha de la
  declaración y quién la hizo, `no_standing_positions` (centro sin personas de pie, con fecha) y nota.
- **`standing_positions`** (puestos): centro, nombre, tareas que se hacen de pie, tipo de bipedestación
  (`static|dynamic|prolonged|combined`), minutos continuos de pie, respuestas de la Figura 1 (`can_work_seated`,
  `has_space_for_seat`, `needs_to_be_nearby`) y su resultado (`placement`: `at_workstation|nearby|within_center`),
  `max_people_at_once` (personas que ocupan el puesto a la vez, base de la razón asientos/personas), descripción de
  condiciones del área (los seis agentes de la Quinta C III), herramientas, carga física, nivel de atención, y las
  condicionantes a, b, c, e y g como valores por omisión del puesto. En industrial: `nature_allows` y su razón, que fija
  RH. Un `check` obliga a que el resultado salga de las respuestas.
  - **Resultado de la Figura 1 (mi llamada):** `figure_one_applies` (la bipedestación es prolongada) y `placement`. Las
    preguntas 3 a 5 se contestan siempre, también en un puesto no prolongado, como criterio de Humetría para decidir dónde
    va el asiento (C-06); `placement` solo queda vacío si el puesto es industrial con `nature_allows = false`.
- **`position_assignments`**: empleado ↔ puesto, con vigencia (una persona está en un puesto a la vez; la historia se
  conserva).
- **`seats`** (inventario): centro, puesto o ubicación, tipo (`high_stool|high_chair|ergonomic_chair|footrest`), uso
  (`workstation|rest_area`), cantidad, las nueve características (booleanos), estado (`good|needs_repair|out_of_service`),
  `signposted` (señalizado) y foto en disco privado.
- **`risk_assessments`** (evaluación por persona): empleado, puesto, centro, fecha, evaluador (usuario asignado al centro con
  `assess-seating`), los 11 datos de la Quinta C, las siete puntuaciones (1 a 3 con `check`), total y nivel (calculados),
  versión del instrumento, `person_provided_health_data` (bool), `recorded_in_document_id` (acta de la Comisión donde se
  asentó, Quinta B), `reassessment_due` (marca), estado (`draft|signed`) y fecha de firma. Un registro firmado no se
  edita: se evalúa de nuevo y la anterior queda en el historial.
- **`person_inputs`** (lo que la persona contesta en su portal; **identificado y de salud**): empleado, centro, calzado
  (condicionante d), malestares (condicionante f), nota opcional, aviso aceptado con fecha y hora. Solo lo leen owner, hr y
  la Comisión de su centro, y solo para evaluar; nunca llega a `viewer`, a logs ni a la bitácora. Una respuesta nueva
  después de una evaluación firmada no la invalida: la marca `reassessment_due` (ver «Planificador diario»).
- **`preventive_measures`** (plan de acción): evaluación o puesto, tipo (`workstation_design|task_rotation|ergonomic_footwear|
  cushioned_floor|active_breaks|other`), estado, responsable, fecha compromiso y evidencia.
- **`pause_agreements`**: puesto o grupo de puestos, pausas por turno, minutos, fecha, cómo se acordó y evidencia.
- **`seating_documents`**: archivos de RH (RIT vigente, acta de la Comisión con el análisis, diagnóstico y programa de SST
  donde se integró la Figura 1, otros), con tipo, fecha y los incisos que cubren.
- **`internal_regulations`** (RIT): centro, borrador de las tres partes (factores de riesgo, pausas, ubicación y
  disponibilidad) más la norma de uso del asiento (423 V), fechas de firma, depósito y reparto, folio del depósito y
  archivo. Estados `draft|signed|deposited|distributed`.
- **`risk_acknowledgments`**: empleado, la evaluación firmada y sus medidas vigentes cuando se informó (`assessment_id`),
  fecha y hora de la confirmación. Lo que se versiona es **la evaluación y las medidas de esa persona**, no las del centro.
- **`seat_reports`**: reporte de un asiento dañado (de la persona, por su portal): asiento o puesto, descripción, estado
  (`open|resolved`) y resolución que registra RH.
- **`medical_referrals`**: empleado, fecha, nota de RH (sin detalle clínico). Solo owner, hr y la Comisión de sus centros.
- **Encuesta**: se aplica con Campaigns y Responses (ver «Encuesta confidencial»). Las respuestas viven en las tablas
  existentes de Responses, **sin ninguna llave** hacia empleado, invitación ni token (CLAUDE.md, «Privacidad»).

## Roles y permisos (Spatie, como los actuales)

Permisos nuevos, por migración como `manage-campaigns`:

| Permiso | owner | hr | viewer | committee |
|---|---|---|---|---|
| `view-seating`: tablero, puestos, inventario, plan, documentos y expediente imprimible | ✓ | ✓ | solo el tablero con conteos, sin nombres ni salud y sin expediente | ✓ (sus centros) |
| `manage-seating`: sector, puestos, asignaciones, inventario, medidas, acuerdos, reglamento, documentos, canalizaciones | ✓ | ✓ | | |
| `assess-seating`: crear y firmar evaluaciones y proponer medidas desde ellas | si está asignado | si está asignado | | ✓ (sus centros) |

- **Firmar** exige el permiso y estar asignado al centro. El patrón o su representante forma parte de la Comisión
  (NOM-019 7.1), así que un owner o hr asignado también evalúa.
- **Qué ve la Comisión:** puestos, inventario, evaluaciones y medidas de sus centros, con nombre y los datos de la
  Quinta C porque los necesita para evaluar. No ve resultados de la encuesta por grupo pequeño ni nada fuera de Ley Silla
  y NOM-037 de sus centros; las pruebas recorren las demás rutas y afirman 403 o 404.
- **Resultados de la encuesta:** solo con `manage-seating` (owner y hr). La Comisión y `viewer` no los ven.
- **Cuentas de sindicato:** las cuentas de tipo sindicato y el registro de herramientas de cumplimiento los define el plan de
  la calculadora de contrato colectivo (su decisión 13), que llega después. Esta entrega no los crea; deja `Seating`
  registrado donde hoy se registran las herramientas (el menú y las rutas), y esa prueba de 404 vive en el plan de la
  calculadora.

## Flujos

### Alta del centro y de sus puestos (RH)

RH abre Ley Silla en un centro, declara el sector y crea puestos. Por puesto responde la Figura 1 y ve el resultado A, B o
C con la nota de C-06 si no es prolongada. Liga personas del padrón al puesto (alta individual o por filtro de área).
Si no hay personas de pie, marca «sin puestos en bipedestación» con fecha.

### Evaluación (Comisión)

Un integrante asignado abre la lista de personas del centro sin evaluación vigente. Por persona ve el registro con las
condicionantes del puesto prellenadas, confirma o corrige cada una, anota los datos de la Quinta C y firma. Si la persona
ya contestó su calzado y sus malestares en el portal (`person_inputs`, C-18) aparecen prellenados; si no, la d y la f se
capturan o la f se marca «sin información» y vale 1. La plataforma calcula total y nivel, sugiere medidas por nivel y abre el plan de acción.

### Portal de la persona (público, con token)

Un enlace personal por correo, sin cuenta. **Se manda solo cuando se firma la evaluación de la persona** y RH puede
reenviarlo o mandarlo por lotes (mi llamada). Ahí la persona confirma que le informaron de los riesgos y las medidas de su
evaluación (con fecha y hora; un cambio de medidas no pide otra confirmación, una evaluación nueva sí), contesta
voluntariamente calzado y malestares con un aviso, y reporta un asiento dañado. Un
reporte llega a la Comisión y a RH como pendiente. El constructor decide si extrae el portal personal de `Telework` a un
lugar compartido o hace uno propio de `Seating`; en ningún caso importa internos de `Telework`.

### Qué cuenta como cumplido (mi llamada)

El tablero muestra tres estados por requisito: **Cumple**, **Con aviso** (cumple, pero hay algo que revisar) y **Falta**. Ninguno
es un «apto»; son conteos de lo registrado. Un puesto industrial con `nature_allows = false` queda fuera de R2, R4 y R5,
su razón se lista en el expediente y R1 lo cuenta como documentado.

| Req. | Cumple cuando | Con aviso | Falta |
|---|---|---|---|
| R1 | sector declarado y cada puesto con su Figura 1, o «sin puestos en bipedestación» con fecha | un puesto sin personas asignadas | sin sector o sin puestos |
| R2 | cada persona de un puesto elegible tiene una evaluación firmada vigente | alguna con `reassessment_due` | alguna sin evaluación |
| R3 | cada evaluación firmada tiene su acta (`recorded_in_document_id`) | | alguna sin acta |
| R4 | cada puesto elegible tiene asientos registrados | la razón asientos/`max_people_at_once` es menor a 1 o hay asientos en mal estado | un puesto sin asientos |
| R5 | cada puesto con resultado B o C tiene un asiento de descanso en un área designada | | falta |
| R6 | todo asiento fuera del puesto está señalizado | | alguno sin señalizar |
| R7 | cada evaluación Media o Alta tiene al menos una medida no cancelada | una medida vencida | alguna sin medida |
| R8 | cada persona con evaluación firmada tiene su confirmación | | alguna sin confirmar |
| R9 | cada persona con malestares en 3 (condicionante f) tiene una canalización o la nota de RH «evaluada, sin canalización» | | alguna sin registro |
| R10 | reglamento con las tres partes, firmado, depositado y repartido | firmado sin depósito o sin reparto | sin reglamento o sin alguna parte |
| R11 | cada puesto con resultado B o C tiene su acuerdo de pausas | | alguno sin acuerdo |
| R12 | existe el documento donde se integró el análisis | | no existe |
| R13 | la Comisión tiene al menos un integrante asignado | | ninguno |

### Plan de acción

Cada medida (estación de trabajo, alternar tareas, calzado, piso, pausas activas, otra) nace de una evaluación o de un
puesto, con responsable, fecha y estado. La Comisión puede proponerla al firmar; RH la administra. Los acuerdos de pausas se capturan por puesto o grupo con su evidencia.

### Reglamento interior

RH genera el borrador de las tres partes con los datos del centro (puestos, pausas acordadas, ubicación de los
asientos), lo edita y lo descarga. Después sube el RIT firmado por la comisión mixta y registra fecha de firma, fecha y
folio del depósito y constancia de reparto y publicación. El aviso «no es asesoría legal; lo revisa su abogado y lo aprueba
la comisión mixta» no se puede quitar. El sistema no deposita ni presenta nada ante la autoridad.

### Planificador diario

**Reevaluar no termina la evaluación vigente** (mi llamada): un cambio de puesto, de calzado, de piso, de pausas o un
malestar nuevo en `person_inputs` solo pone `reassessment_due`; la evaluación sigue vigente hasta que se firma otra o
pasan los 12 meses, y R2 sale «Con aviso».

Avisa a RH y a la Comisión de: personas de pie sin evaluación vigente; evaluaciones que cumplen un año (el plazo es
configurable, C-07) o con `reassessment_due`; reportes de asiento abiertos más de 15 días; puestos con resultado B o C sin
acuerdo de pausas (C-14); reglamento sin
depósito o sin las tres partes; asientos sin señalizar; centros sin integrantes de la Comisión asignados.

### Encuesta confidencial

Se lanza como una campaña del centro con el instrumento `seating.survey`, a **todas las personas activas del centro** (sin
muestra; `Population::All` ya existe) y no solo a las de un puesto de pie: la primera pregunta es «¿trabaja de pie?» y
quien contesta «no» responde «no aplica» al resto (mi llamada),
con invitación por correo y enlace de un solo uso. Los resultados pasan por el servicio único que aplica el mínimo de
respondentes por grupo (por defecto 5) y la supresión complementaria; cada filtro adicional reduce el
grupo; solo se filtra por el **área** que Responses ya guarda: no hay resultados por puesto ni una columna nueva de puesto en
Responses, que no puede depender de `Seating` y el mínimo se aplica después de filtrar. No hay hora exacta de cada respuesta. **Cambio estructural:** hoy
Campaigns tiene un `kind` y una `population` pensados para la NOM-035 (Guía I, II y III, con muestra). El constructor
generaliza lo que haga falta (un tipo nuevo de campaña sin muestra) **sin meter reglas de Ley Silla en Campaigns ni en
Responses**; si la generalización pide cambiar el comportamiento de NOM-035, se separa en un commit propio con sus pruebas.

## Pantallas

Todas siguen el rediseño de `docs/design-system/pantallas/` (CLAUDE.md, «Design system»). Referencias por pantalla:

| Pantalla | Quién | Referencia del rediseño |
|---|---|---|
| Ley Silla: lista de centros con su avance | owner, hr, viewer, committee | `NOM035` (centros a evaluar) |
| Tablero del centro: requisitos y pestañas Puestos, Personas, Inventario, Plan, Reglamento, Documentos, Encuesta | los de `view-seating` | `Eval-Activa` (ficha, medidor de arco, pestañas con conteo) |
| Puesto: Figura 1, condiciones y personas | owner, hr | `Empleados-Alta` (panel lateral) |
| Evaluación de una persona | committee y asignados | `Canalizacion` (revisión) |
| Inventario de asientos | owner, hr | `Empleados` (tabla paginada) |
| Plan de acción y acuerdos de pausas | owner, hr (committee: lectura y proponer medidas) | `Empleados` |
| Reglamento interior: borrador, firma y depósito | owner, hr | `Ajustes` + pasos de `Empleados-CSV` |
| Subir documento | owner, hr | `Empleados-CSV` |
| Encuesta: lanzamiento y resultados | owner, hr (pestaña oculta a los demás) | `Eval-Activa` y `Eval-Cerrada` |
| Expediente imprimible | owner, hr, committee | `results/report` |
| Portal de la persona | persona, sin cuenta | `Encuesta-Movil` |

- Toda lista va paginada en el servidor (puestos, personas, inventario, evaluaciones, medidas, reportes). El tablero es
  un **resumen con conteos**, con el detalle paginado a un clic (CLAUDE.md, «Listas»). Los centros y los puestos que
  crecen se buscan en el servidor (`serverSource`).
- Toda página de RH tiene su prueba de props: `viewer` no recibe nombres, datos de salud ni respuestas individuales. Toda
  página tiene su prueba de navegador con `visitPage()`.
- En el menú, `Cumplimiento → Ley Silla` (ya está previsto en `app-navigation.ts`).
- El riesgo se muestra siempre con `RiskLevel`; el constructor decide cómo encaja Bajo, Medio y Alto en ese enum sin
  inventar un segundo.

### Tablero: los requisitos

Un renglón por requisito, con lo que hay, con fecha, o «Falta»: R1 puestos y Figura 1; R2 evaluación de cada persona;
R3 registro asentado en el acta de la Comisión (documento); R4 asientos suficientes y adecuados; R5 áreas designadas para
descansos; R6 señalización; R7 medidas y pausas; R8 información a las personas; R9 canalizaciones; R10 reglamento con
las tres partes, firmado y depositado; R11 acuerdo de pausas; R12 análisis integrado al diagnóstico y programa; R13
Comisión con integrantes asignados. Cada renglón cita su criterio.

## Expediente para la inspección

Página imprimible desde el navegador, como el informe de NOM-037 y el 7.7 de NOM-035, con una sección por requisito:
lo que hay, con fecha, o «Falta». Incluye sector y declaración, puestos con su resultado de la Figura 1, inventario,
distribución de niveles de riesgo **sin nombres**, medidas, acuerdos de pausas, estado del reglamento (con fechas y folio),
documentos con sus incisos y avisos de lo faltante. Las evaluaciones por persona, las fotos, las canalizaciones y las
respuestas **no** van impresas: se listan con un enlace dentro de la aplicación, porque el expediente puede salir del
sistema. Una nota fija dice que la multa no es específica y su rango (C-15). Ver o imprimir va a la bitácora.

## Logs y bitácora

- Logs `seating.*` en pasado, solo con IDs y estados: `seating.profile_declared`, `seating.position_saved`,
  `seating.seat_registered`, `seating.assessment_signed`, `seating.measure_saved`, `seating.pause_agreement_saved`,
  `seating.regulation_status_changed`, `seating.acknowledged`, `seating.seat_reported`, `seating.referral_recorded`,
  `seating.survey_launched`. **Nunca** nombres con salud, puntajes por persona, notas, ni respuestas.
- A la bitácora: firma de una evaluación, cambio de estado del reglamento, canalización, lanzar y cerrar la encuesta, ver
  resultados o exportarlos, ver o imprimir el expediente, asignar o quitar a alguien de la Comisión.
- `RedactSensitiveData` agrega las llaves nuevas (`health_note`, `pain_level`, `referral_note`, `assessment_scores`…)
  con su prueba.

## Decisiones del diseñador

El usuario puede corregir cualquiera al leer:

1. **Módulo propio `Seating`**, sin reutilizar `Telework` ni meter nada en el kernel. La Comisión se comparte por
   contrato (ver «Módulo»).
2. **Evaluación por persona con herencia del puesto.** La alternativa de evaluar solo por puesto no cumple la Quinta D.
   Heredar las condicionantes a, b, c, e y g del puesto y dejar la d y la f a la persona reduce el trabajo sin cambiar el
   resultado individual. **Es mi interpretación;** la Disposición calla sobre si una evaluación colectiva basta (C-07).
3. **La Figura 1 decide dónde va el asiento, no a quién se le exige la ley** (C-06): un puesto de bipedestación no
   prolongada igual cuenta para el inventario y la evaluación.
4. **El nivel de riesgo no obliga ninguna medida** porque la Disposición no la liga a un nivel; la plataforma sugiere, no
   impone, y se rotula.
5. **Dato de salud voluntario.** Si la persona no lo da, la f vale 1 y el registro lo dice; la Comisión la corrige. Prefiero
   un registro completo y honesto a uno bloqueado. Lo valida el abogado de la empresa (C-18).
6. **La evaluación se reitera cada 12 meses** o antes si cambia algo; configurable. La Disposición no fija plazo.
7. **No se calcula una multa en pesos.** Solo el rango en UMA y la regla por persona (C-15). Una cifra concreta sería una
   afirmación que la ley no hace.
8. **La encuesta no tiene muestra.** Todas las personas del centro; es lo que da más grupos sobre el mínimo.
9. **`viewer` ve conteos sin nombres**, como en NOM-037.
10. **Resueltas tras la revisión del documento** (también mías): lo que la persona contesta en su portal vive en
    `person_inputs` (identificado, de salud, acceso restringido); el enlace se manda al firmar la evaluación; los puestos no
    prolongados responden igual las preguntas 3 a 5; la razón de asientos usa `max_people_at_once`; reevaluar no termina la
    evaluación vigente; la encuesta va a todo el centro y solo filtra por área; sus resultados solo los ven owner y hr; la
    Comisión propone medidas pero RH las administra; el criterio «qué cuenta como cumplido» está en su tabla.
11. **La landing dice «Todo patrón, en todo centro de trabajo».** La ley aplica a servicios, comercio y análogos, y a
    industriales cuando la naturaleza lo permite (C-03). **No lo cambio en este trabajo** (es copy compartido de otro PR);
    queda anotado para que se revise antes del lanzamiento.

## Requisitos de producción

- **Disco privado `seating_files`** (`SEATING_FILES_DISK`): S3 en producción, nunca público; los archivos se sirven solo
  a través de la aplicación. Mismas extensiones y límites de subida que NOM-037 (fotos y PDF).
- **Colas con Horizon y el scheduler corriendo:** los correos (invitación a la encuesta, recordatorios, resumen) van en
  cola, y el planificador diario lo dispara el scheduler.
- **`APP_PREVIOUS_KEYS` al rotar `APP_KEY`** si el portal usa enlaces derivados de la llave.
- **UMA y otros valores de la ley** no se guardan como constantes en código: el rango en UMA va en `config/seating.php` con
  su fuente y fecha.

## Pruebas (resumen; el detalle en el plan)

- **Instrumento:** cada texto de las siete tablas, los tres niveles y los cinco pasos de la Figura 1 contra
  `texto-de-referencia.md`; códigos únicos.
- **Bordes del puntaje:** total 7 → Bajo; 8 → Medio; 14 → Medio; 15 → Alto; 21 → Alto; una puntuación de 0 o de 4
  rechazada; una evaluación con menos de siete condicionantes rechazada.
- **Figura 1:** las 3 salidas A, B y C y los dos «no aplica»; el resultado guardado no coincide con las respuestas →
  rechazado; el caso «no prolongada» sigue pidiendo evaluación.
- **Evaluación:** solo un integrante asignado al centro firma; owner sin asignación no puede; un registro firmado no se
  edita; evaluar de nuevo conserva el historial; la f en 1 con «sin información» queda marcada.
- **Inventario:** un asiento de descanso exige ubicación designada; faltante contra personas por turno avisa.
- **Reglamento:** sin una de las tres partes no se marca completo; depósito antes de la firma rechazado; estados válidos.
- **Sanciones (texto):** la pantalla y el expediente dicen 50 a 5,000 UMA por persona y no nombran una fracción.
- **Tenant y roles:** A no ve a B (consulta y URL → 404); `committee` no ve otro centro; `viewer` sin nombres ni salud en
  props; token de portal de otra empresa → inválido; cuenta de sindicato → 404.
- **Privacidad:** una prueba envía una evaluación con datos de salud y una encuesta, y afirma que ninguna línea de log ni de
  la bitácora contiene sus textos, y que ninguna tabla de respuestas tiene llave hacia empleado, invitación o token.
- **Encuesta:** grupo bajo el mínimo no se muestra; cada filtro reduce el grupo; supresión complementaria; sin hora exacta.
- **Mutación:** `mutates()` en las acciones con reglas (cálculo del nivel, resultado de la Figura 1, firma, planificador,
  borrador del reglamento, suficiencia de asientos).
- **Consultas:** el tablero del centro afirma su número de consultas.
- **Navegador:** evaluación completa por la Comisión, portal en móvil (confirmar y reportar un asiento), tablero y
  expediente.

## Lo que queda al constructor

Nombres finales de tablas, columnas, clases y rutas; textos de UI y de correos en español (tuteo en el panel, «usted» en
el portal y la encuesta); redacción de las preguntas `SS-nn`, de las medidas sugeridas por nivel y de las cláusulas modelo
del reglamento, siguiendo los criterios; cómo se comparte la Comisión entre `Telework` y `Seating`; cómo se comparte el
portal personal; cómo se generaliza Campaigns para una campaña sin muestra; cómo encajan Bajo, Medio y Alto en
`RiskLevel`; los plazos del planificador; tamaños máximos de archivo; orden de los commits (uno por comportamiento, cada
uno en verde).

## Pendiente

Nada que impida aprobar. Quedan **tres cosas que valida una persona con criterio legal antes de lanzar**, no el
constructor: el aviso y la base legal para los datos de salud (C-18), la clasificación de «análogo» (C-03) y el texto de
las cláusulas modelo (C-13).
