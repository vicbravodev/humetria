# NOM-035: Programa de intervención (5.4, 8.3, 8.4, 8.5 e inciso g del 7.7)

Fecha: 2026-11-03. Estado: propuesta (pendiente de aprobación; sin decisiones del usuario todavía). Es el trabajo 2 de
[`gaps-pendientes.md`](../../normas/nom-035/gaps-pendientes.md); el plan de implementación se escribe al aprobarse este
documento. Todavía no hay código, migración ni prueba.

Anexos obligatorios:

- [`docs/normas/nom-035/criterios.md`](../../normas/nom-035/criterios.md) (versión 1.2): **cómo y por qué** se aplica cada
  obligación. Manda sobre este documento; aquí se cita como C-nn. Este trabajo propone siete criterios nuevos (C-30 a C-36,
  sección «Criterios nuevos») que se agregan a ese archivo al implementar.
- [`docs/normas/nom-035/texto-de-referencia.md`](../../normas/nom-035/texto-de-referencia.md): Tablas 3, 4, 6 y 7.
- [`docs/normas/nom-035/fuentes/`](../../normas/nom-035/fuentes/README.md): el DOF del 23/10/2018 y las preguntas
  frecuentes de la STPS. Todos los numerales de abajo se verificaron contra `dof-2018-10-23-nom-035.txt`.

Este documento dice **qué se construye**. Los anexos dicen qué pide la norma y cómo se interpreta.

## Intención

Hoy un cliente que cierra una evaluación con riesgo medio o más llega a «se requiere un Programa» (C-15, Tablas 4 y 7) y no
tiene dónde seguir. Con este trabajo, RH convierte el diagnóstico en un **Programa de intervención** que cumple el numeral
8.4: qué categorías y dominios se atienden, en qué áreas, con qué tipo de acción y a qué nivel, con fecha, responsable,
control de avances y evaluación posterior; lo conserva como evidencia (5.8 b, 10.4); lo ve impreso dentro del informe 7.7
(inciso g) y recibe recordatorios por correo de lo que vence.

Principios:

- **El Programa nace del resultado.** Las filas salen de la evaluación cerrada, no se teclean desde cero.
- **El Programa no puede revelar una respuesta.** Solo usa lo que Results ya muestra (grupos con al menos 5 cuestionarios) y
  nunca liga a una persona con un resultado de las Guías II o III (C-22, C-23).
- **Una sola regla de «qué requiere Programa».** Vive en Results, junto a los niveles de riesgo; el Programa la consume.
- **Reutilizar.** Resultados por `RiskResults`, áreas por `Areas`, centros por `WorkCenters`, personas con acceso por
  `Members`, correos con el patrón de los resúmenes existentes, evidencia con el patrón de Ley Silla.

## No objetivos

- Política de prevención, difusión y portal de la persona trabajadora (5.1, 5.7): es el trabajo 3. Este deja un contrato
  para que ese trabajo publique las medidas (5.7 c) sin tocar el Programa.
- Canal de quejas y denuncias (8.1 b) y registro de canalización (5.5, 5.8 c): trabajos 4 y 1. El **seguimiento por persona**
  de la atención clínica vive en el registro de canalización, nunca aquí (ver «Privacidad»).
- Exámenes médicos y evaluaciones psicológicas (5.6) y unidades de verificación (9): fuera de alcance (`gaps-pendientes.md`).
- Programa para centros de hasta 15 personas: la norma no les pide 8.3 a 8.5, solo 5.4, 8.1 y 8.2 (num. 2 a). No hay
  evaluación 7.1 de la que partir. Su pantalla dice qué sí les aplica.
- Medir la eficacia de las acciones comparando evaluaciones (antes y después) de forma automática: la evaluación posterior
  es una nota con fecha (ver «Evaluación posterior»).
- Presupuestos, costos, tareas con subtareas, adjuntar acciones a personas, SMS o WhatsApp: solo correo, como en las demás
  herramientas.
- Integración al diagnóstico de la NOM-030 (7.6): sigue fuera.

## Anti-objetivos (fracasaría aunque funcione)

- **Mostrar o permitir elegir un grupo bajo el mínimo**, o una fila cuyo grupo no se muestra en Results.
- **Ligar a una persona con un resultado de Guía II o III**, directa o indirectamente: ninguna tabla del Programa tiene llave
  a empleado, invitación o token; no hay selector de personas.
- **Dar una cifra o un nivel que Results no da**: todo número del Programa se lee de `RiskResults` al momento de mostrarlo y
  vuelve a pasar por el mínimo vigente.
- **Escribir texto libre de RH en logs, correos o la bitácora** (puede traer nombres).
- **Borrar evidencia** (5.8 b, 10.4): sin botón de borrar; se cancela con motivo.
- **Declarar «cumplido» o «apto»**: el Programa muestra lo registrado y lo que falta; no certifica.
- **Un segundo catálogo de áreas, personas, centros o evaluaciones.**

## Qué exige la norma

| Numeral | Dice (resumido; literal en `fuentes/`) | Cómo lo cumple este diseño |
|---|---|---|
| 5.4 | Adoptar las medidas para prevenir y controlar los factores de riesgo psicosocial, promover el entorno organizacional favorable y atender las prácticas opuestas y los actos de violencia laboral, con base en el Capítulo 8 | El Programa es la forma de adoptarlas cuando el resultado lo determina (8.3) |
| 8.3 | Si las evaluaciones 7.1 a 7.4, «conforme a los criterios establecidos en el método aplicado», determinan la necesidad de acciones de control, se implementan a través de un Programa que cumpla el 8.4 | Programa por evaluación cerrada con riesgo medio o más (II.4, III.4); C-30 |
| 8.4 a | «Las áreas de trabajo y/o los trabajadores sujetos al programa» | Todo el centro o áreas visibles; nunca personas (C-33). El «y/o» permite cumplirlo con áreas |
| 8.4 b | «El tipo de acciones y las medidas de control que deberán adoptarse» | Catálogo de tipos de acción + descripción (C-31) |
| 8.4 c | «Las fechas programadas para su realización» | Inicio opcional y fecha límite obligatoria por acción |
| 8.4 d | «El control de los avances de la implementación del programa» | Historial de avances, sin edición ni borrado, con estado y evidencia |
| 8.4 e | «La evaluación posterior a la aplicación de las medidas de control, en su caso» | Evaluación posterior opcional por fila |
| 8.4 f | «El responsable de su ejecución» | Responsable del Programa y de cada acción |
| 8.5 a, b, c | Acciones «según aplique» en tres niveles: **primero** organizacional (política de prevención, organización del trabajo); **segundo** grupal (información, sensibilización, apoyo social); **tercero** individual, cuando hay signos o síntomas, con intervenciones clínicas o terapéuticas | Cada acción lleva su nivel 1, 2 o 3 (C-32) |
| 8.5, último párrafo | Las intervenciones de tercer nivel clínicas o terapéuticas «deberán ser realizadas invariablemente por un médico, psicólogo o psiquiatra según corresponda» | Nivel 3 exige declarar el tipo de profesional (`check` en la base) |
| II.4 y III.4, Tablas 4 y 7 | Medio, Alto y Muy alto requieren Programa; Alto y Muy alto además «análisis de cada categoría y dominio» y campaña de sensibilización; Muy alto «evaluaciones específicas» (Alto «podrá incluir» una); todos «revisar la política (…) y reforzar su aplicación y difusión» | Lista «Lo que pide la Tabla» con lo cubierto y lo que falta (C-34) |
| 5.8 b | Llevar los registros de «las medidas de control adoptadas cuando el resultado (…) lo señale» | El Programa es ese registro |
| 7.7 g | El informe lleva «recomendaciones y acciones de intervención, en su caso» | Sección g del informe lee el Programa (ver «Informe 7.7»); C-29 |
| 10.2 (fila 5.4, 8.3, 8.4, 8.5) | Evidencia **documental**: cuenta con el Programa «cuando el resultado (…) así lo determinen» y este contiene los seis puntos del 8.4 | El Programa imprimible dentro del informe y los archivos de evidencia |
| 10.4 | Evidencias y registros, impresos o electrónicos, «al menos durante un año» desde su elaboración | Nada se borra (C-35) |
| Transitorio SEGUNDO | 8.3, 8.4 y 8.5 entraron en vigor a los dos años de la publicación (octubre de 2020) | Ya exigibles |

Criterios de la STPS (preguntas frecuentes, copia en `fuentes/`):

- «Los casos que caigan en niveles de riesgo Medio, Alto y Muy alto, deben ser atendidos a través del Programa de acciones
  de control referido en los numerales 8.3, 8.4 y 8.5» [STPS-1, pregunta 12; STPS-2, preguntas 2 y 8]. Es lo que ya cita C-14 y la base de C-30.
- Los numerales 5.4 y 8.3 obligan al patrón a adoptar las medidas mediante un Programa con el contenido del 8.4; hacer firmar a la persona trabajadora que «todo está bien» contradice la norma, según la STPS [STPS-1, pregunta 10].
- **Evidencia de las actividades:** convocatoria, listas de asistencia y resultados de la evaluación practicada, «entre otros
  similares» [STPS-1, pregunta 18]. Por eso cada avance admite un archivo (y por eso hay que avisar que esas listas llevan
  nombres; ver «Privacidad»).

Nota sobre el DOF: en la tabla 10.2, bajo el procedimiento de 7.1 b a 7.9, el texto «Esta disposición (numeral 8.5) sólo
aplica para aquellos centros de trabajo que desarrollen y utilicen métodos diferentes a los contenidos en la Guía de
referencia III» está impreso con «8.5», pero se refiere al 7.5 (validación de métodos propios). Lo leemos como errata: el
8.5 aplica a todo Programa. Es una interpretación y se rotula como tal en C-31.

## Qué decide este diseño

1. **Un Programa por evaluación cerrada con Guía II o III.** El de otra empresa es 404. Una evaluación solo de Guía I o por
   acontecimiento no tiene Programa (su atención es la canalización, 5.5).
2. **Filas = lo que la Tabla 4 o 7 manda analizar**: una fila por calificación final, categoría y dominio con **al menos un
   cuestionario en Medio, Alto o Muy alto** (C-30). La fila guarda el nivel más alto, no cifras: las cifras se leen vivas de
   Results (y con el mínimo vigente).
3. **Sujetos: el centro entero o áreas visibles.** Nunca personas (C-33).
4. **Cada acción** tiene nivel (1, 2 o 3), tipo, descripción, fechas, responsable, estado y su historial de avances.
5. **La evaluación posterior es por fila** (categoría o dominio), no por acción: es lo que se re-evalúa.
6. **Lo que pide la Tabla** se muestra como lista de verificación según el nivel más alto de la evaluación (C-34).
7. **El Programa se crea como borrador al cerrar la evaluación** (escucha `CampaignClosed`) y RH lo completa; también se puede
   crear con un botón si no existe.
8. **Módulo propio `Interventions`**; Results lo lee a través de un contrato suyo (ver «Módulo»).

## Módulo `Interventions`

`app/Modules/Interventions/` sigue la estructura de CLAUDE.md (Actions, Models, Enums, Http, Providers, Support,
Notifications, Listeners). Va aparte de Results porque tiene datos propios, permisos, correos y un planificador; Results sigue
siendo «agregados y reportes».

**API pública de `Interventions`:**

- `Contracts/Programs`: lo que otros módulos necesitan de un Programa, hoy nada que no cubra el puerto de Results. Se declara
  solo cuando el trabajo 3 (portal de la persona, 5.7 c) lo pida: «las acciones publicables de un centro, sin responsables».
  No se construye ahora (YAGNI), pero el diseño no lo estorba.
- `Events/ProgramDrafted` (program_id, campaign_id). Por ahora nadie lo escucha.

**Dependencias (solo contratos y datos públicos):**

| De | Qué usa |
|---|---|
| `Campaigns` | `Contracts\CampaignRecords::find` (evaluación cerrada, guías, centro), `Contracts\EvaluationSchedule` (sugerir la fecha de la evaluación posterior: la siguiente, num. 7.9), `Events\CampaignClosed` |
| `Results` | `Contracts\RiskResults` (cifras vivas por fila y por área, con el mínimo) y su extensión `areaGroups` (ver abajo); `Data\RiskBreakdown::needingProgram()` |
| `Instruments` | `Data\RiskLevel` (nivel de cada fila) y el catálogo de categorías y dominios de la guía de la evaluación para las filas manuales |
| `Organization` | `Contracts\Areas` (nombres de áreas) |
| `Tenancy` | `Contracts\Companies` (entrar a una empresa en el planificador, correo del titular), `Members` (responsables), `WorkCenters` (nombre del centro) |

**Qué agrega Results (commits propios, con sus pruebas):**

- `Results\Data\RiskBreakdown::needingProgram(): list<RiskFocus>`: **la** regla de qué requiere Programa (nivel Medio o más
  en la calificación final, una categoría o un dominio; `RiskFocus` = alcance `final|category|domain`, clave, etiqueta,
  categoría, nivel más alto y número de cuestionarios en Medio o más). Vive junto a `RiskLevels`, así que un cambio de
  la norma toca un solo lugar y un test de mutación la protege. También responde «¿requiere Programa?» para el informe.
- `Results\Contracts\RiskResults::areaGroups(CampaignRecord): list<AreaRisk>`: por cada **área visible** (mínimo y
  supresión complementaria ya aplicados por `RiskDistribution`), sus niveles por final, categoría y dominio. Una sola lectura
  de las respuestas anónimas, no una por área.
- `Results\Contracts\ProgramOutlines` (puerto): `forCampaign(string $campaignId): ?ProgramOutline`. Lo implementa
  `Interventions` y lo registra su proveedor. `ProgramOutline` y sus hijos son DTO readonly de `Results\Data` con **texto ya
  formateado** (etiquetas, fechas «2 nov 2026», nombres de áreas), como `CampaignRecord`.

**Por qué el puerto está en Results y no al revés.** Interventions depende de Results (lee cifras) y el informe de Results
necesita pintar el Programa. Si Results importara un contrato de Interventions habría un ciclo entre módulos. Con el puerto
del lado de Results, la flecha es una sola: Interventions → Results. Lo mismo para la tarjeta del Programa en la página de
resultados. La página de la evaluación (`campaigns/show`) solo lleva un enlace por nombre de ruta, sin datos, para no hacer
que Campaigns dependa de Interventions.

## Modelo de datos

Todas las tablas de negocio llevan `tenant_id`, aplican `BelongsToCurrentTenant`, usan UUID v4 y llaves compuestas
`(id, tenant_id)`, y traen su prueba de aislamiento (la empresa A no lee ni modifica lo de B; por URL, 404). Fechas
calendario en `date`; momentos en `timestampTz`. Los nombres exactos los fija el constructor; la estructura es:

**`intervention_programs`** (uno por evaluación)
- `campaign_id` (llave compuesta a `campaigns`, `unique`), `highest_level` (`RiskLevel`: `medium|high|very_high`, nulo en un
  Programa manual de muestra bajo el mínimo), `responsible_name` (nulo hasta que RH lo confirma), `responsible_user_id`
  (miembro de la empresa, nulo si es una persona externa), `created_by` (nulo = lo creó el sistema al cerrar).
- `check`: `responsible_user_id` no nulo ⇒ `responsible_name` no nulo.

**`intervention_items`** (las filas)
- `program_id`, `scope` (`final|category|domain`), `scope_key` (la clave del grupo en el instrumento), `label` (etiqueta
  congelada: el instrumento está versionado y el registro debe leerse igual dentro de años), `category_key` (para agrupar los
  dominios), `origin` (`results|manual`), `highest_level` (nulo en `manual`), `analysis` (texto, el «análisis» de II.4 y III.4),
  `follow_up_due_on`, `follow_up_done_on`, `follow_up_result` (texto), `follow_up_campaign_id` (opcional: evaluación posterior
  del mismo centro, llave compuesta).
- `unique (program_id, scope, scope_key)`. `check`: `origin = 'results'` ⇒ `highest_level` no nulo;
  `follow_up_done_on` no nulo ⇒ `follow_up_result` con texto.
- **No guarda conteos de cuestionarios.** Se leen vivos de `RiskResults` (ver «Privacidad»).

**`intervention_actions`**
- `program_id`, `item_id`, `level` (1, 2, 3; `check`), `kind` (enum, `check`), `title` (160), `description` (texto),
  `scope_of_people` (`center|areas`), `starts_on` (opcional), `due_on` (obligatoria; `check` `due_on >= starts_on`),
  `responsible_name`, `responsible_user_id` (por omisión el del Programa), `professional` (`physician|psychologist|psychiatrist`),
  `status` (`planned|in_progress|done|cancelled`), `completed_on`, `cancelled_reason`, `created_by`.
- `check`: `kind = 'clinical_care'` ⇒ `level = 3`; `level = 3` ⇔ `professional` no nulo (8.5, último párrafo);
  `status = 'done'` ⇔ `completed_on` no nulo; `status = 'cancelled'` ⇒ `cancelled_reason` con texto.
- Que `scope_of_people = 'areas'` tenga al menos un área lo valida la acción y lo prueba un test (un `check` no puede contar
  filas de otra tabla).

**`intervention_action_areas`** (las áreas sujetas)
- `action_id`, `area_id`, ambas con llave compuesta a su tabla; `primary key (action_id, area_id)`. Sin llave a empleados.

**`intervention_progress_entries`** (el control de avances, 8.4 d)
- `action_id`, `recorded_on` (date), `status` (el estado al que pasó), `note` (texto), `evidence_path` (disco privado),
  `recorded_by`. **Solo inserción** en el código: sin `updated_at`, sin editar, sin borrar (C-35). El estado y `completed_on`
  de la acción se actualizan en la misma transacción.

No hay tabla de recordatorios entregados: el resumen semanal corre en un solo servidor (`onOneServer`), como el de Campaigns.

## Roles y permisos (Spatie, como los actuales)

Permisos nuevos, por migración como `manage-campaigns`:

| Permiso | owner | hr | viewer | committee |
|---|---|---|---|---|
| `view-interventions`: Programa, filas, acciones, avances y su tarjeta en resultados | ✓ | ✓ | ✓ (sin archivos de evidencia) | |
| `manage-interventions`: crear, editar, registrar avances, cancelar, subir y ver evidencia, evaluación posterior | ✓ | ✓ | | |

- El **rol de consulta** ve lo mismo que ya ve en Resultados e Informe (grupos con mínimo), pero no abre archivos: una lista de
  asistencia lleva nombres.
- La **Comisión de seguridad e higiene** no tiene acceso: la NOM-035 no le da papel en el Capítulo 8.
- Una **persona responsable** que es miembro con rol de consulta recibe el correo y puede leer, pero no registra avances; los
  registra RH. Es una decisión que se puede abrir después con una Policy (ver «Preguntas abiertas», 5).
- El front recibe `can.view_interventions` y `can.manage_interventions` en `CurrentTenant.can`. Las rutas nuevas siguen
  exigiendo autenticación y nombre (`RouteSecurityTest`): ninguna es pública. FormRequests con `authorize()` por permiso.

## Flujos

### Se crea el Programa (sistema o RH)

1. Al cerrarse una evaluación con Guía II o III, un listener en cola (`DraftProgramOnClose`, como `SendClosingSummary`) llama
   a `CreateProgram`. RH también puede crearlo con un botón en la página del Programa si no existe (evaluaciones cerradas antes
   de este trabajo, o un fallo de cola); es idempotente por `unique(campaign_id)`.
2. `CreateProgram` lee `RiskResults::forCampaign`. Según su estado:
   - **`available`**: crea una fila por cada `RiskFocus` de `needingProgram()`. Si ninguna, **no crea Programa** y la página dice
     «Ningún cuestionario quedó en riesgo medio o más: la norma no pide un Programa» (num. 8.3).
   - **`below_minimum`** (menos cuestionarios que el mínimo: no hay cifras ni se sabe si hay riesgo): crea un Programa **sin
     filas**; RH puede agregar filas manuales (ver abajo). No se deriva nada de un grupo que no se muestra.
   - **`open`, `not_scored`** (sigue abierta, o solo Guía I): 404.
3. Todo en `DB::transaction()`: Programa y filas. Log `intervention.program_drafted`. El Programa nace con `responsible_name` nulo.

### Filas manuales

RH puede agregar una fila de una categoría o dominio que no salió en riesgo (acción preventiva, 8.1 y 8.2) o, en un Programa
de muestra bajo el mínimo, la que quiera. Las filas manuales muestran «Agregada por RH», **sin nivel ni cifra**, y no
cuentan para «lo que pide la Tabla». Se ofrecen del catálogo de la guía de la evaluación, no escritas a mano.

### Acciones

Se agrega desde un panel lateral por fila. Campos: tipo, nivel, título, descripción, sujetos (todo el centro o áreas), fecha de
inicio opcional, fecha límite, responsable. Reglas:

- **Sujetos:** `center`, o `areas` con áreas elegidas **solo del conjunto visible** de `RiskResults::areaGroups`. Un área
  que no está en ese conjunto se rechaza con el mismo mensaje que una que no existe («Elige áreas de la lista»). Junto a cada
  área el panel muestra, para esa fila, cuántos cuestionarios del área están en Medio o más (lo que ya muestra el filtro por
  área de Resultados).
- **Nivel 3** exige `professional` (médico, psicólogo o psiquiatra) y el tipo `clinical_care`; no se puede elegir una persona.
  La acción se redacta como **servicio disponible** («convenio con institución, atención psicológica a solicitud»), nunca como
  «a quién». La atención individual se registra en el registro de canalización (trabajo 1).
- **Responsable:** un miembro de la empresa (recibe correo y se le nombra) o un nombre libre (persona externa; el correo va a
  RH). Por omisión, el del Programa.
- **Copiar a otros dominios:** un botón crea copias independientes de la acción en otras filas; no hay una acción ligada a varias
  filas (más simple de seguir y de reportar). Ver «Preguntas abiertas», 14.

### Control de avances (8.4 d)

«Registrar avance» agrega una entrada: fecha, nuevo estado, nota y archivo opcional. Las transiciones válidas: `planned →
in_progress → done`, `planned → done`, y volver de `done` a `in_progress` si se reabre (otra entrada, queda en el historial).
`done` pide fecha de cierre (`completed_on`, por omisión hoy, no futura). Cancelar pide motivo y queda en el historial: **no
se borra una acción** (C-35). Una acción cancelada no cuenta para el avance ni para la lista de la Tabla.

### Evaluación posterior (8.4 e, «en su caso»)

Por fila: fecha programada (se sugiere la de la siguiente evaluación, que da `EvaluationSchedule`), y al hacerla, el resultado
(texto) y, si ya existe, la evaluación cerrada posterior del mismo centro (`follow_up_campaign_id`). Es una **nota con fecha**:
no se compara automáticamente contra la evaluación anterior en esta entrega (ver «Preguntas abiertas», 11). La Tabla 4 pide
«evaluaciones específicas» en Muy alto: es un **tipo de acción** (`specific_assessment`), distinto de esta evaluación posterior.

### Lo que pide la Tabla (C-34)

El nivel más alto del Programa (`highest_level`) decide qué se muestra como lista, con **Cubierto** o **Falta** según haya al
menos una acción no cancelada del tipo (en cualquier fila):

| Nivel más alto | Pide (II.4 y III.4) | Tipo que lo cubre |
|---|---|---|
| Medio o más | Revisar la política de prevención y los programas, y reforzar su aplicación y difusión | `policy_review` y `policy_dissemination` |
| Alto o Muy alto | Análisis de cada categoría y dominio | La fila tiene su `analysis` escrito |
| Alto o Muy alto | Campaña de sensibilización | `awareness_campaign` |
| Muy alto | Evaluaciones específicas | `specific_assessment` |
| Alto | «Podrá incluir» una evaluación específica | Opcional, se muestra sin «Falta» |

«Cubierto» solo dice que hay algo registrado; no juzga si basta. La lista no bloquea nada y no es un «apto».

### Estados derivados (un solo lugar, `ProgramProgress`)

- **Fila:** `sin acciones` (ninguna no cancelada) · `en curso` · `terminada` (todas las no canceladas en `done`). Una fila de nivel
  Alto o Muy alto sin `analysis` lleva además «Falta el análisis».
- **Acción vencida:** `due_on` anterior a hoy (hora de México) y estado `planned` o `in_progress`.
- **Programa:** `sin acciones` · `en curso` · `con acciones vencidas` · `terminado` (todas las filas terminadas y sus evaluaciones
  posteriores hechas, o sin ellas). El medidor de arco muestra acciones hechas de las no canceladas.

### Nuevo ciclo (num. 7.9)

Cada evaluación (cada 2 años o menos) tiene su Programa; no se copia nada. Al crear el nuevo, la página avisa si el Programa
anterior del mismo centro tiene acciones abiertas, con enlace. Qué hacer con ellas lo decide RH.

## Informe 7.7, inciso g

Hoy el inciso g del informe (`ReportIncises`) es un texto que RH escribe (`recommendations`). Con este trabajo:

- `ReportBuilder` llama al puerto `ProgramOutlines::forCampaign` y arma `intervention` con un estado: `program` (hay Programa),
  `not_required` (ninguna fila en Medio o más: «no se requiere Programa», num. 8.3), `missing` (se requiere y no existe) o
  `below_minimum`.
- El inciso g muestra **el texto de RH (recomendaciones) y debajo el Programa**: por fila, su nivel, el análisis, y por acción su
  tipo, nivel 1, 2 o 3, sujetos (centro o nombres de áreas visibles), fechas, responsable y estado; y la evaluación posterior
  con su fecha. Una nota dice «Estado al {fecha de impresión}». Sin archivos de evidencia, sin descripciones de las notas de avance.
- El indicador «pendiente» del inciso g deja de depender solo del texto: está pendiente cuando se requiere Programa y no hay ni
  texto ni Programa. `complete` del informe sigue siendo «RH guardó lo que captura».
- Aplica igual al **informe compartido por enlace temporal** (sin cuenta): usa el mismo `ReportBuilder` y, por construcción, solo
  lleva nombres de áreas visibles y nombres de responsables (no correos ni ids).
- Un cambio en el Programa se refleja en el informe de inmediato; el informe no guarda copia.

## Recordatorios por correo

Patrón de `SendProgressDigests` (Campaigns), con el cuidado de Ley Silla de no mandar nada si todo está en orden:

- Comando `interventions:send-digests`, **lunes 9:30** (hora de México), `onOneServer()`, `withoutOverlapping()`, registrado en
  `routes/console.php` con su prueba en `ScheduleTest`. Recorre las empresas con Programas, cada una con
  `Context::scope(['user_id' => null])` y `try/catch` que reporta sin detener a las demás. Una empresa bloqueada no recibe nada.
- **Al titular (owner):** una línea por cada cosa que no sea cero: acciones vencidas, acciones que vencen en los próximos 14
  días, filas sin acciones, Programas sin responsable, evaluaciones posteriores vencidas. Con enlace a la lista de Programas.
- **A cada responsable que es miembro:** sus acciones vencidas y las que vencen en 14 días, con enlace a su Programa.
- **Solo conteos**, nunca títulos, descripciones ni nombres (el texto libre puede traer datos de personas). Voz del panel (tuteo).
- `Notification` en cola, `afterCommit`, canal `mail` (CLAUDE: correo solo por Notification), clase `ProgramDigest`.
- Sin correo si no hay nada que decir. Log `intervention.digests_sent` con conteos.
- El **resumen de cierre** de Results (`ClosingSummary`) suma, cuando hay riesgo medio o más, «Siguiente paso: completa el Programa
  de intervención» con enlace (cambio mínimo en el correo y su prueba).

## Privacidad

El invariante: las respuestas de las Guías II y III no se ligan a nadie y ningún grupo bajo el mínimo se muestra (C-22, C-23). El
modelo de amenaza es RH y la aplicación. Un Programa es nuevo estado que RH escribe, así que se revisa cada vector:

| Vector | Riesgo | Cómo se evita | Prueba |
|---|---|---|---|
| Filas | Una fila revela que hay alguien en riesgo en un dominio | Solo se derivan de `RiskResults` con el grupo del centro ≥ mínimo; con `below_minimum` no se deriva nada | Evaluación con 4 cuestionarios: Programa sin filas; con 5, las filas esperadas |
| Cifras | Un conteo guardado sobrevive a un mínimo más alto | No se guardan conteos; se leen vivos y vuelven a pasar por el mínimo vigente | Subir `results.min_group` y comprobar que la fila ya no muestra cifras |
| Áreas | Elegir un área chica la ligaría con un dominio | Solo áreas de `areaGroups` (visibles, con supresión complementaria); las demás, error idéntico al de un área inexistente | Área con 4 cuestionarios y áreas 10+2, 5+5+2, 6+3+3: solo las visibles; POST a un área oculta: 422 |
| Resta | «Centro menos áreas» | La supresión complementaria ya garantiza que lo oculto suma 0 o ≥ 5; el Programa no agrega desgloses nuevos | Mismas pruebas de `RiskDistribution` sobre `areaGroups` |
| Personas | Elegir a quién se atiende lo liga con un resultado | No hay selector de personas, ni columna `employee_id` en ninguna tabla; el nivel 3 es un servicio disponible | Prueba de esquema: ninguna tabla `intervention_*` tiene llave a empleado, invitación o token |
| Texto libre | Un nombre en una descripción o análisis | Aviso fijo en cada campo («No escribas nombres ni datos de personas»); los textos nunca salen a logs, bitácora ni correos | Prueba de log y de correo con un texto que trae un nombre |
| Evidencia | Una lista de asistencia lleva nombres | Disco privado, solo `manage-interventions`, nunca impresa ni en el enlace temporal, servida por la app; aviso al subir | Rol de consulta: 403; el informe y el enlace compartido no traen rutas de archivo |
| Informe y enlace | Quien lo abre sin cuenta ve el Programa | Solo áreas visibles, responsables por nombre, sin evidencia; igual que lo demás del informe | Prueba del enlace compartido con Programa |
| Props | Respuestas individuales en Inertia | Las props del Programa no traen `answers`, ids de respuestas ni de empleados | Prueba de props en cada página, como `HrPagesPrivacyTest` |
| Participación | Probar áreas sueltas para saber cuáles se ocultan | RH ya ve quién contestó (C-22): no es una respuesta | — (anotado) |
| Hora | La hora de una acción acerca a una respuesta | Las acciones no tienen relación temporal con las respuestas; los avances llevan fecha, no hora visible | — |

## Pantallas

Todas siguen el rediseño de `docs/design-system/pantallas/` (CLAUDE.md, «Design system»): marco con sidebar y migas, encabezado
con título, estado y ficha con íconos, tarjetas, tablas con cabecera `muted`, medidor de arco, pestañas con conteo, estados
vacíos con siguiente paso. Se revisan en claro, oscuro y a 375 px contra la referencia antes de cerrar.

| Pantalla | Quién | Referencia del rediseño |
|---|---|---|
| Programas: resumen por estado y lista paginada de evaluaciones cerradas con su Programa | los de `view-interventions` | `NOM035` (centros a evaluar) y `Empleados` (tabla paginada) |
| Programa de una evaluación: encabezado, medidor de arco, «Lo que pide la Tabla», pestañas Filas, Acciones y Evaluación posterior | los de `view-interventions` | `Eval-Cerrada` (ficha, medidor, historial) |
| Agregar o editar acción: panel lateral con sujetos, nivel y fechas | `manage-interventions` | `Empleados-Alta` (panel lateral) |
| Registrar avance, evaluación posterior, cancelar: diálogos | `manage-interventions` | `Canalizacion` (estado en pasos: Planeada, En curso, Hecha) |
| Tarjeta «Programa de intervención» en Resultados | los de `view-interventions` | `Eval-Cerrada` (tarjeta de la Guía I) |
| Sección g del informe 7.7 | quien ve el informe, y el enlace temporal | `results/report` existente |
| Enlace «Programa» en la página de la evaluación cerrada | los de `view-interventions` | `Eval-Cerrada` (botones del encabezado) |

- **Navegación:** el menú `Cumplimiento → NOM-035` gana submenú: «Evaluaciones» (lo de hoy) y «Programas».
- **Listas:** el resumen (conteos por estado: sin Programa, en curso, con vencidas, terminado) va arriba, con el detalle paginado a
  un clic (CLAUDE.md, «Listas»). **Acciones** va paginada en el servidor (`->paginate()->withQueryString()`, `Paginated<T>`),
  filtrable por fila y estado. **Filas** son a lo más 16 (calificación final + categorías + dominios de la Guía III), un catálogo
  corto y acotado por el instrumento: viaja como arreglo con esa razón anotada en `tests/Unit/PaginatedListsTest.php`. Las
  páginas nuevas entran en `tests/Feature/PaginatedListsTest.php`.
- **Riesgo** siempre con `RiskLevel` y sus colores del design system; ningún color crudo.
- **Voz:** tuteo en el panel; los avisos de texto libre y la nota de privacidad en cada formulario.
- **Frontend:** `pages/interventions/{index,show}.tsx` delgadas; `components/interventions/*` (encabezado, medidor, lista de la Tabla,
  tabla de filas, fila, panel de acción, selector de áreas, selector de nivel, diálogos, paso de estado); `lib/interventions.ts`
  (etiquetas, vencidas, texto de sujetos) con su Vitest en `tests/js/lib/`; tipos en `resources/js/types/interventions.ts`;
  `components/results/{program-card,report-program}.tsx` reciben el Programa por props de Results (un módulo no importa
  componentes de otro). Respetan los límites de CLAUDE.md (100 líneas por componente, 300 por archivo, complejidad 10).

## Logs y bitácora

- **Logs** `intervention.*` en pasado, con ids, enumeraciones y conteos: `intervention.program_drafted`,
  `intervention.program_updated`, `intervention.item_added`, `intervention.analysis_saved`, `intervention.action_saved`,
  `intervention.action_cancelled`, `intervention.progress_recorded`, `intervention.follow_up_recorded`,
  `intervention.digests_sent`. **Nunca** título, descripción, análisis, notas, nombres ni nombres de áreas.
- **Bitácora** (`RecordAudit`, solo metadatos): `interventions.program_created` (incluye si lo creó el sistema), `interventions.program_viewed`
  (la página reproduce cifras de Resultados; paralelo de `results.viewed`), `interventions.action_cancelled`, `interventions.responsible_changed`,
  `interventions.evidence_viewed`. El informe 7.7 ya audita su apertura (`results.report_viewed`) y ahora incluye el inciso g.
- `RedactSensitiveData` ya borra `description` y `note`; se agregan `analysis`, `follow_up_result` y `cancelled_reason` con su
  prueba.

## Criterios nuevos (se agregan a `criterios.md` al implementar)

Con tipo y fuente, en el formato de ese archivo; sección **G. Programa de intervención**, y se quita el punto del Programa de
«Lo que la plataforma no cubre», se actualiza «Cómo se prueba» y se borra el gap 2 de `gaps-pendientes.md`.

- **C-30. Un Programa por evaluación cerrada; una fila por categoría, dominio y calificación final en Medio o más · Norma +
  STPS + Interpretación.** Base: 8.3, II.4, III.4 y la STPS. Interpretación: tomamos el nivel más alto presente en la calificación
  final, las categorías o los dominios; el DOF no dice si la regla va por el nivel final o por cada grupo y la STPS menciona
  los tres («calificación del dominio, la categoría y la final»).
- **C-31. Contenido de cada acción · Norma.** Los seis puntos de 8.4 (a a f). El catálogo de tipos de acción es de Humetría, no del
  DOF: sale de los textos de las Tablas 4 y 7 y de 8.1 y 8.2, y se rotula así en pantalla. Incluye la lectura del «8.5» de la errata.
- **C-32. Niveles 1, 2 y 3 · Norma.** 8.5; el tercero lo hace un médico, psicólogo o psiquiatra, que se declara.
- **C-33. Sujetos: el centro o áreas visibles, nunca personas · Humetría (sustentado en 8.4 a y C-22).** El «y/o» de 8.4 a permite
  cumplir con áreas. Elegir personas de la Guía II o III las ligaría con su resultado.
- **C-34. Qué pide la Tabla según el nivel más alto · Norma (II.4, III.4) + Interpretación** (usar el nivel más alto de todo).
- **C-35. Evidencia y conservación · Norma.** 5.8 b y 10.4: nada se borra; se cancela con motivo.
- **C-36. Recordatorios · Humetría.** La norma no pone plazos; los de 14 días de aviso y el correo semanal son nuestros.

## Requisitos de producción

- **Disco privado `intervention_files`** (`INTERVENTION_FILES_DISK`): S3 en producción, nunca público; los archivos se sirven solo
  a través de la aplicación. PDF e imágenes (las imágenes se recodifican y pierden metadatos de cámara), mismos límites de
  tamaño que Ley Silla. Config nueva en `config/interventions.php` y `.env.example` sin secretos.
- **Colas con Horizon y el scheduler corriendo:** el listener de cierre y los correos van en cola; el resumen semanal lo
  dispara el scheduler. Tras cambiar un listener o job: `sail artisan horizon:terminate`.
- **Evaluaciones cerradas antes del lanzamiento** no tienen borrador: se crean con el botón. Sin script de relleno.
- `config('interventions.due_soon_days')` (14). No hay otras constantes de plazo en código.

## Pruebas (resumen; el detalle en el plan)

TDD estricto, cada comportamiento con camino feliz, bordes y fallos.

- **Regla de qué requiere Programa (`needingProgram`):** Nulo y Bajo no; Medio, Alto y Muy alto sí, en cada uno de final, categoría y
  dominio; el nivel más alto de la fila es el mayor con al menos un cuestionario; evaluación con todo en Nulo y Bajo: ninguna
  fila y «no se requiere Programa».
- **Creación:** evaluación cerrada con II y con III (8 y 10 dominios); evaluación abierta, de solo Guía I o por acontecimiento:
  404; idempotente (dos cierres, dos clics); el listener crea el borrador; `below_minimum`: Programa sin filas y sin cifras;
  4 contra 5 cuestionarios en el centro; otra empresa: 404.
- **Filas:** una por clave única; una fila manual no cuenta para la Tabla ni lleva nivel; etiqueta congelada.
- **Acciones:** nivel 1, 2 y 3; nivel 3 sin profesional rechazado (y a nivel de la base); `clinical_care` en nivel 1 rechazado;
  fecha límite anterior al inicio rechazada; `areas` sin áreas rechazada; área oculta, inexistente o de otra empresa:
  mismo error; responsable de otra empresa rechazado; hoy como fecha límite no es vencida y ayer sí.
- **Avance:** `planned → in_progress → done`, el salto directo y reabrir; `done` sin fecha rechazado; cancelar sin motivo
  rechazado; una acción cancelada no se edita; el historial no se edita ni se borra (sin ruta, sin método); evidencia PDF e
  imagen, extensión o tamaño fuera de regla rechazados; la entrada y el estado cambian en una transacción.
- **Evaluación posterior:** fecha sugerida igual a la siguiente evaluación; resultado sin texto rechazado; la evaluación posterior
  de otro centro o de otra empresa rechazada.
- **Lista de la Tabla:** cada nivel más alto contra lo que pide, con y sin acción del tipo, con la acción cancelada (no cuenta) y
  con el análisis escrito o no; Alto no marca «Falta» en evaluación específica.
- **Estados:** vencida (borde de hoy), fila terminada, Programa terminado; medidor sin canceladas.
- **Informe 7.7 g:** `program`, `not_required`, `missing`, `below_minimum`; el texto de RH y el Programa conviven; el pendiente de g;
  el enlace temporal lleva el Programa sin evidencia, sin correos ni ids; una empresa bloqueada o un enlace vencido siguen sin
  abrir nada.
- **Recordatorios:** una línea por cosa distinta de cero; nada si todo está en orden; el titular recibe todo y el responsable
  solo lo suyo; un responsable sin correo no rompe; empresa bloqueada, sin titular o con una falla no detiene a las demás; solo
  conteos (ni texto libre ni nombres en el correo); el lunes 9:30 en `ScheduleTest`.
- **Privacidad:** los vectores de la tabla de arriba, y una prueba que crea un Programa con textos y una evaluación y afirma que
  ninguna línea de log ni de la bitácora los contiene y que ninguna tabla `intervention_*` tiene llave a empleado, invitación o token.
- **Permisos y tenant:** owner y RH escriben; consulta lee sin evidencia (403 al archivo); comité y rutas fuera de su permiso:
  403; la empresa A no lee ni modifica lo de B (consulta directa y por URL, 404).
- **Consultas:** la página del Programa y la de resultados con la tarjeta afirman su número (`expectsDatabaseQueryCount`), con
  `with()` en áreas y último avance (modo estricto).
- **Mutación:** `mutates()` en `needingProgram`, `CreateProgram`, `SaveAction`, `RecordProgress`, `ProgramProgress`, la lista de
  la Tabla y `SendProgramDigests` (se corre acotada y a petición, CLAUDE.md).
- **Navegador (`visitPage()`):** cada página nueva (componente, errores de JS, CSP, axe); el flujo crear acción, registrar avance
  y ver el informe, a 375 px y en claro y oscuro.
- **Arquitectura y harness:** `ArchitectureTest` (Interventions solo usa contratos), `TenantScopedModelsTest`,
  `RouteSecurityTest`, `PaginatedListsTest`, `BrowserCoverageTest`, `TimestampColumnsTest`.

## Tamaño y descomposición en commits

**Tamaño: L.** Estimación: unos 50 archivos PHP (5 modelos, 6 enums, ~12 acciones, 1 listener, 1 notificación, 4 controladores,
FormRequests, 4 migraciones), ~25 de React y TypeScript, 3 vistas de correo y ~14 archivos de pruebas. Un solo PR con estos
commits atómicos, cada uno con test, código mínimo y la suite en verde (CLAUDE.md, «Un commit = un comportamiento»):

1. **Results: `RiskBreakdown::needingProgram()`** (la regla y su `RiskFocus`) y `RiskResults::areaGroups`. Pruebas de bordes y de
   mínimo y supresión por área.
2. **Interventions: permisos y esqueleto** (migración de `view-` y `manage-interventions`, proveedor, rutas vacías y navegación).
3. **Interventions: tablas, enums, modelos, factories y aislamiento** (checks de la base probados con inserciones directas).
4. **`CreateProgram`:** derivar filas, casos `available`, sin filas y `below_minimum`, 404s e idempotencia; botón y página mínima.
5. **Listener `DraftProgramOnClose`** y la entrada en `ClosingSummary`.
6. **Acciones:** `SaveAction` con tipo, nivel, sujetos (áreas visibles), fechas y responsable; panel lateral.
7. **Avances:** `RecordProgress`, estados, vencidas, medidor; cancelar con motivo.
8. **Evidencia:** disco privado, subida, descarga con permiso y bitácora.
9. **Análisis, evaluación posterior y filas manuales.**
10. **Lista de la Tabla** (`policy_review`, campaña, evaluación específica) y copiar acción a otros dominios.
11. **Páginas:** lista de Programas con resumen y la página del Programa en claro, oscuro y móvil; tarjeta en Resultados y enlace en
    la evaluación; navegador.
12. **Inciso g del informe 7.7:** puerto `ProgramOutlines`, `ReportBuilder`, sección impresa y enlace temporal.
13. **Recordatorios:** `SendProgramDigests`, `ProgramDigest`, comando y planificación.
14. **Documentos y seeder:** C-30 a C-36 en `criterios.md` y «Cómo se prueba», fuera del gap en `gaps-pendientes.md`, `RedactSensitiveData`,
    y un Programa de ejemplo en `Nom035EvaluationSeeder` (con avances vencidos y hechos) y su prueba. Sin correos.

## Lo que queda al constructor

Nombres finales de tablas, columnas, clases y rutas; textos de UI y de correo en español (tuteo en el panel); la redacción de los
tipos de acción y de sus sugerencias; cómo se obtiene el catálogo de categorías y dominios de la guía para las filas manuales
(`Instruments\Contracts\Instruments`, sin inventar una API); el formato exacto de `ProgramOutline`; el orden y los plazos
(14 días) del planificador; tamaños de archivo; reutilizar el almacenamiento privado de Ley Silla (ver «Preguntas abiertas», 8).

## Preguntas abiertas para el usuario

Respóndelas todas juntas; cada una trae mi recomendación por defecto («usa los defaults» las acepta).

1. **Módulo.** ¿Módulo nuevo `Interventions` con un puerto en Results para el informe y la tarjeta, o todo dentro de Results?
   *Recomiendo módulo nuevo con el puerto en Results* (evita un ciclo y deja a Results en «agregados y reportes»).
2. **Cuándo nace el Programa.** ¿Borrador automático al cerrar la evaluación (más botón de respaldo) o solo cuando RH pulse
   «Crear»? *Recomiendo borrador automático:* quien cierra ya encuentra el Programa listo y los recordatorios no necesitan otro
   origen.
3. **Qué filas.** ¿Calificación final, categorías y dominios, o solo dominios y categorías? *Recomiendo los tres* (la final aloja
   «revisar la política», que la Tabla pide por nivel; STPS menciona los tres).
4. **Personas sujetas.** ¿Solo todo el centro o áreas visibles, sin elegir personas, y el nivel 3 como servicio disponible? *Recomiendo
   sí:* elegir personas ligaría a alguien con su resultado (C-22); la atención individual queda en el registro de canalización (trabajo 1).
5. **Responsable.** ¿Un miembro de la empresa o un nombre libre, en el Programa y en cada acción, y que solo owner y RH registren
   avances? *Recomiendo sí, y abrir después que el responsable registre lo suyo* si lo piden (requiere una Policy).
6. **Quién ve qué.** ¿Owner y RH escriben; consulta lee sin abrir archivos; la Comisión de seguridad e higiene no entra? *Recomiendo sí.*
7. **Recordatorios.** ¿Un correo semanal (lunes 9:30) al titular y a cada responsable con solo conteos, aviso 14 días antes y
   nada si todo está en orden? *Recomiendo sí* (mismo patrón que Campaigns; el diario de Ley Silla es más ruido para un Programa
   que cambia poco).
8. **Evidencia en esta entrega.** ¿Archivos (PDF e imagen) en un disco privado nuevo, con otra copia del almacenamiento de Ley
   Silla, o extraer ese código a lo compartido, o dejarlo para después? *Recomiendo incluirla y copiar con cuidado:* extraer toca dos
   módulos ya entregados y no lo necesita esta función; queda como deuda anotada para su propio PR.
9. **Muestra bajo el mínimo.** ¿Permitir un Programa manual (sin cifras ni filas derivadas) cuando la evaluación cerró con menos
   cuestionarios que el mínimo? *Recomiendo sí,* con el aviso de por qué no hay cifras.
10. **Centros de hasta 15 personas.** ¿Quedan fuera de este trabajo (la norma no les pide 8.3 a 8.5)? *Recomiendo sí;* su acción
    preventiva (8.1 y 8.2) entra con el trabajo 3.
11. **Evaluación posterior.** ¿Nota con fecha y evaluación posterior opcional, sin comparar automáticamente contra la anterior?
    *Recomiendo sí;* la comparación antes y después por grupo pide una regla de mínimo propia y es un siguiente trabajo.
12. **Nuevo ciclo.** ¿Programa nuevo por evaluación, avisando de las acciones abiertas del anterior, sin copiarlas? *Recomiendo sí.*
13. **Inciso g.** ¿Conviven el texto de RH («recomendaciones») y el Programa impreso, o el Programa sustituye el texto? *Recomiendo
    que convivan:* el texto cubre las «recomendaciones» del 7.7 g y el Programa las «acciones de intervención».
14. **Catálogo de tipos de acción.** ¿Aprobar estos diez, rotulados como catálogo de Humetría con su nivel por omisión (ver C-31)?
    `policy_review` (1), `policy_dissemination` (1), `work_organization` (1), `violence_prevention` (1), `awareness_campaign` (2),
    `training` (2), `social_support` (2), `specific_assessment` (2), `clinical_care` (3), `other` (cualquiera, con descripción).
    Y, ¿copiar una acción a otras filas en vez de ligarla a varias? *Recomiendo aprobar ambas.*
15. **Navegación.** ¿Submenú «Evaluaciones» y «Programas» en NOM-035, o solo la tarjeta en la evaluación y en Resultados?
    *Recomiendo el submenú:* es donde RH busca lo vencido.

## Decisiones del usuario (2026-11-03)

Se aceptaron **todos los valores por defecto** de «Preguntas abiertas», incluida la declaración de
`bacon/bacon-qr-code` en `composer.json` cuando el diseño la necesita (dependencia ya presente por Fortify).
Orden de implementación: Programa de intervención y Canal de quejas (en paralelo), Política y portal al final.
