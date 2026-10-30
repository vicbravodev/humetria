# NOM-035 como instrumento, campañas y la encuesta del empleado

Fecha: 2026-10-29. Estado: propuesta.

Anexo obligatorio: [`2026-10-29-nom-035-reference.md`](2026-10-29-nom-035-reference.md). Ahí está, literal del DOF,
el texto de las tres guías, las tablas de calificación, los rangos con sus `≤` verificados, la muestra y los criterios
oficiales de la STPS. Este documento dice **qué se construye**; el anexo dice **qué dice la norma**.

## Intención

Hoy Humetría sabe a quién evaluar (empresas, centros de trabajo, áreas, empleados) pero no tiene con qué ni cómo. Este
PR agrega lo que falta para que una empresa aplique la NOM-035 de punta a punta, salvo el reporte:

1. **El instrumento NOM-035** (módulo `Instruments`): las Guías I, II y III transcritas literal del DOF, con su
   calificación exacta. Es el primer instrumento de la plataforma; NOM-037 y Ley Silla llegarán después y casi seguro
   reutilizarán campañas, así que el núcleo (campañas, invitaciones, respuestas) **no sabe nada de la NOM-035**: lo
   específico vive en el instrumento.
2. **Campañas** (módulo `Campaigns`): RH crea una evaluación para un centro de trabajo, la lanza, ve el avance y la
   cierra. La plataforma invita por correo con un enlace personal de un solo uso.
3. **La encuesta** (módulo `Responses` + página pública): el empleado contesta desde el celular. Guía I identificada;
   Guías II y III anónimas, sin ninguna llave hacia el empleado.

El usuario pidió precisión total: el instrumento debe calificar exactamente como lo marca la STPS, sin redondeos,
interpretaciones blandas ni promedios que la norma prohíbe.

## Decisiones del usuario

El usuario aceptó todas las recomendaciones de la investigación (2026-10-29):

1. **Un PR**: instrumento NOM-035 + campañas e invitaciones + el flujo del empleado. Resultados, lista de canalización
   de Guía I e informe 7.7 van en el PR siguiente.
2. **Una "Evaluación NOM-035" por centro de trabajo**, que incluye Guía I y, según los trabajadores del centro al
   lanzarla, la Guía II o la III. Aparte, una **campaña de solo Guía I** para personas elegidas, para cuando ocurre un
   acontecimiento traumático severo.
3. **Guía I en la evaluación: a todos**, siempre.
4. **Guía III: RH elige "todos" o "muestra"; por defecto, todos.**
5. **Sin ficha de datos (Guía V) en el MVP.** La respuesta anónima guarda solo centro de trabajo y área, copiados al
   contestar, sin llave al empleado.
6. **Las interpretaciones de la investigación**, tal cual:
   - Límite inferior de cada rango inclusivo, superior exclusivo (`20 ≤ Cfinal < 45`); verificado en el formato del DOF.
   - Los ítems de un bloque filtro contestado "No" (clientes/usuarios, ser jefe) valen 0 y no se preguntan.
   - El tamaño de la muestra se redondea **hacia arriba**.
   - El número de trabajadores del centro se toma de la plantilla activa al lanzar y queda fijo en la campaña.
   - El género "Otro / prefiero no decir" es un estrato más del reparto proporcional de la muestra.
   - El resultado oficial de un centro **no es un promedio**: cada cuestionario se califica por sí solo (criterio
     escrito de la STPS). Aplica al PR de resultados; aquí solo se guarda lo necesario para hacerlo.

**Decisiones del diseñador** (sobre preguntas que quedaron abiertas; el usuario puede corregirlas al leer):

7. **Canal: solo correo** en este PR. Un empleado sin correo aparece como "Sin correo" en la campaña, cuenta en la
   plantilla y puede caer en la muestra; RH le agrega un correo en Empleados y lo invita desde la campaña. SMS y
   WhatsApp llegan con el módulo `Notifications` (requiere proveedor y aprobación de dependencia).
8. **Sin recordatorios automáticos** en este PR. RH puede reenviar la invitación a una persona o a todos los
   pendientes con un botón.

## Objetivos

1. RH (owner o hr) crea una Evaluación NOM-035 para un centro de trabajo y ve, antes de lanzarla, qué guías aplican,
   cuántas personas cuenta la plantilla y, si eligió muestra, el tamaño `n` y su reparto por género.
2. RH lanza la campaña con una fecha de cierre; cada persona invitada recibe un correo con su enlace.
3. El empleado abre el enlace en el celular, lee la presentación que exige la norma (objetivo, confidencialidad,
   "considere los dos últimos meses"), contesta la Guía I y luego la Guía II o III, y termina. El enlace deja de
   servir en cuanto contesta todo lo que le tocaba.
4. RH ve el avance: invitados, contestados y pendientes por guía; con muestra, si ya se alcanzó `n`.
5. RH cierra la campaña, o se cierra sola al vencer la fecha. Cerrada, ningún enlace sirve.
6. RH lanza una campaña de solo Guía I para las personas que elija.
7. La calificación de cada guía es exacta y está probada en **cada** límite de **cada** rango.

## No objetivos (llegan en otros PRs)

- Resultados, distribución por nivel de riesgo, mínimo de respondentes por grupo, informe 7.7, PDF, consulta de los
  trabajadores (7.8) y la **lista de canalización** de Guía I con su permiso. Este PR guarda los datos; no los muestra.
- Programa de intervención (8.3–8.5), política (5.1, Guía IV), quejas.
- Ficha de datos de la Guía V.
- SMS, WhatsApp, recordatorios automáticos, enlaces impresos o modo kiosco.
- NOM-037 y Ley Silla. Solo se cuida que campañas y respuestas no las impidan.
- Cuestionarios propios validados (7.5) y un editor de cuestionarios.
- Aviso de vencimiento de los 2 años (7.9) más allá de lo que se muestra en la lista (ver "Lista de campañas").

## Anti-objetivos

- **Que una respuesta de Guía II/III se pueda ligar a una persona**: ni por llave, ni por hora exacta, ni por log, ni
  por bitácora, ni por las props de una página de RH.
- **Promediar** calificaciones como resultado del centro.
- Que un enlace sirva dos veces para la misma guía, después de reenviarlo, o con la campaña cerrada.
- Guardar un cuestionario incompleto o con un bloque filtro incoherente (un "No" con ítems contestados, o un "Sí" con
  ítems faltantes).
- Que la encuesta avance sola, o que RH vea el avance de una persona dentro del cuestionario.
- Meter "NOM-035" en el núcleo: tablas, rutas y nombres de campañas y respuestas son genéricos.

## Reglas de la norma que el sistema hace cumplir

Resumen; el texto literal está en el anexo.

| Regla | Fuente | Cómo se cumple |
|---|---|---|
| Guías por centro: ≤ 15 → I; 16–50 → I y II; > 50 → I y III | 2, 7.1 | `Nom035Guides` (ya existe) con la plantilla fija al lanzar |
| Guía II a **todos** | II.1 | Sin opción de muestra con Guía II |
| Guía III a todos o muestra `n = ⌈0.9604N / (0.0025(N−1) + 0.9604)⌉` | III.1 | Cálculo en enteros: `⌈9604·N / (25·(N−1) + 9604)⌉`; N=100 → 80, N=51 → 46 |
| Muestra aleatoria y proporcional por género | III.1 | Estratos por `Gender`, cuotas por residuo mayor (suman exactamente `n`), sorteo con `random_int` |
| Guía I a todos; además, cada vez que ocurre un acontecimiento | 5.5, STPS | Va en toda evaluación + campaña de solo Guía I |
| Guía I: Sección I toda "No" → termina; si no, II ≥ 1, III ≥ 3 o IV ≥ 2 "Sí" → valoración clínica | GR.I | Calificación de Guía I; el formulario oculta II–IV si la I es toda "No" |
| Escala 0–4 o 4–0 según el ítem | Tablas 2 y 5 | Definición del instrumento |
| Cdom, Ccat y Cfinal son sumas; nivel por rangos | II.3, III.3 | Calificación por cuestionario |
| Bloques filtro: clientes/usuarios y ser jefe | Guías II y III | Pregunta Sí/No; "No" salta el bloque y sus ítems valen 0 |
| Presentación: objetivo, confidencialidad, contestar completo, sin respuestas correctas, **últimos dos meses** | II.2, III.2 | Pantalla de inicio de la encuesta |
| Solo cuenta un cuestionario completo y sin enmiendas | II.2 c, III.2 c | Se envía completo una sola vez; no se edita después |
| Entregados = respondidos ≥ muestra mínima | III.2 c | El avance compara contestados contra `n` y avisa si falta |
| Cada razón social por separado | STPS | Ya es así: cada empresa es un tenant |

## Módulos

### `Instruments` (contenido y calificación; sin base de datos)

- **Definiciones en código**, versionadas: `nom035.guide-i`, `nom035.guide-ii`, `nom035.guide-iii`, con versión
  `dof-2018-10-23`. Cada una describe sus secciones (con el texto de instrucción literal), sus ítems (número de la
  norma y texto literal), sus opciones de respuesta, sus bloques filtro y si es **identificada** (Guía I) o
  **anónima** (II y III). El texto sale del anexo, sin corregir la redacción de la norma; solo se corrigen erratas
  evidentes de etiquetas internas ("Escaza" → "Escasa").
- **Validación de un cuestionario** contra su definición: completo, cada valor dentro de sus opciones, filtros
  coherentes.
- **Calificación**:
  - Guía I → si requiere valoración clínica, y por qué regla.
  - Guías II y III → Cfinal, Ccat por categoría y Cdom por dominio, cada uno con su nivel de riesgo (enum
    `RiskLevel`: `null`, `low`, `medium`, `high`, `very_high`, con etiquetas "Nulo o despreciable", "Bajo", "Medio",
    "Alto", "Muy alto"). Las sumas de dimensión también se exponen (la norma las agrupa y el reporte las usará).
- **Plan NOM-035**: además de `Nom035Guides`, el tamaño de muestra y su reparto por estratos.
- API pública para los demás módulos: un contrato para obtener una definición por clave y validarla/calificarla, y
  sus DTOs (definición, resultado de calificación). Los otros módulos nunca importan las clases de la NOM-035.

### `Campaigns` (genérico)

Una **campaña** es la aplicación de uno o varios instrumentos a personas de **un** centro de trabajo, en un periodo.

- **Tipo** de campaña: `nom035-evaluation` o `nom035-trauma` (solo Guía I). El tipo decide qué instrumentos toca a
  cada persona; esa decisión la toma el plan del instrumento, no el núcleo de campañas.
- **Estados**: borrador → activa (lanzada) → cerrada. Un borrador se puede borrar; una activa o cerrada, nunca.
- **Datos fijos al lanzar**: plantilla `N` del centro, guías que aplican, modo de la Guía III (todos/muestra), `n` y
  el reparto por estrato.
- **Participante**: una persona invitada a una campaña, con los instrumentos que le tocan, el estado de cada uno
  (pendiente/contestado, con fecha y hora de cuándo contestó: es el "ya contestó" de la invitación) y el hash del
  token de su enlace. Una persona aparece una vez por campaña.
- **Restricción**: un centro tiene a lo más **una** Evaluación NOM-035 en borrador o activa. Las campañas de solo
  Guía I no tienen ese límite.
- **Fecha de cierre** obligatoria al lanzar (fecha, en `display_timezone`; cierra al terminar ese día). Mientras está
  activa se puede extender; nunca acortar a una fecha pasada. Un comando programado cierra cada hora las vencidas, en
  todas las empresas.

### `Responses`

- **Respuestas anónimas** (Guías II y III): `id` UUID v4, `tenant_id`, campaña, instrumento y versión, centro de
  trabajo y área (copiados al contestar), y las respuestas. **Sin** columna, llave ni índice hacia empleado,
  participante, invitación o token, y **sin timestamps** (ni `created_at`): el orden o la hora delatarían a quién
  corresponde. Solo inserción.
- **Tamizajes de Guía I** (identificados, numeral 5.5): `tenant_id`, campaña, empleado, respuestas, si requiere
  valoración clínica y fecha de respuesta. Solo inserción. Este PR no tiene pantalla que los muestre.

### Lo que cada módulo pide a los demás

- `Organization` expone un contrato con la plantilla activa de un centro (id, nombre, correo, género, área), y un
  evento cuando se da de baja a un empleado. `Headcount` ya existe.
- `Tenancy` expone una forma de **trabajar dentro de una empresa por su id** fuera de una ruta `/admin/{slug}`: la
  necesitan la página pública de la encuesta (la empresa sale del enlace) y el comando que cierra campañas vencidas.
  Hoy `TenantContext::set()` recibe un modelo `Tenant`, interno de Tenancy.
- `Campaigns` llama a `Responses` (contrato) para guardar respuestas, y a `Instruments` (contrato) para definiciones,
  validación y plan. `Responses` no conoce participantes ni invitaciones.

## Flujos de RH

Todo bajo `/admin/{tenant:slug}/campaigns…`, con nombre de ruta. En la navegación: grupo **Cumplimiento → NOM-035**,
que lleva a la lista de campañas de NOM-035 (`lib/app-navigation.ts` ya deja el lugar).

### Permisos

- Permiso Spatie nuevo `manage-campaigns` para **owner** y **hr** (migración como la de `manage-organization`).
  Crear, lanzar, cerrar, extender, invitar, reenviar y sortear exigen ese permiso.
- **viewer** ve la lista de campañas y los conteos de avance, sin la lista de personas.
- Route model binding de campañas y participantes de otra empresa da 404.

### Lista de campañas

- Campañas activas (con avance) y cerradas, de todos los centros.
- Por cada centro activo: la fecha de su última Evaluación NOM-035 cerrada y "Siguiente evaluación antes del …" (+2
  años, num. 7.9), o "Sin evaluar".
- Botones: "Nueva evaluación" y "Aplicar Guía I a personas".

### Nueva evaluación (borrador)

1. RH elige el centro de trabajo (solo los no archivados, con al menos una persona activa, y sin otra evaluación en
   borrador o activa).
2. La pantalla muestra la plantilla `N`, las guías que aplican y, si aplica la III, el selector **Todos / Muestra**
   (todos por defecto). Con muestra, muestra `n` y el reparto, por ejemplo "80 de 100: 44 mujeres, 34 hombres, 2
   otro".
3. Aviso visible: "La plantilla debe estar completa: la norma cuenta a todas las personas que trabajan en el centro".
4. Guardar crea el borrador y, con muestra, **sortea** a las personas. RH puede volver a sortear mientras sea borrador.
   Ver quiénes quedaron en la muestra es válido (no revela respuestas).

### Lanzar

- RH pone la fecha de cierre (al menos mañana) y lanza. Al lanzar se recalcula `N` con la plantilla de ese momento. Si
  cambió lo suficiente para cambiar las guías o `n`, el lanzamiento se detiene y pide revisar el borrador (nunca se
  lanza con datos viejos sin avisar). Con muestra, también se detiene si alguien sorteado ya no está activo en el
  centro o si las cuotas por género ya no coinciden con la plantilla: RH vuelve a sortear (la proporción por género es
  regla de III.1).
- Se crea un participante por persona: todos con Guía I; con Guía II todos además la II; con Guía III todos o solo la
  muestra además la III.
- Se encola un correo por participante con correo. Los que no tienen correo quedan "Sin correo".
- Log `campaign.launched` y bitácora (`RecordAudit`), con conteos, nunca nombres ni correos.

### Avance (campaña activa o cerrada)

- Por instrumento: invitados, contestados y pendientes. Con muestra: "Contestaron 61 de 80 (mínimo de la norma)" y un
  aviso mientras no se alcance.
- Lista de personas (no para viewer): nombre, área, si le toca la II/III, estado por guía (Pendiente, Contestó, Sin
  correo, Cancelada), con búsqueda. **Nunca** respuestas, puntajes ni progreso dentro del cuestionario.
- Acciones (activa): reenviar a una persona o a todos los pendientes (rota el token: el enlace anterior muere);
  **invitar a quien falte** (personas activas del centro que no son participantes, por ejemplo altas recientes: solo
  Guía I, más la II si la campaña es de Guía II; nunca entran a una muestra fuera del sorteo); **sortear reemplazos**
  con muestra (agrega `k` personas al azar del mismo estrato entre las que no están en la muestra, para cubrir a quien
  no contestará); extender la fecha; cerrar.
- La pantalla de avance afirma su número de consultas (`expectsDatabaseQueryCount`).

### Cerrar

- Manual o por vencimiento. Cierra todos los enlaces. Log `campaign.closed` y bitácora, con conteos.
- Cerrar con la muestra sin cubrir está permitido, con confirmación que lo advierte.

### Campaña de solo Guía I

- RH elige centro y personas (búsqueda en la plantilla activa del centro), fecha de cierre y lanza. Mismo avance y
  mismas acciones, sin muestra.

### Cambios de plantilla durante la campaña (decisiones del diseñador)

- **Baja**: sus instrumentos pendientes quedan **cancelados** y su enlace deja de servir. Lo ya contestado se conserva.
- **Cambio de centro**: igual que una baja para esta campaña (ya no trabaja en ese centro). Un cambio de **área** no
  cancela nada.
- **Reactivación, o regreso al centro**: "invitar a quien falte" también toma a los participantes con instrumentos
  cancelados que vuelven a estar activos en el centro, y les devuelve esos instrumentos con un enlace nuevo.
- **Archivar un centro** con una campaña en borrador o activa se rechaza: primero se cierra o se borra la campaña.
- Para esto `Organization` publica eventos de baja, reactivación y cambio de centro (hoy esas acciones solo escriben
  log), y `Campaigns` los escucha. Para el archivo, `Campaigns` expone un contrato que `Tenancy` consulta.
- **Qué se copia en la respuesta anónima**: el centro **de la campaña** (fijo) y el área **actual** de la persona al
  contestar.
- **"Invitar a quien falte" con Guía III en modo todos**: la persona nueva recibe también la III, porque "todos"
  significa todos. Con muestra, nunca entra a la III fuera del sorteo. El avance compara contra los participantes
  invitados y, con muestra, contra `n`; no contra la `N` fija.

## La encuesta del empleado

- Ruta pública con nombre, `GET /survey/{token}` y su envío, en la lista pública de `RouteSecurityTest` con su razón
  ("la persona invitada no tiene cuenta; el token es su credencial"). Con throttle.
- El token es aleatorio de 64 caracteres; solo se guarda su hash (como `TenantInvitation`). La empresa se resuelve
  del participante.
- **Enlace inválido, vencido, ya contestado, campaña cerrada o empresa suspendida**: pantalla clara, sin decir de qué
  empresa ni de quién es el enlace si el token no existe.
- Trata de **usted**; nunca avanza sola; sigue `EncuestaMovil`, `EncuestaEscritorio` y `SurveyQuestion` del design
  system (una pregunta por pantalla en móvil, hasta 3 de la misma sección en escritorio, opciones verticales de 56 px,
  progreso por sección, "Siguiente" explícito, teclado y lector de pantalla).
- **Inicio**: nombre de la empresa, objetivo, que es confidencial y que los resultados se usan solo para mejorar el
  ambiente de trabajo, que no hay respuestas correctas o incorrectas, que debe contestar todo, que considere **las
  condiciones de los dos últimos meses**, y la fecha de cierre.
- **Orden**: la encuesta ofrece primero la Guía I, pero cada guía es independiente. El servidor acepta cualquiera en
  cualquier orden, para que quien no quiera contestar la parte con nombre no se quede sin contestar la anónima (eso
  dejaría corta la muestra). Decisión del diseñador.
- **Guía I** (si le toca): antes de empezarla se le dice claramente que **esta parte sí lleva su nombre**,
  porque la empresa está obligada a canalizar a atención a quien lo necesite, y que solo la verán personas
  autorizadas. Si la Sección I es toda "No", termina ahí. Se envía al terminarla.
- **Guía II o III** (si le toca): se le dice que **esta parte es anónima**. Bloques filtro como en la norma. Se envía
  al terminarla.
- Cada guía se envía **completa en un solo envío**: el servidor valida contra la definición y rechaza lo incompleto o
  incoherente. Mientras tanto, el avance se guarda **solo en el dispositivo** (`localStorage`), para que una recarga o
  un corte de señal no lo pierda; se borra al enviar. El servidor nunca recibe un cuestionario parcial.
- Al terminar todo: "Gracias", y el enlace ya no sirve.
- **Envío de Guía II/III**, en una transacción: bloquea al participante, verifica que el instrumento le toca y está
  pendiente, guarda la respuesta anónima y marca "contestó". Si cualquier paso falla, no queda nada.
- **Envío de Guía I**: igual, guardando el tamizaje identificado con su resultado de valoración clínica.

## Privacidad, logs y seguridad

- La tabla de respuestas anónimas no tiene llaves ni columnas hacia empleado, participante, invitación ni token; un
  test lo afirma sobre el esquema.
- Ningún log ni registro de bitácora lleva respuestas, puntajes de una persona, ni el id de una respuesta anónima.
  `campaign_participant.completed` lleva participante e instrumento, no la respuesta. `response.recorded` lleva
  campaña e instrumento, no el participante. `answers` ya está en `RedactSensitiveData`; si se agrega otra llave
  sensible (por ejemplo `scores`), entra ahí con su test.
- Test de Responses: enviar una respuesta y afirmar que ninguna línea de log contiene sus valores.
- Las páginas de RH de este PR tienen test de que sus props no contienen respuestas.
- Correo de invitación: empresa, fecha de cierre, enlace, nota de confidencialidad, en español de "usted"; plantilla
  de correo existente. Encolado. Nunca lleva datos de otros participantes.
- CSP: la encuesta es una página Inertia más, con su `visitPage()` y `cspViolations()`.

## Casos borde (cada uno con su test)

- Plantilla de 15/16 y 50/51 al crear y al lanzar; centro sin personas; centro archivado.
- `n` para N = 51, 52, 100, 1000 y valores grandes; nunca `n > N`; reparto que suma exactamente `n` con estratos
  chicos (incluido un estrato de 1 persona) y con un solo estrato.
- Sorteo: solo personas activas del centro; reemplazos solo del mismo estrato y fuera de la muestra; sin personas
  disponibles, aviso.
- Rango de cada categoría y dominio de las Guías II y III en `límite − 1`, `límite` y `límite + 1` para los cuatro
  cortes; Cfinal mínimo (0) y máximo (184 Guía II, 288 Guía III).
- Ítems 33 (Guía II) y 57 (Guía III), positivos dentro de Violencia.
- Filtros "No" en ambos bloques: sus ítems valen 0 y no se aceptan si vienen; "Sí" sin todos sus ítems: rechazo.
- Guía I: toda "No"; un "Sí" en I y nada más; II con 1; III con 2 y con 3; IV con 1 y con 2; respuestas de II–IV
  enviadas con la Sección I toda "No": rechazo.
- Enlace: inexistente, reenviado (el viejo muere), usado para una guía y luego para la otra, dos envíos simultáneos de
  la misma guía (solo uno entra), campaña cerrada, campaña vencida pero aún no cerrada por el comando (se trata como
  cerrada), empresa suspendida, empleado dado de baja, empleado movido a otro centro, Guía III contestada antes que
  la I.
- Plantilla: reactivado y reinvitado; cambio de área antes de contestar (la respuesta lleva el área nueva); archivar
  un centro con campaña activa (rechazo); muestra con alguien dado de baja entre el sorteo y el lanzamiento (rechazo).
- Dos evaluaciones del mismo centro: la segunda se rechaza mientras la primera esté en borrador o activa.
- Otra empresa: listar, ver, lanzar, cerrar, reenviar por URL → 404; consultas directas no la ven.
- Usuario sin permiso (viewer) en cada acción → 403; viewer no ve la lista de personas.
- Fecha de cierre pasada o de hoy al lanzar; extender a una fecha anterior.
- Participante sin correo: no se envía nada; al agregarle correo, "invitar a quien falte" o "reenviar" le llega.

## Pruebas

- **Unit (Pest)**: definiciones contra el anexo (conteo de ítems: 20, 46 y 72; cada ítem en una sola dimensión;
  escalas de Tablas 2 y 5), calificación de las tres guías, muestra y reparto. Con `mutates()`: calificación,
  validación, muestra, reparto, sorteo, transiciones de la campaña, envío de respuestas y vigencia del enlace.
- **Feature**: flujos de RH, permisos, aislamiento por empresa, props de cada página, encuesta (validación, un solo
  uso, concurrencia), privacidad (esquema, logs, props), comando de cierre.
- **Browser**: el empleado contesta Guía I y Guía III desde el celular; enlace ya usado; Sección I toda "No"; cada
  página nueva con `visitPage()`.
- **Arquitectura**: los módulos nuevos respetan las reglas existentes; `Campaigns` y `Responses` no importan nada de
  `Instruments` fuera de su API pública.

## Lo que queda al constructor

- Nombres de clases, tablas y columnas (en inglés), forma del JSON de respuestas, división en componentes y archivos
  dentro de los límites del frontend.
- Diseño exacto de cada pantalla, siguiendo el design system y sus capturas.
- El orden de los commits, uno por comportamiento.
