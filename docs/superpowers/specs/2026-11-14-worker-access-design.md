# Identidad única de la persona trabajadora

*   **Author:** Victor Jesus Bravo de la Peña
*   **Status:** Implemented (pendiente de PR)
*   **Date:** 2026-11-14

--------------------------------------------------------------------------------

## Overview

Humetría atiende a dos tipos de usuario. Recursos Humanos entra al panel `/admin` con cuenta y contraseña; eso no cambia. La persona trabajadora no tiene cuenta y hoy usa dos credenciales distintas, según la herramienta:

- **El enlace del portal** (`/portal/{token}`): llega por correo, es siempre el mismo y da acceso a vacaciones, equipo, Ley Silla, política, teletrabajo y, para algunas personas, al mensaje de apoyo de la NOM-035.
- **La llave de acceso** (WebAuthn): se crea en el teléfono al darse de alta en asistencia y firma cada marca en `/a/{slug}`.

Este diseño las reemplaza por una sola identidad. La persona tiene una **sesión**, y entra a ella de dos formas: con su llave de acceso, o con un enlace de un solo uso que llega por correo. El enlace estable del portal desaparece. Un módulo nuevo es dueño de la llave, la sesión, el enlace de un solo uso y la revocación.

El trabajo se hace ahora porque la aplicación está en producción pero todavía no tiene empresas clientes (2026-11-14): no hay a quién migrar. Es además el paso previo de la app instalable descrita en `docs/superpowers/specs/2026-11-14-worker-pwa-brief.md`, que necesita una dirección de entrada sin credencial y una identidad a la cual ligar los avisos.

## The Problem

**1. La credencial del portal viaja en la dirección y no caduca.** El token del portal se deriva de la empresa, la persona y una versión, así que cada correo que recibe la persona lleva el mismo enlace hasta que Recursos Humanos lo revoca. Quien tenga un correo viejo, un reenvío o el historial del navegador entra al portal. El portal incluye datos delicados: Ley Silla, documentos de teletrabajo y el mensaje de apoyo ligado a una canalización identificada de la Guía I.

**2. Hay dos credenciales para la misma persona.** Una persona con asistencia y portal tiene una llave para una cosa y un enlace para la otra. No existe un lugar único donde entrar, y Recursos Humanos revoca cada una por separado.

**3. La llave pertenece al módulo de asistencia.** `AttendanceDevice` se crea en la misma transacción que la aceptación del acuerdo de asistencia (criterio 5.1). Una empresa que no usa asistencia no puede dar llaves a su gente, y ningún otro módulo puede apoyarse en la llave sin importar algo interno de Attendance.

**4. El portal no puede pedir una prueba más fuerte para lo delicado.** Con el enlace como única credencial, ver el mensaje de apoyo de la NOM-035 exige lo mismo que ver el saldo de vacaciones.

**5. No hay una dirección de entrada sin credencial.** La app instalable necesita abrir en una dirección fija por empresa. Hoy la única es `/a/{slug}`, que es de asistencia; la del portal lleva el token.

**La solución propuesta.** Un módulo nuevo guarda la llave de la persona y abre una sesión. La persona entra con su llave o con un enlace de un solo uso. El portal deja de leer un token de la dirección y lee la sesión. Attendance conserva el acuerdo y las marcas, y verifica las firmas a través del módulo nuevo. Las vistas delicadas piden una prueba reciente.

## Goals

1. **Ninguna credencial estable en una dirección.** No queda ninguna ruta `portal/{token}`. Una prueba recorre las rutas y falla si alguna dirección de la persona trabajadora lleva un token que sirva más de una vez.
2. **Una sesión, dos entradas.** La persona entra con su llave de acceso o con un enlace de un solo uso. El enlace que emite Recursos Humanos vence en 48 horas; el que pide la propia persona vence en 30 minutos. Las dos entradas abren el mismo tipo de sesión.
3. **Una dirección de entrada por empresa, sin credencial**, que sirve igual a una empresa con asistencia o sin ella.
4. **El portal funciona sin asistencia.** Una persona de una empresa sin la herramienta de asistencia puede crear su llave y entrar a su portal.
5. **Marcar exige el acuerdo, no solo la llave.** Una persona con identidad que no aceptó el acuerdo de asistencia no puede marcar; una prueba lo afirma. Cada marca, cada lectura de Mi semana y cada solicitud de corrección sigue pidiendo una firma propia.
6. **Registrar una llave deja rastro y se puede deshacer.** Cada llave nueva envía un aviso a la persona con un botón "No fui yo" que revoca la llave y las sesiones y avisa a Recursos Humanos. Cada registro y cada revocación van a la bitácora.
7. **Recursos Humanos revoca y reemite en un solo lugar.** Reemitir anula todas las llaves y sesiones anteriores de la persona.
8. **Las vistas delicadas piden una prueba reciente.** El mensaje de apoyo de la NOM-035, confirmar documentos y pedir vacaciones exigen una firma o una prueba equivalente hecha en los últimos minutos.
9. **La sesión es corta y no deja rastro de la persona anterior.** Tiene un límite sin uso y un máximo absoluto, hay un botón "Salir", y la pantalla de entrada nunca muestra el nombre de quien entró antes.
10. **Los módulos del portal dependen de un contrato, no de un token.** Leave, Seating, Disclosure, Organization, Engagement, Results y Telework preguntan "quién es la persona de esta sesión" a través de un contrato del módulo nuevo.

## Non-Goals

- La app instalable: manifest, service worker, lector de QR, avisos push y marcar sin señal. Es el diseño siguiente y parte de este.
- El inicio de sesión de Recursos Humanos en `/admin` (cuenta, contraseña, 2FA y llaves de acceso de la cuenta). No se toca.
- Las encuestas anónimas (`/survey/{token}`, `/engagement/{token}`), el canal de denuncias (`/c/{code}`) y la página de trabajadores (`/workers/{token}`). Siguen fuera de cualquier identidad y sin sesión.
- La credencial de la pantalla del centro (la tableta del QR). Su cambio a una credencial renovable va con la app instalable.
- Enviar enlaces por SMS o WhatsApp. El enlace de un solo uso sale por correo o lo entrega Recursos Humanos en mano.
- Probar que una llave vive en un solo teléfono. Las llaves sincronizadas siguen aceptadas, como hoy.
- Migrar datos. No hay empresas clientes; las tablas `portal_links` y la de dispositivos de asistencia se rehacen o se borran sin conservar filas.
- Cambiar los niveles de evidencia de una marca o el criterio 3.3 (la hora la pone el servidor).

## Open Questions

Las decisiones de arquitectura ya están tomadas (ver "Decisiones del autor" en el plan técnico). Queda por comprobar en dispositivos reales:

*   **El selector de llaves mezcla las del panel y las de la persona.** Las llaves de la cuenta de Recursos Humanos y las de la persona trabajadora pertenecen al mismo dominio. Alguien que tiene las dos verá ambas al tocar "Entrar con mi huella". Hay que comprobar en Safari y Chrome que el nombre con que se registra cada llave las distingue, y qué mensaje ve quien elige la equivocada.
*   **Registro de la llave desde el navegador interno de una app de correo.** El enlace de un solo uso se abre ahí en muchos teléfonos. Hay que comprobar en cuáles falla la creación de la llave, para mostrar "Ábralo en Safari o Chrome" antes de gastar el enlace.

## Critical User Journeys (CUJs)

### CUJ 1: Primera entrada y registro de la llave

*   **Given**: Recursos Humanos dio de alta a Ana en una empresa y le envió su invitación. Ana no tiene llave ni sesión.
*   **When**: Ana abre en su teléfono el enlace de un solo uso del correo y, ya dentro, crea su llave con su huella.
*   **Then**: El enlace deja de servir. Ana queda con una sesión abierta y una llave registrada. Recibe un aviso "Se registró una llave de acceso" con el botón "No fui yo". La bitácora guarda el registro. Si alguien abre después el mismo enlace, ve que ya no sirve.

### CUJ 2: Entrar con la llave

*   **Given**: Ana tiene una llave registrada y ninguna sesión abierta en este navegador.
*   **When**: Abre la dirección de entrada de su empresa y toca "Entrar con mi huella".
*   **Then**: Se abre su sesión y ve las herramientas que su empresa usa. La dirección no contiene ninguna credencial. La pantalla de entrada no mostró el nombre de nadie antes de la firma.

### CUJ 3: Entrar sin llave

*   **Given**: Beto usa una computadora compartida que no guarda llaves de acceso.
*   **When**: Pide un enlace desde la dirección de entrada y abre el que le llega por correo.
*   **Then**: Se abre su sesión. El enlace sirvió una vez. La respuesta al pedirlo es la misma exista o no esa persona, de modo que nadie puede averiguar quién trabaja en la empresa.

### CUJ 4: Marcar por primera vez

*   **Given**: Ana tiene llave y sesión, su empresa usa asistencia, y Ana no ha aceptado el acuerdo de asistencia.
*   **When**: Entra a Marcar.
*   **Then**: Antes de poder marcar lee y acepta el acuerdo. Sin esa aceptación, el servidor rechaza una marca aunque la firma sea válida. Después, cada marca pide su propia firma, como hoy.

### CUJ 5: Ver algo delicado

*   **Given**: Ana tiene una sesión abierta desde hace 20 minutos.
*   **When**: Abre el mensaje de apoyo de la NOM-035.
*   **Then**: La app le pide su huella otra vez antes de mostrarlo. Sin esa prueba reciente, el servidor no envía el contenido.

### CUJ 6: "No fui yo"

*   **Given**: Alguien que tuvo acceso al correo de Ana abrió su enlace de un solo uso y registró una llave.
*   **When**: Ana recibe el aviso y toca "No fui yo".
*   **Then**: Esa llave y todas las sesiones de Ana quedan revocadas, Recursos Humanos recibe un aviso, y la bitácora lo guarda. Ana necesita un enlace nuevo de Recursos Humanos para volver a entrar.

### CUJ 7: Teléfono perdido

*   **Given**: Ana perdió el teléfono donde estaba su llave.
*   **When**: Recursos Humanos reemite su acceso desde el panel.
*   **Then**: Todas las llaves y sesiones anteriores de Ana dejan de servir en ese momento. Ana recibe un enlace de un solo uso nuevo y repite el CUJ 1.

### CUJ 8: Teléfono compartido

*   **Given**: Ana entró a su portal en el teléfono de Beto y tocó "Salir".
*   **When**: Beto abre la dirección de entrada en ese mismo teléfono.
*   **Then**: Ve la pantalla de entrada sin el nombre de Ana ni ningún dato suyo, y entra con su propia llave.

### CUJ 9: Empresa sin asistencia

*   **Given**: La empresa de Carla usa vacaciones y Ley Silla, pero no asistencia.
*   **When**: Carla abre su invitación, crea su llave y entra.
*   **Then**: Ve vacaciones y Ley Silla. No ve Marcar ni se le pide el acuerdo de asistencia.

## Technical Plan (Architecture)

### Decisiones del autor

| # | Pregunta | Decisión |
|---|---|---|
| 1 | Qué llevan los correos que hoy traen el enlace del portal | La dirección de entrada, sin credencial |
| 2 | Qué prueba reciente da quien no tiene llave | Un código de seis dígitos que le llega por correo |
| 3 | Quién autoriza una llave nueva tras perder el teléfono | Solo Recursos Humanos |
| 4 | Dónde vive la sesión | En la sesión de Laravel, con un guard `worker` |
| 5 | Quién es dueño de la pantalla de inicio y de las secciones | El módulo nuevo de identidad |

Valores y nombres fijados por el diseño (mecánicos, se pueden cambiar sin tocar la arquitectura): módulo `WorkerAccess`; prefijo `/me/{tenant:slug}`; sesión de 30 minutos sin uso y 8 horas como máximo; prueba reciente de 5 minutos; hasta 3 llaves activas por persona; código de 10 minutos con 5 intentos. Todos en `config/worker_access.php`.

### Vista general

```text
                         ┌──────────────────────── WorkerAccess (nuevo) ───────────────────────┐
 Correo ──enlace de      │  worker_access_links   worker_credentials   worker_access_states    │
 un solo uso──────────►  │        │                      │                     │               │
                         │        ▼                      ▼                     ▼               │
 Teléfono ──huella────►  │   abrir enlace ──► sesión de Laravel (guard worker) ◄── firma       │
                         │                           │                                         │
                         │   Contratos: WorkerSession · WorkerSignatures · WorkerLinks ·       │
                         │              WorkerInvitations · WorkerCredentials · WorkerSection  │
                         └───────────────┬───────────────────────────┬─────────────────────────┘
                                         │ WorkerSession             │ WorkerSignatures
                 ┌───────────────────────▼─────────────┐   ┌─────────▼───────────────────────┐
                 │ Secciones en /me/{slug}/...         │   │ Attendance en /a/{slug}/...     │
                 │ Telework · Leave · Seating ·        │   │ Marcar, Mi semana, corrección:  │
                 │ Disclosure · Organization ·         │   │ una firma por acción + acuerdo  │
                 │ Engagement · Results                │   │ aceptado                        │
                 └─────────────────────────────────────┘   └─────────────────────────────────┘
```

Organization sigue siendo dueño de las personas (`Roster`). Tenancy sigue resolviendo la empresa por su slug (`Companies::enterBySlug`). WorkerAccess no es una herramienta que se venda: existe para toda empresa que no esté bloqueada y no entra en el catálogo `Module`.

### Datos

Tres tablas nuevas, todas con `tenant_id` y el scope de empresa. Reemplazan a `portal_links`, `attendance_devices` y `attendance_enrollment_links`, que se borran sin migrar filas.

**`worker_credentials`** (la llave de la persona)

| Columna | Nota |
|---|---|
| `id` uuid, `tenant_id`, `employee_id` | FK compuesta a `employees`, como hoy `attendance_devices` |
| `credential_id` text único, `public_key`, `sign_count`, `backup_eligible`, `backup_state`, `label` | Lo mismo que hoy guarda el dispositivo de asistencia |
| `registered_via` | `issued_link` o `session` |
| `registered_at`, `last_used_at`, `revoked_at`, `revoked_reason` | Motivos: `reissued`, `revoked_by_hr`, `not_me` |

Las tablas de Attendance que hoy apuntan a `attendance_devices.device_id` (marcas, confirmaciones de semana, solicitudes de corrección, aceptaciones del acuerdo) cambian esa columna a `credential_id` con llave foránea a `worker_credentials`. Es una llave foránea entre módulos, igual que la que ya existe hacia `employees`; ningún módulo hace joins a la tabla del otro.

**`worker_access_links`** (enlace de un solo uso)

| Columna | Nota |
|---|---|
| `id`, `tenant_id`, `employee_id`, `token_hash` char(64) único | Solo se guarda el hash; el token es aleatorio, no derivado |
| `kind` | `issued`: lo emite Recursos Humanos, permite registrar una llave. `requested`: lo pide la persona, solo abre sesión |
| `issued_by` | Usuario del panel; nulo en `requested` |
| `expires_at`, `used_at`, `revoked_at` | Un UPDATE condicional lo gasta, como hoy `EnrollmentLink` |

**`worker_access_states`** (una fila por persona)

| Columna | Nota |
|---|---|
| `tenant_id`, `employee_id` único | |
| `valid_after` timestamptz | Toda sesión y todo enlace anteriores a este instante dejan de valer |

No hay tabla de sesiones propia (decisión 4). El código de seis dígitos y los retos de firma viven en caché con su vencimiento, como ya hacen hoy los retos de `WebAuthnCeremony`.

### Sesión

El guard `worker` se registra con `Auth::viaRequest` y se declara en `config/auth.php`. No usa un proveedor de usuarios: lee una entrada de la sesión de Laravel y la valida en cada petición.

```text
session['worker'] = {
  tenant_id, employee_id,
  entered_at, seen_at,
  via: key | issued_link | requested_link,
  credential_id?,      # la llave con la que entró, si entró con llave
  proof_at?            # última prueba reciente
}
```

Una petición tiene sesión de persona solo si se cumple todo esto:

1. `tenant_id` es la empresa del slug de la dirección. Una sesión de la empresa A no vale en la dirección de la empresa B.
2. `now - seen_at` no pasa de 30 minutos y `now - entered_at` no pasa de 8 horas.
3. `entered_at` es posterior a `valid_after` de esa persona.
4. La persona sigue activa en `Roster` y la empresa no está bloqueada.

Si falla, la entrada se borra y la petición sigue como visitante. Cada petición válida actualiza `seen_at`. Entrar y salir regeneran el identificador de la sesión. "Salir" borra solo la entrada `worker`.

Consecuencias aceptadas de la decisión 4:

- La cookie es la misma que la del panel. Una persona de Recursos Humanos que también es empleada tiene las dos identidades en una sesión; cerrar la sesión del panel cierra también la de persona.
- Revocar no borra filas de `sessions`: mueve `valid_after`. Recursos Humanos no puede ver una lista de sesiones abiertas.

### Entrar

Todas las rutas de identidad viven en `routes/worker-access.php` bajo `/me/{tenant:slug}`, con un middleware `ResolveWorkerTenant` que entra a la empresa del slug y responde 404 si no existe o está bloqueada.

| Ruta | Qué hace |
|---|---|
| `GET /me/{slug}` (`worker.home`) | Con sesión: inicio con las secciones. Sin sesión: pantalla de entrada, sin el nombre de nadie |
| `POST /me/{slug}/enter/options`, `POST /me/{slug}/enter` | Reto y verificación de la firma; abre sesión con `via: key` y `proof_at = ahora` |
| `POST /me/{slug}/enter/link` | La persona escribe su correo y pide un enlace. La respuesta es siempre la misma |
| `GET /me/{slug}/link/{token}` | Muestra un botón "Continuar". No gasta el enlace: los filtros de correo abren los enlaces por adelantado |
| `POST /me/{slug}/link/{token}` | Gasta el enlace y abre sesión. `issued` lleva a registrar la llave; `requested` lleva al destino |
| `GET /me/{slug}/key`, `POST .../key/options`, `POST .../key` | Registrar una llave |
| `POST /me/{slug}/leave` | Salir |
| `GET` y `POST /me/{slug}/proof...` | Prueba reciente, con llave o con código |
| `GET` y `POST /me/{slug}/not-me/{credential}` | "No fui yo", con dirección firmada |

**Pedir un enlace (decisión 1 y CUJ 3).** El servidor busca el correo entre las personas activas de esa empresa. Envía el enlace solo si hay exactamente una coincidencia; si hay cero o varias, no envía nada y responde igual. Pedir uno nuevo anula los `requested` anteriores de esa persona. `Roster` gana un método para buscar por correo.

**Registrar una llave (decisión 3).** Se permite en dos casos:

- La sesión entró con un enlace `issued`. Es la primera llave, o la que sigue a una reemisión.
- La sesión entró con una llave. Es una llave adicional, hasta 3 activas (personal de confianza que usa teléfono y computadora).

Una sesión que entró con un enlace `requested` nunca puede registrar una llave. Cada registro escribe en la bitácora, envía el aviso `KeyRegistered` con el botón "No fui yo" y muestra una línea de advertencia: una cuenta de iCloud o Google compartida en familia comparte la llave.

**"No fui yo".** El aviso lleva una dirección firmada por la aplicación con el identificador de la llave. Abrirla muestra una confirmación; confirmar revoca esa llave, mueve `valid_after`, anula los enlaces pendientes, avisa a quienes administran la empresa y escribe en la bitácora.

**Reemitir (CUJ 7).** Desde el panel, `ReissueAccess` revoca todas las llaves de la persona, mueve `valid_after`, anula sus enlaces y crea un enlace `issued` nuevo en una sola transacción.

**Personas sin correo.** Recursos Humanos emite el enlace `issued` y la pantalla se lo muestra una vez, para entregarlo en mano, como hoy con el enlace de teletrabajo. La bitácora guarda quién lo mostró. Esa persona necesita registrar una llave, porque no puede pedir enlaces ni recibir códigos. Quien no tiene correo ni un dispositivo con llaves de acceso no puede usar la app; es un límite aceptado.

### Prueba reciente

Un middleware `RequireRecentProof` protege las rutas delicadas. Deja pasar si `proof_at` tiene menos de 5 minutos. Si no:

- La persona con al menos una llave activa firma con ella. No se le ofrece el código, para que el código no sea un atajo alrededor de la llave.
- La persona sin llave pide un código de seis dígitos (decisión 2). Se guarda su hash en caché 10 minutos, admite 5 intentos y los envíos están limitados.

En una página, el middleware redirige a la pantalla de prueba y regresa al destino. En un envío de formulario, regresa a la página con una bandera para que abra el paso de prueba y reenvíe.

Rutas delicadas: el mensaje de apoyo de la NOM-035; confirmar política, documentos, riesgos de Ley Silla, estado de cuenta de vacaciones, equipo recibido y visita; pedir vacaciones; aprobar o rechazar las vacaciones de un equipo. Cada módulo agrega el middleware en su propio archivo de rutas.

### Inicio y secciones

La pantalla de inicio y el registro de secciones se mudan de Telework a WorkerAccess (decisión 5).

- El contrato `PortalSection` de Telework se reemplaza por `WorkerSection` de WorkerAccess: `for(WorkerIdentity $person): ?WorkerSectionLink`. Ya no recibe un token. La etiqueta del contenedor pasa de `portal.sections` a `worker.sections`.
- Lo que hoy muestra el inicio del portal sobre teletrabajo (listas, fotos, visitas, documentos, equipo) pasa a ser la sección de Telework en `/me/{slug}/telework`.
- Attendance registra una sección "Marcar" que lleva a `/a/{slug}`, visible solo si la empresa usa asistencia.
- Las 29 rutas `portal/{token}/...` pasan a `/me/{tenant:slug}/...` con `ResolveWorkerTenant` y `auth:worker`. Conservan sus nombres `portal.*` para no tocar el inventario de rutas más de lo necesario.
- Los controladores dejan de recibir `$token` y de llamar a `PortalAccess::open($token)`. Piden `WorkerSession::current()`.
- `RequirePortalTool` deja de depender de Telework: pregunta a `Companies::current()` si la herramienta está detenida.
- Los correos que hoy llaman a `PortalAccess::token()` (Seating, Disclosure, Engagement, Results, Leave, Telework) llaman a `WorkerLinks::to('portal.vacations.show')`, que arma `/me/{slug}/vacations` sin credencial. Una persona sin sesión que abre esa dirección entra primero y después llega a su destino.

### Attendance

- `WebAuthnCeremony` se muda a WorkerAccess. Attendance verifica firmas con el contrato `WorkerSignatures`: `options(string $context): array` y `verify(string $context, array $response): SignedBy`, que devuelve el identificador de la llave y de la persona.
- Marcar, Mi semana y la solicitud de corrección no cambian de dirección ni de comportamiento: siguen en `/a/{slug}`, sin exigir sesión, con una firma por acción. Así una marca cuesta una huella, no dos.
- El alta de dispositivo desaparece: `EnrollmentLink`, `IssueEnrollment`, `EnrollDevice`, `SendEnrollmentLinks`, su correo, su página y sus tres rutas.
- **El acuerdo (criterio 5.1) se separa de la llave.** `AgreementAcceptance` se guarda por persona y versión del acuerdo, con la llave que firmó la aceptación. `RecordMark` rechaza una marca si la persona no aceptó la versión vigente; la respuesta le dice a la página que muestre el acuerdo. Aceptar es una acción con su propia firma en una ruta nueva de `/a/{slug}`. El criterio 5.1 de `docs/normas/jornada/criterios.md` se actualiza: la aceptación ocurre antes de la primera marca, no al registrar la llave.
- La pantalla "Dispositivos" del panel se muda a WorkerAccess como "Accesos" (ver abajo). Lo que Attendance necesita saber de una llave para sus reportes (etiqueta, banderas de respaldo) lo pide por el contrato `WorkerCredentials`.

### Panel de Recursos Humanos

Una página nueva "Accesos" en `/admin/{tenant}/access`, paginada en el servidor y con búsqueda, reemplaza a "Dispositivos" de Attendance y a los tres botones que hoy envían o regeneran el enlace del portal (empleados, teletrabajadores, saldos de vacaciones).

- Por persona: estado (sin invitar, invitada, con llave), número de llaves, banderas de respaldo y último uso.
- Acciones: invitar, invitar a todas las personas de un centro, reemitir, revocar una llave. Para una persona sin correo, mostrar el enlace una vez.
- Permisos nuevos: `view-worker-access` (owner, hr, viewer) y `manage-worker-access` (owner, hr).
- Los módulos que hoy tienen un botón de "enviar enlace" llaman al contrato `WorkerInvitations` o enlazan a esta página.

### Contratos públicos de WorkerAccess

| Contrato | Para quién | Forma |
|---|---|---|
| `WorkerSession` | Los módulos del portal | `current(): ?WorkerIdentity` |
| `WorkerSignatures` | Attendance | `options(string $context): array`, `verify(string $context, array $response): SignedBy` |
| `WorkerLinks` | Correos de cualquier módulo | `home(): string`, `to(string $routeName, array $parameters = []): string` |
| `WorkerInvitations` | Acciones del panel de otros módulos | `send(string $employeeId, int $issuedBy): InvitationOutcome` |
| `WorkerCredentials` | Attendance (reportes y preparación de un centro) | `describe(array $credentialIds): array`, `countsByEmployee(array $employeeIds): array` |
| `WorkerSection` | Cada módulo registra la suya | `for(WorkerIdentity $person): ?WorkerSectionLink` |

Datos: `WorkerIdentity` (empresa, persona, nombre de la empresa, nombre de la persona), `SignedBy`, `WorkerSectionLink`, `InvitationOutcome`, `CredentialSummary`. Evento: `WorkerAccessRevoked`.

### Frontend

- `pages/worker-access/`: `enter`, `home`, `link`, `key`, `proof`, `not-me` (marco propio, sin sidebar) y `access` (panel).
- `components/worker-access/`: las piezas de esas pantallas. La pantalla de inicio parte de la del portal actual y la de registro de llave parte de la de alta de asistencia, que ya cumplen el rediseño.
- `lib/webauthn.ts`: se muda desde `lib/attendance/webauthn.ts`, porque ahora la usan dos módulos.
- Las 13 páginas del portal dejan de recibir la propiedad `token`; sus enlaces usan las rutas generadas por Wayfinder con el slug.
- `PageFrame.php`, `lib/page-frame.ts` y `layouts/frames.ts` registran las páginas nuevas.

### Orden de trabajo

El proyecto usa un PR por trabajo, con commits atómicos que dejan las pruebas en verde. Este diseño es un trabajo, así que va en una rama y un PR, en este orden de commits:

1. Módulo `WorkerAccess` con la llave: tabla `worker_credentials`, la ceremonia mudada, y Attendance verificando por contrato.
2. El acuerdo separado de la llave y exigido al marcar.
3. Enlaces de un solo uso, guard `worker`, entrada, registro de llave, "No fui yo" y salir.
4. Página "Accesos" del panel; se borra el alta de dispositivo de Attendance.
5. Inicio y `WorkerSection`; Telework pasa a ser una sección.
6. Cada módulo del portal a sesión, uno por commit: Telework, Leave, Seating, Disclosure, Organization, Engagement, Results.
7. Prueba reciente con llave y con código, y su middleware en las rutas delicadas.
8. Correos sin credencial.
9. Borrado de `portal_links`, los contratos de portal de Telework y las rutas con `{token}`; la prueba que prohíbe credenciales en direcciones.

## Alternatives Considered

Las alternativas A a E vienen de `docs/superpowers/specs/2026-11-14-worker-pwa-brief.md` y de su revisión. Las F a M son las opciones que el autor descartó al tomar las cinco decisiones del plan técnico. Las N a P surgieron al redactar.

### Alternative A: Usar el enlace estable del portal como arranque de la llave

*   **Pros**: No hace falta un tipo nuevo de enlace; la persona ya tiene ese correo.
*   **Cons**: Cada correo lleva el mismo enlace hasta que Recursos Humanos lo revoca. Quien tenga un correo viejo o reenviado registraría una llave a nombre de la persona y la dejaría fuera.
*   **Reason for Rejection**: Rejected by author: convierte una exposición de solo lectura, que hoy comparten todos los que tienen el enlace, en la toma exclusiva de la identidad.

### Alternative B: Conservar el enlace estable como respaldo para quien no tiene llave

*   **Pros**: Quien no puede guardar una llave conserva la comodidad de hoy: un enlace que siempre sirve.
*   **Cons**: Quedan dos modelos de credencial para siempre, con dos revocaciones y dos bitácoras, y la credencial sigue en la dirección y en el historial.
*   **Reason for Rejection**: Rejected by author: un modelo con dos entradas (llave o enlace de un solo uso) cubre el mismo caso sin dejar una credencial permanente en un correo.

### Alternative C: Entregar el enlace del portal después de una firma ("Mi portal")

*   **Pros**: Cambio pequeño: una ruta nueva en Attendance que llama a `TokenPortalAccess::token()`.
*   **Cons**: La credencial estable aparece en la barra de direcciones y en el historial. Solo sirve a empresas con asistencia. Una llave sincronizada se vuelve la entrada a datos más delicados sin ninguna prueba adicional.
*   **Reason for Rejection**: Rejected by author: mantiene el enlace estable, que es el problema de fondo.

### Alternative D: Dejar la llave en Attendance, o moverla a Telework o a Organization

*   **Pros**: No se crea un módulo. En Attendance no se mueve nada.
*   **Cons**: En Attendance, una empresa sin asistencia no tiene credencial y seis módulos dependerían de una herramienta opcional. En Telework ocurre lo mismo con la NOM-037. En Organization, el módulo de las personas tendría que cargar con WebAuthn, sesiones, límites y correos de alta.
*   **Reason for Rejection**: Rejected by author: ninguna de las tres cierra el hueco de las empresas sin asistencia sin romper el aislamiento de módulos.

### Alternative E: Construir primero la app instalable, o hacer solo el módulo y el prefijo antes de la app

*   **Pros**: La app instalable sale antes.
*   **Cons**: La app se construiría sobre `/a/{slug}` y solo para empresas con asistencia; después habría que mover el manifest, el service worker y las suscripciones. El camino intermedio deja vivo el enlace estable del portal mientras tanto.
*   **Reason for Rejection**: Rejected by author: sin empresas clientes no hay urgencia que justifique construir sobre una base que se va a mover.

### Alternative F: Un enlace de un solo uso en cada correo de rutina

*   **Pros**: Nadie tiene un paso extra: un clic y la persona está en su destino.
*   **Cons**: Cada correo de rutina es una credencial durante 48 horas y una persona puede tener varias vivas a la vez. En un buzón compartido o leído por el patrón, quien abre primero obtiene una sesión.
*   **Reason for Rejection**: Rejected by author: se prefirió que ningún correo de rutina sirva para entrar.

### Alternative G: Correos distintos según la persona tenga llave o no

*   **Pros**: Nadie tiene paso extra y solo quien no tiene llave recibe correos que sirven para entrar.
*   **Cons**: Dos variantes de cada correo y una consulta más en cada envío. Quien tiene la llave en el teléfono y abre el correo en una computadora queda en un caso intermedio.
*   **Reason for Rejection**: Rejected by author: se eligió una sola forma de correo, sin credencial.

### Alternative H: Que la prueba reciente de quien no tiene llave sea "entró hace poco"

*   **Pros**: No se construye ningún mecanismo nuevo.
*   **Cons**: Pasados unos minutos la persona tiene que salir y pedir otro enlace para hacer algo delicado.
*   **Reason for Rejection**: Rejected by author: se eligió el código por correo, que no expulsa a la persona de su sesión.

### Alternative I: Que lo delicado solo se abra con llave

*   **Pros**: La prueba fuerte protege lo delicado, sin excepciones y sin un mecanismo adicional.
*   **Cons**: Quien no tiene un dispositivo con llaves de acceso no puede pedir vacaciones ni confirmar documentos desde el portal, y el mensaje de apoyo de la NOM-035 tendría que llegarle por otro medio.
*   **Reason for Rejection**: Rejected by author: dejaría sin acceso a funciones básicas a quien no tiene un dispositivo adecuado.

### Alternative J: Recuperación por la propia persona, al instante o con 24 horas de espera

*   **Pros**: La persona no depende de que Recursos Humanos esté disponible. Con espera, la persona real tiene un día para cancelar.
*   **Cons**: Al instante, quien lee el buzón registra una llave y puede marcar por la persona en un minuto. Con espera, la persona pasa un día sin marcar y hay que construir el estado "llave en espera".
*   **Reason for Rejection**: Rejected by author: una llave firma marcas de asistencia; solo Recursos Humanos autoriza una nueva.

### Alternative K: Tabla y cookie propias para la sesión

*   **Pros**: Revocar es marcar filas, Recursos Humanos puede ver las sesiones abiertas, la cookie no viaja a `/admin` ni a las páginas anónimas, y los avisos push futuros tendrían una fila a la cual ligarse.
*   **Cons**: Más código y pruebas (middleware, limpieza de filas vencidas), y la seguridad del mecanismo de sesión queda a cargo del proyecto.
*   **Reason for Rejection**: Rejected by author: se prefirió reusar la sesión de Laravel con un guard propio y revocar por marca de tiempo.

### Alternative L: Dejar la pantalla de inicio en Telework

*   **Pros**: Se mueve menos código y el módulo de identidad se limita a llave y sesión.
*   **Cons**: Una empresa sin NOM-037 depende de Telework para su inicio, y Marcar se registraría como sección dentro de un módulo de teletrabajo.
*   **Reason for Rejection**: Rejected by author: el inicio es lo primero que ve toda persona y no pertenece a una herramienta en particular.

### Alternative M: Dos módulos nuevos, uno para identidad y otro para el portal

*   **Pros**: Separa la seguridad (llaves, sesiones) de la pantalla (inicio, secciones); un cambio en el inicio no toca el código de llaves.
*   **Cons**: Dos juegos de contratos y más archivos para una sola persona que mantiene el proyecto.
*   **Reason for Rejection**: Rejected by author: se eligió un solo módulo con identidad e inicio.

### Alternative N: Exigir sesión para Marcar

*   **Pros**: Un solo modelo: todo lo de la persona pasa por la sesión, y la página de Marcar sabría quién es antes de la primera firma.
*   **Cons**: Una marca costaría dos huellas, una para entrar y otra para firmar la marca. Quien escanea el QR del centro con la cámara tendría que entrar antes de marcar.
*   **Reason for Rejection**: Rejected by author: marcar es la acción más frecuente y ya exige una firma propia, que identifica a la persona por sí sola.

### Alternative O: Una cookie firmada sin estado en el servidor

*   **Pros**: Ninguna lectura por petición y ningún dato de sesión guardado.
*   **Cons**: No se puede revocar antes de que venza.
*   **Reason for Rejection**: Rejected by author: contradice las metas 6 y 7, que exigen revocar llaves y sesiones al momento.

### Alternative P: Mantener el contrato `PortalAccess` con el token y poner un puente a la sesión

*   **Pros**: Los seis módulos del portal no cambian sus controladores ni sus rutas.
*   **Cons**: El puente necesita seguir pasando un token por la dirección, que es lo que se quiere eliminar.
*   **Reason for Rejection**: Rejected by author: el contrato cambia una vez y el barrido de rutas es mecánico.

--------------------------------------------------------------------------------

## Non-Functional Requirements

### Seguridad y privacidad

- **Anonimato de las encuestas.** La sesión de persona comparte cookie con todo el sitio, incluidas las páginas de encuesta. El identificador de la persona se agrega al contexto de los logs solo dentro de `/me/{slug}` y `/a/{slug}`, nunca de forma global. Una prueba abre una sesión de persona, contesta una encuesta en el mismo navegador y afirma que ninguna línea de log de esa petición contiene el identificador de la persona.
- **No revelar quién trabaja en una empresa.** Pedir un enlace, fallar una firma y abrir un enlace gastado responden igual exista o no la persona. La pantalla de entrada no muestra nombres.
- **Límites por dirección y por persona** para entrar, pedir enlace, abrir enlace, registrar llave y pedir o intentar un código.
- **Bitácora** para: enlace emitido, enlace mostrado en pantalla, llave registrada, llave revocada, acceso reemitido y "No fui yo". **Log** con nombre fijo para cada cambio de estado (`worker.entered`, `worker.left`, `worker.link_requested`, `worker.link_used`, `worker.key_registered`, `worker.key_revoked`, `worker.access_reissued`, `worker.not_me`, `worker.proof_passed`, `worker.proof_failed`). Ningún token, código, reto ni llave pública llega a un log; `RedactSensitiveData` gana las llaves nuevas con su prueba.
- **Aislamiento de empresa.** Cada tabla nueva tiene su prueba de que la empresa A no lee ni modifica lo de B, y una sesión de A en la dirección de B se trata como visitante.
- **Límite conocido.** Para una persona sin correo, Recursos Humanos ve el enlace en pantalla y podría registrar una llave a nombre de esa persona. Queda en la bitácora quién lo mostró. Ya ocurre hoy con el enlace de alta entregado en mano.
- **CSP y encabezados** sin cambios.

### Rendimiento

Cada petición con sesión de persona hace dos lecturas: `worker_access_states` y la persona en `Roster`. Las páginas del portal ya hacían una lectura del enlace y una de la persona, así que el conteo no sube. Las pruebas de las páginas de inicio y de "Accesos" afirman su número de consultas.

### Despliegue y reversa

No hay empresas clientes: las migraciones borran y crean tablas sin conservar filas, y no hay interruptores. La reversa es revertir el PR. Las llaves del panel de Recursos Humanos no se tocan.

--------------------------------------------------------------------------------

### Additional Resources

*   `docs/superpowers/specs/2026-11-14-worker-pwa-brief.md`: el brief de la app instalable, del que este diseño es el proyecto previo. Al reconciliar, el autor mantuvo el borrador en cuatro puntos donde difería del brief: la vigencia de los enlaces (48 horas y 30 minutos), el alcance del módulo (incluye inicio, secciones y "Accesos"), un solo PR, y los avisos push ligados a la llave y a la persona.
*   `docs/normas/jornada/criterios.md`: criterios 3.3 (hora del servidor), 5.1 (acuerdo) y 5.3 (niveles de evidencia).
*   `docs/superpowers/specs/2026-11-07-attendance-design.md`: el diseño de asistencia que introdujo la llave de acceso.
*   `CLAUDE.md`: reglas de módulos, privacidad, logging, pruebas y flujo de trabajo.

### Future Improvements

*   **Avisos push**: se ligarán a la llave y a la persona, porque no hay registro por sesión. El diseño de la app instalable debe borrar la suscripción desde el navegador al tocar "Salir" y cuando entra otra persona en el mismo teléfono.
*   **Tabla propia de sesiones** (alternativa K): si Recursos Humanos necesita ver o cerrar sesiones una por una, la sesión puede pasar a una tabla sin cambiar los contratos, porque `WorkerSessionStore` es la única clase que toca la sesión.
*   **Recuperación por la propia persona**: con espera y aviso, si depender de Recursos Humanos resulta un freno en turnos de noche o empresas pequeñas.
*   **Enlaces por SMS o WhatsApp**: para personas sin correo, cuando existan esos canales.

--------------------------------------------------------------------------------

## Detailed Implementation Plan

Cada grupo es una serie de commits (un comportamiento por commit: su prueba primero, luego el código) dentro de un solo PR en la rama `feature/worker-access`. Cada tabla da el directorio completo en su encabezado y los archivos por nombre. **C** = crear, **M** = modificar, **B** = borrar, **Mv** = mover y renombrar.

### Grupo 1. El módulo y la llave

Objetivo: la llave deja de ser de Attendance sin cambiar ningún comportamiento visible.

**`app/Modules/WorkerAccess/`**

| Archivo | | Cambio y motivo |
|---|---|---|
| `Providers/WorkerAccessServiceProvider.php` | C | Enlaza los contratos con sus implementaciones. Más adelante registra el guard y los limitadores |
| `Models/WorkerCredential.php` | C | Modelo `final` con `BelongsToCurrentTenant` y `#[UseFactory]`. Columnas de la tabla `worker_credentials` del plan técnico |
| `Enums/RegisteredVia.php`, `Enums/RevokedReason.php` | C | `issued_link`, `session`; `reissued`, `revoked_by_hr`, `not_me` |
| `Support/WebAuthnCeremony.php` | Mv | Desde `app/Modules/Attendance/Support/`. Mismos cuatro métodos públicos; lee y escribe `WorkerCredential` en lugar de `AttendanceDevice` |
| `Data/CredentialData.php` | Mv | Desde Attendance. Interno al registro de la llave |
| `Data/SignedBy.php` | C | Reemplaza a `VerifiedAssertion`: `credentialId` (uuid de la fila) y `employeeId`. Cruza la frontera del módulo |
| `Exceptions/InvalidCredential.php` | Mv | Desde Attendance. La lanza la ceremonia; Attendance la atrapa por el contrato |
| `Contracts/WorkerSignatures.php` | C | `options(string $context): array`, `verify(string $context, array $response): SignedBy`. `@throws InvalidCredential` |
| `Contracts/WorkerCredentials.php` | C | `describe(array $credentialIds): array` (etiqueta, banderas de respaldo, fecha de registro y de revocación) y `countsByEmployee(array $employeeIds): array` (llaves activas por persona) |
| `Data/CredentialSummary.php` | C | Lo que devuelve `describe` |
| `Support/CeremonySignatures.php`, `Support/StoredCredentials.php` | C | Implementaciones de los dos contratos |
| `README.md` | C | Qué es el módulo, sus contratos y sus reglas (una sesión, dos entradas; quién puede registrar una llave) |

**Otros**

| Archivo | | Cambio y motivo |
|---|---|---|
| `bootstrap/providers.php` | M | Registra `WorkerAccessServiceProvider` |
| `config/worker_access.php` | C | `challenge_seconds` (60), `max_keys` (3); los grupos siguientes agregan sus llaves |
| `config/attendance.php` | M | Sale `webauthn.challenge_seconds` |
| `.env.example` | M | Variables nuevas de `worker_access`; sale la de asistencia |
| `database/migrations/…_create_worker_credentials_table.php` | C | La tabla, con único `(id, tenant_id)`, índice `(tenant_id, employee_id)` y FK compuesta a `employees` |
| `database/migrations/…_point_attendance_at_worker_credentials.php` | C | En `attendance_marks`, `attendance_weekly_confirmations`, `attendance_correction_requests` y `attendance_agreement_acceptances`: renombra `device_id` a `credential_id` y cambia la FK a `worker_credentials`. Borra `attendance_devices`. Sin filas que conservar |
| `database/factories/WorkerAccess/WorkerCredentialFactory.php` | C | Con estados `revoked()` y `synced()` |
| `database/factories/Attendance/AttendanceDeviceFactory.php` | B | El modelo desaparece |
| `database/factories/Attendance/WeeklyConfirmationFactory.php` | M | Usa `credential_id` y la factory nueva |

**`app/Modules/Attendance/`**

| Archivo | | Cambio y motivo |
|---|---|---|
| `Models/AttendanceDevice.php` | B | Lo reemplaza `WorkerCredential` |
| `Data/VerifiedAssertion.php` | B | Lo reemplaza `SignedBy` |
| `Models/AttendanceMark.php`, `Models/WeeklyConfirmation.php`, `Models/CorrectionRequest.php`, `Models/AgreementAcceptance.php` | M | `device_id` pasa a `credential_id`; se quita la relación al modelo borrado |
| `Actions/RecordMark.php`, `ConfirmWeek.php`, `DisputeWeek.php`, `ReopenWeeks.php`, `RequestCorrection.php` | M | Reciben `credentialId` en lugar de `deviceId` |
| `Data/MarkContext.php`, `Support/MarkPolicyCheck.php` | M | Renombre del campo |
| `Http/Controllers/MarkController.php`, `WeekController.php`, `CorrectionRequestController.php` | M | Inyectan `WorkerSignatures` en lugar de `WebAuthnCeremony`. `MarkController::remembered()` pregunta a `WorkerCredentials::countsByEmployee` si la persona tiene una llave activa |
| `Support/DevicePages.php`, `Support/SetupProgress.php`, `Support/CompanyAttendanceUsage.php` | M | Leen llaves por `WorkerCredentials`, no por el modelo |
| `Actions/EnrollDevice.php`, `Actions/RevokeDevice.php` | M | Temporal en este grupo: crean y revocan `WorkerCredential` a través de una acción interna de WorkerAccess expuesta por contrato. Se borran en el grupo 4 |

**Pruebas**

| Archivo | | Cambio y motivo |
|---|---|---|
| `tests/Feature/WorkerAccess/WebAuthnCeremonyTest.php` | Mv | Desde `tests/Feature/Attendance/`. Declara `mutates(WebAuthnCeremony::class)` |
| `tests/Feature/WorkerAccess/TenantIsolationTest.php` | C | La empresa A no lee ni firma con una llave de B, por consulta y por dirección |
| `tests/Feature/WorkerAccess/WorkerCredentialsContractTest.php` | C | `describe` y `countsByEmployee`, con su número de consultas |
| `tests/Support/SoftAuthenticator.php`, `tests/Browser/attendance-helpers.php` | M | Crean `WorkerCredential` |
| `tests/Feature/Attendance/` (`PunchTest`, `WeekTest`, `WeeklyConfirmationTest`, `CorrectionRequestTest`, `ResolveCorrectionTest`, `RevokeDeviceTest`, `EnrollmentTest`, `DeviceTableTest`, `DevicesPageTest`, `SetupTest`, `CenterTest`, `AttendanceUsageTest`, `AuthorityReportExportTest`, `LogPrivacyTest`, `TenantIsolationTest`) | M | Factory y nombre de columna nuevos. Ninguna aserción de comportamiento cambia |
| `tests/Unit/ArchitectureTest.php` | M | Bloque `it('has the WorkerAccess module registered')` y la regla de que Attendance no importa internos de WorkerAccess |

### Grupo 2. El acuerdo separado de la llave

| Archivo | | Cambio y motivo |
|---|---|---|
| `database/migrations/…_key_agreement_acceptances_by_person.php` | C | `attendance_agreement_acceptances`: único `(tenant_id, employee_id, agreement_id)`; `credential_id` queda como la llave que firmó la aceptación |
| `app/Modules/Attendance/Actions/AcceptAgreement.php` | M | Firma: `handle(string $employeeId, string $credentialId, int $version)`. Ya no se llama desde el alta |
| `app/Modules/Attendance/Support/CurrentAgreement.php` | M | Gana `acceptedBy(string $employeeId): bool` para la versión vigente |
| `app/Modules/Attendance/Exceptions/AgreementRequired.php` | C | La lanza `RecordMark` cuando la persona no aceptó la versión vigente |
| `app/Modules/Attendance/Actions/RecordMark.php` | M | Revisa el acuerdo antes de guardar la marca |
| `app/Modules/Attendance/Http/Controllers/AgreementController.php` | C | `options` (reto) y `store` (firma + versión leída). Contexto de reto propio: `agreement:{sesión}` |
| `app/Modules/Attendance/Http/Requests/AgreementRequest.php` | C | `credential` y `agreement_version` |
| `app/Modules/Attendance/Http/Controllers/MarkController.php` | M | `store` responde 409 con `{agreement: {version, body}}` ante `AgreementRequired` |
| `routes/attendance-device.php` | M | `attendance.device.agreement.options` y `.store` bajo `/a/{tenant:slug}/agreement`, con el limitador `attendance-punch` |
| `app/Modules/Attendance/Providers/AttendanceServiceProvider.php` | M | Nada nuevo aquí salvo que el limitador cubra las rutas del acuerdo |
| `resources/js/lib/attendance/punch.ts`, `resources/js/hooks/use-punch.ts` | M | Estado nuevo `agreement`: ante el 409 muestra el acuerdo, firma la aceptación y reintenta la marca |
| `resources/js/components/attendance/punch-agreement.tsx` | Mv | Desde `enroll-agreement.tsx`; lo usa la pantalla de Marcar |
| `resources/js/components/attendance/punch-screen.tsx` | M | Muestra el paso del acuerdo |
| `resources/js/types/attendance.ts` | M | Tipo del estado nuevo |
| `docs/normas/jornada/criterios.md` | M | Criterio 5.1: la aceptación ocurre antes de la primera marca, con firma propia, y no al registrar la llave. Con su justificación |
| `tests/Feature/Attendance/AgreementTest.php` | M | Marca rechazada sin acuerdo; aceptada después; versión vieja rechazada; una persona con llave y sin acuerdo no puede marcar |
| `tests/Feature/RouteSecurityTest.php` | M | Dos rutas públicas nuevas con su razón |
| `tests/js/lib/attendance/punch.test.ts` | M | Transiciones del estado `agreement` |
| `tests/Browser/AttendancePunchTest.php` | M | Primera marca: lee el acuerdo, acepta, marca |

### Grupo 3. Enlaces, sesión y entrada

**`app/Modules/WorkerAccess/`**

| Archivo | | Cambio y motivo |
|---|---|---|
| `Models/WorkerAccessLink.php`, `Models/WorkerAccessState.php` | C | Las otras dos tablas. `WorkerAccessLink::findPending(string $token)` como hoy `EnrollmentLink` |
| `Enums/LinkKind.php`, `Enums/EntryVia.php` | C | `issued`, `requested`; `key`, `issued_link`, `requested_link` |
| `Data/WorkerIdentity.php` | C | `tenantId`, `employeeId`, `company`, `name` |
| `Contracts/WorkerSession.php` | C | `current(): ?WorkerIdentity` |
| `Support/WorkerSessionStore.php` | C | Implementa el contrato. Lee, valida (las cuatro condiciones del plan técnico), escribe y borra la entrada `worker` de la sesión; mueve `seen_at`. Es la única clase que toca esa entrada |
| `Support/SessionWorker.php` | C | Envoltura `Authenticatable` de `WorkerIdentity` para el guard |
| `Providers/WorkerAccessServiceProvider.php` | M | `Auth::viaRequest('worker-session', …)`; limitadores `worker-enter`, `worker-link`, `worker-key` |
| `Http/Middleware/ResolveWorkerTenant.php` | C | Entra a la empresa del slug; 404 si no existe o está bloqueada. No exige ninguna herramienta |
| `Actions/IssueAccessLink.php` | C | Crea un enlace `issued` (48 h) y anula los pendientes de esa persona. Devuelve el token en claro; solo guarda el hash |
| `Actions/RequestAccessLink.php` | C | Busca el correo en `Roster`; con exactamente una persona activa crea un enlace `requested` (30 min), anula los `requested` anteriores y lo envía. Nunca dice si encontró a alguien |
| `Actions/OpenAccessLink.php` | C | UPDATE condicional que gasta el enlace; abre la sesión con `via` según su tipo. Nulo si el enlace no sirve |
| `Actions/EnterWithKey.php` | C | Verifica la firma y abre sesión con `via: key`, `credential_id` y `proof_at` |
| `Actions/RegisterKey.php` | C | Permite registrar solo si la sesión entró con `issued_link` o con `key`, y si hay menos de `max_keys` activas. Bitácora, log y aviso |
| `Actions/DisownKey.php` | C | "No fui yo": revoca la llave, mueve `valid_after`, anula enlaces, avisa al contacto de la empresa (`Companies::contact()`), bitácora |
| `Actions/LeaveSession.php` | C | Borra la entrada `worker` y regenera el identificador |
| `Notifications/AccessLinkRequested.php`, `Notifications/KeyRegistered.php`, `Notifications/KeyDisowned.php` | C | Correos en "usted", `MailBag::ESSENTIAL`. `KeyRegistered` lleva la dirección firmada de "No fui yo" y reemplaza a `DeviceLinked` |
| `Http/Controllers/HomeController.php` | C | `worker.home`: con sesión, el inicio (grupo 5 le da las secciones); sin sesión, la pantalla de entrada |
| `Http/Controllers/EntryController.php` | C | `options`, `store`, `leave` |
| `Http/Controllers/AccessLinkController.php` | C | `request` (pide enlace), `show` (botón "Continuar", no gasta), `open` (gasta) |
| `Http/Controllers/KeyController.php` | C | `show`, `options`, `store` |
| `Http/Controllers/NotMeController.php` | C | `show`, `store`; ambas con dirección firmada |
| `Http/Requests/RequestLinkRequest.php`, `AssertionRequest.php`, `RegisterKeyRequest.php` | C | Validación de cada entrada |

**Otros**

| Archivo | | Cambio y motivo |
|---|---|---|
| `routes/worker.php` | C | Las rutas `worker.*` de la tabla "Entrar" del plan técnico, bajo `/me/{tenant:slug}` |
| `routes/web.php` | M | `require` de `worker.php` |
| `config/auth.php` | M | Guard `worker` con driver `worker-session` |
| `bootstrap/app.php` | M | Un visitante en una ruta `auth:worker` va a `worker.home` de ese slug guardando su destino; el resto sigue yendo al inicio de sesión del panel. `AssignRequestContext` no agrega la persona al contexto global |
| `config/worker_access.php`, `.env.example` | M | `session.idle_minutes` (30), `session.absolute_hours` (8), `links.issued_hours` (48), `links.requested_minutes` (30) |
| `database/migrations/…_create_worker_access_links_table.php`, `…_create_worker_access_states_table.php` | C | Las tablas |
| `database/factories/WorkerAccess/WorkerAccessLinkFactory.php`, `WorkerAccessStateFactory.php` | C | Estados `requested()`, `used()`, `expired()`, `revoked()` |
| `app/Modules/Organization/Contracts/Roster.php`, `app/Modules/Organization/Support/CompanyRoster.php` | M | `idsByEmail(string $email): array`, sin distinguir mayúsculas, solo personas activas |
| `app/Logging/RedactSensitiveData.php` | M | Llaves `code`, `credential`, `challenge` |
| `app/Http/ErrorPage.php` | M | `worker.*` entra en `WORKER_ROUTES` |
| `app/Http/PageFrame.php`, `resources/js/lib/page-frame.ts`, `resources/js/layouts/frames.ts` | M | Páginas `worker-access/*` con marco propio |
| `lang/es/validation.php` | M | Atributos `email`, `label`, `code` de los formularios nuevos |

**Frontend**

| Archivo | | Cambio y motivo |
|---|---|---|
| `resources/js/lib/webauthn.ts` | Mv | Desde `lib/attendance/webauthn.ts`; ahora la usan dos módulos |
| `resources/js/lib/attendance/signed-request.ts`, `enroll.ts`, `punch.ts`, `week.ts` | M | Importan desde la ruta nueva |
| `resources/js/lib/worker-access/entry.ts`, `key.ts` | C | Estados de la entrada y del registro de llave, sin presentación |
| `resources/js/hooks/use-worker-entry.ts`, `use-worker-key.ts` | C | Conectan esas lógicas con las rutas |
| `resources/js/pages/worker-access/enter.tsx`, `link.tsx`, `key.tsx`, `not-me.tsx`, `home.tsx` | C | Una página por ruta, delgadas |
| `resources/js/components/worker-access/entry-screen.tsx`, `entry-link-form.tsx`, `link-continue.tsx`, `link-problem.tsx`, `key-screen.tsx`, `key-progress.tsx`, `key-done.tsx`, `key-problem.tsx`, `key-shared-account-note.tsx`, `not-me-confirm.tsx`, `leave-button.tsx` | C | Parten de las pantallas de alta de asistencia (`enroll-*`), que ya cumplen el rediseño |
| `resources/js/components/humetria/person-frame.tsx`, `person-heading.tsx` | Mv | Desde `components/attendance/`. Las usan dos módulos, y un módulo no importa componentes de otro |
| `resources/js/types/worker-access.ts` | C | Tipos de las props |

**Pruebas**

| Archivo | | Qué afirma |
|---|---|---|
| `tests/Feature/WorkerAccess/SessionTest.php` | C | Las cuatro condiciones; límites exactos de 30 min y 8 h; sesión de A en la dirección de B es visitante; "Salir" no cierra la sesión del panel. `mutates(WorkerSessionStore::class)` |
| `tests/Feature/WorkerAccess/EnterWithKeyTest.php` | C | Firma válida abre sesión; llave revocada, de otra empresa o de persona inactiva dan el mismo error |
| `tests/Feature/WorkerAccess/AccessLinkTest.php` | C | Un uso; vencido; anulado; dos peticiones a la vez; `GET` no gasta; `requested` no permite registrar llave |
| `tests/Feature/WorkerAccess/RequestLinkTest.php` | C | Misma respuesta con cero, una o varias coincidencias; solo una coincidencia envía correo; el nuevo anula el anterior; límite por dirección y por correo |
| `tests/Feature/WorkerAccess/RegisterKeyTest.php` | C | Permitido con `issued_link` y con `key`; prohibido con `requested_link`; tope de 3; aviso y bitácora |
| `tests/Feature/WorkerAccess/NotMeTest.php` | C | Dirección sin firma da 403; confirmar revoca llave y sesiones y avisa |
| `tests/Feature/WorkerAccess/LogPrivacyTest.php` | C | Ningún token, código, reto ni llave pública en una línea de log |
| `tests/Feature/WorkerAccess/SurveyAnonymityTest.php` | C | Con sesión de persona abierta, contestar una encuesta no deja el identificador de la persona en ninguna línea de log de esa petición |
| `tests/Feature/Organization/RosterTest.php` | M | `idsByEmail`: sin distinguir mayúsculas, solo personas activas, solo de la empresa actual |
| `tests/Feature/RouteSecurityTest.php`, `tests/Feature/ErrorPagesTest.php`, `tests/Unit/PageFrameTest.php`, `tests/Feature/MailInventoryTest.php`, `tests/Unit/MailBagTest.php`, `tests/Unit/ActionsLogTest.php` | M | Inventarios: rutas públicas `worker.*`, páginas, correos y acciones nuevas |
| `tests/Feature/Logging/StructuredLogTest.php` | M | Las llaves nuevas de `RedactSensitiveData` |
| `tests/js/lib/webauthn.test.ts` | Mv | Desde `tests/js/lib/attendance/` |
| `tests/js/lib/worker-access/entry.test.ts`, `key.test.ts` | C | Estados |
| `tests/Browser/WorkerEntryTest.php` | C | `visitPage` de cada página nueva (componente, sin errores de JS, CSP, axe); CUJ 1, 2, 3, 6 y 8 con el autenticador virtual |

### Grupo 4. "Accesos" en el panel y fin del alta de asistencia

| Archivo | | Cambio y motivo |
|---|---|---|
| `app/Modules/WorkerAccess/Contracts/WorkerInvitations.php`, `Data/InvitationOutcome.php` | C | `send(string $employeeId, int $issuedBy): InvitationOutcome` con resultados `sent`, `without_email`, `has_key`, `has_open_link` |
| `app/Modules/WorkerAccess/Actions/InviteWorker.php`, `InviteCenter.php`, `ReissueAccess.php`, `RevokeKey.php` | C | `InviteCenter` despacha un job por persona y registra el lote una vez en la bitácora, como hoy `SendEnrollmentLinks`. `ReissueAccess` es el CUJ 7 |
| `app/Modules/WorkerAccess/Jobs/SendWorkerInvitation.php`, `Notifications/WorkerInvitation.php` | C | El correo de invitación con el enlace `issued` |
| `app/Modules/WorkerAccess/Events/WorkerAccessRevoked.php` | C | Lo disparan `ReissueAccess`, `RevokeKey` y `DisownKey` |
| `app/Modules/WorkerAccess/Support/AccessPages.php` | C | Filas paginadas de "Accesos" con estado por persona; reemplaza a `DevicePages` |
| `app/Modules/WorkerAccess/Http/Controllers/AccessController.php`, `Http/Requests/InviteRequest.php` | C | `index`, `invite`, `inviteCenter`, `reissue`, `revoke`. Para una persona sin correo, `invite` y `reissue` devuelven el enlace una vez en el flash y lo registran en la bitácora |
| `routes/worker-access.php` | C | `tenant.access.index`, `.invite`, `.invite-center`, `.reissue`, `.revoke` bajo `admin/{tenant:slug}/access` |
| `routes/company.php` | M | `require` de `worker-access.php` dentro del grupo de la empresa |
| `database/migrations/…_add_worker_access_permissions.php` | C | `view-worker-access` (owner, hr, viewer) y `manage-worker-access` (owner, hr), con el patrón de la migración de permisos de asistencia |
| `database/migrations/…_drop_attendance_enrollment_links.php` | C | Borra la tabla |
| `resources/js/pages/worker-access/access.tsx` | C | Página del panel, paginada |
| `resources/js/components/worker-access/access-table.tsx`, `access-row.tsx`, `access-pipeline.tsx`, `access-key-item.tsx`, `invite-button.tsx`, `invite-center-button.tsx`, `reissue-dialog.tsx`, `one-time-link.tsx` | Mv | Desde `components/attendance/`: `devices-table`, `device-person-row`, `device-pipeline`, `device-item`, `enroll-button`, `enroll-all-button`, `one-time-link`; `reissue-dialog` es nuevo |
| `resources/js/lib/app-navigation.ts`, `tests/js/lib/app-navigation.test.ts` | M | Entrada "Accesos" en la navegación de la empresa, junto a "Empleados" |
| `resources/js/lib/attendance-navigation.ts`, `resources/js/lib/attendance/setup.ts`, `resources/js/components/attendance/link-steps.tsx`, `list-toolbar.tsx`, `resources/js/hooks/use-panel-list-filters.ts` | M | Los enlaces a "Dispositivos" apuntan a "Accesos" |
| `app/Modules/Attendance/Http/Controllers/DeviceController.php`, `EnrollmentController.php`, `Http/Requests/EnrollRequest.php` | B | La página y el alta pasan a WorkerAccess |
| `app/Modules/Attendance/Actions/EnrollDevice.php`, `IssueEnrollment.php`, `RevokeDevice.php`, `SendEnrollmentLinks.php`, `Jobs/SendEnrollmentLink.php`, `Notifications/EnrollmentInvitation.php`, `Notifications/DeviceLinked.php`, `Models/EnrollmentLink.php`, `Data/IssuedEnrollment.php`, `Support/DevicePages.php` | B | Reemplazados por las acciones de WorkerAccess |
| `routes/attendance.php` | M | Salen las cuatro rutas `tenant.attendance.devices.*` |
| `routes/attendance-device.php` | M | Salen las tres rutas `attendance.device.enroll.*` |
| `config/attendance.php` | M | Sale `enrollment.link_hours` |
| `resources/js/pages/attendance/enroll.tsx`, `devices.tsx`; `resources/js/hooks/use-enrollment.ts`; `resources/js/lib/attendance/enroll.ts`; `resources/js/components/attendance/enroll-screen.tsx`, `enroll-progress.tsx`, `enroll-confirm.tsx`, `enroll-done.tsx`, `enroll-exit.tsx`, `enroll-key.tsx`, `enroll-problem.tsx`, `link-device-note.tsx`, `device-people.tsx` | B | Sus piezas útiles ya se movieron a `worker-access` en el grupo 3 |
| `app/Modules/Organization/Http/Controllers/EmployeeController.php`, `routes/organization.php`, `resources/js/components/organization/employee-row-actions.tsx` | M | El botón "enviar enlace del portal" llama a `WorkerInvitations::send` |
| `app/Modules/Leave/Actions/SendPortalLinks.php`, `Http/Controllers/BalanceController.php`, `Notifications/VacationsPortalLink.php` | M / B | La acción llama a `WorkerInvitations`; la notificación propia se borra |
| `app/Modules/Telework/Http/Controllers/TeleworkerController.php`, `Jobs/SendPortalInvitation.php`, `Notifications/PortalInvitation.php`, `Notifications/PortalLinkEmail.php`; `resources/js/components/telework/teleworker-detail.tsx`, `resources/js/pages/telework/teleworker.tsx`, `resources/js/types/global.d.ts` | M / B | "Generar enlace" enlaza a "Accesos"; las dos notificaciones y el flash `portalLink` se borran |
| `tests/Feature/WorkerAccess/AccessPageTest.php`, `InviteTest.php`, `InviteCenterTest.php`, `ReissueTest.php`, `RevokeKeyTest.php` | C | Página sin datos individuales de respuestas, paginador y número de consultas; cada resultado de invitación; lote por centro; reemitir anula llaves, sesiones y enlaces; permisos de cada rol; otra empresa da 404 |
| `tests/Feature/PaginatedListsTest.php`, `tests/Unit/PaginatedListsTest.php`, `tests/Unit/ModuleGatesTest.php` | M | La página nueva y sus permisos |
| `tests/Feature/Attendance/EnrollmentTest.php`, `EnrollmentLinkTest.php`, `EnrollmentBatchTest.php`, `RevokeDeviceTest.php`, `DeviceTableTest.php`, `DevicesPageTest.php`; `tests/Browser/AttendanceEnrollTest.php`, `AttendanceDevicesTest.php`; `tests/js/lib/attendance/enroll.test.ts` | B | El comportamiento cambió por decisión del diseño: el alta ya no es de asistencia. Sus casos se reescriben en las pruebas de WorkerAccess de los grupos 3 y 4 |
| `tests/Feature/Organization/EmployeePortalLinkTest.php`, `tests/Feature/Leave/SendPortalLinksTest.php`, `tests/Feature/Leave/BalancesPanelTest.php` | M | Afirman la llamada a `WorkerInvitations` |
| `tests/Browser/WorkerAccessPanelTest.php` | C | `visitPage` de "Accesos" y el CUJ 7 |

### Grupo 5. Inicio y secciones

| Archivo | | Cambio y motivo |
|---|---|---|
| `app/Modules/WorkerAccess/Contracts/WorkerSection.php`, `Data/WorkerSectionLink.php` | C | `for(WorkerIdentity $person): ?WorkerSectionLink`; etiqueta `worker.sections` |
| `app/Modules/WorkerAccess/Http/Controllers/HomeController.php` | M | Reúne las secciones en un orden fijo, como hoy `PortalController::sections` |
| `app/Modules/WorkerAccess/Providers/WorkerAccessServiceProvider.php` | M | Los limitadores `portal-views` y `portal-actions` se mudan aquí desde Telework |
| `app/Http/Middleware/RequirePortalTool.php` | M | Pregunta a `Companies::current()->stopped($module)`; ya no lee un token ni depende de Telework |
| `app/Modules/Attendance/Support/PunchWorkerSection.php`, `Providers/AttendanceServiceProvider.php` | C / M | Sección "Marcar" que lleva a `/a/{slug}`, solo si la empresa usa asistencia |
| `resources/js/components/worker-access/home-screen.tsx`, `home-section-card.tsx`, `home-empty.tsx` | Mv | Desde `components/telework/portal/portal-home.tsx` y sus tarjetas de sección |
| `resources/js/components/portal-home-link.tsx` | M | Lleva a `worker.home` |
| `tests/Feature/WorkerAccess/HomeTest.php` | C | Secciones según las herramientas de la empresa; empresa sin asistencia no ve Marcar (CUJ 9); número de consultas |

### Grupo 6. Cada módulo del portal a sesión

El mismo cambio en cada módulo, un commit por módulo: la ruta pasa de `portal/{token}/…` a `me/{tenant:slug}/…` con `ResolveWorkerTenant` y `auth:worker`; el controlador pide `WorkerSession::current()` en lugar de abrir un token; la sección implementa `WorkerSection`; la página deja de recibir `token`; las pruebas entran con una sesión de persona en lugar de un enlace.

| Módulo | Archivos de backend (M) | Archivos de frontend (M) | Pruebas (M) |
|---|---|---|---|
| Telework | `routes/portal.php`; `Http/Controllers/PortalController.php` (pasa a ser la sección en `/me/{slug}/telework`, nombre `portal.telework.show`), `PortalChecklistController`, `PortalDocumentController`, `PortalEquipmentController`, `PortalEvidenceController`, `PortalNoticeController`; `Actions/Acknowledge`, `ConfirmVisitAuthorization`, `FulfillPhotoRequest`, `RespondEquipment`, `SubmitChecklist`, `SubmitNotice`, `RegisterAspirant`; `Support/OpenedPortal.php` (se arma desde `WorkerIdentity` más el teletrabajador), `Support/PortalPending.php`; `Support/TeleworkWorkerSection.php` (C); `Providers/TeleworkServiceProvider.php` | `pages/telework/portal.tsx`, `portal-checklist.tsx`, `portal-document.tsx`, `portal-notices.tsx`; `components/telework/portal/*` (12 archivos), `checklist-form`, `equipment-dialog`, `accident-answer`, `accident-answered`, `applies-yes`, `review-attach-form`, `review-cancel-button`; `hooks/use-checklist-form.ts`, `use-evidence-photos.ts`; `types/telework.ts`, `telework-documents.ts` | `tests/Feature/Telework/`: `PortalTest`, `PortalChecklistTest`, `PortalSectionsTest`, `PortalToolGateTest`, `AcknowledgementPagesTest`, `AcknowledgementsTest`, `AccidentEmailsTest`, `AccidentNoticeTest`, `EquipmentTest`, `EvidenceAndVisitsTest`, `NoticesTest`, `PhotoStoreTest`, `PrivacyTest`, `PublishWritingTest`, `RegisterAspirantTest`, `SubmitChecklistTest`, `TeleworkerStorageTest`, `TenantIsolationTest`; `tests/Browser/TeleworkTest.php`, `AccidentNoticeTest.php`, `WritingTest.php` |
| Leave | `routes/leave-portal.php`; `Http/Controllers/PortalVacationsController`, `PortalStatementController`, `PortalTeamRequestsController`; `Support/PortalVisitor.php`, `VacationsPortalSection.php`; `Providers/LeaveServiceProvider.php` | `pages/leave/portal.tsx`, `portal-statement.tsx`, `portal-team.tsx`; `components/leave/decision-card`, `portal-statements`, `portal-team-link`, `reject-dialog`, `request-form`, `request-row`, `portal-requests`; `types/leave.ts` | `tests/Feature/Leave/`: `PortalVacationsTest`, `PortalStatementTest`, `PortalTeamTest`, `ApproverTest`, `DecideRequestTest`, `RouteRequestsTest`, `LogPrivacyTest`; `tests/Browser/LeavePortalTest.php`, `LeavePortalStatementTest.php`, `LeavePortalTeamTest.php` |
| Seating | `routes/seating-portal.php`; `Http/Controllers/PortalController`, `PortalAcknowledgmentController`, `SeatReportController`; `Actions/AcknowledgeRisks`, `ReportSeat`, `SubmitPersonInput`; `Support/SeatingPortalSection.php`; `Providers/SeatingServiceProvider.php` | `pages/seating/portal.tsx`; `components/seating/portal-acknowledgment`, `portal-questions`, `portal-seat-report`, `portal-confirmation`; `lib/seating/route.ts` | `tests/Feature/Seating/`: `PortalTest`, `AcknowledgmentsTest`, `AssessmentTest`, `MeasuresTest`, `PersonInputTest`, `PrivacyTest`, `SeatReportsTest`, `StandingQuestionsTest`; `tests/Browser/SeatingTest.php` |
| Disclosure | `routes/disclosure-portal.php`; `Http/Controllers/PortalPolicyController`; `Support/PolicyPortalSection.php`, `CenterPortalSection.php`, `PortalPolicyPages.php`; `Providers/DisclosureServiceProvider.php` | `pages/disclosure/portal-policy.tsx`; `components/disclosure/portal-confirm.tsx`; `types/disclosure.ts` | `tests/Feature/Disclosure/PortalPolicyTest`, `PrivacyTest`; `tests/Browser/DisclosureTest.php` |
| Organization | `routes/organization-portal.php`; `Http/Controllers/PortalTeamController`; `Support/TeamPortalSection.php`; `Providers/OrganizationServiceProvider.php` | `pages/organization/portal-team.tsx` | `tests/Feature/Organization/PortalTeamTest`; `tests/Browser/OrgChartTest.php` |
| Engagement | `routes/engagement-portal.php`; `Http/Controllers/PortalClimateController`, `PortalFeedbackController`; `Support/ClimatePortalSection.php`, `FeedbackPortalSection.php`, `PortalClimatePages.php`; `Providers/EngagementServiceProvider.php` | `pages/engagement/portal-climate.tsx`, `portal-feedback.tsx`; `types/engagement.ts` | `tests/Feature/Engagement/PortalClimateTest`, `PortalFeedbackTest`, `PagesPrivacyTest`, `Pulse/PulseResultsTest`; `tests/Browser/Engagement/PortalClimateTest.php`, `PortalFeedbackPageTest.php` |
| Results | `routes/results-portal.php`; `Http/Controllers/PortalSupportController`; `Support/SupportPortalSection.php`; `Providers/ResultsServiceProvider.php` | `pages/results/portal-support.tsx`; `types/results.ts` | `tests/Feature/Results/SupportNoticeTest`; `tests/Browser/CampaignsTest.php` |

Transversal a este grupo:

| Archivo | | Cambio y motivo |
|---|---|---|
| `resources/js/lib/private-paths.ts`, `tests/js/lib/private-paths.test.ts` | M | Las direcciones privadas de la persona pasan de `/portal/` a `/me/` |
| `resources/js/routes/**`, `resources/js/actions/**` | M | Generados por Wayfinder; se regeneran, no se editan a mano |
| `tests/Feature/RouteSecurityTest.php` | M | Las 29 rutas `portal.*` dejan la lista pública: ahora exigen `auth:worker` |
| `tests/Pest.php` | M | Ayudante `actingAsWorker($employee)` que abre una sesión de persona para las pruebas |

### Grupo 7. Prueba reciente

| Archivo | | Cambio y motivo |
|---|---|---|
| `app/Modules/WorkerAccess/Http/Middleware/RequireRecentProof.php` | C | Alias `worker.proof`. Pasa con `proof_at` de menos de 5 minutos; si no, redirige a la pantalla de prueba (página) o regresa con la bandera `proofRequired` (formulario) |
| `app/Modules/WorkerAccess/Support/ProofCodes.php` | C | Código de seis dígitos: hash en caché por sesión, 10 minutos, 5 intentos |
| `app/Modules/WorkerAccess/Actions/ProveWithKey.php`, `SendProofCode.php`, `ProveWithCode.php` | C | `SendProofCode` y `ProveWithCode` rechazan a quien tiene una llave activa |
| `app/Modules/WorkerAccess/Notifications/ProofCode.php` | C | Correo con el código, `MailBag::ESSENTIAL` |
| `app/Modules/WorkerAccess/Http/Controllers/ProofController.php`, `Http/Requests/ProofCodeRequest.php` | C | `show`, `options`, `store`, `sendCode`, `verifyCode` |
| `routes/worker.php`, `app/Modules/WorkerAccess/Providers/WorkerAccessServiceProvider.php`, `bootstrap/app.php` | M | Rutas `worker.proof.*`, limitador `worker-proof`, alias del middleware |
| `config/worker_access.php`, `.env.example` | M | `proof.minutes` (5), `proof.code_minutes` (10), `proof.code_attempts` (5) |
| `routes/results-portal.php`, `disclosure-portal.php`, `seating-portal.php`, `leave-portal.php`, `portal.php` | M | `worker.proof` en: `portal.support.show`; `portal.policy.acknowledge`; `portal.seating.acknowledgments.store`; `portal.vacations.requests.store`, `.statements.acknowledge`, `.team.approve`, `.team.reject`; `portal.documents.acknowledge`, `.equipment.respond`, `.visits.confirm` |
| `resources/js/pages/worker-access/proof.tsx`; `resources/js/components/worker-access/proof-screen.tsx`, `proof-code-form.tsx`, `proof-dialog.tsx`; `resources/js/hooks/use-recent-proof.ts`; `resources/js/lib/worker-access/proof.ts` | C | Pantalla de prueba y el paso que los formularios delicados abren ante `proofRequired` |
| `resources/js/components/leave/request-form.tsx`, `decision-card.tsx`, `reject-dialog.tsx`, `portal-statements.tsx`; `components/disclosure/portal-confirm.tsx`; `components/seating/portal-acknowledgment.tsx`; `components/telework/portal/portal-document-card.tsx`, `portal-equipment-card.tsx`, `portal-visit-card.tsx` | M | Usan `use-recent-proof` antes de enviar |
| `tests/Feature/WorkerAccess/RecentProofTest.php` | C | Límite exacto de 5 minutos; con llave no se ofrece código; código vencido, equivocado y al quinto intento; límite de envíos. `mutates(RequireRecentProof::class, ProofCodes::class)` |
| `tests/Feature/Results/SupportNoticeTest.php`, `tests/Feature/Leave/PortalVacationsTest.php`, `tests/Feature/Disclosure/PortalPolicyTest.php` | M | Sin prueba reciente, el servidor no entrega el contenido ni acepta el envío |
| `tests/js/lib/worker-access/proof.test.ts`, `tests/Browser/WorkerProofTest.php` | C | Estados; CUJ 5 con llave y con código |

### Grupo 8. Correos sin credencial

| Archivo | | Cambio y motivo |
|---|---|---|
| `app/Modules/WorkerAccess/Contracts/WorkerLinks.php`, `Support/RouteWorkerLinks.php` | C | `home()` y `to(string $routeName, array $parameters = [])` para la empresa actual |
| `app/Modules/Seating/Jobs/SendRisksToConfirm.php`, `SendStandingQuestions.php`; `app/Modules/Disclosure/Jobs/SendPolicyReminder.php`; `app/Modules/Engagement/Jobs/SendFeedbackPublished.php`, `Listeners/NotifyManagers.php`; `app/Modules/Results/Jobs/SendSupportNotice.php`; `app/Modules/Leave/Support/RequestNotifier.php`; `app/Modules/Telework/Jobs/SendPortalReminder.php`, `SendNoticeAnswer.php` | M | `PortalAccess::token()` y `route('portal.*', $token)` pasan a `WorkerLinks::to(...)` |
| `tests/Feature/Seating/StandingQuestionsTest.php`, `AcknowledgmentsTest.php`; `tests/Feature/Disclosure/SendPolicyReminderTest.php`; `tests/Feature/Engagement/ManagerFeedbackNoticeTest.php`, `PublishFeedbackTest.php`; `tests/Feature/Results/SupportNoticeTest.php`; `tests/Feature/Leave/DecideRequestTest.php`; `tests/Feature/Telework/AccidentEmailsTest.php`, `NoticesTest.php` | M | La dirección del correo no contiene ningún token |
| `tests/Feature/WorkerAccess/IntendedDestinationTest.php` | C | Una persona sin sesión que abre una dirección de un correo entra y llega a ese destino, no al inicio |

### Grupo 9. Borrado

| Archivo | | Cambio y motivo |
|---|---|---|
| `app/Modules/Telework/Models/PortalLink.php`, `Actions/IssuePortalLink.php`, `Actions/OpenPortal.php`, `Contracts/PortalAccess.php`, `Contracts/PortalLinks.php`, `Contracts/PortalSection.php`, `Data/PortalIdentity.php`, `Data/PortalSectionLink.php`, `Exceptions/PortalUnavailable.php`, `Support/TokenPortalAccess.php`, `Support/EmailedPortalLinks.php` | B | Ya nadie los usa |
| `app/Modules/Telework/Providers/TeleworkServiceProvider.php` | M | Salen los enlaces de esos contratos |
| `database/factories/Telework/PortalLinkFactory.php` | B | |
| `database/migrations/…_drop_portal_links.php` | C | Borra la tabla |
| `tests/Feature/Telework/PortalAccessContractTest.php`, `PortalLinkMigrationTest.php` | B | Cubrían el contrato y la tabla que desaparecen |
| `tests/Feature/RouteSecurityTest.php` | M | Prueba nueva: ninguna ruta bajo `/me/` o `/a/` tiene un parámetro `{token}` salvo `worker.link.show`, `worker.link.open` y `attendance.device.kiosk.activate`, cada una con su razón |
| `tests/Unit/ArchitectureTest.php` | M | Ningún módulo importa `App\Modules\Telework\Contracts\Portal*` |
| `CLAUDE.md` | M | `WorkerAccess` en la lista de módulos; en "Privacidad", la regla de que la persona no entra al contexto global de los logs |

### Verification Plan

*   **Mientras se trabaja**: `php artisan test --compact tests/Feature/WorkerAccess` y el archivo de la tarea; Pint y PHPStan (nivel max, sin supresiones) en cada PHP editado; `vp fmt` y `vp lint` en cada TS/TSX editado.
*   **Al cerrar el trabajo**: `composer ci:check` (Pint, PHPStan, Pest Unit y Feature con cobertura de 90 % o más, Browser), `npm run types:check` y `npm run test:js` (cobertura de 90 % o más en `resources/js/lib`).
*   **Arquitectura**: `tests/Unit/ArchitectureTest.php`, `FrontendArchitectureTest.php`, `TenantScopedModelsTest.php`, `RouteSecurityTest.php`, `BrowserCoverageTest.php`, `PageFrameTest.php`, `PaginatedListsTest.php`, `DesignTokensOnlyTest.php`.
*   **Recorridos**: los nueve CUJ tienen prueba de navegador con el autenticador virtual, en `tests/Browser/WorkerEntryTest.php`, `WorkerProofTest.php`, `WorkerAccessPanelTest.php` y `AttendancePunchTest.php`.
*   **Diseño**: cada pantalla nueva se revisa en el navegador en claro, oscuro y a 375 px contra su pantalla de referencia de `docs/design-system/pantallas/`.
*   **A mano, en dispositivos reales**: las dos preguntas abiertas (selector de llaves con cuenta del panel y de persona; registro de la llave desde el navegador interno del correo), en un iPhone y un Android.
*   **Mutación**: no corre en el PR. Las clases con reglas declaran `mutates()` y entran solas a la corrida semanal: `WorkerSessionStore`, `OpenAccessLink`, `RequestAccessLink`, `RegisterKey`, `ReissueAccess`, `DisownKey`, `RequireRecentProof`, `ProofCodes`, `WebAuthnCeremony`.

--------------------------------------------------------------------------------

--------------------------------------------------------------------------------

## Desviaciones

Anotadas al construir el grupo 3 (enlaces, sesión, entrada, registro de llave y «No fui yo»).

- **Nombres de log** `worker_access.*` (`worker_access.entered`, `.left`, `.link_requested`, `.link_used`,
  `.key_registered`, `.not_me`…) en lugar de `worker.*`: el módulo ya registraba así sus llaves y la regla es `modulo.accion`.
- **Archivo de rutas** `routes/worker-access.php`, no `routes/worker.php`.
- **Sin acciones que toquen la sesión** (`EnterWithKey`, `LeaveSession`): las acciones no dependen de HTTP. Entrar y salir
  los hacen los controladores con `Support/WorkerSessionStore`; `OpenAccessLink` solo gasta el enlace.
- **`auth:worker` aún no está en las rutas.** El guard existe y responde; los controladores preguntan a la sesión. El
  middleware y el regreso al destino guardado (`bootstrap/app.php`) llegan con el grupo 6 y 8.
- **Varias coincidencias de correo** no pueden ocurrir hoy (`employees` tiene correo único por empresa); la regla de
  «exactamente una» se conserva en `RequestAccessLink`.
- **«No fui yo» avisa a `Companies::contact()`** (el dueño), como dice la tabla del grupo 3, no a cada miembro owner/hr.
  La dirección firmada vale 30 días. Si RH ya había revocado la llave, igual cierra sesiones y enlaces y deja el motivo
  `not_me` con la fecha de la primera revocación. Después la persona todavía puede pedir un enlace `requested` (que no crea
  llaves); para una llave nueva necesita a RH.
- **La página del enlace no recibe el token**: recibe `opens` y «Continuar» envía a su propia dirección.
- **El marco de la persona** queda en `components/person-frame.tsx` (no en `components/humetria/`, que no puede importar
  el logo); `components/attendance/person-frame.tsx` es su envoltura con «Registro de jornada».
- **`RedactSensitiveData`** no ganó llaves: ningún log nuevo lleva token, código, reto ni llave pública.

Anotadas al construir el grupo 4 («Accesos» y fin del alta de asistencia).

- **Rutas del panel** en `routes/worker-access-panel.php` con nombres `tenant.worker-access.*` (no `tenant.access.*`);
  están en `ModuleRoutes::always()` (toda empresa, con o sin herramientas) y en `ComplianceRoutes` (un sindicato, 404).
- **Menos clases**: no hay `InviteWorker`, `RevokeKey`, `InviteRequest` ni el evento `WorkerAccessRevoked` (nadie lo
  escucha). Se reusan `IssueAccessLink`, `RevokeCredential`, `AccessPersonRequest` e `InviteCenterRequest`.
  `InvitationOutcome` no ganó `has_key`/`has_open_link`: invitar a una persona siempre emite; el lote por centro cuenta
  `with_key` y `with_link`.
- **Revocar una llave** desde el panel solo la detiene (`revoked_by_hr`, deja de verificar); no mueve `valid_after`. El
  teléfono perdido es «Reemitir acceso», que también cierra sesiones; el diálogo de revocar lo dice.
- **Estados**: con llave, invitada (un enlace `issued` que aún abre) y sin invitar (incluye un enlace vencido y solo
  llaves revocadas). Sin la tarjeta «Avance del alta»: las pestañas con conteo dicen lo mismo.
- **Lote por centro**: a quien no tiene correo solo se le cuenta; RH crea su enlace desde su renglón y lo ve una vez.
- **Bitácora del enlace mostrado**: es `worker_access.link_issued` con `delivered_by_rh: true` y el actor.
- **«Mi jornada» del portal por token** ya no emite enlaces para crear llave (alternativa A y decisión 3): lista las
  llaves, pide a quien no tiene el enlace de RH y lleva a quien tiene una a `/me/{slug}` para agregar otra con sesión.
  Marcar sin llave dice lo mismo, con un enlace a `/me/{slug}`.
- **Se borraron sin uso**: `WorkerCredentials::revoke` (era temporal para «Dispositivos»), `CenterReadiness` (el aviso
  «hoy nadie puede marcar» sale de Accesos), `DeviceLabel`, `MaskedEmail` y `Attendance\Exceptions\InvalidCredential`.
  `CompanyAttendanceUsage` ya no cuenta `worker_credentials`: una llave es de toda empresa, no de Asistencia.
- **Piezas compartidas**: `list-toolbar` y `center-filter` pasan a `components/` (raíz), porque `components/humetria` no
  importa otras piezas de humetria; `one-time-link` a `components/humetria`; `panelListQuery` a `lib/panel-list.ts`.
- **Los tres botones del enlace del portal** (empleados, teletrabajadores, saldos de vacaciones) siguen igual hasta los
  grupos 6 y 8.

Anotadas al construir el grupo 5 (inicio, `WorkerSection` y Telework como sección).

- **`RequireWorkerSession` en lugar de `auth:worker`.** `Authenticate` llama a `Auth::shouldUse('worker')`: la persona se
  volvía el usuario de la petición (las props compartidas del panel y el contexto de los logs la leían como usuario, y
  `GenericUser` rompía `isSuperadmin()`), y además corría antes de `ResolveWorkerTenant` por la prioridad de middleware. El
  middleware propio de WorkerAccess va después de `ResolveWorkerTenant`, nunca cambia el usuario y, sin sesión, lleva a la
  entrada de esa empresa (JSON: 401). `RouteSecurityTest` lo cuenta como autenticación y exige que toda ruta con él viva bajo
  `me/{tenant}/` con `ResolveWorkerTenant` antes. El guard `worker` sigue registrado; no se usa en rutas.
- **El destino pretendido** vive en su propia llave de sesión (`worker_destination`, `Support/WorkerDestination`), no en
  `url.intended`, para que un inicio de sesión del panel nunca caiga en una página de la persona ni al revés. Solo se guarda
  un `GET` de `/me/{slug}/...` de la misma empresa y en este sitio, y se vuelve a revisar al usarlo (otra empresa, otro sitio,
  `/admin`, `..` y un slug que solo empieza igual se ignoran). Lo consumen: entrar con llave, abrir un enlace `requested` y
  registrar la llave tras un enlace `issued` (que sigue yendo primero a registrar la llave).
- **`WorkerSession` se lee una vez por petición** (`Support/RequestWorkerSession`, memo en los atributos de la petición;
  `WorkerSessionStore::start()`/`end()` lo olvidan): el middleware y la página no pagan dos lecturas.
- **Una sección que falla** se reporta (`report()`) y se omite; el inicio no se cae.
- **Interludio (pasos 5 a 9).** El inicio por enlace `portal/{token}` (`portal.show`, `Telework\PortalController::show`)
  sigue vivo mientras Leave, Seating, Disclosure, Organization, Engagement y Results abren sus páginas con el token: lista
  sus `PortalSection` (etiqueta `portal.sections`) y, para Telework, la `WorkerSection` de Telework, que lleva a
  `/me/{slug}/telework` sin credencial (la persona entra primero). Ya no muestra los pendientes de teletrabajo ni recibe
  `token`, `canNotify` ni `pending`. El inicio nuevo `/me/{slug}` lista solo las `WorkerSection` registradas: Telework y
  Marcar. En el paso 6 cada módulo cambia su `PortalSection` por una `WorkerSection`; el inicio por enlace se borra en el 9.
- **Telework.** Sus 9 rutas de contenido pasan a `/me/{slug}/telework/...` con los mismos nombres `portal.*`, más
  `portal.telework.show` (página `telework/portal-section`: lo que antes mostraba el inicio sobre teletrabajo, con la tarjeta
  de «Sus avisos por escrito» para quien tiene periodo abierto). La sección se ofrece con NOM-037 a quien teletrabaja y, a
  cualquier otra persona, mientras tenga un documento por confirmar (antes los veía en el inicio del portal). La página la
  abre cualquier persona con sesión (sin nada pendiente, «Nada pendiente»); los avisos siguen siendo 404 sin periodo abierto.
  Las páginas reciben `slug` en lugar de `token`. `Support/SessionPortal` arma el `OpenedPortal` desde `WorkerIdentity`, así
  las acciones (`SubmitChecklist`, `Acknowledge`…) no cambian. `OpenPortal`, `IssuePortalLink` y `PortalLink` quedan para el
  inicio por enlace y los otros módulos hasta el paso 9.
- **`RequirePortalTool`** pregunta a `Companies::current()` en las páginas de la persona y va antes de la sesión (una
  empresa sin la herramienta da 404 sin pedir entrar). En las rutas que aún llevan `{token}` sigue preguntando al enlace
  (`PortalAccess`) hasta que cada módulo se mude en el paso 6: la dependencia de Telework se va con la última.
- **Limitadores** `portal-views` y `portal-actions` siguen en `TeleworkServiceProvider` (los usan todavía las rutas con
  token): sin token cuentan por sesión del navegador (`portal-session:` + hash del id), nunca una cubeta para todos. Se mudan
  a WorkerAccess cuando no quede token (paso 9).
- **El correo «Su empresa respondió su aviso»** (`SendNoticeAnswer`) ya usa `WorkerLinks::to('portal.notices.show')`: su
  ruta dejó de tener token, así que no podía esperar al paso 8.
- **Attendance.** «Marcar» (`PunchWorkerSection`, clave `attendance`) lleva a `/a/{slug}` solo si la empresa tiene la
  herramienta. «Mi jornada» por token (`AttendancePortalSection`, `portal.attendance.show`) sigue en el inicio por enlace
  hasta el paso 9: es lo mínimo coherente mientras ese inicio exista, y ya no crea llaves (grupo 4). Se borra con él.
- **`components/portal-home-link.tsx`** sigue llevando a `portal.show`: lo usan las páginas por token de los otros módulos
  (paso 6).

Paso 6-1 (Organization, lo que quedaba de Telework, Leave y Seating, con sus correos del grupo 8).

- **Rutas**: `portal.team.show` en `/me/{slug}/team`; las 9 de vacaciones bajo `/me/{slug}/vacations/...`; las 4 de Ley Silla
  bajo `/me/{slug}/seating/...`. Mismos nombres `portal.*`, `ResolveWorkerTenant`, `RequirePortalTool`, `RequireWorkerSession`
  y los limitadores `portal-views`/`portal-actions` (por sesión del navegador). Las páginas reciben `slug`, no `token`; el
  regreso al inicio es `components/worker-home-link.tsx` (`portal-home-link.tsx` queda para Disclosure, Engagement y Results).
- **Secciones**: `TeamWorkerSection`, `VacationsWorkerSection` y `SeatingWorkerSection` (etiqueta `worker.sections`) reemplazan
  a sus `PortalSection`; el inicio por enlace (`portal/{token}`) ya no muestra Mi equipo, Mis vacaciones ni Mi puesto de pie.
- **Correos en el mismo commit que sus rutas** (Leave y Seating): en cuanto la ruta deja de tener `{token}`,
  `route('portal.x', $token)` arma `/me/{token}/...`; esperar al grupo 8 dejaba correos rotos.
- **Correos sin credencial**: la invitación del aspirante a teletrabajo (`SendPortalInvitation`), su recordatorio
  (`SendPortalReminder`), las dos de Ley Silla y las tres de vacaciones (`RequestNotifier`) usan `WorkerLinks::to()`. En las
  de Telework y Ley Silla se quitó «Si su empresa le envía uno nuevo, el anterior deja de funcionar»; queda «Este enlace es
  solo suyo: no lo comparta», como en `NoticeAnswered`.
- **Botones del panel**: el de la fila de Empleados y el de la ficha del teletrabajador son «Ver su acceso», un enlace a
  «Accesos» buscando a la persona por nombre (allí se invita, se reemite o se muestra el enlace a quien no tiene correo); no
  llaman a `WorkerInvitations`, porque el enlace de quien no tiene correo solo se muestra en «Accesos». Se borraron la ruta
  `tenant.employees.portal-link`, `tenant.telework.teleworkers.link`, el flash `portalLink`, `Telework\Contracts\PortalLinks`,
  `Support\EmailedPortalLinks` y `Notifications\PortalLinkEmail`. El de Saldos de vacaciones sigue siendo un envío en lote:
  `SendPortalLinks` (mismo nombre y mismo log `leave.portal_links_sent`) llama a `WorkerInvitations::send` solo para quien
  tiene correo; `VacationsPortalLink` se borró.
- **Ley Silla**: `AcknowledgeRisks`, `ReportSeat` y `SubmitPersonInput` reciben `WorkerIdentity` (solo usaban `employeeId`);
  ya no dependen de `Telework\Data\PortalIdentity`.
- **Consultas del inicio**: `Companies::current()` lee la empresa y sus herramientas (2 consultas) cada vez que una sección
  pregunta si la empresa tiene la herramienta; el inicio pasó de 12 a 19 consultas (Mi equipo +2, Mis vacaciones +3, Mi
  puesto de pie +2). Constante, sin N+1; si pesa, memorizar `current()` por petición en `CompanyDirectory`.
- **Rutas delicadas para el grupo 7** (aún sin `worker.proof`): `portal.seating.acknowledgments.store`,
  `portal.vacations.requests.store`, `portal.vacations.statements.acknowledge` (y `.statements.show` si «estado de cuenta»
  incluye leerlo: la tabla del grupo 7 solo pone el acuse),
  `portal.vacations.team.approve` y `.team.reject`; de Telework, `portal.documents.acknowledge`, `portal.equipment.respond` y
  `portal.visits.confirm`.

Paso 6-2 (Disclosure, Engagement y Results, con sus correos del grupo 8).

- **Rutas**: `portal.policy.show` y `.acknowledge` en `/me/{slug}/policy`; `portal.climate.show` y `portal.feedback.show` en
  `/me/{slug}/climate` y `/me/{slug}/feedback`; `portal.support.show` en `/me/{slug}/support`. Mismos nombres, limitadores y
  gates: política y apoyo siguen con `RequirePortalTool:nom035`; clima y retroalimentación siguen sin gate de ruta (como
  antes: la página responde 404 si no hay nada publicado y la sección revisa la herramienta). Las páginas reciben `slug`;
  «Nos dijeron / Haremos» ya no arma su propio regreso a `portal.show`: usa `worker-home-link`. Se borró
  `components/portal-home-link.tsx` (ya nadie lo usaba).
- **Secciones**: `PolicyWorkerSection`, `CenterWorkerSection`, `ClimateWorkerSection`, `FeedbackWorkerSection`,
  `Engagement\SurveyWorkerSection` y `SupportWorkerSection`. Además, fuera de los tres módulos, `Campaigns\SurveyWorkerSection`
  («Su evaluación NOM-035») y `ComplaintsWorkerSection` («Quejas y denuncias»): no llevaban token y eran las únicas
  `PortalSection` que quedaban fuera de Attendance; ahora solo `AttendancePortalSection` usa el contrato viejo y el inicio por
  enlace solo muestra «Mi jornada» (hasta el paso 9). Las encuestas, el canal y sus enlaces no cambiaron.
- **Correos sin credencial**: `SendPolicyReminder`, `SendFeedbackPublished` (una sola dirección para todos, armada una vez),
  `NotifyManagers` y `SendSupportNotice` usan `WorkerLinks::to()`. En `PolicyToConfirm`, `FeedbackPublished` y
  `SupportWaiting` se quitó «Si su empresa le envía uno nuevo, el anterior deja de funcionar».
- **Pendiente para el paso 9**: la invitación a clima y pulso (`Engagement\Jobs\SendInvitations`) y la de la evaluación
  (`Campaigns\Jobs\SendSurveyInvitations`) aún ponen al pie `PortalAccess::url()`, el inicio viejo con token. No es una página
  de estos módulos; cambia con el borrado del inicio viejo.
- **Consultas del inicio**: de 19 a 32 (Política +2, Centro +2, Clima +2, Retroalimentación +2, Apoyo +2, y una consulta por
  cada lista de pendientes —evaluación, clima o pulso, canal— que solo lee la empresa si hay algo). Constante. El inicio por
  enlace bajó de 21 a 8.
- **Rutas delicadas para el grupo 7** (aún sin `worker.proof`): `portal.policy.acknowledge` y `portal.support.show`. Ni
  `portal.policy.show`, ni `portal.climate.show` ni `portal.feedback.show` entran en la tabla del grupo 7.

Paso 7 (prueba reciente y las rutas delicadas).

- **Rutas** `worker.proof.show` (`GET /me/{slug}/proof`), `.options`, `.store` (`POST /me/{slug}/proof`, la firma),
  `.code.send` (`POST .../proof/code`) y `.code.check` (`POST .../proof/code/check`), con `ResolveWorkerTenant`,
  `RequireWorkerSession` y el limitador `worker-proof` (60 por minuto por dirección y 20 por sesión). No son públicas.
- **Menos clases**: sin `ProveWithKey`; la firma la verifica `ProofController` como `EntryController` (las acciones no tocan la
  sesión) y exige que la llave sea de la persona de la sesión (otra persona, otra empresa, revocada, reto de otro contexto o
  alterada: la misma respuesta 422). `SendProofCode` y `ProveWithCode` son acciones; `Support/ProofSteps` dice el modo
  (`key`, `code` o `none`) y arma las direcciones; `WorkerSessionStore::prove()` fija `proof_at`.
- **El código** (`Support/ProofCodes`): en caché un HMAC (con la llave de la app) del código y del id de la sesión, por persona;
  10 minutos, 5 intentos contados antes de comparar (el sexto se rechaza aunque sea correcto), sirve una vez, uno nuevo anula el
  anterior y nunca es igual. Envíos: 3 por persona y 3 por sesión cada 10 minutos (`proof.code_sends`). Se rechaza (403) a
  quien tiene una llave activa y a quien no tiene correo, al pedir y al escribir. El correo `ProofCode` sale con `notifyNow`
  y, como todo correo, lleva un botón («Ir a mi portal»).
- **Un envío sin prueba** regresa a la página de donde salió (`WorkerDestination::back`, mismas reglas: si no es una página
  `/me/{slug}/...` de esa empresa, al inicio) con el flash `proofRequired` (el paso). En lugar de tocar los nueve
  formularios con `use-recent-proof`, un solo `components/worker-access/recent-proof-dialog.tsx`, montado en `app.tsx`,
  recuerda el último envío de Inertia (`router.on('before')`), abre el paso ante el flash y, ya probada, lo reenvía con los
  mismos datos. Inertia v3 conserva el estado de la página en el regreso de un envío; los `onSuccess` del formulario original no
  corren en el reenvío (la página recarga sus props). JSON: 403 con `proofRequired: true`.
- **Una página sin prueba** guarda su destino en la misma llave que la entrada (`worker_destination`): entrar y probar nunca
  ocurren a la vez, y entrar con llave ya es una prueba.
- **Una lectura por petición**: `RequestWorkerSession` ahora memoriza la `OpenSession` (atributo `worker_access.session`), así
  `RequireRecentProof` no lee la sesión otra vez.
- **Logs** `worker_access.proof_given` (`via`: `key` con `key_id`, o `code`), `worker_access.proof_refused` y
  `worker_access.proof_code_sent`, no `worker.proof_passed`/`_failed`. Sin bitácora ni evento.
- **Rutas delicadas**, las de la tabla del grupo 7 y nada más (las fija `RecentProofTest`). Leer el estado de cuenta de
  vacaciones (`portal.vacations.statements.show`) y la política (`portal.policy.show`) no pide prueba: son lo propio de la
  persona, como su saldo; solo confirmarlos. `RouteSecurityTest` exige `RequireWorkerSession` antes de `worker.proof`.
- **Navegador**: `tests/Browser/WorkerProofTest.php` y los helpers `provedWithCode()` y `enteringProvedTo()` de `tests/Pest.php`;
  Ley Silla, la política y el mensaje de apoyo recorren el paso por código; vacaciones y teletrabajo prueban antes de enviar.

Paso 9 (borrado del portal por enlace y la prueba que prohíbe credenciales en direcciones).

- **Invitaciones a encuestas**: el pie de la evaluación (`SendSurveyInvitations`) y de clima y pulso (`SendInvitations`) lleva
  `WorkerLinks::home()` (`/me/{slug}`), la misma para todos y sin credencial; el enlace de la encuesta (`/survey/{token}`,
  `/engagement/{token}`) sigue siendo su credencial de un solo uso. Sus pruebas viven ahora en `InvitationsTest` y
  `SendSurveyInvitationsTest`; `PortalLinkInEmailsTest` se borró.
- **Se borró** el inicio por enlace (`portal.show`, `Telework\PortalController::show`, `pages/telework/portal.tsx`,
  `portal-start`, `portal-sections`, `portal-unavailable`, el tipo `PortalSection`), «Mi jornada» por enlace
  (`portal.attendance.show`, `routes/attendance-portal.php`, `Attendance\PortalController`, `AttendancePortalSection`,
  `pages/attendance/portal.tsx`, `portal-devices`, `portal-link-card`), y los contratos y piezas del token: `PortalAccess`,
  `PortalSection`, `PortalSectionLink`, `PortalIdentity`, `PortalUnavailable`, `TokenPortalAccess`, `OpenPortal`,
  `IssuePortalLink`, `PortalLink` y su factory. La lista de llaves de la persona ya está en `/me/{slug}/key`.
  `OpenedPortal` queda (lo arma `SessionPortal` para las acciones de Telework, que no cambiaron de firma); las pruebas que lo
  armaban con el token usan `teleworkPortalOf()` (`tests/Pest.php`).
- **`routes/portal.php` pasa a `routes/telework-portal.php`**: solo quedan las páginas de teletrabajo de la persona, como los
  demás `*-portal.php`.
- **La tabla** `portal_links` se borra con `2027_01_23_100000_drop_portal_links_table` (sin migrar filas; `down()` la crea como
  estaba: hash, `version` con su `check`, una por persona). Sus columnas e índices viejos (`previous_token_hash`…) ya no
  existían; las migraciones antiguas quedan como historia.
- **`RequirePortalTool`** solo pregunta a `Companies::current()`; los limitadores `portal-views` y `portal-actions` se mudaron a
  `WorkerAccessServiceProvider` y cuentan solo por sesión del navegador (más el tope por dirección).
- **La prueba de Goal 1** está en `RouteSecurityTest`: toda ruta sin cuenta del panel (la lista pública o con
  `RequireWorkerSession`) cuyo parámetro se llame como credencial (`token`, `code`, `credential`, `key`…) o no sea el slug, un
  UUID o una fecha debe estar en `credentialAddresses()` con su razón; bajo `me/`, `a/` o `portal` solo se permiten
  `worker.link.show`, `worker.link.open`, `worker.not-me.*` (firmadas: la prueba exige `signed:relative`) y
  `attendance.device.kiosk.activate` (la pantalla de un centro, no una persona). Se probó que falla con una ruta
  `me/{slug}/sample/{token}` registrada en la prueba. También quedan permitidas, por no ser de la persona: las encuestas, el
  canal de denuncias, la página de trabajadores, los reportes compartidos, el restablecer contraseña y la invitación de un
  miembro del panel.
- **Textos**: la página de trabajadores, la política pública y el mensaje de apoyo ya no mandan a «el enlace personal que le
  llegó» ni piden «no comparta su enlace» (`tests/Unit/WorkerLinkCopyTest.php`).
- **Leave** conserva `SendPortalLinks`/«Enviar enlace a su portal»: ya invita con `WorkerInvitations` (un enlace de un solo uso);
  se renombró a `InviteToPortal` con el log `leave.portal_invitations_sent`.
- **Deuda**: el inicio `/me/{slug}` hace 32 consultas (constante): `Companies::current()` relee la empresa y sus herramientas en
  cada sección. Memorizarlo por petición en `CompanyDirectory` no es un cambio pequeño (jobs y acciones esperan leer el estado
  «como está ahora», y las pruebas cambian la empresa a mitad de una petición); queda para su propio trabajo.

*Drafted with EGM*
