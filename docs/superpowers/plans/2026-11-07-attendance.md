# Registro de jornada (`Attendance`) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Registro electrónico de jornada (LFT art. 132-XXXIV) con marcaje desde teléfono (dispositivo vinculado por passkey, QR rotativo, geocerca) o navegador (personal de confianza), solo inserción, conformidad semanal, cálculo de topes y reporte para la autoridad.

**Architecture:** Módulo nuevo `app/Modules/Attendance` con las capas del monolito modular. Lee personas por `Organization\Contracts\Roster`, centros por `Tenancy\Contracts\WorkCenters`; no importa modelos ajenos. Las personas no tienen `User`: el dispositivo (passkey WebAuthn) las identifica, bajo `/a/{tenant:slug}/…`.

**Tech Stack:** Laravel / Pest / Inertia + React / Postgres / `web-auth/webauthn-lib` (aprobada por el usuario el 2026-11-07, dependencia directa).

**Spec:** `docs/superpowers/specs/2026-11-07-attendance-design.md` · **Criterios (mandan):** `docs/normas/jornada/criterios.md` · **Texto del decreto:** `docs/normas/jornada/fuentes/dof-2026-05-01-decreto-jornada.txt`

## Global Constraints

- TDD estricto: test, verlo fallar por la razón correcta, código mínimo. Un commit = un comportamiento (test + código). Sin `phpstan-ignore`, sin `as`/`!`/`any`, sin saltar tests.
- Identificadores en inglés; textos de UI en español (es-MX); el panel tutea, las pantallas de la persona hablan de «usted».
- Toda tabla lleva `tenant_id` y su modelo `BelongsToCurrentTenant`; cada modelo tiene prueba A-no-ve-B por consulta y por URL (404). Clases concretas `final`; `declare(strict_types=1)`.
- Fechas `timestampTz`, guardadas en UTC; se muestran en `config('app.display_timezone')`.
- Toda lista paginada en el servidor; rutas con nombre; toda ruta exige auth salvo las de `tests/Feature/RouteSecurityTest.php` (cada una con razón).
- Logs `attendance.accion` en pasado, datos en el contexto; **nunca** latitud, longitud ni la credencial en logs ni bitácora (`latitude`, `longitude`, `credential_id`, `public_key` a `RedactSensitiveData` con su prueba). Acciones sensibles a `RecordAudit`.
- `AttendanceMark` es de solo inserción (trigger Postgres rechaza UPDATE, DELETE, TRUNCATE).
- Tabla legal por año: jornada ordinaria 48/46/44/42/40 (2026–2030), tope de horas extra 9/9/10/11/12; diaria 8 h diurna, 7 nocturna, 7.5 mixta; extras: máximo 4 h al día y 4 días por semana; entre ordinarias y extras máximo 12 h al día; triple solo hasta 4 h por semana sobre el tope.
- Fuera de toda zona **se bloquea por defecto** (la política puede permitir y señalar). Una marca la sella siempre el servidor.
- Antes de cerrar: `composer ci:check`. Declarar `mutates(...)` en los tests de lógica nueva; no correr mutación por iniciativa.

## Review Focus

1. Medianoche y huso: una jornada nocturna que cruza las 00:00 pertenece al día local de su `start`; la semana va de lunes a domingo en `America/Mexico_City`. Pruebas con marcas a las 23:59 y 00:01 locales.
2. Doble toque / repetición: una aserción WebAuthn o un QR no se reutilizan, y una segunda marca del mismo tipo seguido se rechaza sin duplicar.
3. Secuencia imposible: `break_end` sin `break_start`, `end` sin `start`, `start` con una jornada abierta.
4. Persona dada de baja o movida de centro a media semana: su dispositivo deja de marcar y la zona se evalúa contra su centro actual.
5. Centro sin zonas, GPS sin permiso, precisión mala o coordenadas inválidas (fuera de ±90/±180): mensaje claro y salida por solicitud de corrección; un centro sin zonas no deja a nadie sin poder marcar (se señala a RH).

---

## File Structure

```
app/Modules/Attendance/
  Data/      WeekSummary.php DayHours.php MarkContext.php ZoneReading.php AuthorityRow.php
  Enums/     MarkType.php MarkMethod.php EvidenceLevel.php ShiftType.php WorkMode.php OutOfZoneRule.php WeekStatus.php CorrectionStatus.php OverLimit.php
  Models/    AttendanceMark AttendanceDevice AttendanceAgreement AgreementAcceptance AttendanceZone AttendancePolicy AttendanceSchedule WeeklyConfirmation CorrectionRequest EnrollmentLink KioskDevice
  Actions/   RecordMark CorrectMark RequestCorrection ResolveCorrection AcceptAgreement IssueEnrollment EnrollDevice RevokeDevice SaveZone IssueKioskDevice RevokeKioskDevice SaveSchedule SavePolicy ConfirmWeek DisputeWeek CloseDay BuildAuthorityReport
  Support/   LegalLimits.php HoursCalculator.php Geofence.php QrToken.php WebAuthnCeremony.php EffectiveMarks.php WeekPeriod.php AttendancePages.php
  Jobs/      CloseDays.php
  Events/    AttendanceMarked.php DayClosed.php WeekConfirmed.php WeekDisputed.php
  Http/Controllers, Http/Requests, Providers/AttendanceServiceProvider.php
routes/attendance.php (panel, required from routes/company.php)  routes/attendance-device.php (personas, from routes/web.php)
database/migrations/2026_11_*  database/factories/Attendance/
resources/js/pages/attendance/  resources/js/components/attendance/  resources/js/lib/attendance/ (formato y geolocalización)
tests/Unit/Attendance  tests/Feature/Attendance  tests/Browser/Attendance
```

---

### Task 1 ✅: Esqueleto del módulo, dependencia y permisos

**Files:** Create `app/Modules/Attendance/Providers/AttendanceServiceProvider.php`, `routes/attendance.php`, `routes/attendance-device.php`, migración `…_add_attendance_permissions.php` (patrón de `2026_11_21_100000_add_seating_permissions.php`). Modify `bootstrap/providers.php`, `routes/web.php`, `routes/company.php`, `tests/Unit/ArchitectureTest.php`, `composer.json`.

**Interfaces — Produces:** permisos `view-attendance` (owner, hr, viewer), `manage-attendance` (owner, hr); el módulo registrado en las reglas de arquitectura.

- [ ] **Step 1:** `composer worktree:setup` si falta `vendor/`. Luego `composer require web-auth/webauthn-lib:^5.3` (aprobada).
- [ ] **Step 2: Write the failing test** `tests/Feature/Attendance/PermissionsTest.php`: `owner` y `hr` tienen `manage-attendance`; `viewer` tiene `view-attendance` y no `manage-attendance`. Y en `ArchitectureTest.php` agrega `Attendance` a la lista de módulos para que se cumplan las reglas genéricas.
- [ ] **Step 3:** Run `php artisan test --compact --filter=Attendance` → FAIL (permisos inexistentes).
- [ ] **Step 4:** Implementa provider (vacío, `register()/boot()`), migración de permisos, registro en `bootstrap/providers.php`, y los dos archivos de rutas (vacíos con el comentario de patrón) incluidos desde `routes/company.php` y `routes/web.php`.
- [ ] **Step 5:** Run → PASS; `composer check:fast`. Commit `feat(attendance): module skeleton, permissions and webauthn dependency`.

### Task 2 ✅: Límites legales y calculadora de horas (lógica pura)

**Files:** Create `Support/LegalLimits.php`, `Support/HoursCalculator.php`, `Data/DayHours.php`, `Data/WeekSummary.php`, `Enums/ShiftType.php`, `Enums/OverLimit.php`; `docs/normas/jornada/criterios.md` (criterio 2.9). Test `tests/Unit/Attendance/LegalLimitsTest.php`, `HoursCalculatorTest.php` con `mutates(LegalLimits::class, HoursCalculator::class)`.

**Interfaces — Produces:**
- `LegalLimits::weeklyOrdinaryMinutes(int $year): int` (2026→2880 … 2030→2400); `weeklyOvertimeCapMinutes(int $year): int` (9 h 2026–27 … 12 h 2030); `dailyOrdinaryMinutes(ShiftType): int` (480/420/450); constantes `DAILY_OVERTIME_MAX = 240`, `OVERTIME_DAYS_MAX = 4`, `DAY_TOTAL_MAX = 720`, `TRIPLE_WEEKLY_MAX = 240`. Año fuera de 2026–2030: 2026 antes, 2030 después.
- `HoursCalculator::week(array $days, ShiftType $shift, int $year): WeekSummary`, con `$days` = `list<DayHours>`; `DayHours(CarbonImmutable $date, int $workedMinutes, int $scheduledMinutes, bool $restDay)`.
- `WeekSummary`: `ordinaryMinutes`, `overtimeMinutes`, `doubleMinutes`, `tripleMinutes`, `restDayMinutes`, `list<OverLimit> $flags`, `array<string,int> $overtimeByDay`.
- Criterio 2.9 (**el usuario debe revisarlo**): tiempo extra de un día = trabajado sobre lo programado ese día; el trabajado en día de descanso va a `restDayMinutes`, no es extra; si la suma de lo ordinario semanal rebasa el máximo del año, el exceso pasa a extra; doble = hasta el tope del año; triple = lo que lo rebase, hasta 240 min; más allá, `OverLimit::TripleCapExceeded`.

- [ ] **Step 1: Write failing tests** (valores del decreto): 2027 → `weeklyOrdinaryMinutes === 2760`, `weeklyOvertimeCapMinutes === 540`; 2028 → 2640 / 600; turno nocturno → 420. Calculadora: 5 días programados 8 h trabajando 8 h → 0 extra; un día de 9 h sobre 8 programadas → 60 doble; 2027, extras 540 → todo doble, 541 → 1 de triple; 780 min (13 h) en un día → `DayOver12Hours`; 5 h de extra en un día → `DailyOvertimeOver4`; extras en 5 días → `OvertimeDaysOver4`; 4 h de triple + 1 min → `TripleCapExceeded`; sábado de descanso trabajado 4 h → `restDayMinutes === 240`, sin extra.
- [ ] **Step 2:** Run `php artisan test --compact tests/Unit/Attendance` → FAIL (clases inexistentes).
- [ ] **Step 3:** Implementa las clases (tabla por año como arreglo constante; la calculadora sin dependencias).
- [ ] **Step 4:** Run → PASS. Commit `feat(attendance): legal limits and hours calculator`.

### Task 3 ✅: Marcas de solo inserción, secuencia y marca vigente

**Files:** Create migraciones `create_attendance_marks_table` (con el trigger), `create_attendance_schedules_table`, `create_attendance_policies_table`; modelos `AttendanceMark`, `AttendanceSchedule`, `AttendancePolicy`; enums `MarkType`, `MarkMethod`, `EvidenceLevel`, `WorkMode`, `OutOfZoneRule`; `Support/EffectiveMarks.php`, `Support/WeekPeriod.php`; factories; `Actions/RecordMark.php`, `Actions/CorrectMark.php`, `Actions/SaveSchedule.php`, `Actions/SavePolicy.php`. Tests: `tests/Feature/Attendance/MarksAppendOnlyTest.php`, `RecordMarkTest.php`, `CorrectMarkTest.php`, `tests/Unit/Attendance/EffectiveMarksTest.php`, `WeekPeriodTest.php`, aislamiento de tenant.

**Interfaces — Produces:**
- `MarkType`: `Start|BreakStart|BreakEnd|End`. `MarkMethod`: `QrAndGeofence|Geofence|Qr|Remote|TrustedBrowser|Correction`, con `evidence(): EvidenceLevel` (`Strong` para QrAndGeofence; `Medium` Geofence/Qr; `Basic` Remote/TrustedBrowser; `Corrected`).
- `AttendanceMark` columnas: `id` uuid v4, `tenant_id`, `employee_id`, `work_center_id`, `type`, `marked_at` (servidor, UTC), `method`, `zone_id` null, `latitude`/`longitude` `numeric(9,6)` null, `accuracy_m` null, `distance_m` null, `device_id` null, `corrects_mark_id` null, `corrected_by` null (user id), `reason` null.
- `AttendanceSchedule(employee_id, shift_type, work_mode, minutes_by_weekday json)`; `AttendancePolicy(tenant_id, out_of_zone default Block, max_accuracy_m default 100, require_qr_onsite bool default true, trusted_may_use_browser bool default true, retention_months default 12 con check ≥ 12)`.
- `RecordMark::handle(string $employeeId, MarkType $type, MarkContext $context): AttendanceMark` lanza `InvalidMarkSequence` (código de motivo) si rompe la secuencia: `Start` solo sin jornada abierta; `BreakStart`/`End` solo con jornada abierta; `BreakEnd` solo tras `BreakStart`. `MarkContext(MarkMethod $method, ?string $zoneId, ?float $lat, ?float $lng, ?float $accuracy, ?float $distance, ?string $deviceId)`.
- `CorrectMark::handle(string $markId, ?CarbonImmutable $newMarkedAt, string $reason, string $userId): AttendanceMark` inserta una marca `Correction` con `corrects_mark_id`; no toca la original; escribe `RecordAudit('attendance.mark_corrected')`.
- `EffectiveMarks::forWeek(string $employeeId, WeekPeriod $week): list<AttendanceMark>`: la corrección sustituye a su original; `WeekPeriod::containing(CarbonImmutable $instant): self` (lunes 00:00 a domingo 23:59 en `display_timezone`; `start()`, `end()`, `localDate(CarbonImmutable): CarbonImmutable`).

- [ ] **Step 1: Write failing tests:** UPDATE y DELETE sobre `attendance_marks` por SQL directo lanzan excepción de Postgres; `RecordMark` rechaza `End` sin `Start` y un segundo `Start` seguido (sin crear fila); un `Start` a las 23:59 y un `End` a las 00:01 locales cuentan en el día local del `Start`; `CorrectMark` deja la original intacta y `EffectiveMarks` devuelve solo la corrección; semana: 2026-11-08 (domingo) pertenece a la semana que empezó el lunes 2026-11-02; empresa A no ve marcas de B por consulta ni por URL.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implementa migraciones (check constraints para tipos, trigger `BEFORE UPDATE OR DELETE OR TRUNCATE` como el de `audit_entries`), modelos con `BelongsToCurrentTenant`, acciones con `DB::transaction()` y log `attendance.mark_recorded` (ids, sin coordenadas).
- [ ] **Step 4:** Run → PASS. Commit por comportamiento: (a) tabla y trigger, (b) secuencia, (c) corrección y marca vigente.

### Task 4 ✅: Zonas y geocerca

**Files:** Create migración `create_attendance_zones_table`, `Models/AttendanceZone.php`, `Support/Geofence.php`, `Data/ZoneReading.php`, `Actions/SaveZone.php`, factory. Tests `tests/Unit/Attendance/GeofenceTest.php` (`mutates(Geofence::class)`), `tests/Feature/Attendance/ZoneTest.php`.

**Interfaces — Produces:**
- `AttendanceZone(work_center_id, name, latitude, longitude, radius_m)` con check `radius_m between 10 and 5000`, `latitude between -90 and 90`, `longitude between -180 and 180`.
- `Geofence::evaluate(array $zones, float $lat, float $lng, float $accuracyM, int $maxAccuracyM): ZoneReading` con `ZoneReading(?string $zoneId, ?float $distanceM, bool $inside, bool $accuracyOk)`; distancia Haversine en metros; elige la zona más cercana; `inside` si `distancia <= radio`; `accuracyOk = accuracy <= max`. Coordenadas inválidas lanzan `InvalidArgumentException`.
- `SaveZone::handle(string $workCenterId, string $name, float $lat, float $lng, int $radius, ?string $zoneId = null): AttendanceZone` (centro archivado o ajeno → 404 en el controlador).

- [ ] **Step 1: Write failing tests:** dos puntos a 100 m (cálculo conocido) dan 100 ± 1 m; punto a 49.9 m de una zona de 50 m → `inside`; a 50.1 → fuera; elige «Edificio A» sobre «Almacén» según cercanía; precisión 101 con máximo 100 → `accuracyOk false`; `lat = 91` lanza; empresa A no ve zonas de B.
- [ ] **Step 2–4:** FAIL, implementar, PASS. Commit.

### Task 5 ✅: Token QR rotativo y pantalla del centro

**Files:** Create `Support/QrToken.php`, migración y modelo `KioskDevice`, `Actions/IssueKioskDevice.php`, `Actions/RevokeKioskDevice.php`, controlador `KioskController`. Tests `tests/Unit/Attendance/QrTokenTest.php` (`mutates(QrToken::class)`), `tests/Feature/Attendance/KioskTest.php`.

**Interfaces — Produces:**
- `QrToken::issue(string $tenantId, string $workCenterId, CarbonImmutable $at): string` = HMAC-SHA256 con la clave de la aplicación sobre `tenantId|workCenterId|floor(timestamp/30)`; `QrToken::verify(string $token, string $tenantId, string $workCenterId, CarbonImmutable $now): bool` acepta la ventana actual y la anterior (comparación `hash_equals`).
- `KioskDevice(work_center_id, token_hash, last_seen_at, revoked_at)`; `IssueKioskDevice::handle(string $workCenterId): string` devuelve el token en claro **una sola vez**; solo se guarda su hash.
- Rutas: `GET a/{tenant:slug}/kiosk` (token de pantalla en cabecera) devuelve el QR vigente (`/a/{slug}/qr`, JSON `{token, expiresAt}`); `tenant.attendance.kiosks.store|destroy` en el panel (RH).

- [ ] **Step 1: Write failing tests:** el token de ahora y el de hace 29 s verifican; el de hace 61 s no; token de otro centro o de otra empresa no; manipular un carácter falla; un kiosko revocado recibe 404 sin decir por qué; el token en claro nunca aparece en la tabla.
- [ ] **Step 2–4:** FAIL, implementar, PASS. Commit.

### Task 6: Acuerdo, enlace de alta y dispositivo vinculado (WebAuthn)

**Files:** Create migraciones y modelos `AttendanceAgreement`, `AgreementAcceptance`, `EnrollmentLink`, `AttendanceDevice`; `Support/WebAuthnCeremony.php`, `Actions/IssueEnrollment.php`, `Actions/AcceptAgreement.php`, `Actions/EnrollDevice.php`, `Actions/RevokeDevice.php`; `Http/Controllers/EnrollmentController.php`, `routes/attendance-device.php`; páginas `pages/attendance/enroll.tsx` y `lib/attendance/webauthn.ts` (codificación base64url; Vitest). Tests Feature + Unit del ceremony con vectores del paquete.

**Interfaces — Produces:**
- `IssueEnrollment::handle(string $employeeId, string $userId): string` crea `EnrollmentLink` (hash del token, vence en 48 h, de un solo uso), manda el enlace por el canal de correo de `Notifications` existente y escribe auditoría; devuelve la URL `a/{slug}/enroll/{token}`.
- `AttendanceAgreement(version, body)` vigente más reciente; `AcceptAgreement::handle(string $employeeId, string $deviceId): AgreementAcceptance` guarda versión y momento.
- `WebAuthnCeremony`: `registrationOptions(string $employeeId, string $name): array`, `verifyRegistration(string $employeeId, array $response): CredentialData`, `assertionOptions(): array` (credenciales descubribles, desafío de un solo uso en caché, 60 s), `verifyAssertion(array $response): VerifiedAssertion` (firma, origen, `rp_id`, contador creciente, verificación de usuario obligatoria). Usa `web-auth/webauthn-lib`; el contador y el desafío no se reutilizan.
- `AttendanceDevice(employee_id, credential_id unique, public_key, sign_count, label, enrolled_at, last_used_at, revoked_at)`.
- Rutas (todas con `throttle`): `GET a/{tenant:slug}/enroll/{token}`, `POST …/enroll/{token}/options`, `POST …/enroll/{token}/register` (acepta el acuerdo y registra la llave). Un token inválido, usado, vencido o de una persona dada de baja da el mismo 404.

- [ ] **Step 1: Write failing tests:** un enlace sirve una sola vez (el segundo da 404); un enlace vencido da 404; una aserción con firma alterada o contador igual o menor se rechaza; un desafío usado dos veces falla; un dispositivo revocado no verifica; la persona dada de baja no puede dar de alta; el mismo 404 por cualquier causa; no se registra sin aceptar el acuerdo; ni `credential_id` ni `public_key` aparecen en logs ni bitácora; empresa A no ve dispositivos de B.
- [ ] **Step 2–4:** FAIL, implementar (primero `WebAuthnCeremony` contra vectores, luego controlador), PASS. Browser: `tests/Browser/Attendance/EnrollTest.php` con autenticador virtual de Playwright (CDP `WebAuthn.addVirtualAuthenticator`, `isUserVerified`), `visitPage('attendance/enroll')`.
- [ ] **Step 5:** Commits: (a) acuerdo y enlace, (b) ceremonia, (c) revocación.

### Task 7: Marcar (teléfono con QR y geocerca; navegador de confianza)

**Files:** Create `Http/Controllers/MarkController.php`, `Http/Requests/MarkRequest.php`, `Support/MarkPolicyCheck.php`, `pages/attendance/punch.tsx`, `components/attendance/punch-button.tsx`, `lib/attendance/geolocation.ts` y `lib/attendance/next-step.ts` (Vitest). Tests `tests/Feature/Attendance/PunchTest.php`, Browser `PunchTest.php`.

**Interfaces — Consumes:** `WebAuthnCeremony::verifyAssertion`, `QrToken::verify`, `Geofence::evaluate`, `RecordMark::handle`, `AttendancePolicy`, `AttendanceSchedule.work_mode`. **Produces:** rutas `GET a/{tenant:slug}` (pantalla Marcar), `POST a/{tenant:slug}/punch/options`, `POST a/{tenant:slug}/punch` (cuerpo: aserción, `type`, `qr`, `lat`, `lng`, `accuracy`).
- `MarkPolicyCheck::decide(AttendanceSchedule $s, AttendancePolicy $p, StaffGroup $group, ?string $qr, ?ZoneReading $reading): MarkContext` lanza `MarkRejected` con motivo: `outside_zone` (política `Block`), `accuracy_too_low`, `qr_invalid`, `qr_required`, `no_location`. Método resultante: remoto → `Remote`; confianza con navegador permitido → `TrustedBrowser`; presencial con QR válido y dentro → `QrAndGeofence`; solo uno → `Geofence`/`Qr`; con `Allow` y fuera de zona → marca con `zone_id` null (queda señalada).
- Un centro sin zonas: la geocerca no se exige y la marca se hace con el QR; el panel lo señala (Task 10).

- [ ] **Step 1: Write failing tests:** presencial dentro de zona con QR vigente → marca `QrAndGeofence` con `zone_id`, coordenadas, precisión y distancia guardadas; fuera de zona con política `Block` → 422 con motivo `outside_zone` y sin fila; con `Allow` → marca sin zona; QR vencido → rechazo; remoto sin geocerca → `Remote`; confianza en navegador → `TrustedBrowser`; sin permiso de ubicación → `no_location`; precisión mala → `accuracy_too_low`; aserción de otro tenant o dispositivo revocado → 404; segunda marca inválida por secuencia → 422; hora siempre del servidor aunque el cuerpo traiga otra; persona dada de baja → 404; props sin coordenadas de otras personas.
- [ ] **Step 2–4:** FAIL, implementar, PASS. Browser: marcar con autenticador virtual, geolocalización simulada de Playwright (`context.setGeolocation`) dentro y fuera, axe, sin violaciones de CSP.
- [ ] **Step 5:** Commits: (a) decisión de política, (b) endpoint, (c) pantalla móvil.

### Task 8: Cierre del día, semana y conformidad

**Files:** Create migración `create_weekly_confirmations_table`, `Models/WeeklyConfirmation.php`, `Actions/CloseDay.php`, `Actions/ConfirmWeek.php`, `Actions/DisputeWeek.php`, `Jobs/CloseDays.php`, eventos `DayClosed`, `WeekConfirmed`, `WeekDisputed`; `ScheduleAttendance` en `routes/console.php` (patrón de `ScheduleSeatingChecks`); `pages/attendance/week.tsx`. Tests Feature + `tests/Feature/ScheduleTest.php`.

**Interfaces — Produces:** `ConfirmWeek::handle(string $employeeId, CarbonImmutable $weekStart, string $deviceId): WeeklyConfirmation`; `DisputeWeek::handle(string $employeeId, CarbonImmutable $weekStart, string $reason, string $deviceId): WeeklyConfirmation`; `CloseDay::handle(string $employeeId, CarbonImmutable $localDate): ?HoursSummary` (señala fin sin marcar con `OverLimit`-like flag `MissingEnd`, no lo cierra). `WeekStatus`: `Pending|Confirmed|Disputed`. Rutas `GET a/{tenant:slug}/week/{start?}`, `POST …/week/{start}/confirm|dispute` (aserción WebAuthn).

- [ ] **Step 1: Write failing tests:** confirmar fija `Confirmed` con el momento y el dispositivo; objetar exige motivo y deja `Disputed`; el silencio deja `Pending` (el reporte dice «sin confirmar»); una corrección posterior a la confirmación reabre la semana a `Pending`; el job cierra el día local anterior y es idempotente; un día sin `End` queda señalado, no cerrado; una persona dada de baja no genera semanas nuevas.
- [ ] **Step 2–4:** FAIL, implementar, PASS. Commit.

### Task 9: Solicitud de corrección y cola de RH

**Files:** Create migración y modelo `CorrectionRequest`, `Actions/RequestCorrection.php`, `Actions/ResolveCorrection.php`, `Enums/CorrectionStatus.php`, `Http/Controllers/CorrectionController.php`, `pages/attendance/corrections.tsx`. Tests Feature.

**Interfaces — Produces:** `RequestCorrection::handle(string $employeeId, ?string $markId, MarkType $type, CarbonImmutable $proposedAt, string $reason): CorrectionRequest` (desde el dispositivo, también cuando `Block` impidió marcar); `ResolveCorrection::handle(string $requestId, bool $approve, string $userId, ?string $note): CorrectionRequest` (solo `manage-attendance`; aprobar inserta la marca con `CorrectMark` o una marca nueva `Correction`; auditoría). Cola paginada con filtro por estado.

- [ ] **Step 1: Write failing tests:** la persona solicita, RH aprueba y se inserta una marca `Correction` (la original sigue); rechazar no inserta; `viewer` no resuelve (403); solicitud de otra empresa 404; la lista llega paginada; una solicitud no se puede resolver dos veces.
- [ ] **Step 2–4:** FAIL, implementar, PASS. Commit.

### Task 10: Panel de RH

**Files:** Create `Http/Controllers/AttendanceController.php`, `DeviceController.php`, `ZoneController.php`, `PolicyController.php`, `Support/AttendancePages.php`; páginas `pages/attendance/{index,center,devices,policy}.tsx`; entrada en el menú lateral; `lib/attendance/format.ts`. Partir de las pantallas del rediseño más parecidas (`docs/design-system/pantallas/`: resumen con tarjetas, tabla con cabecera `muted`, pestañas con conteo, estado vacío con siguiente paso).

**Interfaces — Consumes:** `Roster`, `WorkCenters`, `HoursCalculator`, `EffectiveMarks`. **Produces:** rutas `tenant.attendance.index` (resumen por estado: al día, objetadas, sin confirmar, topes en riesgo, centros sin zonas; detalle paginado), `tenant.attendance.center`, `tenant.attendance.devices.index|enroll|revoke`, `tenant.attendance.zones.*`, `tenant.attendance.policy.show|update`.

- [ ] **Step 1: Write failing tests:** `expectsDatabaseQueryCount` en el resumen; props sin latitud ni longitud en listas (solo en el detalle de una marca para `manage-attendance`); lista de personas paginada (agregar la página a `tests/Feature/PaginatedListsTest.php`); centro sin zonas aparece señalado; revocar un dispositivo escribe auditoría; `viewer` ve el resumen y no puede crear zonas ni revocar (403); aislamiento por URL (404).
- [ ] **Step 2–4:** FAIL, implementar, PASS; formulario de zona con latitud, longitud, radio y botón «usar mi ubicación actual» (`lib/attendance/geolocation.ts`). Browser: `visitPage()` por página (claro, oscuro y 375 px), revisión visual contra la referencia.
- [ ] **Step 5:** Commits por pantalla.

### Task 11: Reporte para la autoridad

**Files:** Create `Actions/BuildAuthorityReport.php`, `Data/AuthorityRow.php`, `Http/Controllers/ReportController.php`, `pages/attendance/report.tsx`; exportación CSV por flujo (`StreamedResponse`). Tests Feature.

**Interfaces — Produces:** `BuildAuthorityReport::handle(string $workCenterId, CarbonImmutable $from, CarbonImmutable $to): LazyCollection<int, AuthorityRow>`; `AuthorityRow(personName, localDate, start, end, breakMinutes, ordinaryMinutes, doubleMinutes, tripleMinutes, evidence, corrections, confirmation)`. Ruta `tenant.attendance.report` (vista paginada) y `…report.export` (CSV); cada descarga y cada vista del reporte van a `RecordAudit('attendance.report_exported')`.

- [ ] **Step 1: Write failing tests:** el CSV trae las columnas y filas esperadas con horas locales; incluye correcciones con su motivo y la conformidad («sin confirmar» cuando falta); la exportación escribe auditoría; no incluye coordenadas; otro centro/empresa 404; periodo mayor a 366 días rechazado.
- [ ] **Step 2–4:** FAIL, implementar, PASS. Commit.

### Task 12: Transversales y cierre

**Files:** Modify `app/Logging/…RedactSensitiveData` (+ test), `tests/Feature/RouteSecurityTest.php` (rutas `attendance.device.*`, con su razón), `tests/Feature/TenantScopedModelsTest.php` (solo si algún modelo debe leerse entre empresas: ninguno previsto), `ForbidWhileImpersonating::protectedRoutes()` si aplica, `tests/Unit/BrowserCoverageTest.php`, `.env.example` y `config/attendance.php` (huso de la semana, ventana del QR, vigencia del enlace). Docs: `docs/normas/jornada/criterios.md` historial.

- [ ] **Step 1: Write failing tests:** `RedactSensitiveData` borra `latitude`, `longitude`, `credential_id`, `public_key`; ninguna línea de log de un flujo completo de marcado contiene coordenadas; toda ruta de `attendance*` tiene nombre y está en auth o en la lista pública; `BrowserCoverageTest` exige `visitPage()` por página.
- [ ] **Step 2–4:** FAIL, implementar, PASS.
- [ ] **Step 5:** `composer ci:check`, `npm run types:check`, `npm run test:js`. Commit `chore(attendance): cross-cutting checks`.

---

## Self-Review

- **Cobertura del spec:** metas 1–5 → Tasks 3, 6–8, 2/10, 11; QR y geocerca → 4, 5, 7; conformidad → 8; correcciones → 9; privacidad/bitácora → 3, 12; panel → 10; política y bloqueo por defecto → 3, 7; decisión de dependencia → 1, 6. Fuera de fase por el spec: `Leave`, organigrama, polígonos, mapa, borrado.
- **Criterio nuevo que el usuario debe revisar:** 2.9 (qué es tiempo extra de un día; cómo se trata el día de descanso), agregado en Task 2.
- **Tipos:** `MarkContext`, `ZoneReading`, `WeekPeriod`, `EffectiveMarks` y las firmas de `RecordMark`/`CorrectMark` se usan igual en las Tasks 3, 7, 8, 9 y 11.
- **Riesgo conocido:** la ceremonia WebAuthn (Task 6) es lo más delicado y lo menos conocido; se prueba primero contra vectores y después en navegador con autenticador virtual.
