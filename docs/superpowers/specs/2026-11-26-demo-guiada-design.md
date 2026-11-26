# Demo guiada: sesiones a la medida con Humi como guía

Fecha: 2026-11-26 · Estado: propuesto (pendiente de aprobación de Victor)

## Para qué y por qué

Victor vende Humetría mostrando la empresa demo (Manufacturas Alba del Bajío) en vivo. Hoy la sala de demostración solo
prepara la empresa, deja entrar como dueño o RH y abre la puerta con QR; la demostración la improvisa él. Queremos que
cada demo sea **ordenada, a la medida del cliente y real de punta a punta**:

- Victor escoge en la sala **solo los módulos que ese cliente quiere ver** (p. ej. NOM-035 y Asistencia) y la sesión se
  arma con eso; nada de recorrer módulos que no le importan.
- Un **guía** lo acompaña paso a paso: un panel con la cara y la voz de Humi que dice qué hacer, dónde hacer clic (lo
  ilumina), qué está pasando en vivo y por qué importa. Victor explica al cliente; el guía lo lleva a él.
- **Todo es real**: Victor da de alta en vivo a las personas que están en la sala con su correo y teléfono reales; la
  encuesta o el enlace de acceso **les llega de verdad** por correo y WhatsApp, contestan o marcan desde su teléfono, y la
  pantalla de Victor lo ve llegar. Al cerrar se ve el reporte final.
- El paso **avanza solo cuando el hecho ocurre** (la campaña se lanzó, 3 de 5 contestaron), no con «Siguiente».
- Al terminar, **se borra todo**, incluidos los datos de las personas reales: son registros volátiles de prueba.

Éxito = Victor escoge módulos, empieza la sesión, la opera de la mano del guía con su público real, y al terminar la demo
queda limpia. Funciona en claro, oscuro y a 375 px.

## Metas, no metas y anti-metas

**Metas**
- Sesión de demo con módulos escogidos, guía con pasos que se cumplen con datos reales, narración de Humi, mensajes reales
  al público, resultados visibles con cualquier número de personas, y «Terminar sesión» que borra todo.
- La sala alineada al design system (contenedor estándar, `PageHeader`, tarjetas y cifras de `DESIGN.md`).

**No metas**
- Que el cliente vea el guía en su propio teléfono o computadora: el guía vive en la pantalla de Victor (compartida o
  proyectada). El público solo usa su portal y sus mensajes, como cualquier persona trabajadora.
- Deshacer un solo módulo a media sesión. Para empezar de cero se reinicia la demo completa.
- Que Humi improvise el guion. La narración es copy fijo; Humi responde de verdad solo cuando se le pregunta.

**Anti-metas (si pasa esto, falló aunque funcione)**
- Que una empresa real vea algo de esto, o que una excepción de la demo (WhatsApp, guías, mínimo por grupo) se filtre a
  una empresa real.
- Que los datos de las personas del público sobrevivan a «Terminar sesión».
- Que el guía se trabe en vivo frente al cliente (un paso que nunca detecta su hecho sin salida).

## Restricciones

- Solo el superadmin (gate `manage-demo`) y solo en la empresa demo (`Tenancy\Contracts\DemoCompanies::isDemo`).
- Monolito modular: el módulo `Demos` lee los otros módulos **solo por sus contratos** (`Contracts`, `Events`, `Data`).
- Sin dependencias nuevas: driver.js (ya instalado, lo usa Primeros pasos) para iluminar; `usePoll` de Inertia v3 para lo
  vivo.
- TDD estricto, Larastan max, TS estricto, límites del frontend (300 líneas por archivo, etc.).
- Anonimato: las respuestas siguen sin llave hacia la persona; el guía y Humi solo ven conteos y estados.

## Enfoques considerados

- **A. Sesión en el servidor + guía tipo Primeros pasos** (escogido). Cada módulo trae sus pasos escritos; cada paso sabe
  que se cumplió leyendo datos reales por contrato; panel con Humi, foco con driver.js, estado vivo por sondeo.
  Predecible en vivo, sin costo por paso, avanza con hechos reales. La narración es guion.
- **B. Humi como agente que narra en vivo.** Se siente más agente, pero tarda 2–5 s por paso, cuesta tokens por clic y
  puede decir algo raro frente al cliente; si falla la IA, se cae la demo.
- **C. Solo en el navegador.** Lo más barato, pero no sabe que alguien del público contestó o marcó: no avanza con hechos.

Victor escogió **A**. Humi se suma con un botón «Pregúntale a Humi» en cada paso (pregunta escrita, modelo real).

## Decisiones de Victor

1. **Sesión a la medida**: se escogen los módulos a mostrar; el guía solo lleva esos.
2. **Guía = panel + Humi narrando + Victor explicando** (opción C de la primera ronda).
3. **El paso avanza solo cuando el hecho ocurre.**
4. **Pantalla de Victor**; el público recibe la encuesta, el acceso o el QR en su teléfono, por correo y WhatsApp reales.
5. **WhatsApp en la demo**: se quita la excepción de `ChannelRules` para la demo; los mensajes salen por todos los canales
   al correo y número de cada persona dada de alta.
6. **Resultados siempre visibles en la demo**, con 1, 5 o 20 personas: la demo no es una empresa, la regla de la norma
   sobre guías por tamaño no aplica.
7. **Cada sesión empieza con la demo reiniciada de cero.**
8. **«Terminar sesión» borra de inmediato todo**, incluidas las personas del público.
9. **Solo el superadmin lo ve.** Los dueños reales tienen su propio onboarding (Primeros pasos).
10. **El público interactúa; lo demás se ve en la pantalla de Victor.** Nadie recibe acceso al portal por darlo de alta:
    las encuestas llegan por enlace (el enlace es la credencial) y solo Asistencia envía acceso al portal, a quien va a
    marcar desde su teléfono.
11. **Vacaciones en la pantalla de Victor** (opción a): el guía abre en su computadora el portal de una persona dada de
    alta, Victor crea la llave con su Touch ID, pide los días y luego aprueba como RH.

### Decisiones tomadas por Claude (Victor puede corregirlas)

- **Todos los canales a la vez en la demo** (de la decisión 5): hoy `ChannelRules::route` manda por el primer canal que
  sirve; en la demo manda por correo **y** WhatsApp.
- **Plantilla de WhatsApp para el acceso al portal**: se registra en `TemplateRegistry` y `WorkerInvitation` pasa por los
  canales. Mientras Meta no la apruebe (trámite de Victor), el acceso sale solo por correo; aprobada, sale también por
  WhatsApp sin tocar código. Aplica a empresas reales también (es la misma notificación), detrás de las reglas de canal
  de siempre. **Enviada a Meta el 2026-11-26** (`worker_invite_v1`, id 1462008746046287, UTILITY, es_MX, pie «Humetría»
  con acento, botón «Crear mi acceso» → `https://humetria.com.mx/access/{{1}}`). El registro del código lleva ese pie
  (las demás plantillas siguen con «Humetria» hasta que Victor las reenvíe). Meta solo deja la variable al final del
  botón y el enlace de hoy lleva la empresa a la mitad (`/me/{empresa}/link/{token}`), así que se construye la ruta
  pública `access/{token}` (en `worker-access.php`, con throttle y en la lista de `RouteSecurityTest` con su razón):
  busca la empresa por el hash del token y redirige a `worker.link.show` sin gastarlo; un token desconocido responde la
  misma página que uno vencido. El correo sigue llevando el enlace de siempre.
- **Fecha de ingreso**: el guía pide, al dar de alta, una fecha de ingreso de hace dos años, para que la persona tenga
  saldo de vacaciones (LFT: sin días antes del primer año). Es el comportamiento real; no hay excepción.

## Diseño

### 1. La sesión (módulo `Demos`)

Tabla nueva `demo_sessions` (sin `tenant_id` de negocio: es de la plataforma, como `demo_runs`; se agrega a las
excepciones de `TenantScopedModelsTest` con su razón):

| Columna | Qué |
|---|---|
| `id` | uuid |
| `client_name` | texto opcional («Grupo Ejemplo»), solo para la sala y el nombre del centro |
| `modules` | lista ordenada de llaves de recorrido escogidas (`nom035`, `attendance`, …) |
| `work_center_id` | el centro de la sesión, creado al empezar |
| `skipped_steps` | llaves de pasos que Victor saltó |
| `status` | `preparing`, `active`, `ending`, `ended` |
| `started_at`, `ended_at` | `timestampTz` |

Solo una sesión activa a la vez (índice único parcial sobre `status in ('preparing','active','ending')`).

**Empezar sesión** (`StartDemoSession`): reinicia la demo con la corrida que ya existe (`ResetDemo`) y, al terminar esa
corrida, un paso nuevo de la preparación (en `database/seeders/Demo`, el único código al que la regla `arch()` deja llamar
`SetGroupMinimum` y las acciones de otros módulos) crea el **centro de la sesión** («Sala · {cliente}» o «Sala de demostración»)
y deja la demo lista para el público real:
- mínimo por grupo de la demo en **1** (función que ya existe en `GroupMinimums`; con 1 el producto ya advierte con
  honestidad que un grupo chico podría identificar respuestas);
- canal de WhatsApp de la demo **encendido con el consentimiento vigente** (la misma fila de ajustes que pondría el dueño).

La sala muestra «Preparando» con el paso, como hoy.

**Terminar sesión** (`EndDemoSession`): marca la sesión `ending` y corre `ResetDemo`. El reinicio borra la empresa demo
entera (las tablas de solo inserción ya permiten borrar filas de la demo) y la vuelve a sembrar: las personas del público,
sus correos, teléfonos, respuestas, marcas, llaves y mensajes desaparecen con ella. Al terminar, `ended`.
**Respaldo** (decisión del constructor, ver abajo): un comando programado termina sola una sesión con más de 24 h.

Bitácora (`RecordAudit`, solo ids): `demo.session_started`, `demo.session_ended`. Logs: `demo.session_*`.

### 2. Excepciones de la demo (solo `isDemo`)

Cada una vive en su módulo, detrás de `DemoCompanies::isDemo($tenantId)`, con su test que prueba que **una empresa real no
cambia**:

1. **WhatsApp** — `Messaging\Support\ChannelRules::whatsAppOpen` deja de excluir la demo. El resto de la regla sigue
   (función disponible, canal encendido con el consentimiento vigente, número válido, sin baja). Además, en la demo
   `route()` devuelve **todos** los canales que sirven (correo y WhatsApp), no solo el primero. Las 180 personas
   sembradas ya no tienen teléfono (`OrganizationStory`), así que a nadie ficticio le sale un mensaje.
2. **Guías NOM-035 por tamaño** — en la demo, una campaña NOM-035 aplica **Guías I y II aunque el centro tenga menos de
   16 personas** (y de 51 en adelante, I y III como siempre). `Instruments\Support\Nom035GuideRule` sigue siendo la regla
   pura de la norma; la excepción se aplica en **todos** los lugares que deciden o muestran las guías por número de
   personas, para que nada se contradiga en vivo: crear el borrador, la revisión de `LaunchCampaign` (que hoy rechaza un
   borrador cuyas guías no cuadran con la plantilla), las páginas de la campaña (`CampaignPages`), la página del centro
   (`WorkCenterController`) y la ruta de cumplimiento que leen Humi y el centro (`Tenancy\Support\Nom035Route`). Lo más
   limpio es un solo punto que todos consulten (decisión del constructor).
3. **Mínimo por grupo** — no es excepción: se usa el 1 que el producto ya permite (ver §1).
4. **Correo** — no cambia: hoy solo se bloquea el que va a `@demo.humetria.test`; al público real le llega.

### 3. Los recorridos y sus pasos

Un **recorrido** es un módulo que se puede mostrar. Siempre va primero, sin escogerlo, **«El centro y su gente»** (lo que
los demás necesitan). Catálogo de la primera versión, en este orden (Victor escoge cuáles y puede reordenarlos):

| Recorrido | Pasos (resumen) | Hecho que cumple cada paso |
|---|---|---|
| **El centro y su gente** (siempre) | 1. Abrir el centro de la sesión · 2. Dar de alta a cada persona del público con correo, teléfono y fecha de ingreso de hace dos años (no reciben acceso al portal) | 1. visitó la página del centro · 2. el centro tiene ≥ 1 persona con contacto |
| **NOM-035** | 1. Crear la evaluación del centro de la sesión · 2. Lanzarla (sale la invitación por correo y WhatsApp) · 3. El público contesta (avance en vivo) · 4. Cerrar la evaluación · 5. Ver resultados y el informe · 6. Canalización de Guía I | 1. hay borrador en el centro · 2. lanzada · 3. ≥ 1 respuesta (el paso muestra «n de m» y se cumple cuando todos o cuando Victor sigue) · 4. cerrada · 5. visitó resultados · 6. visitó la lista |
| **Asistencia** | 1. Escoger el modo del centro (con QR del kiosco o sin él) y la zona · 2. Revisar la política · 3. Sacar el kiosco del centro · 4. Enviar el acceso a cada persona (WorkerAccess) · 5. El público crea su llave y marca · 6. Ver las marcas y el reporte | datos de Attendance y WorkerAccess por contrato: modo puesto, kiosco emitido, enlaces enviados, ≥ 1 llave, ≥ 1 marca, visitó el reporte |
| **Clima y pulso** | crear pulso para el centro · lanzar · el público contesta · ver resultados | Engagement: pulso creado, lanzado, respuestas, visitó resultados |
| **Denuncias** | mostrar el QR del canal (canal público, sin portal) · alguien del público o Victor en su pantalla denuncia · RH la recibe y la atiende | Complaints: denuncia nueva recibida, atendida |
| **Vacaciones** | 1. abrir en la computadora de Victor el portal de una persona dada de alta (el guía emite un enlace de un solo uso de WorkerAccess y lo abre) · 2. crear la llave con Touch ID · 3. pedir días (pide prueba con la llave) · 4. volver al panel y aprobar como RH · 5. ver el saldo | Leave y WorkerAccess: enlace abierto, ≥ 1 llave de esa persona, solicitud nueva, aprobada, visitó el saldo |
| **Humi** | preguntarle a Humi por el estado de cumplimiento | `HumiActivity::hasAsked` después de empezar la sesión |

Cada paso tiene:
- `key` (`nom035.launch`), `title`, **qué hacer** (una frase), **qué está pasando** (frase con dato vivo, p. ej. «Llegaron
  3 de 5 respuestas»), **por qué importa** (una o dos frases para el cliente), `href` (la pantalla exacta de la empresa
  demo), `target` (el `data-tour` que se ilumina), `kind`: **Hazlo** o **Mira**, y una **pregunta para Humi** opcional.
- su **detector**: una función del servidor que, con contratos públicos, dice si el hecho ocurrió y da el dato vivo.
  Lo que no tiene contrato hoy se agrega como contrato de lectura en su módulo (conteos y estados, nunca personas ni
  respuestas). Los pasos de «visitó la página» se cumplen cuando el guía ve esa ruta abierta (lo reporta el navegador).

**Paso actual** = el primero no cumplido ni saltado, en el orden de los recorridos escogidos. Nada se guarda como «hecho»:
igual que Primeros pasos, se calcula de los datos cada vez; solo se guardan los saltados.

Los recorridos de Ley Silla, NOM-037, Compensación, Difusión e Intervenciones quedan para una segunda tanda; el catálogo
se diseña para sumarlos sin tocar el motor.

### 4. El guía en pantalla

- Vive en el layout del panel de la empresa, **solo** cuando quien está es el superadmin impersonando la empresa demo con
  una sesión activa. Prop compartida `demoGuide` (por contrato de `Demos`; el kernel no importa internos).
- **Panel del guía**: dock a la derecha (hoja inferior en 375 px), con el avatar de Humi, el recorrido y el paso actual
  («Paso 2 de 6 · NOM-035»), las tres frases (qué hacer / qué está pasando / por qué importa), la etiqueta Hazlo o Mira, y
  los botones **Llévame** (navega a `href` e ilumina `target` con driver.js), **Pregúntale a Humi** (abre Humi con la
  pregunta escrita, sin enviarla — `openHumi`), **Saltar paso** y la lista de recorridos con su avance. Se puede
  minimizar a una burbuja.
- **Vivo**: `usePoll` cada 3 s recarga solo `demoGuide` mientras el panel está abierto. Cuando el paso se cumple, el panel
  lo celebra en una línea («Listo: la evaluación está lanzada; 5 invitaciones salieron por correo y WhatsApp») y pasa al
  siguiente, e ilumina su botón si ya está en la página.
- Los textos de driver.js son copy fijo del catálogo; nunca datos de la empresa (driver.js escribe HTML).

### 5. La sala

Rediseño alineado al design system: contenedor `flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16`,
`PageHeader` con el estado de la demo.

- **Sin sesión**: tarjeta principal «Nueva sesión»: nombre del cliente (opcional), recorridos para escoger (casillas con
  duración aproximada y qué enseña cada uno, reordenables) y **Empezar sesión** (avisa que reinicia la demo).
- **Con sesión**: tarjeta de la sesión: cliente, recorridos con su avance (cifras según `DESIGN.md`), **Entrar y seguir
  el guía** (entra como dueño a la primera pantalla con el guía abierto) y **Terminar sesión** (confirma: «Se borra todo,
  incluidas las personas que diste de alta»).
- Debajo, **Herramientas de la sala**: entrar como dueño o RH, la puerta con QR (los 40 trabajadores ficticios, útil para
  públicos grandes), el canal de denuncias, los checadores y las notas, como hoy.

### 6. Humi

- Narración: el panel usa el avatar y el tono de Humi; el texto es copy fijo del catálogo (no cuesta, no falla).
- «Pregúntale a Humi»: modelo real, como siempre; Humi ya lee el estado vivo de cada módulo.
- `ModuleCoverageTest` ya exime a `Demos` (razón: no es un módulo de la empresa); no cambia.

## Privacidad y seguridad

- Las personas del público dan su correo y teléfono sabiendo que es una demo; se borran al terminar (y a las 24 h como
  respaldo). Nada suyo va a logs ni a la bitácora (solo ids).
- Con el mínimo en 1, el resultado de 1 a 4 personas puede identificar respuestas: es el aviso honesto que el producto ya
  muestra y Victor lo acepta para la demo. Las respuestas siguen sin llave hacia la persona.
- Cada excepción (§2) prueba que una empresa real no cambia.
- La sala y la prop `demoGuide`: 404 / `null` para cualquiera que no sea el superadmin en la demo.

## Lo que queda al constructor

- Nombre exacto de clases, rutas y del componente; si el catálogo vive en PHP o en un `Data` por recorrido.
- Qué contratos de lectura nuevos hacen falta por detector y su forma (conteos y estados).
- Cómo el navegador reporta «visitó la página» (p. ej. el detector compara la ruta actual que manda el sondeo).
- El respaldo de 24 h (comando programado) y su log.
- Duraciones aproximadas por recorrido.
- El texto de la plantilla de WhatsApp del acceso al portal (es-MX, con las reglas de `TemplateRegistry`).

## Pendiente de Victor fuera del código

- Registrar y aprobar en Meta la plantilla de WhatsApp del acceso al portal (hasta entonces sale por correo).
