# Demo guiada — plan de implementación

> **Para agentes:** sub-skill requerida: superpowers:subagent-driven-development (recomendada) o superpowers:executing-plans,
> tarea por tarea. Los pasos usan casillas (`- [ ]`).

**Meta:** Victor escoge en la sala qué módulos mostrar, empieza una sesión (reinicia la demo y crea el centro de la
sesión), y un guía con la cara de Humi lo lleva paso a paso mientras opera la empresa demo con su público real; «Terminar
sesión» lo borra todo.

**Arquitectura:** una `DemoSession` de plataforma en el módulo `Demos`; la preparación de la sesión vive en
`database/seeders/Demo` (única excepción de arquitectura). Un catálogo de recorridos en PHP con pasos cuyos detectores leen
**contratos públicos** de cada módulo con «lo que pasó desde que empezó la sesión» (`since`). El kernel comparte la prop
`demoGuide` por un contrato de `Demos`; el frontend la sondea con `usePoll` y la pinta en un dock con driver.js.

**Stack:** Laravel 12, Inertia v3 + React, Pest 4, Postgres, driver.js 1.10.2 (ya instalado), Vitest.

**Diseño:** `docs/superpowers/specs/2026-11-26-demo-guiada-design.md` (se lee junto con este plan).

## Restricciones globales

- `CLAUDE.md` manda: TDD estricto (test rojo primero, por la razón correcta), Larastan max sin supresiones, TS estricto sin
  `as`/`!`/`any`, clases `final`, código en inglés y UI en es-MX (el panel tutea), límites del frontend (300 líneas por
  archivo, 100 por función, complejidad 10, 3 parámetros).
- Mientras se trabaja: solo los tests de la tarea (`php artisan test --compact --filter=…`), Pint, PHPStan, `npm run check`,
  `npm run types:check`. **La suite local completa en verde antes de cualquier push**. Sin PR hasta «crea el PR».
- Sin dependencias nuevas.
- Solo el superadmin (`manage-demo`, 404 para el resto) y solo en la empresa demo (`DemoCompanies::isDemo`). Cada
  excepción de la demo lleva un test que prueba que **una empresa real no cambia**.
- `Demos` lee otros módulos solo por `Contracts`/`Data`; nada de `Database\Seeders\Demo` fuera del proveedor de `Demos`.
- Contratos nuevos de lectura: solo conteos y estados; nunca personas, respuestas ni horas exactas de una respuesta.
- Logs `demo.session_*` con ids; bitácora `demo.session_started` / `demo.session_ended` (`RecordAudit`).
- Un commit = un comportamiento (test + código), en verde, a nombre del usuario, sin atribución.

## Puntos a vigilar (no los cubre otra prueba)

1. **La preparación de la sesión falla a media corrida** → la sala dice que falló y deja «Terminar sesión»; la sesión
   nunca queda atorada en `preparing` (test en la Tarea 2).
2. **Victor entra como RH en vez de dueño** → el guía aparece igual (test en la Tarea 8).
3. **Una persona del público ya dio de baja WhatsApp (STOP global)** → le llega por correo y la campaña no falla
   (test en la Tarea 4).
4. **Solo 3 de 5 contestan** → el paso de respuestas muestra «3 de 5» y Victor sigue con «Saltar paso» o cerrando la
   evaluación, que cumple el paso (test en la Tarea 8: cerrar cumple el paso de respuestas).
5. **Se termina la sesión con el guía abierto en otra pestaña** → el siguiente sondeo trae `demoGuide: null` y el dock
   desaparece sin error (test en la Tarea 10).

---

### Task 1: Sesiones de demo — tabla, modelo y acciones de empezar y terminar

**Archivos:**
- Crear: `database/migrations/2026_11_26_000001_create_demo_sessions_table.php`,
  `app/Modules/Demos/Models/DemoSession.php`, `app/Modules/Demos/Enums/DemoSessionStatus.php`,
  `app/Modules/Demos/Actions/StartDemoSession.php`, `app/Modules/Demos/Actions/EndDemoSession.php`,
  `app/Modules/Demos/Http/Requests/StartDemoSessionRequest.php`, `app/Modules/Demos/Jobs/PrepareDemoSession.php`
- Modificar: `routes/demos.php`, `app/Modules/Demos/Http/Controllers/DemoRoomController.php`,
  `app/Modules/Demos/Actions/StartDemoRun.php`, `app/Modules/Demos/Jobs/ResetDemo.php`,
  `tests/Feature/TenantScopedModelsTest.php` (excepción con razón: «plataforma, como `demo_runs`»),
  `lang/es/validation.php` (atributos `client_name`, `tours`)
- Test: `tests/Feature/Demos/DemoSessionTest.php`

**Interfaces:**
- Produce: tabla `demo_sessions` (`id` uuid, `client_name` string null, `tours` jsonb lista de llaves, `work_center_id`
  uuid null, `skipped_steps` jsonb `[]`, `status` string con `check` en `preparing|active|ending|ended|failed`,
  `run_id` uuid null FK `demo_runs`, `started_at`, `ready_at` null, `ended_at` null, `timestampTz`); índice único parcial
  sobre `status in ('preparing','active','ending')`.
- Produce: `DemoSession::current(): ?self` (la no terminada), `DemoSession::markReady(string $workCenterId): void`,
  `markFailed(): void`, `markEnded(): void`.
- Produce: `StartDemoSession::handle(?string $clientName, list<string> $tours, int $startedBy): 'started'|'busy'|'unqueued'`
  — crea la sesión `preparing`, arranca una corrida `session` (ver Tarea 2) y audita `demo.session_started`.
- Produce: `EndDemoSession::handle(): 'started'|'busy'|'unqueued'|'none'` — sesión a `ending`, corrida `reset`, audita
  `demo.session_ended`; al terminar la corrida, `ResetDemo` marca `ended` la sesión `ending`.
- Rutas: `POST admin/demo/session` → `demo-room.session.start`; `DELETE admin/demo/session` → `demo-room.session.end`.
  Validación: `tours` arreglo de 1 a 6 llaves distintas de `TourKey` **sin `base`** (que siempre va primero; mientras no
  exista el enum de la Tarea 7, `in:` con las 6 llaves),
  `client_name` nullable, máx. 80.

- [ ] **Paso 1: tests rojos** en `DemoSessionTest`:
  `it('starts a session that resets the demo')` (204/redirect, fila `preparing` con `tours` en orden, un `DemoRun` kind
  `session`, `Bus::assertDispatched(PrepareDemoSession::class)`, entrada de bitácora `demo.session_started`);
  `it('refuses a second session while one is open')` (flash «Ya hay una sesión en curso»);
  `it('answers 404 to anyone but the superadmin')` (RH de la demo, dueño real, invitado);
  `it('ends the session with a reset and marks it ended when the reset finishes')`;
  `it('validates the tours')` (vacío, repetido, llave desconocida, `client_name` de 81).
- [ ] **Paso 2:** correr `php artisan test --compact tests/Feature/Demos/DemoSessionTest.php` → FALLA (no existe la ruta).
- [ ] **Paso 3:** migración, modelo, enum, acciones, request (`authorize` con `Gate::authorize('manage-demo')`), rutas y
  controlador. `StartDemoRun::handle(string $kind, ?string $sessionId = null)` acepta `'session'` (revisar y ampliar el
  `check` de `demo_runs.kind` si existe) y despacha `PrepareDemoSession($run->id, $sessionId)`.
- [ ] **Paso 4:** correr el test → PASA; Pint y PHPStan.
- [ ] **Paso 5:** commit «Sala de demo: empezar y terminar una sesión».

### Task 2: Preparar la sesión — centro, mínimo 1 y WhatsApp encendido

**Archivos:**
- Crear: `database/seeders/Demo/SessionStage.php`
- Modificar: `app/Modules/Demos/Jobs/PrepareDemoSession.php` (creado en la Tarea 1 con `prepare()` y `markReady`; aquí suma `stageSession`), `app/Modules/Demos/Contracts/DemoWorkspace.php`, `database/seeders/Demo/DemoWorkspaceRunner.php`
- Test: `tests/Feature/Demos/PrepareDemoSessionTest.php`

**Interfaces:**
- Produce: `DemoWorkspace::stageSession(?string $clientName): string` — devuelve el id del centro creado. Dentro de la
  empresa demo, con las acciones reales de Tenancy y Messaging: crea el centro «Sala · {cliente}» (o «Sala de
  demostración»), fija el mínimo por grupo en 1 (`SetGroupMinimum`) y enciende WhatsApp con la versión vigente de
  `whatsapp.consent.version` (la acción que usa la pantalla de Canales).
- Produce: `PrepareDemoSession(string $runId, string $sessionId)` — misma cola y conexión que `ResetDemo`; corre
  `prepare()` con los pasos de siempre, luego «Sala de la sesión» → `stageSession`, y `markReady($centerId)`. Si algo
  falla: corrida `failed` y sesión `failed` (se puede terminar o empezar otra).

- [ ] **Paso 1: tests rojos:** `it('lets the demo send mail and WhatsApp without a billed plan and without counting
  them')` (revisar `MailQuota`, `WhatsAppQuota` y `UsageMeter` en el camino de envío: ni rechazan la demo ni la cuentan en
  cobros; si alguno la rechaza, la excepción va ahí con su test de empresa real);
  `it('stages a new work center, minimum 1 and WhatsApp on')` (con `DemoWorkspace` real
  en un test lento ya existente como patrón: `tests/Feature/Demos/PrepareDemoTest.php`); `it('marks the session failed
  when staging fails')` (workspace falso que lanza); `it('names the center after the client')`.
- [ ] **Paso 2:** correr → FALLA.
- [ ] **Paso 3:** implementar.
- [ ] **Paso 4:** correr → PASA; Pint, PHPStan.
- [ ] **Paso 5:** commit «Demo: la sesión prepara su centro, mínimo 1 y WhatsApp».

### Task 3: Guías I y II con pocas personas en la demo

**Archivos:**
- Crear: `app/Modules/Instruments/Support/DemoNom035Guides.php`
- Modificar: `app/Modules/Instruments/Providers/InstrumentsServiceProvider.php`
- Test: `tests/Feature/Instruments/DemoNom035GuidesTest.php`

**Interfaces:**
- Consume: `Instruments\Contracts\Nom035Guides` (`for(int): array`, `instruments(int): array`), que ya usan
  `CreateEvaluationDraft`, `LaunchCampaign`, `CampaignPages`, `WorkCenterController`, `Nom035Route` y `ApplicabilityPages`;
  `Tenancy\Contracts\DemoCompanies::isCurrent()`.
- **Ojo:** Sucursal Monterrey se siembra con 15 personas (solo Guía I) y su historia NOM-035 depende de eso. La
  excepción va **solo al centro de la sesión**, no a toda la demo.
- Produce: `Nom035Guides::for(int $workers, ?string $workCenterId = null)` e `instruments(int $workers, ?string
  $workCenterId = null)`; cada llamador que conoce el centro lo pasa. `Demos\Contracts\DemoSessionCenter::is(string
  $workCenterId): bool` (implementado en `Demos`, lee la sesión activa). El binding pasa a `DemoNom035Guides` (decorador
  de `Nom035GuideRule`): si el centro es el de la sesión y `$workers <= 50`, Guías I y II; si no, idéntico a la regla.

- [ ] **Paso 1: tests rojos:** `it('applies guides I and II to 3 workers in the demo')`, `it('keeps guide I only for 3
  workers in a real company')`, `it('launches a demo evaluation of 3 people with guide II')` (feature: borrador y
  lanzamiento de punta a punta en el centro de la sesión, sin `DraftOutdated`), `it('keeps I and III over 50 in the
  demo')`, `it('keeps guide I only in Sucursal Monterrey during a session')`.
- [ ] **Paso 2:** correr → FALLA.
- [ ] **Paso 3:** implementar el decorador y el binding.
- [ ] **Paso 4:** correr; además `--filter=Nom035` y el test de preparación de la demo (`--filter=PrepareDemo`); Pint,
  PHPStan.
- [ ] **Paso 5:** commit «NOM-035: la demo aplica la Guía II con cualquier número de personas».

### Task 4: WhatsApp en la demo, por todos los canales a la vez

**Archivos:**
- Modificar: `app/Modules/Messaging/Support/ChannelRules.php` (`whatsAppOpen`, `route`)
- Test: `tests/Feature/Messaging/DemoChannelRulesTest.php`

**Interfaces:**
- Produce: `ChannelRules::route()` en la demo devuelve **todos** los canales que sirven (`['mail' => …, 'whatsapp' => …]`);
  fuera de la demo, el primero como hoy. `whatsAppOpen` ya no excluye la demo. Revisar que cada llamador de `route()`
  (`ChannelPicker` y los `via()` que lo usan) mande por cada canal del arreglo; si alguno toma solo el primero, se
  corrige ahí con su test.

- [ ] **Paso 1: tests rojos:** `it('sends a demo survey invitation by mail and WhatsApp')`, `it('still sends by one
  channel in a real company')`, `it('falls back to mail in the demo when the number opted out')` (punto a vigilar 3),
  `it('keeps WhatsApp closed in the demo while its channel is off')`.
- [ ] **Paso 2:** correr → FALLA.
- [ ] **Paso 3:** implementar.
- [ ] **Paso 4:** correr; además `--filter=Messaging`; Pint, PHPStan.
- [ ] **Paso 5:** commit «Mensajes: la demo manda por correo y WhatsApp a la vez».

### Task 5: El acceso al portal por WhatsApp y la ruta `access/{token}`

**Archivos:**
- Crear: `app/Modules/WorkerAccess/Http/Controllers/ShortLinkController.php`
- Modificar: `routes/worker-access.php`, `tests/Feature/RouteSecurityTest.php` (pública, razón: «el token de un solo uso
  es la credencial; solo redirige sin gastarlo»), `app/Modules/WorkerAccess/Notifications/WorkerInvitation.php`,
  `app/Modules/Messaging/Support/TemplateRegistry.php`, `app/Modules/Messaging/Support/TemplateDefinition.php` (pie por
  plantilla), `docs/superpowers/specs/2026-11-18-whatsapp-templates.md` (estado de `worker_invite_v1`)
- Test: `tests/Feature/WorkerAccess/ShortLinkTest.php`, `tests/Feature/WorkerAccess/WorkerInvitationChannelsTest.php`,
  `tests/Unit/Messaging/TemplateRegistryTest.php` (existente: se agrega el caso)

**Interfaces:**
- Produce: `GET access/{token}` → `worker.link.short` (throttle `worker-link`): busca el enlace por el hash del token,
  redirige 302 a `worker.link.show` de su empresa **sin gastarlo**; desconocido o vencido responde lo mismo que
  `worker.link.show` con un token malo.
- Produce: `worker_invite_v1` con botón `urlWithVariable('Crear mi acceso', https://…/access/{{1}})` y pie «Humetría»
  (las demás siguen con «Humetria»); `WorkerInvitation::via()` pasa por `ChannelPicker` como las invitaciones de encuesta,
  con el token como variable del botón. **Mientras la plantilla no esté aprobada, el acceso sale por correo:** se reusa
  el patrón de `Campaigns\Listeners\FallBackToMail` (si WhatsApp falla, va por correo) y, además, un interruptor
  `whatsapp.templates.worker_invite_v1` (`WHATSAPP_WORKER_INVITE=false` por omisión, en `config/whatsapp.php` y
  `.env.example`) que Victor enciende cuando Meta la apruebe.

- [ ] **Paso 1: tests rojos:** `it('redirects a short link to the company link without using it')`, `it('answers an
  unknown short link like a bad link')`, `it('throttles short links')`, `it('sends the portal invitation by WhatsApp when
  the company can')`, `it('builds worker_invite_v1 with the access button and the accented footer')`, `it('sends the portal invitation by
  mail while the template is off, even when WhatsApp comes first')`, `it('falls back to mail when WhatsApp rejects the
  invitation')`.
- [ ] **Paso 2:** correr → FALLA.
- [ ] **Paso 3:** implementar.
- [ ] **Paso 4:** correr; además `--filter=WorkerAccess`; Pint, PHPStan.
- [ ] **Paso 5:** commit «Portal: el acceso sale también por WhatsApp con la ruta corta».

### Task 6: Lo que pasó desde que empezó la sesión — contratos de lectura

**Archivos (uno por módulo, cada uno con su implementación en `Support/` y su binding en el proveedor):**
- Crear: `app/Modules/Campaigns/Contracts/CampaignActivity.php` + `Data/CenterCampaignActivity.php`;
  `app/Modules/Attendance/Contracts/AttendanceActivity.php` + `Data/CenterAttendanceActivity.php`;
  `app/Modules/WorkerAccess/Contracts/WorkerAccessActivity.php` + `Data/AccessActivity.php`;
  `app/Modules/Engagement/Contracts/EngagementActivity.php` + `Data/SurveyActivity.php`;
  `app/Modules/Complaints/Contracts/ComplaintActivity.php` + `Data/ComplaintCounts.php`;
  `app/Modules/Leave/Contracts/LeaveActivity.php` + `Data/LeaveCounts.php`
- Test: `tests/Feature/Demos/ActivityContractsTest.php` (un `describe` por contrato)

**Interfaces (todas leen la empresa actual; `$since` es `ready_at` de la sesión):**
- `CampaignActivity::atCenter(string $workCenterId): CenterCampaignActivity{drafts:int, launched:bool, invited:int,
  answered:int, closed:bool, campaignId:?string}` (la evaluación NOM-035 más reciente del centro).
- `AttendanceActivity::atCenter(string $workCenterId, CarbonImmutable $since): CenterAttendanceActivity{modeSet:bool,
  zones:int, kiosks:int, marks:int}`.
- `WorkerAccessActivity::since(CarbonImmutable $since): AccessActivity{invited:int, keys:int}`.
- `EngagementActivity::since(CarbonImmutable $since): SurveyActivity{created:bool, launched:bool, invited:int,
  answered:int, closed:bool}` (la encuesta creada después de `$since`; `answered` es el conteo de invitaciones
  contestadas, como ya lo ve RH; nunca respuestas).
- `ComplaintActivity::since(CarbonImmutable $since): ComplaintCounts{received:int, acknowledged:int}`.
- `LeaveActivity::since(CarbonImmutable $since): LeaveCounts{requested:int, approved:int}`.

- [ ] **Paso 1: tests rojos:** por contrato, `it('counts only what happened after the session started')`, `it('does not
  read another company')` y, donde aplica, `it('counts only the session center')`.
- [ ] **Paso 2:** correr → FALLA.
- [ ] **Paso 3:** implementar las seis lecturas (consultas agregadas, sin N+1).
- [ ] **Paso 4:** correr; Pint, PHPStan; `tests/Unit/ArchitectureTest.php`.
- [ ] **Paso 5:** un commit por contrato («Asistencia: actividad del centro desde una hora», etc.).

### Task 7: El catálogo de recorridos y sus pasos

**Archivos:**
- Crear: `app/Modules/Demos/Enums/TourKey.php` (`base`, `nom035`, `attendance`, `engagement`, `complaints`, `leave`,
  `humi`), `app/Modules/Demos/Data/TourStep.php`, `app/Modules/Demos/Data/StepState.php`,
  `app/Modules/Demos/Support/Tours/*Tour.php` (uno por recorrido), `app/Modules/Demos/Support/TourCatalog.php`
- Test: `tests/Unit/Demos/TourCatalogTest.php`, `tests/Feature/Demos/TourStepsTest.php`

**Interfaces:**
- `TourStep{key:string ('nom035.launch'), tour:TourKey, title:string, todo:string, why:string, kind:'do'|'watch',
  route:string (nombre de ruta del panel), target:?string (data-tour), ask:?string (pregunta para Humi)}` — copy fijo
  en es-MX, el panel tutea, «por qué importa» en una o dos frases para el cliente.
- `StepState{done:bool, live:?string}` — `live` es la frase con dato vivo («Llegaron 3 de 5 respuestas»).
- Cada `*Tour` implementa `steps(): list<TourStep>` y `state(TourStep $step, DemoSession $session): StepState`, leyendo
  solo los contratos de la Tarea 6 más `Headcount::byWorkCenter()`, `HumiActivity::hasAsked()` y `$session->visited`.
- `TourCatalog::for(list<TourKey> $tours): list<TourStep>` — `base` siempre primero, luego los escogidos en su orden.
- Pasos y su hecho (del diseño §3): base (personas en el centro ≥ 1); nom035 (borrador, lanzada, respuestas «n de m» —
  **también se cumple al cerrar**, punto a vigilar 4 —, cerrada, visitó `tenant.campaigns.results`, visitó la
  canalización); attendance (modo, zona, kiosco, acceso enviado, llave ≥ 1, marca ≥ 1, visitó `tenant.attendance.report`);
  engagement (creada, lanzada, respuestas, visitó resultados); complaints (recibida, atendida); leave (enlace del portal
  abierto — Tarea 9 —, llave, solicitud, aprobada, visitó el saldo); humi (preguntó).
- «Visitó» se registra en el servidor solo en navegaciones reales de página (no en el sondeo ni en recargas parciales):
  `Demos\Contracts\DemoGuide::recordVisit(string $routeName): void`, llamado desde `HandleInertiaRequests` al
  resolver `demoGuide` en una petición sin `X-Inertia-Partial-Data`; agrega a `demo_sessions.visited` (jsonb, columna
  nueva en esta tarea) cuando hay sesión activa y quien navega es el superadmin en la demo.

- [ ] **Paso 1: tests rojos:** `it('puts base first and keeps the chosen order')`, `it('has unique step keys and routes
  that exist')`, `it('writes no company data in the copy')` (sin `{`, sin nombres sembrados); feature por recorrido:
  `it('marks each nom035 step done as the evaluation advances')`, ídem para cada recorrido, y `it('counts the answers
  step done when the evaluation closes')`.
- [ ] **Paso 2:** correr → FALLA.
- [ ] **Paso 3:** implementar catálogo, recorridos y el registro de visitas.
- [ ] **Paso 4:** correr; Pint, PHPStan.
- [ ] **Paso 5:** commit por recorrido.

### Task 8: El guía en el servidor — prop `demoGuide`, saltar y reiniciar paso

**Archivos:**
- Crear: `app/Modules/Demos/Contracts/DemoGuide.php`, `app/Modules/Demos/Support/SessionGuide.php`,
  `app/Modules/Demos/Actions/SkipDemoStep.php`, `app/Modules/Demos/Actions/RewindDemoStep.php`,
  `app/Modules/Demos/Http/Controllers/DemoGuideController.php`, `routes/demos.php` (rutas nuevas)
- Modificar: `app/Http/Middleware/HandleInertiaRequests.php` (prop perezosa `demoGuide`, `null` fuera de la demo)
- Test: `tests/Feature/Demos/DemoGuideTest.php`

**Interfaces:**
- `DemoGuide::forCurrentRequest(): ?array` — `null` salvo superadmin impersonando la demo (como dueño **o** RH) con
  sesión `active`. Forma: `{tours: list<{key, title, done:int, total:int}>, current: ?{key, tour, title, todo, why, kind,
  href, target, ask, live, index, total}, finished: bool, justDone: ?string}` (`href` ya resuelto con el slug de la demo;
  `justDone` = título del paso que se cumplió desde la última lectura, para celebrarlo; se guarda en la sesión).
- `GET admin/demo/session/guide` → `demo-room.session.guide` (JSON con la misma forma; `null` si ya no hay sesión). No
  registra visitas.
- Rutas (autenticadas, `manage-demo`): `POST admin/demo/session/steps/{step}/skip` → `demo-room.session.skip`;
  `POST admin/demo/session/steps/{step}/rewind` → `demo-room.session.rewind` (quita el salto y la visita de ese paso).

- [ ] **Paso 1: tests rojos:** `it('shares no guide outside the demo or without a session')`, `it('shares the guide to
  the superadmin as owner and as HR')` (punto 2), `it('never shares the guide to the demo HR without impersonation')`,
  `it('moves to the next step when the fact happens')`, `it('skips and rewinds a step')`, `it('reports the step just
  done once')`, `it('says finished when every step is done or skipped')`, `assertInertia` sin datos de personas.
- [ ] **Paso 2:** correr → FALLA.
- [ ] **Paso 3:** implementar.
- [ ] **Paso 4:** correr; Pint, PHPStan; `tests/Unit/ArchitectureTest.php`.
- [ ] **Paso 5:** commit «Demo: el guía sabe el paso actual con los datos reales».

### Task 9: Vacaciones en la pantalla de Victor — abrir el portal de una persona

**Archivos:**
- Modificar: `app/Modules/Demos/Contracts/DemoShowcase.php` (método nuevo), su implementación en
  `database/seeders/Demo/DemoShowcaseLinks.php`, `app/Modules/Demos/Http/Controllers/DemoGuideController.php`, `routes/demos.php`
- Test: `tests/Feature/Demos/DemoPortalLinkTest.php`

**Interfaces:**
- `DemoShowcase::portalLinkFor(string $employeeId): ?string` — emite un enlace de un solo uso de WorkerAccess para una
  persona activa **del centro de la sesión** (null si no lo es), como la jefa de RH de la demo.
- `POST admin/demo/session/portal/{employee}` → `demo-room.session.portal`: redirige (Inertia `location`) a ese enlace
  para abrirlo en la misma computadora. El paso «abrir el portal» del recorrido de Vacaciones ofrece un selector de las
  personas del centro de la sesión (`serverSource`, solo nombres de ese centro) y este botón.

- [ ] **Paso 1: tests rojos:** `it('opens the portal of a session person on this computer')`, `it('refuses a person
  outside the session center')` (404), `it('refuses outside an active session')`.
- [ ] **Paso 2–5:** FALLA → implementar → PASA → commit «Demo: abrir el portal de una persona de la sesión».

### Task 9b: Diseño de la sala y del dock, aprobado por Victor (antes de las Tareas 10 y 11)

`CLAUDE.md`: pantalla nueva o rediseño, primero el diseño aprobado. Se diseñan con `/impeccable shape` (o Claude Design
con el design system «Humetría») partiendo de la app real (la sala de hoy, el dock de Primeros pasos y el panel de
Humi): la sala sin sesión, preparando, con sesión y con falla; el dock abierto, minimizado, celebrando un paso y en
375 px; claro y oscuro. Victor lo aprueba; el enlace va en el PR. Sin código de UI antes de la aprobación.

- [ ] **Paso 1:** maquetas.
- [ ] **Paso 2:** aprobación de Victor (o sus cambios y otra vuelta).

### Task 10: El dock del guía en el panel

**Archivos:**
- Mover a `components/humetria/` lo que el dock comparte con otros módulos (un módulo no importa componentes de otro):
  el avatar de Humi (`humi-avatar.tsx` y `humi-eyes.tsx`) y los estilos del foco de driver.js
  (`first-steps-tour.css` → `components/humetria/spotlight-tour.css`); actualizar sus importaciones, `DESIGN.md`, su
  ficha en `docs/design-system/componentes/` y cerrar con `/design-sync` (o el PR dice que queda pendiente).
- Crear: `resources/js/types/demo-guide.ts`, `resources/js/lib/demos/guide.ts`, `resources/js/hooks/use-demo-guide.ts`,
  `resources/js/components/demos/guide-dock.tsx`, `guide-step-card.tsx`, `guide-tour-list.tsx`, `guide-launcher.tsx`
- Modificar: `resources/js/layouts/app/app-sidebar-layout.tsx` (monta `GuideDock` junto a Primeros pasos y Humi), las
  páginas que dan los `data-tour` de los pasos (agregar atributos: p. ej. `data-tour="launch-evaluation"`)
- Test: `tests/js/lib/demos/guide.test.ts`, `tests/Browser/Demos/DemoGuideTest.php`

**Interfaces:**
- Consume: la prop `demoGuide` (Tarea 8); `createTourRunner`/`driverSteps` de `lib/first-steps/tour.ts` (se reusan, no
  se copian); `openHumi(question)` de `lib/assistant/open-humi`.
- `lib/demos/guide.ts`: `spotlightFor(current, url): {selector:string}|null` (solo si la página abierta es la del paso);
  `progressLabel(current): string` («Paso 2 de 6 · NOM-035»).
- `use-demo-guide.ts`: sondea **su propio endpoint** `GET admin/demo/session/guide` (Tarea 8) cada 3 s con `fetch`,
  nunca con recargas parciales de la página (una recarga parcial vuelve a correr el controlador de la página, y en
  resultados o canalización escribe en la bitácora de solo inserción); solo mientras hay guía y el dock está abierto; al cambiar
  `current.key`, ilumina con driver.js si `spotlightFor` da selector.
- Dock: avatar de Humi, recorrido y paso, «Qué hacer», «Qué está pasando» (`live`), «Por qué importa», etiqueta Hazlo/Mira,
  botones **Llévame** (visita `href` e ilumina), **Pregúntale a Humi** (si `ask`), **Saltar paso**, lista de recorridos
  con avance; minimizable a burbuja; en 375 px, hoja inferior. Celebra `justDone` en una línea con `aria-live="polite"`.
- Copy de driver.js: solo el del catálogo, nunca datos de la empresa.

- [ ] **Paso 1: tests rojos:** Vitest para `spotlightFor` y `progressLabel`; navegador: `it('shows the guide to the
  superadmin in the demo session and moves on when the evaluation launches')`, `it('hides the guide when the session
  ends in another tab')` (punto 5), humo sin errores de JS ni CSP, axe, claro y oscuro, 375 px.
- [ ] **Paso 2:** correr (`npx vitest run tests/js/lib/demos`; `composer test:browser -- --filter=DemoGuide` tras
  `npm run build`) → FALLA.
- [ ] **Paso 3:** implementar.
- [ ] **Paso 4:** correr → PASA; `npm run check`, `npm run types:check`.
- [ ] **Paso 5:** commit «Demo: el dock del guía con Humi».

### Task 11: La sala, alineada al design system y con sesiones

**Archivos:**
- Crear: `resources/js/components/demos/room-session-new.tsx` (cliente, recorridos escogibles y reordenables con su
  duración y qué enseñan, «Empezar sesión» con aviso de reinicio), `room-session-active.tsx` (cliente, avance por
  recorrido, «Entrar y seguir el guía», «Terminar sesión» con confirmación «Se borra todo, incluidas las personas que
  diste de alta»), `room-tools.tsx` (encabezado «Herramientas de la sala» sobre las tarjetas de hoy)
- Modificar: `resources/js/pages/demos/room.tsx` (contenedor `flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8
  md:pb-16`, `PageHeader`, jerarquía: sesión arriba, herramientas abajo), `app/Modules/Demos/Support/DemoRoomPage.php`
  (prop `session` y `tours` del catálogo), `resources/js/types/index.d.ts` (`DemoRoom`)
- Test: `tests/Feature/Demos/DemoRoomTest.php` (existente: props nuevas), `tests/Browser/Demos/DemoRoomTest.php`

**Interfaces:**
- Prop `session: null | {client:?string, status, tours: list<{key,title,done,total}>, failed: bool}`; prop
  `tours: list<{key, title, summary, minutes}>` (sin `base`, que siempre va).
- «Entrar y seguir el guía» usa la impersonación de hoy (`companies.impersonate`) y aterriza en el `href` del paso actual.

- [ ] **Paso 1: tests rojos:** feature `it('lists the tours and the open session')`, `it('shows a failed session with
  a way to end it')` (punto 1); navegador: empezar sesión con dos recorridos, ver «Preparando», terminar; claro, oscuro y
  375 px.
- [ ] **Paso 2–4:** FALLA → implementar → PASA; `npm run check`, `types:check`.
- [ ] **Paso 5:** commit «Sala de demo: sesiones a la medida y el design system».

### Task 12: Respaldo — la sesión se termina sola a las 24 h

**Archivos:**
- Crear: `app/Modules/Demos/Console/EndStaleDemoSession.php` (comando `demo:end-stale-session`)
- Modificar: `routes/console.php` (cada hora, con el patrón de `schedule.*` de siempre)
- Test: `tests/Feature/Demos/EndStaleDemoSessionTest.php`

- [ ] **Paso 1: tests rojos:** `it('ends a session older than 24 hours')`, `it('leaves a younger one')`, `it('logs
  demo.session_expired')`.
- [ ] **Paso 2–5:** FALLA → implementar con `EndDemoSession` → PASA → commit.

### Task 13: Documentación, Humi y cierre

**Archivos:**
- Modificar: `docs/operations/demo.md` (nueva §: «La sesión guiada»: empezar, guía, lo que sale por correo y WhatsApp,
  terminar; la tabla de fallas), `app/Modules/Assistant/README.md` solo si cambia algo de Humi (no se espera),
  `DESIGN.md` y `docs/design-system/` solo si alguna pieza pasa a `components/humetria/`.
- Verificación: `/impeccable critique` y `/impeccable polish` sobre la sala y el dock; revisión en el navegador a 375,
  768, 1024, 1350 y 2000 px en claro y oscuro; **suite local completa** (`composer test` o el equivalente de
  `docs/testing.md`) en verde; `tests/Feature/Assistant/ModuleCoverageTest.php` sigue en verde (Demos ya está eximido).

- [ ] **Paso 1:** docs.
- [ ] **Paso 2:** revisión visual y crítica; arreglos con sus tests.
- [ ] **Paso 3:** suite completa; arreglar lo que falle por su causa.
- [ ] **Paso 4:** commit «Documentación de la demo guiada».
