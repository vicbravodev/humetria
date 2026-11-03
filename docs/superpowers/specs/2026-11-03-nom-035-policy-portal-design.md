# NOM-035: política de prevención, difusión y página de las personas trabajadoras

Fecha: 2026-11-03. Estado: propuesta (pendiente de aprobación). Es el trabajo 3 de
[`gaps-pendientes.md`](../../normas/nom-035/gaps-pendientes.md) (numerales 5.1, 5.7 y 7.8). El plan de implementación se
escribe al aprobarse este documento. Se diseña en paralelo con el programa de intervención (trabajo 2) y el canal de
quejas (trabajo 4): lo que se asume de ellos está en «Integración con el Programa y las Quejas».

Anexos obligatorios:

- [`docs/normas/nom-035/criterios.md`](../../normas/nom-035/criterios.md) (versión 1.2): cómo y por qué se aplica cada
  obligación. Manda sobre este documento; aquí se cita como C-nn (C-14, C-15, C-23, C-27 y C-29 son los que importan).
- [`docs/normas/nom-035/texto-de-referencia.md`](../../normas/nom-035/texto-de-referencia.md): **hoy no trae la Guía de
  referencia IV**. El primer commit del trabajo la transcribe literal desde el DOF (ver «Instrumento»); sin eso no hay
  prueba contra el anexo.
- [`docs/normas/nom-035/fuentes/`](../../normas/nom-035/fuentes/README.md): el DOF del 23/10/2018 (`dof-2018-10-23-nom-035.txt`),
  de donde salen todos los numerales citados abajo, verificados en el texto.

Este documento dice **qué se construye**. Los anexos dicen qué pide la norma y cómo se interpreta.

## Intención

Hoy un cliente puede evaluar, calificar y presentar el informe 7.7, pero la norma pide además que el patrón **tenga por
escrito una política de prevención y la difunda**, y que **las personas trabajadoras puedan consultar** qué se hace y qué
resultó. Hoy la consulta (7.8) se cumple con el informe impreso, o con el enlace temporal de C-29 (15 min a 48 h), que está
pensado para un auditor y no para la plantilla. Un centro de hasta 15 personas, que solo debe cumplir 5.1, 5.4, 5.5, 5.7, 8.1 y
8.2 (num. 2 a), no tiene dónde cumplir 5.1 y 5.7 en la plataforma.

El trabajo tiene cuatro piezas:

1. una **política de prevención** por escrito, a partir del ejemplo de la Guía IV, con versiones y publicación por centro;
2. la **difusión** de la política, con acuse de lectura por persona (y registro de los medios físicos: carteles, boletines);
3. una **página permanente** para las personas trabajadoras, por enlace, que junta lo que 5.7 manda difundir: política,
   medidas, mecanismos de queja, resultados y posibles alteraciones a la salud;
4. la **publicación de resultados agregados** a esa página (7.8), siempre con el mínimo de respondentes por grupo (C-23).

Principios, los mismos que en NOM-037 y Ley Silla:

- **Reutilizar el catálogo central.** Personas = empleados de `Organization`; centros = los de `Tenancy`; el enlace personal
  de la persona es el que ya existe (`Telework\Contracts\PortalAccess`).
- **No meter la difusión en lo compartido ni en las herramientas de al lado.** Todo vive en un módulo nuevo, `Disclosure`.
- **Precisión.** El texto de la Guía IV se transcribe literal y se prueba contra el anexo. Lo que la norma calla se rotula
  «criterio de Humetría» y nunca se presenta como DOF.
- **Nada individual en una página que se abre con un enlace.** Es la regla que organiza todo el diseño de la página.

## Qué exige la norma (verificado en el DOF)

| Numeral | Dice (DOF 23/10/2018) | Cómo lo toma el diseño |
|---|---|---|
| 2 a, b, c | Hasta 15 trabajadores: 5.1, 5.4, 5.5, 5.7, 8.1 y 8.2. De 16 a 50: 5.1, 5.2, 5.4 al 5.8, 7.1 a), 7.2, 7.4 al 7.9 y el Capítulo 8. Más de 50: 5.1, 5.3 al 5.8, 7.1 b), 7.2 al 7.9 y el Capítulo 8. | La política (5.1) y la difusión (5.7) aplican a **todo centro**. Los resultados (5.7 e, 7.8) solo a centros de **16 o más**. |
| 2, último párrafo | Con certificado vigente de la NMX-R-025-SCFI-2015 se dan por cumplidos 5.1 inciso b) y 5.7 inciso d), entre otros. | No se automatiza (ver «No objetivos» y pregunta 14): se muestra el aviso, el cliente decide. |
| 4.9 | Política de prevención: «la declaración de principios y compromisos que establece el patrón para prevenir los factores de riesgo psicosocial y la violencia laboral, y para la promoción de un entorno organizacional favorable». | Es el texto que la plantilla ayuda a escribir. |
| 5.1 | «Establecer por escrito, implantar, mantener y difundir en el centro de trabajo una política de prevención de riesgos psicosociales que contemple: a) la prevención de los factores de riesgo psicosocial; b) la prevención de la violencia laboral, y c) la promoción de un entorno organizacional favorable.» | Política por escrito, versionada, por centro; declara a, b y c. |
| Guía IV | «Ejemplo de política (…) El contenido de esta guía es un complemento (…) y no es de cumplimiento obligatorio.» Nueve compromisos, una nota (responsables y recursos) y ocho principios. | Plantilla editable, no obligatoria. La nota exige que la política **indique responsables y recursos**: son campos obligatorios al publicar. |
| 5.7 | «Difundir y proporcionar información a los trabajadores sobre: a) la política; b) las medidas adoptadas para combatir las prácticas opuestas al entorno organizacional favorable y los actos de violencia laboral; c) las medidas y acciones de prevención y, en su caso, las acciones de control de los factores de riesgo psicosocial; d) los mecanismos para presentar quejas (…) y para denunciar actos de violencia laboral; e) los resultados de la identificación y análisis de los factores de riesgo psicosocial (16 a 50) y de la identificación y análisis (…) y la evaluación del entorno organizacional (más de 50), y f) las posibles alteraciones a la salud por la exposición a los factores de riesgo psicosocial.» | Seis incisos, seis bloques de la página (tabla «Cobertura de 5.7»). |
| 7.8 | «El resultado de la identificación y análisis (…) y la evaluación del entorno organizacional deberá estar disponible para consulta de los trabajadores.» | Resultados agregados publicados en la página, con el mínimo por grupo. Aplica a 16 o más. |
| 10.2 (tabla de verificación) | Para 5.1: evidencia documental de que «establece, implanta y mantiene» la política y «difunde» en el centro; «la evidencia (…) puede ser a través de folletos, boletines, y/o carteles». Lo mismo para 5.7. | La plataforma guarda la política publicada, su difusión (acuses, medios físicos, la página) y un expediente imprimible. |
| 10.4 | Las evidencias «podrán exhibirse de manera impresa o en medios electrónicos, y se deberán conservar al menos durante un año». | Sin borrado de evidencia: versiones, acuses y registros de difusión no se eliminan. |
| 4.7 | Factores de riesgo psicosocial: «aquellos que pueden provocar trastornos de ansiedad, no orgánicos del ciclo sueño-vigilia y de estrés grave y de adaptación (…)». | Base **del DOF** para el texto de 5.7 f, que el DOF no desarrolla. |
| II.4, III.4 (Tablas 4 y 7) | Riesgo Medio: «revisar la política de prevención de riesgos psicosociales y programas (…) así como reforzar su aplicación y difusión, mediante un Programa de intervención»; Bajo: «una mayor difusión de la política». Alto y Muy alto: además campaña de sensibilización. | El tablero avisa cuando una evaluación cerrada pide revisar la política y la vigente es anterior (C-15). |

**Lo que la norma no pide y este diseño no inventa:** el acuse de lectura (5.7 pide «difundir y proporcionar información»,
no confirmar lectura), un plazo de revisión de la política (5.1 dice «mantener», sin periodicidad), una página web ni
un porcentaje mínimo de personas informadas. Lo que sí hace Humetría encima de la norma se rotula así en los criterios
nuevos (ver «Criterios nuevos»).

## Restricciones ya dadas por el repositorio

Aún no hay decisiones del usuario sobre este trabajo: lo que sigue son defaults míos, y las preguntas del final se
contestan todas juntas. Sí vienen dadas, por `CLAUDE.md` y los criterios:

- Privacidad (invariante): ninguna respuesta de Guía II/III ligada a persona; el mínimo por grupo (C-23, hoy 5, configurable
  en `results.min_group`) se aplica **después de filtrar** y en un único servicio (`Results`); resultados de Guías II y III
  solo con la evaluación cerrada (C-27); la Guía I nunca sale de la lista de canalización (C-28).
- Una base, `tenant_id` + `BelongsToCurrentTenant`, 404 y no 403 entre empresas; `Contracts`, `Events` y `Data` son lo único
  público de un módulo; listas paginadas en el servidor; `RecordAudit` para lo sensible; logs `modulo.accion` en pasado.
- Dependencias nuevas solo con aprobación (afecta al QR; pregunta 7).

## Objetivos

1. RH escribe la **política** partiendo de la plantilla de la Guía IV, la revisa, declara qué incisos de 5.1 contempla y la
   **publica** como una versión inmutable para los centros que elija (C-30, C-31).
2. La plataforma pide a cada persona activa de esos centros que **confirme la lectura**, por correo y desde su enlace
   personal; quien llega después (alta, reactivación, cambio de centro) la recibe también. RH ve el avance y puede registrar
   acuses en papel (C-33).
3. RH registra los **medios físicos** de difusión (cartel, boletín, folleto, reunión) con fecha, centro e incisos que cubren
   (C-32).
4. RH tiene un **enlace permanente** a la página de las personas trabajadoras, con cartel imprimible; puede rotarlo,
   desactivarlo y ponerle vencimiento (C-35).
5. La página muestra por centro: la **política vigente**, las **medidas** (del Programa, cuando exista), los **mecanismos de
   queja** (del canal, cuando exista), los **posibles daños a la salud** y los **resultados agregados** ya publicados (C-34).
6. RH **publica y retira** los resultados de una evaluación cerrada hacia esa página. Solo centros de 16 o más, solo cifras
   que pasan el mínimo, nunca áreas ni Guía I.
7. RH ve un **tablero** por centro con el estado de cada inciso de 5.1 y 5.7 y de 7.8, y un **expediente imprimible** de
   difusión para la inspección.

## No objetivos

- El programa de intervención y sus medidas (trabajo 2) y el canal de quejas (trabajo 4): se leen por contrato, no se
  construyen aquí. Mientras no existan, RH puede publicar un texto manual en su lugar.
- **La exención de la NMX-R-025** (num. 2, último párrafo): no se calcula ni se aplica sola. Un cliente certificado puede
  publicar el texto de 5.7 d y listo; la pregunta 14 pregunta si se agrega un interruptor.
- Adjuntar el PDF de la política firmada (pregunta 8); la entrega publica **texto**, no archivos, y evita un disco privado
  y un servicio de archivos más.
- Firma electrónica avanzada: el acuse es una confirmación dentro del enlace personal, con fecha y hora.
- SMS y WhatsApp: solo correo.
- Estadísticas de uso de la página (visitas, quién la abrió): el invariante de privacidad pesa más que esa métrica.
- Que la página reciba datos de las personas: es de solo lectura. Las quejas, cuando existan, van por su propia ruta.
- El informe 7.7 completo para la plantilla: sigue siendo para RH, la dirección y el auditor (C-29). La página muestra
  **resultados**, no el informe.
- Multi-idioma de la página.

## Anti-objetivos (fracasaría aunque funcione)

- **Que el enlace de la página, que se imprime en un cartel y se filtra, exponga a una persona.** Ni nombres, ni correos,
  ni áreas pequeñas, ni medidas dirigidas a personas, ni el correo del owner. La página solo tiene lo que la empresa
  decidió que lean todos.
- **Que un resultado de la página permita reidentificar a alguien**: un grupo bajo el mínimo, un filtro, un área, o restar
  el centro menos un área (C-23). La página no tiene filtros ni áreas.
- **Mostrar resultados sin que RH los haya publicado**, o con la evaluación abierta (C-27).
- **Prometer lo que no controla**: la anonimia de una queja (la promete el canal y la página solo la repite), un «cumple»
  a nombre de la STPS o un «apto». El tablero cuenta lo registrado; no certifica.
- **Publicar compromisos falsos**: la plantilla de la Guía IV incluye compromisos que no todo centro cumple (exámenes
  psicológicos al ingreso, evaluación anual del desempeño). Publicarlos sin revisar sería declarar algo que no es cierto.
- **Borrar evidencia** (10.4): no hay botón de borrar para versiones publicadas, acuses ni registros de difusión.
- **Un segundo catálogo** de personas, centros o enlaces personales.
- **Acoplarse a los internos** del Programa, las Quejas o Telework.

## Reglas de la norma que el sistema hace cumplir

| Regla | Fuente | Cómo se cumple |
|---|---|---|
| Política por escrito en cada centro, difundida | 5.1 | Versión publicada por centro; el tablero marca el centro sin política |
| La política contempla a, b y c | 5.1 | Tres casillas al publicar; el tablero avisa si falta alguna |
| Indica responsables y recursos | Guía IV, nota | Dos campos obligatorios al publicar |
| Difundir lo de 5.7 a–f | 5.7, 10.2 | Un bloque de la página por inciso; o un registro de medio físico que lo cubra |
| Resultados disponibles a las personas | 7.8 | Publicación manual de una evaluación cerrada, solo 16 o más |
| Resultados sin identificar | C-23, C-27, C-14 | Solo vía `Results`; sin filtros; mínimo; nunca en abierto |
| Revisar la política con riesgo Medio o más | Tablas 4 y 7 | Aviso en el tablero mientras la política vigente sea anterior al cierre |
| Evidencia conservada, sin borrado | 10.4 | Versiones inmutables (trigger), acuses y registros sin DELETE |

## Módulo `Disclosure`

`app/Modules/Disclosure/` sigue la estructura de `CLAUDE.md`. Se separa de los módulos de al lado así:

- **No es `Campaigns` ni `Results`:** esos módulos son de la evaluación; la política y la difusión no dependen de que
  haya una evaluación (un centro de 10 personas nunca tendrá una).
- **No es `Telework`:** la política de teletrabajo es un PDF de NOM-037; esta es texto versionado de NOM-035.
- **No es `Instruments`:** `Instruments` solo guarda la *definición* en código de la plantilla (como `Nom037Checklist`); la
  vida de la política (versiones, publicación, acuses) es de `Disclosure`.
- El nombre es genérico a propósito: la página de las personas será también el lugar natural de la política de
  teletrabajo o del reglamento de Ley Silla si algún día se decide difundirlos ahí. **Hoy no se hace**; el módulo no
  asume nada de esas normas.

**API pública:**

- `Events/PolicyPublished` (tenant_id, document_id, work_center_ids). Lo escucha un listener interno que pide los acuses
  (en cola, después del commit); está público para que una reacción futura (p. ej. Humi) no obligue a tocar `Disclosure`.
- `Contracts/DisclosureStanding` + `Data/DisclosureRow`: dónde está cada centro con 5.1/5.7/7.8 (estados y conteos, nunca
  nombres), en el orden de la tabla 10.2, como `TeleworkStanding`. **Opcional:** se agrega solo si una pantalla de otro
  módulo lo usa (panel de inicio, Humi).

**Lee de otros módulos solo por contratos:**

| De | Qué | Contrato |
|---|---|---|
| `Tenancy` | empresa, centros, razón social | `Companies`, `CurrentTenant`, `WorkCenters` (`profile()->legalName`) |
| `Organization` | personas activas por centro, altas y bajas | `Roster`, `Headcount`; eventos `EmployeeCreated`, `EmployeeReactivated`, `EmployeeMoved` |
| `Telework` | enlace personal de la persona | `PortalAccess`, `Data\PortalIdentity` (ya públicos) |
| `Instruments` | plantilla de la política | `Nom035Policy` (nuevo, ver «Instrumento») |
| `Campaigns` | evaluaciones cerradas del centro | `CampaignRecords` (+ un método nuevo, ver abajo) |
| `Results` | cifras agregadas con el mínimo | `RiskResults` (existe) |
| Programa (trabajo 2) | medidas visibles a las personas | contrato supuesto, ver abajo |
| Quejas (trabajo 4) | datos públicos del canal | contrato supuesto, ver abajo |

Un dato que falte se agrega al contrato del módulo dueño, nunca con un join. Lo único nuevo en un contrato ajeno:
**`Campaigns\Contracts\CampaignRecords::latestClosedEvaluations(array $workCenterIds): array<string, CampaignRecord>`**, la
última evaluación NOM-035 cerrada por centro (hoy solo hay `closedEvaluations()` paginado y `find()`), en su propio commit.

### Revisión del módulo Telework: qué se reutiliza y qué se duplica

El documento de gaps pidió revisar Telework antes de diseñar. Esto es lo que hay en el código de hoy:

**Avisos con acuse.** `telework_documents` (PDF, versión por centro y tipo, `DocumentKind` interno, incisos que cubre) y
`acknowledgements` (una fila por documento y persona, empleado **o** usuario de la Comisión, canal `portal|manual|panel`,
IP, `check` de coherencia, llaves compuestas con `tenant_id`, `unique` por documento y persona). `RequestAcknowledgements`
decide la audiencia con `AckAudience` (según el tipo de documento: la política de teletrabajo, todo empleado activo del
centro; la información de riesgos, el padrón y la Comisión), inserta por bloques de 500 con `insertOrIgnore` y manda un
recordatorio diferido con `SendPortalReminder` (único por persona, 120 s). `Acknowledge` confirma bajo candado, una sola
vez y solo la versión vigente. Escuchan la publicación (`RequestOnPublication`, en cola tras el commit) y los ingresos
(`RequestPolicyFromNewStaff`: alta, reactivación y cambio de centro). RH registra acuses en papel por lotes.

**Portal por enlace.** `portal_links`: un enlace por empleado, cuyo token **no es aleatorio**: se deriva con
`HMAC-SHA256(APP_KEY, "portal|tenant|employee|version")`; solo se guarda su hash para encontrar la fila; regenerarlo sube
la `version`; las llaves de `APP_PREVIOUS_KEYS` también lo abren. `OpenPortal` entra a la empresa del enlace y contesta con
**una sola excepción** (`PortalUnavailable`) para enlace desconocido, reemplazado, persona dada de baja o empresa
bloqueada. Las rutas `portal/{token}…` van limitadas por `portal-views` (30/min por enlace, 600/min por IP) y
`portal-actions` (10/min por enlace, 300/min por IP).

**Lo que ya es público.** `Telework\Contracts\PortalAccess` (abrir un token → `PortalIdentity`; dar el token de un
empleado), `Committee` y `TeleworkStanding`. Ley Silla ya los consume: sus cuatro rutas `portal/{token}/seating…`, el
correo `SendRisksToConfirm` y su acuse (`RiskAcknowledgment`) usan `PortalAccess`, y su propio acuse **no** reutilizó el de
Telework.

### Decisión

1. **Se reutiliza `PortalAccess` tal cual.** La identidad y el enlace personal de la persona no son de una herramienta
   (lo dice el propio contrato) y ya hay un precedente de consumo. La política se confirma en `portal/{token}/policy`,
   igual que Ley Silla cuelga de `portal/{token}/seating`.
2. **No se sube el motor de acuses a un contrato público; se duplica el patrón con cuidado en `Disclosure`.** Argumentos:
   - **Los acuses de Telework están atados a su dominio.** La llave de `acknowledgements` apunta a `telework_documents`
     (PDF, `DocumentKind` interno, versión por centro) y su audiencia se decide por tipo (Comisión incluida). Para que
     otro módulo lo use habría que volverlo genérico: o una llave polimórfica (sin integridad referencial, que
     `CLAUDE.md` exige) o una tabla nueva con migración de datos sobre evidencia que «nunca se borra», de un módulo ya
     mergeado (PR #61) y bajo la mutación semanal al 95 %.
   - **Un contrato mínimo sería una puerta a Telework.** `Acknowledgements::request($subject)` metería la audiencia, el
     correo y el candado dentro de un módulo de NOM-037 que otro módulo manda. Eso convierte a Telework en un núcleo de la
     plataforma sin habérselo propuesto, y le añade dependientes que frenarán sus cambios.
   - **Los consumidores no tienen la misma forma.** Telework: «versión de PDF × audiencia por centro». Ley Silla:
     «confirmación individual de una evaluación», sin audiencia. Disclosure: «versión de texto × audiencia por centro».
     Solo dos comparten forma; con dos casos, un abstracto adivina mal. La regla es esperar al tercero de la misma forma.
   - **El costo de duplicar es pequeño y acotado:** unas seis clases (petición, audiencia, confirmación, lote manual,
     listener de ingresos, correo) y una tabla, sin ninguna lógica que sea difícil de copiar bien.
   - **Telework no se toca.** Cero riesgo para un módulo ya mergeado y mutado.
3. **Cómo se duplica con cuidado.** Se copian las **garantías**, no el código atado a `DocumentKind`; el plan las convierte
   en pruebas con los mismos bordes que las de Telework:
   - una fila por (versión, persona), con `unique` y `insertOrIgnore` por bloques de 500: pedir dos veces no duplica;
   - confirmar bajo `lockForUpdate`, solo la versión vigente y una sola vez; una versión superada se rechaza y no cambia nada;
   - audiencia = personas **activas hoy** del centro; el porcentaje se calcula sobre ellas;
   - quien entra después (alta, reactivación, cambio de centro) recibe la versión vigente del centro nuevo;
   - correo diferido y único por persona, ids en el job y nada de tokens en logs; sin correo → acuse manual;
   - `check` de coherencia canal/fecha, llaves compuestas con `tenant_id`.
   **Diferencias deliberadas:** sin IP (Telework la guarda; para confirmar un texto añade un dato personal sin servir a un
   fin), sin usuarios de la Comisión, texto en lugar de PDF, y un nombre de tabla distinto (`acknowledgements` ya existe en
   Telework, y `acknowledgments` es de Ley Silla: aquí `disclosure_acknowledgments`).
4. **Deuda registrada, con disparador.** Cuando aparezca un **tercer** módulo con «versión × audiencia por centro»
   (candidato: el reparto del reglamento de Ley Silla a cada persona, hoy solo un estado), se extrae un módulo
   `Acknowledgements` diseñado una vez y se migran Telework y Disclosure en un trabajo propio. Un comentario en
   `RequestAcknowledgements` de ambos módulos apunta al otro.
5. **También anotado, y no se hace aquí:** `PortalAccess` y `PortalIdentity` pertenecerían mejor a `Organization` o a un
   módulo de portal (son de la persona). Moverlos toca a Telework y Ley Silla; es un cambio de imports sin riesgo de datos,
   pero es otro trabajo. Los límites `portal-views` y `portal-actions` los define el provider de Telework y Ley Silla ya
   dependen de ellos por nombre; `Disclosure` también. Una prueba de rutas falla si dejan de existir.

**Limitación conocida del enlace personal.** La raíz `/portal/{token}` es de Telework y no sabe de Disclosure. La persona
llega a su política por el **enlace del correo** (`portal/{token}/policy`), como hoy llega a Ley Silla. Una raíz única que
junte los pendientes de todas las herramientas (un `PortalSection` público que cada módulo implemente) es la solución de
fondo y se deja como trabajo aparte; hacerla aquí obligaría a reescribir el portal de Telework.

### Integración con el Programa y las Quejas

**Regla.** `Disclosure` lee los datos de esos módulos por **contratos del dueño** y los pasa por un **adaptador propio**
(`Support/ProgramMeasureFeed`, `Support/ComplaintChannelFeed`) que mapea a un DTO público de Disclosure con **lista blanca
de campos**. Así la decisión de qué llega a una página pública es de Disclosure, no de cada proveedor, y cambiar al
proveedor toca un archivo. La alternativa (que cada módulo implemente un `DisclosureSource` de Disclosure y se
etiquete) escala mejor a futuras normas, pero invierte la dependencia y reparte el filtro de privacidad de la página pública
entre tres módulos. Se descarta.

**Mientras el proveedor no exista**, el adaptador es el manual: RH publica un texto (ver «Contenidos manuales»). **Quien
aterrice segundo escribe el adaptador** en un commit propio con sus pruebas: si el Programa ya está en `main` cuando se
construya este trabajo, lo escribe este; si las Quejas llegan después, lo escribe el trabajo 4 (y toca solo
`Disclosure\Support`, con su aprobación).

**Qué asumo del Programa (trabajo 2), nombres provisionales:**

| # | Suposición | Para qué la necesito | Si no se cumple |
|---|---|---|---|
| P1 | Un contrato público `forWorkCenter(string $workCenterId): list<PublicMeasure>`, de la empresa actual, que devuelve **solo** medidas que RH marcó «visible para las personas» (la bandera vive en el Programa). | 5.7 b y c | Se muestra solo el texto manual. |
| P2 | `PublicMeasure` trae: `id` opaco, `title`, `topic` ∈ {`risk_factors`, `violence`, `environment`} (el 5.1 a/b/c y el 8.1), `level` 1\|2\|3 (num. 8.5), `status` ∈ {planned, in_progress, done}, `dueOn` (día o nulo), `scope` ∈ {`center`, `areas`}. | Pintar la tarjeta. | El adaptador mapea lo que haya; los campos que falten se omiten. |
| P3 | **Sin personas, sin nombres de áreas, sin riesgo por área, sin notas libres.** `scope` no tiene caso «personas»: una medida del tercer nivel dirigida a individuos no puede ser pública (8.5 c). | Que la página sea segura por construcción. | Disclosure descarta lo que no esté en la lista blanca y una prueba lo exige. |
| P4 | Se lee en vivo, sin eventos ni copia. | Cero sincronía. | — |
| P5 | Su lectura para el inciso g del 7.7 (otro contrato, hacia `Results`) es independiente. | No mezclar una lectura segura para RH con una pública. | — |

`topic` decide el inciso: `violence` y `environment` → 5.7 b; `risk_factors` → 5.7 c (acciones de prevención y, en su caso,
de control). Si el Programa clasifica distinto, solo cambia el mapa del adaptador.

**Qué asumo de las Quejas (trabajo 4), nombres provisionales:**

| # | Suposición | Para qué la necesito | Si no se cumple |
|---|---|---|---|
| Q1 | Un contrato público `publicInfo(): ?ComplaintChannelInfo` de la empresa actual; `null` cuando no hay canal activo. | 5.7 d | Texto manual. |
| Q2 | `ComplaintChannelInfo` trae: `url` (absoluta, del mismo origen: la entrada pública del canal), `anonymousAllowed` (bool), `categories` (etiquetas) y `statement` (frase corta sobre confidencialidad, **escrita y prometida por las Quejas**). | Pintar un botón y una frase. | Solo se muestra el botón si hay `url`. |
| Q3 | La entrada del canal es una ruta pública propia, con su propio límite, encabezados y bitácora. | La página **no** incrusta el formulario. | — |
| Q4 | Disclosure **no promete anonimia por su cuenta**: repite `anonymousAllowed` y `statement` tal cual. | No prometer lo que no controla. | — |
| Q5 | El enlace/QR de las Quejas y el de esta página son credenciales distintas; nada se comparte. | Rotar uno no rompe el otro. | — |
| Q6 | Ningún dato de un caso (ni su existencia, ni un conteo) cruza hacia Disclosure. | Privacidad de las quejas. | — |

Si el diseño de las Quejas decide que su enlace *es* el de la empresa (un solo QR para todo), el cartel de Disclosure
simplemente trae dos QR; esto no cambia lo anterior.

## Instrumento: la plantilla de la política

En código, versionada, en `Instruments` como las demás: contrato **`Nom035Policy`**, versión `dof-2018-10-23`, con
`template(): PolicyTemplate` (`Data/PolicyTemplate`, `Data/PolicySection`). La transcripción es **literal** de la Guía IV
y vive también en `texto-de-referencia.md` (primer commit); una prueba compara cada texto contra el anexo.

Secciones (clave → contenido de la Guía IV):

| Clave | Contenido | Editable | Se puede quitar |
|---|---|---|---|
| `commitments` | «En este centro de trabajo `{legal_name}` en relación con la prevención de los factores de riesgo psicosocial; la prevención de la violencia laboral, y la promoción de un entorno organizacional favorable, se asumen los compromisos siguientes:» + los **nueve compromisos**, uno por línea | sí | **no** |
| `responsibles` | Campo vacío. La **nota** de la Guía IV («La política deberá indicar a los responsables (…)») va como ayuda, no como parte de la política | sí, **obligatorio** | no |
| `resources` | Campo vacío (misma nota como ayuda) | sí, **obligatorio** | no |
| `principle_1` … `principle_8` | «La política se basa en los principios siguientes:» y cada principio; el 1 trae sus ocho incisos a) a h) | sí | sí |

- `{legal_name}` es el único marcador: se resuelve al **publicar y al mostrar** con la razón social de cada centro
  (`WorkCenters::profile()->legalName`); el texto guardado conserva el marcador, así dos centros de razones sociales
  distintas comparten una versión.
- Modelo del contenido: `sections: [{key, heading, text, items[], ordered}]`, **solo texto plano**. Sin Markdown ni HTML: no
  hay analizador que mantener ni vector de inyección en una página pública. Los párrafos del `text` se separan por línea en
  blanco. Un mismo componente de React pinta el contenido en el panel, en la página personal y en la pública.
- Los compromisos y principios de la Guía IV son un **ejemplo no obligatorio**: no hay `origin` ni marca de «modificado» por
  pregunta (a diferencia de NOM-037, aquí no hay un instrumento que proteger de alteraciones).
- Para los contenidos manuales de 5.7 b, c, d y f ver «Contenidos manuales»; su base de texto está en esa sección.

## Modelo de datos

Todas las tablas llevan `tenant_id` (llave compuesta `(id, tenant_id)` en las referencias a otros módulos, como las demás),
aplican `BelongsToCurrentTenant` y traen su prueba de aislamiento (A no lee ni modifica lo de B; por URL, 404). Los
nombres exactos los fija el constructor; la estructura es:

- **`disclosure_documents`**: una versión de un contenido de la empresa. `kind` (`policy|violence_measures|prevention_measures|
  complaint_mechanism|health_effects`), `version` (entera, por empresa y tipo), `status` (`draft|published`), `content`
  (jsonb, el modelo de secciones), `template_version` (nulo en los que no parten de plantilla), `covers` (lista de `a|b|c`;
  solo en `policy`), `created_by`, `published_at` y `published_by` (juntos o ambos nulos).
  - `unique (tenant_id, kind, version)`; índice único parcial: **un solo borrador** por empresa y tipo.
  - `check`: publicado ⇒ `published_at` y `published_by`; `covers` solo con `kind = policy`; `version ≥ 1`.
  - **Trigger de Postgres** (como el de `audit_entries`): una fila `published` no admite UPDATE ni DELETE. Un borrador sí
    se edita y se descarta. Es evidencia (10.4); el candado no depende de que nadie recuerde no tocar.
- **`disclosure_document_centers`**: los centros a los que se publicó una versión. `(document_id, work_center_id)` único;
  llaves compuestas a `disclosure_documents` y `work_centers`. Se escribe solo al publicar; no hay ruta para cambiarla. La
  versión **vigente** de un tipo en un centro es la más alta que lo incluye.
- **`disclosure_acknowledgments`**: la petición de confirmar una versión de la política y, una vez dada, la confirmación.
  `document_id`, `employee_id`, `requested_at`, `acknowledged_at` (nulo hasta confirmar), `channel` (`portal|manual`, nulo
  hasta confirmar), `note` (solo en lo manual), `recorded_by` (el usuario que registró lo manual). `unique (document_id,
  employee_id)`; `check`: `(channel is null) = (acknowledged_at is null)` y `note`/`recorded_by` solo con `manual`.
  Nunca se borra; una versión nueva pide de nuevo (filas nuevas, las viejas quedan).
- **`disclosure_dissemination_records`**: un medio físico o presencial. `work_center_id`, `medium`
  (`poster|bulletin|brochure|meeting|other`), `covered_items` (lista de `a..f`, 5.7), `done_on` (fecha), `note` (≤ 500),
  `recorded_by`. Sin UPDATE ni DELETE desde la app. `check`: `covered_items` no vacío.
- **`disclosure_page_links`**: **una fila por empresa**. `version` (sube al rotar), `token_hash` (sha256 del token derivado, para
  encontrar la fila), `enabled_at`/`disabled_at`, `expires_at` (nulo = no vence), `rotated_at`, `created_by`. `unique
  (tenant_id)`; `check`: `expires_at` posterior a la creación. **El token no se guarda**: se deriva (ver «Página
  permanente»).
- **`disclosure_result_publications`**: «esta evaluación cerrada se publicó a las personas». `campaign_id` (llave compuesta a
  `campaigns`), `work_center_id`, `published_at`, `published_by`, `withdrawn_at`, `withdrawn_by`. Índice único parcial: **una
  publicación activa** por campaña. Retirar y volver a publicar crea una fila nueva; las anteriores se conservan. **Sin
  ninguna columna de persona, invitación o token** (la tabla no puede ligar a nadie con una respuesta).

No hay tabla de «vistas» ni de lecturas de la página (ver «No objetivos»). Todas las columnas de fecha y hora son
`timestampTz`.

## Política: borrador, versiones y publicación

**Borrador.** RH abre «Política y difusión» y pulsa «Redactar»: se crea el único borrador de la empresa copiando la plantilla
(o la última versión publicada, si ya hay una: «Nueva versión desde la vigente»). Edita cada sección, quita las que se
puedan quitar y llena `responsibles` y `resources`. Guardar no valida nada salvo el tamaño (≤ 20 000 caracteres por
contenido, ≤ 40 secciones). Se puede **descartar** el borrador.

**Publicar.** El diálogo pide:

1. **Los centros** (búsqueda en el servidor, `serverSource`; por defecto «todos los centros en uso»); al menos uno, todos de la
   empresa y no archivados.
2. **Qué incisos de 5.1 contempla**: a, b y c, tres casillas. Publicar con alguna sin marcar se permite, con un aviso que
   queda en el tablero (patrón de la cobertura en NOM-037); no se inventa un bloqueo que la norma no pone.
3. **Una casilla de revisión**: «Revisé que cada compromiso y principio es cierto en mis centros». Es obligatoria (aviso del
   anti-objetivo «compromisos falsos»); lo que se guarda es el acto de publicar (`published_by`, `published_at`), no la casilla.
4. `responsibles` y `resources` no vacíos; si faltan, error de validación por campo.

Al publicar, **en una transacción**: se toma `pg_advisory_xact_lock` por (empresa, tipo) para que dos publicaciones
simultáneas no elijan la misma versión (el patrón de `PublishDocument` de Telework); se asigna `version = max + 1`; se
escriben los centros; el borrador pasa a `published`; se escribe la bitácora y el log; se despacha `PolicyPublished` (después
del commit). Una versión posterior para un subconjunto de centros solo sustituye a la anterior **en esos centros**.

**Un centro nuevo** (creado después de publicar) no tiene política: el tablero lo marca «Falta» (D1). Un centro archivado
no cuenta.

## Difusión con acuse

1. **Petición.** El listener (en cola, después del commit) pide el acuse a las personas activas de los centros de la
   versión: una fila por persona, por bloques de 500, con `insertOrIgnore`. Cada persona **con correo** recibe un correo
   (diferido 120 s y único por persona, como `SendRisksToConfirm`/`SendPortalReminder`) con su enlace personal
   `portal/{token}/policy`, donde el token sale de `PortalAccess::token()`. Quien no tiene correo queda «Sin correo» para el
   registro manual.
2. **Quien llega después.** Listener sobre `EmployeeCreated`, `EmployeeReactivated` y `EmployeeMoved` (en cola, tras el
   commit): pide la versión vigente del centro de la persona. Una baja no borra nada: la fila queda y la persona sale del
   porcentaje porque este se calcula sobre personas **activas hoy** en los centros de la versión.
3. **Confirmar.** En `portal/{token}/policy` la persona lee la política vigente de su centro y pulsa «Confirmo que la leí».
   Bajo candado, una sola vez, solo la versión vigente; con una versión superada el botón desaparece y se le pide la nueva.
   Se guardan fecha, hora y versión (no IP). Un token no válido da 404 sin decir nada más (`PortalAccess::open()` ya
   devuelve `null` por todas las causas).
4. **Avance (RH).** Por versión: confirmadas de activas, medidor de arco, lista **paginada** con búsqueda y filtro
   «Pendientes». Acuse en papel: RH elige personas (varias a la vez) y registra fecha y nota; la fecha no puede ser
   anterior a la publicación ni futura. «Recordar a los pendientes» reenvía el correo (único por persona: pulsarlo dos veces
   no manda dos).
5. **Medios físicos.** «Registrar medio» crea un `disclosure_dissemination_record`. Es lo que la norma nombra como evidencia
   (folletos, boletines, carteles, 10.2).

El acuse es **de la política (5.7 a)**, no de los demás bloques de la página: los otros se difunden poniéndolos a la vista
(página o medio físico). El acuse es de Humetría, no de la norma (C-33).

## Página permanente de las personas trabajadoras

**Qué es.** Un solo enlace por empresa (`workers/{token}`), con selector de centro, que se imprime como cartel con QR. Es de
lectura: no tiene formularios ni cuenta. La confirmación de lectura **no** vive aquí (necesita identidad): vive en el enlace
personal.

**Modelo de acceso.**

- **Token derivado, como el del portal personal** (`HMAC-SHA256(APP_KEY, "page|{tenant_id}|{version}")` en base64url, 43
  caracteres; solo se guarda su sha256). Por qué derivado y no aleatorio con hash (como `ReportShare`): el cartel se imprime
  una vez y RH necesita **volver a ver** el enlace sin rotarlo; con hash a ciegas solo se vería al crearlo y olvidarlo
  obligaría a reimprimir. Riesgo aceptado, ya aceptado en Telework: quien tenga `APP_KEY` puede derivarlo; por eso rotar
  `APP_KEY` exige conservar la anterior en `APP_PREVIOUS_KEYS`, o todos los carteles mueren.
- **Estados:** activo, desactivado (`disabled_at`), vencido (`expires_at ≤ ahora`), rotado (el token viejo ya no deriva de la
  versión vigente). El **vencimiento es opcional** (por defecto no vence: es una «página permanente»); acepta una fecha
  futura de hasta 36 meses (`config/disclosure.php`).
- **Rotar** sube `version`: el cartel viejo deja de servir al instante y el diálogo lo advierte con el motivo típico
  («el enlace se filtró a personas que ya no trabajan aquí»). **Desactivar/Activar** es el interruptor sin reimprimir.
- **Todo fallo es el mismo 404 neutro:** enlace desconocido, rotado, desactivado, vencido, empresa bloqueada o un
  `workCenter` que no es de la empresa. La página dice «Este enlace ya no está activo. Pídale a Recursos Humanos el vigente»,
  **sin nombrar la empresa ni la causa** (patrón de `Enlace-Vencido` y de `PortalUnavailable`).
- **Quién gestiona el enlace:** `manage-disclosure` (owner y hr). Verlo, crearlo, rotarlo, desactivarlo y cambiar el
  vencimiento van a la bitácora (sin el token).

**Rutas públicas** (todas nombradas, una razón cada una en `RouteSecurityTest`):

| Ruta | Nombre | Qué hace |
|---|---|---|
| `GET workers/{token}` | `workers.show` | Centros de la empresa (paginados, con búsqueda en el servidor); si hay uno solo, lo muestra |
| `GET workers/{token}/centers/{workCenter}` | `workers.center` | Las seis secciones de 5.7 de ese centro |
| `GET portal/{token}/policy` | `portal.policy.show` | La política vigente de la persona y su estado de acuse (enlace personal) |
| `POST portal/{token}/policy/acknowledge` | `portal.policy.acknowledge` | Confirma, una vez |

**Límites de peticiones.** Las dos rutas de `workers` **no** usan `portal-views`: ese límite es de 30/min **por enlace**, y
este enlace es uno solo, que cientos de personas abren a la vez al escanear el QR del cartel. Un límite propio,
`workers-page`: 60/min por dirección y un techo generoso por enlace (1 200/min) contra raspado. Las rutas del enlace personal
sí usan `portal-views` y `portal-actions`.

**Seguridad de la respuesta** (las pruebas lo afirman en cada ruta pública):

- Encabezados de `SecurityHeaders` con su CSP de nonce, sin relajarla; **ningún origen externo**, fuentes ni scripts.
- Además, solo en estas rutas: `X-Robots-Tag: noindex, nofollow` y `<meta name="robots">`, `Cache-Control: no-store, private`
  (como `SharedReportController`) y `Referrer-Policy: no-referrer` (el token va en la ruta; `SecurityHeaders` respeta un
  encabezado que la respuesta ya trae). Sin enlaces salientes: el contenido es texto plano y el único enlace es el botón de
  queja, del mismo origen.
- Las pruebas de navegador afirman `->assertScript(cspViolations(), '[]')`, axe y 375 px.
- Impresión: la página tiene una hoja de estilos de impresión para sacar un boletín.

**Qué nunca contiene** (una prueba recorre las props de las páginas públicas y lo afirma): nombres o correos de personas,
`employee_id`, el token del enlace personal de nadie, nombres de áreas, el correo del owner, ninguna respuesta, ninguna
medida dirigida a personas, nada de la Guía I. El contacto que ven las personas es el que RH escribe en `responsibles`.

## Resultados para las personas (7.8)

**Qué se muestra.** Por centro, el resultado de la **última evaluación cerrada que RH publicó**: fecha de cierre (día, sin
hora), cuántos cuestionarios se contaron y la distribución por nivel de riesgo del nivel final, las categorías y los
dominios, con la nota fija de C-14 («cuántos cuestionarios quedan en cada nivel; no es un promedio»). Mismas cifras que ve RH
(pregunta 4), **sin el desglose por áreas**. El título distingue el alcance de 5.7 e: «identificación y análisis de los
factores de riesgo psicosocial» (Guía II, 16 a 50) y «…y evaluación del entorno organizacional» (Guía III, más de 50).

**Cómo se calcula (C-23).**

- La página **solo** lee `RiskResults::forCampaign($campaign)`, el servicio único que ya aplica el mínimo y el estado: no
  hay otra ruta de datos. El adaptador desecha `areas` y `hiddenAreas`.
- **La página no ofrece filtros**, así que el único grupo es el centro y el mínimo se aplica a él. Cualquier filtro futuro
  tiene que pasar por `RiskResults` con el mínimo después de filtrar; una prueba afirma que la página llama siempre con
  `areaId = null`.
- Estados de `RiskBreakdown`: `available` se muestra; `open` (evaluación abierta, C-27) y `not_scored` (solo Guía I) **no se
  muestran** ni se pueden publicar; `below_minimum` se publica pero la página dice «Los resultados de este centro no se
  muestran porque contestaron muy pocas personas para proteger su confidencialidad»; no da el conteo.
- **Centros de hasta 15 personas:** no aplica (num. 2 a: no tienen 7.x). El tablero lo dice y la página no tiene el bloque.
  Sin evaluación aún, `Headcount` decide si 7.8 aplicaría (≥ 16).

**Publicación.** RH publica o retira por evaluación (`disclosure_result_publications`); se registra en la bitácora. Si hay
una evaluación cerrada más reciente sin publicar, la página sigue mostrando la publicada, y el tablero avisa. Al publicar el
diálogo recuerda qué verá la plantilla («estas cifras las verá cualquier persona con el enlace») y, si el Programa existe y
el centro tiene riesgo Medio o más, que conviene publicar con las medidas visibles (aviso, no bloqueo).

**Por qué manual y no automático al cerrar** (pregunta 3): las cifras con riesgo alto sin contexto ni medidas asustan; RH
debe poder preparar las medidas primero. A cambio, el tablero y el resumen semanal presionan para que no se quede sin
publicar, porque 7.8 sí es una obligación.

**Riesgo residual declarado.** La página es leída por la plantilla, no solo por RH. Con un grupo de cinco, «1 persona en riesgo
alto» es un dato que un compañero podría intentar atribuir. El mínimo de 5 es el mismo de RH y el propósito de C-23; si el
usuario quiere un mínimo **mayor** para la página, hay que agregar un parámetro a `RiskResults` (no se duplica el servicio):
pregunta 5.

## Contenidos manuales de 5.7 b, c, d y f

Mientras el Programa o las Quejas no existan, y siempre como complemento, RH publica texto para esos incisos con el mismo
mecanismo de versiones que la política (`disclosure_documents` con otro `kind`), sin acuse:

| Inciso | `kind` | Texto base que ofrece la plataforma | Si hay proveedor |
|---|---|---|---|
| 5.7 b | `violence_measures` | Vacío con ayuda de cómo redactarlo | Se muestran las medidas `violence`/`environment` del Programa; el texto manual va debajo como «Otras medidas» |
| 5.7 c | `prevention_measures` | Vacío con ayuda | Se muestran las medidas `risk_factors`; texto manual debajo |
| 5.7 d | `complaint_mechanism` | Vacío con ayuda («buzón en…, correo…, persona…») | Se muestra el canal (botón + `statement`); el texto manual va debajo |
| 5.7 f | `health_effects` | Párrafo base **con la definición del DOF 4.7** (trastornos de ansiedad, no orgánicos del ciclo sueño-vigilia, estrés grave y de adaptación) | — |

La regla es **mezclar, no reemplazar**: lo que llega del proveedor nunca desaparece porque RH escribió un texto. El texto
base de 5.7 f se rotula «borrador de Humetría, basado en el numeral 4.7; revíselo antes de publicarlo» y es una de las
**tres cosas que valida una persona con criterio** (ver «Pendiente»), porque el DOF dice qué difundir pero no el texto.

## Qué cuenta como cumplido (tablero)

Por centro, tres estados por requisito, como en Ley Silla: **Cumple**, **Con aviso** (cumple, pero hay algo que revisar) y
**Falta**. Ninguno es un «apto»: son conteos de lo registrado. Un requisito que no aplica se rotula «No aplica», con su
numeral.

«**Entregado** el inciso x en el centro» significa: la página está activa (enlace sin desactivar ni vencer) **o** existe un
registro de medio físico de ese centro que cubre ese inciso. La política y los demás contenidos solo cuentan si existen.

| Req. | Numeral | Cumple cuando | Con aviso | Falta |
|---|---|---|---|---|
| D1 | 5.1 | el centro tiene una política publicada vigente | — | no tiene |
| D2 | 5.1 a–c | la versión vigente declara a, b y c | declara menos de tres | — |
| D3 | 5.1, 5.7 a | la política vigente está entregada **y** los acuses pedidos están al 100 % de las personas activas | entregada, con acuses pendientes (la norma no los pide) | existe y no está entregada |
| D4 | 5.7 b | hay medidas o texto de b, entregados | — | no hay contenido o no está entregado |
| D5 | 5.7 c | ídem para c | — | ídem |
| D6 | 5.7 d | hay canal activo o texto de d, entregado | — | ídem |
| D7 | 5.7 e, 7.8 | la última evaluación cerrada del centro está publicada y se puede mostrar | publicada pero bajo el mínimo; o hay una cerrada más reciente sin publicar | no hay evaluación cerrada; o no se publicó. **No aplica** a centros de hasta 15 |
| D8 | 5.7 f | hay texto publicado de f, entregado | — | no hay |
| D9 | Tablas 4 y 7 | — | la última evaluación publicable tiene algún cuestionario en Medio o más y la política vigente es anterior a su cierre («revise la política»); con Bajo, «mayor difusión» | — |
| D10 | 10.4 | — | el enlace vence en ≤ 30 días | — |

D9 solo se muestra si `RiskResults` puede dar las cifras (con menos del mínimo no se sabe y no se dice). Los estados se
**derivan** al leer; no se guardan.

## Roles y permisos (Spatie, como los actuales)

Permisos nuevos por migración, como `manage-seating`:

| Permiso | owner | hr | viewer | committee |
|---|---|---|---|---|
| `view-disclosure`: tablero, texto de la política y de los contenidos, historial de versiones, conteos de acuses | ✓ | ✓ | ✓ (sin nombres, sin lista de pendientes, sin enlace) | |
| `manage-disclosure`: redactar y publicar, acuses y recordatorios, registro manual, medios, contenidos, enlace de la página, publicar/retirar resultados, expediente imprimible | ✓ | ✓ | | |

- La Comisión de seguridad e higiene **no** entra: `LimitCommitteeToTelework` ya le cierra todo lo que no sea
  `tenant.telework.*` o `tenant.seating.*`, y las rutas de Disclosure son `tenant.disclosure.*`. Una prueba lo afirma.
- `viewer` ve el estado y los conteos, pero no la lista de quiénes no han confirmado (nombres), no el enlace y no el
  expediente.
- Las personas del enlace público y del enlace personal no tienen permisos: el enlace es la credencial.
- Publicar resultados exige `manage-disclosure`; ver los resultados en el panel sigue gobernado por los permisos de
  `Results`, que no cambian.

## Logs y bitácora

- Logs `disclosure.*` en pasado, solo con IDs y estados: `disclosure.draft_saved`, `disclosure.draft_discarded`,
  `disclosure.published`, `disclosure.acknowledgment_requested`, `disclosure.acknowledged`,
  `disclosure.acknowledgment_recorded`, `disclosure.dissemination_recorded`, `disclosure.page_link_issued`,
  `disclosure.page_link_rotated`, `disclosure.page_link_disabled`, `disclosure.page_link_enabled`,
  `disclosure.page_link_expiry_changed`, `disclosure.results_published`, `disclosure.results_withdrawn`. **Nunca**: el token
  de la página ni el personal, nombres, correos, IP, el texto de la política ni notas de acuses manuales.
- A la bitácora (`RecordAudit`): publicar una versión (cualquier tipo), cambios del enlace (crear, rotar, desactivar, activar,
  vencimiento), publicar y retirar resultados, registrar acuses manuales (conteo, no nombres), ver o imprimir el expediente.
  **No** se audita cada apertura de la página (es lectura pública de contenido que la empresa quiso publicar; auditarla sería
  una vigilancia de la plantilla que el diseño evita).
- `RedactSensitiveData` agrega las llaves nuevas (`page_url`, `worker_page_link`, `acknowledgment_note`…) con su prueba.

## Pantallas

Todas siguen el rediseño de `docs/design-system/pantallas/`. Referencias:

| Pantalla | Quién | Referencia del rediseño |
|---|---|---|
| **NOM-035 › Política y difusión**: encabezado con estado, tablero D1 a D10 y centros (paginados) con su estado | `view-disclosure` | `NOM035` (centros) y `Eval-Activa` (ficha, medidor de arco, pestañas con conteo) |
| Pestaña **Política**: versión vigente, borrador, historial paginado; diálogo de publicación | `view` (leer), `manage` (editar) | `Ajustes` (campos) y `Empleados-Alta` (panel lateral para el diálogo) |
| Pestaña **Difusión**: acuses (medidor, lista paginada, pendientes, registro manual) y medios físicos | `manage` | `Eval-Activa` (avance) y `Empleados` (tabla) |
| Pestaña **Contenidos**: 5.7 b, c, d y f, con el origen de cada bloque («Del Programa», «Del canal», «Texto de RH») | `manage` | `Ajustes` |
| Pestaña **Resultados**: evaluaciones cerradas por centro, publicar/retirar | `manage` | `Eval-Cerrada` |
| Pestaña **Página**: enlace, QR, copiar, rotar, desactivar, vencimiento, «Ver como persona», cartel | `manage` | `Ajustes` + `Confirmar` (diálogos de rotar) |
| **Expediente de difusión** imprimible | `manage` | `results/report` |
| **Cartel** imprimible (A4, enlace y QR) | `manage` | `results/report` |
| **Política en el enlace personal** (`portal/{token}/policy`) | la persona | `Encuesta-Movil` |
| **Página pública**: centros y centro (seis secciones) | cualquiera con el enlace | `Encuesta-Movil` (móvil primero) y `Eval-Cerrada` (medidor de resultados) |
| **Enlace no activo** | cualquiera | `Enlace-Vencido` |

- Las páginas de React son `pages/disclosure/*`, componentes en `components/disclosure/*`, una sola pieza
  (`policy-sections`) que pinta el contenido en las tres superficies, y la lógica en `lib/disclosure.ts` (etiquetas, estados,
  conteos). Sin Markdown: no se reutiliza el analizador de Humi (`components/assistant` es de otro módulo).
- Toda lista va paginada en el servidor (centros del tablero, historial, acuses, medios, evaluaciones, centros de la página
  pública) y cada una entra en `tests/Feature/PaginatedListsTest.php`. El tablero resume con conteos y manda el detalle a
  un clic.
- Menú: `Cumplimiento › NOM-035` pasa de un enlace a un submenú (Evaluaciones, Política y difusión; Programa y Quejas
  cuando existan), como NOM-037. Los trabajos 1, 2 y 4 tocan la misma lista: se coordinan al construir.
- El riesgo, cuando se muestra, va con `RiskLevel`; el panel tutea; las dos páginas de personas tratan de «usted».
- Estados vacíos con siguiente paso: «Aún no hay política» → «Redactar a partir de la plantilla».
- Se revisa en el navegador en claro, oscuro y a 375 px contra la pantalla de referencia antes de cerrar.

## Expediente de difusión y cartel

**Expediente.** Página imprimible por centro (como el informe 7.7 y el de NOM-037), con una sección por requisito D1 a D10:
la versión vigente de la política con su fecha, los incisos que declara, la difusión (acuses confirmados de activas, medios
físicos con fecha y los incisos que cubren, el estado y la fecha de emisión del enlace), los contenidos 5.7 b, c, d y f con
su fecha de publicación y su origen, y la publicación de resultados (evaluación, fecha). **Sin nombres**: los acuses van
como conteo; el detalle se lista con un enlace dentro de la aplicación, porque el expediente puede salir del sistema. Una
nota fija dice que **lista lo registrado y no certifica cumplimiento**. Ver o imprimir va a la bitácora.

**Cartel.** Una hoja A4 con el nombre de la empresa, la frase «Consulte aquí la política de prevención, las medidas, cómo
presentar una queja y los resultados», el enlace en texto grande y su **QR**. El QR exige una librería: `bacon/bacon-qr-code`
ya está instalada como dependencia de Fortify pero **no declarada** en `composer.json`; usarla en el código de la app pide
declararla, y eso es una dependencia que requiere aprobación (`CLAUDE.md`, regla 5; pregunta 7). Sin aprobación, el cartel
lleva solo el enlace y el resto del trabajo no cambia.

## Criterios nuevos que propongo para `criterios.md` (versión 1.3)

Se redactan con su fuente en el primer commit y se agregan a «Cómo se prueba». Los números siguen a C-29.

| Criterio | Qué dice | Tipo |
|---|---|---|
| C-30. Política por escrito, por centro y con versiones | La política es texto versionado; cada versión publicada es inmutable; la vigente de un centro es la más alta que lo incluye. 5.1, 4.9, 10.4 | Norma + Interpretación |
| C-31. La plantilla es la Guía IV y no obliga | Se ofrece literal y editable; responsables y recursos son obligatorios por la nota de la guía; publicar exige revisar que los compromisos son ciertos | Norma + Humetría |
| C-32. Qué es difundir y con qué medios se evidencia | Los seis incisos de 5.7; evidencia: carteles, boletines y folletos (10.2) o medios electrónicos (10.4); la página y el registro de medios físicos cuentan como entrega | Norma + Interpretación |
| C-33. Acuse de lectura de la política | Confirmación por persona con fecha y hora; no la pide la norma; no cuenta como requisito | Humetría |
| C-34. Resultados para las personas | Solo evaluación cerrada, publicada por RH, centros de 16 o más, centro completo sin filtros, mínimo C-23, sin Guía I; 7.8 y 5.7 e | Norma + Humetría |
| C-35. La página permanente y su enlace | Un enlace por empresa, derivado, rotable y desactivable; nada individual; mismo 404 neutro por toda causa | Humetría |
| C-36. Revisar la política con riesgo Medio o más | Aviso mientras la política vigente sea anterior al cierre de la evaluación (Tablas 4 y 7; consistente con C-15) | Norma |

Al cerrar: se actualiza «Lo que la plataforma no cubre» (5.1, 5.7 y el párrafo de 7.8 desaparecen), se agrega a C-29 que la
consulta de la plantilla ya tiene su camino (el informe sigue siendo para RH y el auditor) y se borra el trabajo 3 de
`gaps-pendientes.md`.

## Decisiones del diseñador

El usuario puede corregir cualquiera al leer:

1. **Módulo propio `Disclosure`**; la definición de la plantilla en `Instruments`.
2. **Reutilizar `PortalAccess`; duplicar el patrón de acuse, no extraerlo ni subir el de Telework.** Argumentos arriba;
   disparador de extracción: el tercer módulo de la misma forma.
3. **Integración con el Programa y las Quejas por contratos del dueño + adaptador propio con lista blanca**, y manual
   mientras no existan; quien aterriza segundo escribe el adaptador.
4. **Contenido de la política en secciones de texto plano**, sin Markdown ni HTML, sin PDF.
5. **Una versión por empresa, publicada a un conjunto de centros**; la vigente por centro es la más alta que lo incluye.
6. **Token de la página derivado (HMAC con versión), no aleatorio con hash**, para poder volver a mostrarlo sin rotar.
7. **Límite de peticiones propio para la página** (el de `portal-views` por enlace tumbaría un cartel escaneado por toda la
   plantilla).
8. **Resultados: publicación manual, centro completo, mismas cifras que RH sin áreas**; sin filtros en la página.
9. **Acuse sin IP**; solo de la política; no se exige 100 % para «entregada».
10. **Sin vistas ni estadísticas** de la página y sin auditar cada apertura.
11. **Trigger de Postgres** para que una versión publicada no cambie: es evidencia.
12. **Texto base de 5.7 f sacado del numeral 4.7 del DOF**, rotulado borrador de Humetría.
13. **Aviso de revisar la política (D9)** derivado de las Tablas 4 y 7, solo cuando las cifras se pueden mostrar.
14. **Los centros de hasta 15 personas** tienen política, difusión y página, sin resultados.

## Requisitos de producción

- **Colas con Horizon y el scheduler corriendo:** las peticiones de acuse, los correos y el resumen semanal van en cola.
- **`APP_PREVIOUS_KEYS` al rotar `APP_KEY`:** el enlace de la página y los personales se derivan de la llave. Rotarla sin
  conservar la anterior mata los carteles impresos.
- **Límites de peticiones:** `workers-page` se define en el provider de Disclosure; `portal-views` y `portal-actions` los
  define el de Telework.
- **Configuración en `config/disclosure.php`** (con `.env.example`, sin secretos): meses máximos de vencimiento del enlace,
  tamaño de página de las listas, límites de `workers-page`. El mínimo por grupo sigue en `results.min_group`.
- Sin disco privado nuevo (no hay archivos). El QR (si se aprueba) se genera en el servidor como SVG en línea; la CSP ya
  permite `img-src data:` y el SVG en el HTML no necesita nada nuevo.

## Pruebas (resumen; el detalle en el plan)

- **Instrumento:** cada texto de la Guía IV contra `texto-de-referencia.md`; nueve compromisos, ocho principios, el 1 con
  ocho incisos; `{legal_name}` es el único marcador.
- **Versiones y publicación:** la primera es la 1, la segunda la 2; **dos publicaciones simultáneas** no repiten número;
  publicar sin centros, con un centro de otra empresa o archivado → rechazado; sin `responsibles` o `resources` →
  rechazado por campo; sin la casilla de revisión → rechazado; con a, b o c sin marcar → publica y el tablero avisa; solo un
  borrador a la vez; descartar; **un UPDATE o DELETE crudo sobre una fila publicada falla** (trigger); una versión para un
  subconjunto de centros solo sustituye a la anterior en esos centros; un centro creado después queda en «Falta».
- **Acuses:** la audiencia son las **personas activas** del centro; 499, 500 y 501 personas (bordes del bloque); pedir dos
  veces no duplica; ingreso, reactivación y cambio de centro piden la vigente del centro nuevo; una baja sale del porcentaje
  y su fila queda; confirmar dos veces, o confirmar una versión superada, se rechaza y no cambia nada; un empleado de otra
  empresa o un token de otra empresa → 404; sin correo → no hay envío y sí registro manual; registro manual en lote con
  fecha futura o anterior a la publicación → rechazado; recordatorio dos veces → un solo correo.
- **Enlace de la página:** derivado y reproducible; solo se guarda el hash; **rotar mata el viejo y abre el nuevo**; desactivar
  y activar; vencimiento exacto (`expires_at` igual a ahora ya no abre; un segundo antes, sí); fecha de vencimiento de hoy →
  rechazada, de mañana → aceptada, de 36 meses y un día → rechazada; una llave de `APP_PREVIOUS_KEYS` abre, una descartada
  no; un `workCenter` de otra empresa → el mismo 404 neutro, **sin diferencia observable** con un enlace inventado; empresa
  bloqueada → 404.
- **Seguridad de la respuesta:** `X-Robots-Tag`, `Cache-Control: no-store`, `Referrer-Policy: no-referrer`, CSP con nonce y
  sin orígenes externos en cada ruta pública; `RouteSecurityTest` con las cuatro rutas y su razón; dos direcciones distintas no
  se limitan entre sí y la 61.ª petición de una dirección da 429; abrir el enlace desde cien direcciones no da 429.
- **Resultados:** abierta → no se muestra ni se publica; cerrada y **sin publicar** → no se muestra; publicada → se muestra;
  retirada → no; **4 cuestionarios → no se muestra, 5 → sí**; solo Guía I → no aplica; centro de 15 → sin bloque, de 16 → con;
  una evaluación cerrada más nueva sin publicar → la página sigue con la publicada y el tablero avisa; publicar la campaña de
  otra empresa → 404; **un espía afirma que `forCampaign` se llama siempre con `areaId = null`**; las props no tienen
  `areas`, ni nombres de áreas, ni conteos de áreas.
- **Privacidad:** una prueba recorre las props de **cada página pública** y de las de RH y afirma que no hay nombres,
  correos, ids de empleado, tokens ajenos, respuestas ni la Guía I; otra afirma que ninguna línea de log ni de la bitácora
  contiene el token, un correo, un nombre ni el texto de la política; el esquema de `disclosure_result_publications` no tiene
  columnas de persona, invitación o token, y ninguna tabla de Disclosure tiene llave hacia `responses`.
- **Integración:** con el contrato del Programa falso: solo se pintan los campos de la lista blanca; un `scope` o campo
  inesperado se ignora; sin Programa, solo el texto manual; **con ambos, se mezclan sin que uno borre al otro**. Igual con el
  canal: sin `url` no hay botón; la página repite `anonymousAllowed` y no agrega promesas.
- **Roles:** `viewer` ve el tablero sin nombres, sin lista de pendientes y sin enlace; `committee` recibe 403 en cualquier
  ruta de Disclosure; A no ve a B (consulta y URL → 404).
- **Consultas:** el tablero, la página pública de un centro y la lista de acuses afirman su número de consultas
  (`expectsDatabaseQueryCount`).
- **Arquitectura:** `Disclosure` no usa internos de otro módulo (solo `Contracts`, `Events` y `Data`); ninguno usa los suyos;
  `Actions` y `Models` sin HTTP; las páginas nuevas están en `PaginatedListsTest` y `BrowserCoverageTest`.
- **Mutación:** `mutates()` en la publicación, la audiencia, la petición y confirmación de acuses, el estado del enlace y su
  derivación, la selección de la evaluación publicada y el cálculo del tablero.
- **Navegador:** redactar y publicar una política; la persona confirma desde el celular; la página pública a 375 px en claro
  y oscuro con `visitPage()`, axe y `cspViolations()`; el enlace no activo.

## Tamaño y descomposición en commits

**Tamaño: L** (más cerca de M si se recortan los dos últimos funcionales): ~6 migraciones, ~45 clases PHP, 11 páginas de
Inertia, un contrato de `Campaigns` y uno de `Instruments`. Un solo PR con estos commits atómicos (cada uno deja la suite en
verde; un comportamiento cada uno, con su prueba primero):

| # | Commit | Notas |
|---|---|---|
| 1 | Docs: la Guía IV literal en `texto-de-referencia.md`; criterios C-30 a C-36 | Primero, para escribir las pruebas contra el anexo |
| 2 | `Instruments`: `Nom035Policy` y la plantilla | Prueba contra el anexo |
| 3 | `Disclosure`: esqueleto (provider, permisos, reglas de arquitectura, rutas vacías, comité cerrado, submenú) | |
| 4 | Borrador de política desde la plantilla: crear, editar, descartar | |
| 5 | Publicar una versión a centros: versiones, candado, trigger, cobertura, evento; tablero D1 y D2 | |
| 6 | Pedir acuses: audiencia, listener, ingresos, correo | |
| 7 | Confirmar desde el enlace personal | |
| 8 | Avance para RH, registro manual por lotes y recordatorio | |
| 9 | Registro de medios físicos; tablero D3 | |
| 10 | Enlace de la página: derivar, rotar, desactivar, vencer; pantalla de gestión | |
| 11 | Página pública con la política: encabezados, límite, 404 neutro, selector de centros, navegador | |
| 12 | Contenidos manuales 5.7 b, c, d y f y su sección en la página; tablero D4 a D6 y D8 | |
| 13 | `Campaigns`: `latestClosedEvaluations` | Commit propio, con sus pruebas |
| 14 | Resultados para las personas: publicar/retirar, sección de la página, mínimo; tablero D7 y D9 | |
| 15 | Adaptadores del Programa y de las Quejas | **Solo los que tengan proveedor en `main`**; si no, los escribe el trabajo que aterriza después |
| 16 | Expediente de difusión y cartel (QR si se aprueba) | |
| 17 | Planificador y resumen semanal al owner (D10, política sin publicar, resultados sin publicar) | **Recortable**: sin él, el tablero sigue funcionando |
| 18 | Docs de cierre: «lo que no cubre» de `criterios.md`, quitar el 3 de `gaps-pendientes.md`, README | |

Cada comportamiento nuevo con su prueba de aislamiento de tenant, su prueba de props y su prueba de navegador.

## Lo que queda al constructor

Nombres finales de tablas, columnas, clases y rutas (con la restricción de no chocar con `acknowledgements` ni
`acknowledgments`); textos de UI y de correos en español (tuteo en el panel, «usted» en las páginas de personas); redacción
de la ayuda de cada contenido manual y del texto base de 5.7 f; el diseño visual de las seis secciones de la página y del
cartel, siguiendo las pantallas de referencia; los tamaños de página de las listas; cómo se pinta el submenú de NOM-035 junto
a los otros trabajos; el orden exacto de los commits.

## Pendiente

Nada que impida aprobar. Quedan **tres cosas que valida una persona con criterio legal o médico antes de lanzar**, no el
constructor: el texto base de 5.7 f (posibles alteraciones a la salud), la lectura de que la entrega de 5.7 se cubre con la
página activa o con un medio físico registrado, y el aviso que acompaña a los compromisos de la plantilla de la Guía IV.

## Preguntas abiertas para el usuario

Contéstalas todas juntas. Cada una trae mi recomendación por defecto.

1. **¿Una política por empresa aplicada a los centros que elijas, o una política por centro?** Recomiendo **por empresa,
   publicada a los centros que elijas**: una sola redacción para cinco centros, con la razón social de cada uno puesta
   al mostrarla; la versión vigente de un centro sigue siendo independiente.
2. **¿El acuse de lectura es solo de la política, o también de los demás bloques de 5.7?** Recomiendo **solo de la política**
   (5.7 a). Los demás se difunden poniéndolos a la vista; confirmar la lectura de todo sería burocracia que la norma no pide.
3. **¿Los resultados se publican a la página cuando RH lo decide, o solos al cerrar la evaluación?** Recomiendo **cuando RH lo
   decide**, con aviso en el tablero y en el resumen semanal si se queda sin publicar, para que pueda preparar las medidas antes.
4. **¿Qué cifras ve la plantilla?** Recomiendo **las mismas que RH** (nivel final, categorías y dominios), **sin áreas**, con
   la nota de que cuentan cuestionarios y no son promedio. Alternativa: solo el nivel final.
5. **¿El mínimo de respondentes de la página es el mismo de RH (5), o más alto?** Recomiendo **el mismo (5)**, porque es el
   servicio único de C-23 y no se duplica; si prefieres uno mayor (p. ej. 10), se agrega un parámetro a `RiskResults` en
   un commit propio.
6. **¿El enlace de la página vence?** Recomiendo **no vence por defecto** (es «permanente»), con rotación y desactivación
   manuales, y un vencimiento opcional de hasta 36 meses, con aviso a 30 días si lo ponen.
7. **¿Apruebas declarar `bacon/bacon-qr-code` en `composer.json` para el QR del cartel?** Ya está instalada (viene con
   Fortify); declararla es un cambio de dependencias. Recomiendo **sí**; sin ella, el cartel lleva solo el enlace.
8. **¿Se puede adjuntar el PDF de la política ya firmada?** Recomiendo **no en esta entrega**: se publica el texto (se pega o
   se parte de la plantilla). Evita un disco privado, un servicio de archivos y la extracción de metadatos; se puede
   agregar después.
9. **¿Quién revisa el texto base de 5.7 f (alteraciones a la salud) y las cláusulas de la plantilla?** Recomiendo que lo
   revise **una persona con criterio médico/legal antes del lanzamiento**; mientras tanto va rotulado «borrador de
   Humetría» y se parte de la definición del numeral 4.7.
10. **Si el Programa o las Quejas no existen todavía, ¿RH puede publicar un texto manual en su lugar?** Recomiendo **sí**,
    y que cuando el módulo exista se **mezcle** (lo automático arriba, el texto de RH debajo) y nunca lo reemplace.
11. **¿Quién gestiona y quién solo mira?** Recomiendo **owner y hr gestionan** (`manage-disclosure`); **viewer** ve el
    tablero y los conteos sin nombres ni enlace; la Comisión de seguridad e higiene no entra.
12. **¿Quién escribe el adaptador del Programa y el de las Quejas?** Recomiendo **el trabajo que aterrice segundo**, en un
    commit propio que toca solo `Disclosure\Support`. Si el Programa ya está en `main` al construir esto, lo escribo aquí.
13. **¿Se extrae ya un módulo `Acknowledgements` genérico, o se duplica el patrón en `Disclosure`?** Recomiendo **duplicar
    con cuidado** (argumentos en «Revisión del módulo Telework») y extraer cuando haya un tercer módulo con «versión × audiencia
    por centro»; queda como deuda con ese disparador.
14. **¿Se agrega un interruptor «centro con certificado NMX-R-025-SCFI-2015 vigente» que dé por cumplidos 5.1 b) y 5.7 d)
    (num. 2, último párrafo)?** Recomiendo **no en esta entrega**: es un dato que solo el cliente puede afirmar y la
    exención está sujeta a que el certificado esté vigente; basta con que pueda publicar el texto de 5.7 d.
15. **¿Se registran los medios físicos (cartel, boletín, folleto) con fecha, centro e incisos que cubren?** Recomiendo **sí,
    mínimo** (una tabla y un formulario): es literalmente lo que la norma nombra como evidencia (10.2) y no todos los
    centros tendrán correo para todos.
16. **¿El resumen semanal al owner entra en este trabajo?** Recomiendo **sí, como último commit recortable**: política sin
    publicar, resultados sin publicar y enlace por vencer; solo al owner, como los demás resúmenes.
17. **¿El submenú de NOM-035 («Evaluaciones», «Política y difusión») se hace en este trabajo?** Recomiendo **sí**, y que los
    trabajos 1, 2 y 4 agreguen sus entradas al mismo submenú.
