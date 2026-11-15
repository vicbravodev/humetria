# F5 · Asistencia: centros que sí pueden marcar y retardos — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que RH sepa antes de invitar que un centro no puede marcar, que la persona reciba un mensaje que sí puede seguir, y que el registro de jornada muestre los retardos.

**Architecture:** Todo dentro del módulo `Attendance`. La regla de «el centro no está listo» va en `Support/MarkPolicyCheck`, que ya decide cada marca, y el panel reutiliza `centerState()`. El retardo se deriva al leer (`Support/WorkedDay`) de la primera marca de entrada contra una hora de entrada opcional del horario y una tolerancia de la política; no se guarda ni cambia las horas trabajadas.

**Tech Stack:** Laravel 13, Pest, Vitest, Inertia + React, Postgres, Leaflet (ya instalado). Sin dependencias nuevas.

**Spec:** `docs/superpowers/plans/2026-11-12-launch-readiness.md` (E-01, HR-03, HR-13, HR-14, E-14, E-15; decisión 5) · Spec: `docs/superpowers/specs/2026-11-07-attendance-design.md` («Modelos»: `AttendancePolicy` lista tolerancias; «QR rotativo y geocerca») · **Manda:** `docs/normas/jornada/criterios.md` (§2.6).

## Global Constraints

- TDD estricto; un commit = un comportamiento; commits a nombre del usuario. PHPStan `max` y TS strictest sin supresiones ni `as`.
- `attendance_marks` es de solo inserción: nada de este frente la modifica.
- **El retardo es informativo** (decisión 5): no descuenta minutos, no genera faltas, no cambia `worked` ni `scheduled`. Se dice así en `criterios.md`.
- La hora de entrada es opcional por día; un horario sin hora se comporta exactamente como hoy.
- Horas como minutos desde la medianoche local (`config('app.display_timezone')`); instantes en UTC con `timestampTz`.
- La pantalla de marcar habla de usted; el panel tutea.
- Migración con prefijo `2027_01_16_*`, con `CHECK` en la base. Atributos nuevos en `lang/es/validation.php` → `attributes`.
- Geocodificación solo por el contrato existente `Attendance\Contracts\PlaceSearch` (Nominatim desde el servidor); en tests, su fake.
- Referencia de diseño: las pantallas de asistencia ya construidas y `docs/design-system/pantallas/`; claro, oscuro y 375 px.
- Rama `fix/launch-attendance`.

## Review Focus

1. **Centro con una zona y ninguna pantalla, con `require_qr_onsite` activo.** No es «centro sin método»: el mensaje sigue siendo el del QR, y RH ve qué falta. → Task 1.
2. **Turno nocturno que entra a las 22:00 y marca a las 00:05.** El retardo es de 125 minutos sobre el día en que empezó el turno, no un adelanto del día siguiente. → Task 2.
3. **Marca exactamente en el minuto de tolerancia y un minuto después.** 09:10 con tolerancia 10 no es retardo; 09:11 sí, de 11 minutos. → Task 2.
4. **Día con vacaciones aprobadas, día de descanso y día sin marca.** Ninguno muestra retardo. → Task 2.
5. **Centro sin domicilio completo o que Nominatim no encuentra.** El mapa abre en todo México como hoy, sin error. → Task 4.

---

### Task 1: Centro que no puede marcar: avisar a RH y a la persona (E-01)

**Files:**
- Modify: `app/Modules/Attendance/Support/MarkPolicyCheck.php:54-56`, `Support/DevicePages.php` (o `Http/Controllers/DeviceController.php:43-75`) para pasar el estado de cada centro, `Actions/SendEnrollmentLinks.php` (solo lectura del estado para el log)
- Modify: `resources/js/lib/attendance/punch-problem.ts:91-97`, `lib/attendance/punch.ts:151`, `components/attendance/enroll-all-button.tsx:32`, `enroll-button.tsx`
- Test: `tests/Unit/Attendance/MarkPolicyCheckTest.php`, `tests/Feature/Attendance/PunchTest.php`, `DevicesPageTest.php`, `EnrollmentBatchTest.php`, `tests/js/lib/attendance/punch-problem.test.ts`, `center-state.test.ts`, `tests/Browser/AttendanceDevicesTest.php`

**Interfaces:**
- Produces: código de rechazo nuevo `center_not_ready`, devuelto cuando el centro tiene 0 zonas **y** 0 pantallas activas, antes de evaluar `qr_required`.
- Produces: cada centro en la página de Dispositivos lleva `state` (el mismo valor que `centerState()` en `lib/attendance/center-state.ts:22-41`).

- [ ] **Step 1: Tests que fallan.**
  - `MarkPolicyCheckTest`: centro con 0 zonas y 0 pantallas → `center_not_ready`. Con 1 zona y 0 pantallas y `require_qr_onsite` → sigue `qr_required`. Con pantalla → como hoy.
  - `punch-problem.test.ts`: `center_not_ready` → «Su centro aún no está listo para marcar. Avise a Recursos Humanos; mientras tanto puede pedir una corrección.», sin la palabra «pantalla».
  - `DevicesPageTest`: la fila del centro sin método trae `state` de «no puede marcar».
  - `EnrollmentBatchTest`: enviar enlaces a ese centro **sí envía** (no bloquea) y el log `attendance.enrollment_sent` lleva `center_ready => false`.
- [ ] **Step 2–4:** Falla → implementar → verde.
- [ ] **Step 5:** El diálogo de «Enviar enlaces» muestra, para un centro que no puede marcar: «Hoy nadie puede marcar en {centro}: le falta una zona o una pantalla.» con el enlace a configurarlo, y deja enviar.
- [ ] **Step 6: Commit** (dos): `Asistencia: un centro sin zona ni pantalla responde «aún no está listo», no «escanee la pantalla»` · `Asistencia: RH ve qué centros no pueden marcar antes de enviar los enlaces`.

### Task 2: Retardo derivado de la hora de entrada y la tolerancia (HR-03)

**Files:**
- Create: `database/migrations/2027_01_16_100000_add_start_times_and_tolerance_to_attendance.php` — `attendance_schedules.starts_by_weekday` `jsonb` nullable; `attendance_policies.late_tolerance_minutes` `unsignedSmallInteger` por omisión `10`, `CHECK (late_tolerance_minutes BETWEEN 0 AND 120)`
- Modify: modelos `AttendanceSchedule` y `AttendancePolicy`, `Support/ScheduleInput.php`, `Support/CompanyPolicy.php`, `Support/WorkedDay.php`, `Data/DayHours.php`, `Support/PersonWeek.php:50-60`, `Support/TeamWeek.php`, `Actions/SaveSchedule(s)`, `Actions/SavePolicy`, sus FormRequest, `lang/es/validation.php`
- Modify: `docs/normas/jornada/criterios.md` (párrafo nuevo en §2.6: el retardo se muestra, no se sanciona; la tolerancia es de la empresa porque la LFT no la fija)
- Test: `tests/Unit/Attendance/WorkedDayTest.php`, `tests/Feature/Attendance/ScheduleAndPolicyTest.php`, `WeekTest.php`

**Interfaces:**
- `starts_by_weekday`: mapa `1..7 → ?int` (minutos desde medianoche; `null` = sin hora ese día).
- `DayHours` gana `public ?int $lateMinutes` — `null` cuando el día no tiene hora de entrada, no hay marca de entrada, es descanso o hay ausencia aprobada; `0` cuando la primera entrada cae dentro de `start + tolerance` (inclusive); en otro caso, los minutos transcurridos desde `start`.
- Validación: cada hora entre `0` y `1439`; tolerancia entera entre `0` y `120`.

- [ ] **Step 1: Tests que fallan** en `WorkedDayTest.php` (declara `mutates(WorkedDay::class)`), con entrada 09:00 y tolerancia 10:
  - 08:50 → `0`; 09:10 → `0`; 09:11 → `11`; 10:30 → `90`;
  - sin hora de entrada → `null`; sin marca → `null`; descanso → `null`; ausencia aprobada → `null`;
  - turno 22:00 con marca 00:05 del día siguiente → `125`, atribuido al día del turno;
  - tolerancia `0`: 09:01 → `1`.
  `ScheduleAndPolicyTest`: guardar hora `1440` o tolerancia `121` → 422; guardar sin horas deja `starts_by_weekday` en `null` y la semana igual que antes.
- [ ] **Step 2:** Correr → fallan por propiedad y columnas inexistentes.
- [ ] **Step 3:** Migración, modelos, entrada y cálculo. `worked` y `scheduled` no cambian (afirmarlo en `WeekTest`).
- [ ] **Step 4:** Verde. Las pantallas de semana fijan su número de consultas: no debe subir.
- [ ] **Step 5: Commit** `Asistencia: el horario admite hora de entrada y la política una tolerancia; la semana calcula el retardo`.

### Task 3: El retardo a la vista (HR-03)

**Files:**
- Modify: el diálogo de horario y `policy-fields.tsx` en `resources/js/components/attendance/`, las vistas de semana (persona y equipo), `components/attendance/report-day-row.tsx`, `lib/attendance/` (formato del retardo), tipos
- Test: `tests/js/lib/attendance/` (test nuevo `late.test.ts`), `tests/Feature/Attendance/SchedulesPageTest.php`, `tests/Browser/AttendanceSchedulesTest.php`, `AttendanceWeekTest.php`, `AttendancePolicyTest.php`

**Interfaces:**
- `lateLabel(minutes: number | null): string | null` — `null` o `0` → `null`; `11` → «Retardo de 11 min»; `90` → «Retardo de 1 h 30 min».

- [ ] **Step 1: Tests que fallan.** `late.test.ts` con los casos de arriba. Browser: en Horarios, poner entrada 09:00 de lunes a viernes con `<input type="time">`; en Política, tolerancia 10; en la semana de una persona que marcó 09:20, la insignia «Retardo de 20 min»; en el reporte, la columna «Retardos» con el conteo del periodo.
- [ ] **Step 2–4:** Falla → implementar → verde.
- [ ] **Step 5:** La insignia usa el tono de aviso del design system (no el de riesgo). Texto bajo el campo de tolerancia: «Solo se muestra: Humetria no descuenta tiempo ni marca faltas.» Revisar claro, oscuro y 375 px.
- [ ] **Step 6: Commit** `Asistencia: hora de entrada y tolerancia en la configuración, y el retardo en la semana y el reporte`.

### Task 4: El mapa de la zona abre en el domicilio del centro (HR-13)

**Files:**
- Modify: `app/Modules/Attendance/Support/AttendancePages.php` (prop `centerAddress` en la página de zonas), `resources/js/hooks/use-zone-picker-map.ts:23-24,37-39`, `components/attendance/zone-dialog.tsx`, `lib/attendance/zone-picker.ts`
- Test: `tests/Feature/Attendance/ZoneTest.php`, `tests/js/lib/attendance/zone-picker.test.ts`

- [ ] **Step 1: Tests que fallan.** La página de zonas trae `centerAddress` armado con calle, número, colonia, CP, municipio y estado del centro (por el contrato de Tenancy que ya usa el módulo). `zone-picker.test.ts`: `initialSearch(zone, centerAddress)` → la dirección cuando la zona no tiene centro; `null` cuando ya lo tiene o la dirección está vacía.
- [ ] **Step 2–4:** Falla → implementar: al abrir una zona nueva, la búsqueda de lugar parte del domicilio y se ejecuta una vez contra el endpoint existente; sin resultado, queda todo México.
- [ ] **Step 5:** Verde. Navegador con el fake local de `PlaceSearch`.
- [ ] **Step 6: Commit** `Asistencia: el mapa de una zona nueva abre en el domicilio del centro`.

### Task 5: Textos de la pantalla de marcar (HR-14, E-14, E-15)

**Files:**
- Modify: `resources/js/lib/attendance/punch-view.ts:21-23`, `lib/attendance/correction-effects.ts:16` (un solo `firstName`), `components/attendance/link-device-note.tsx:19-25`, `components/attendance/correction-form.tsx`
- Test: `tests/js/lib/attendance/punch-view.test.ts`, `correction.test.ts`, `tests/Browser/AttendancePunchTest.php`

- [ ] **Step 1: Tests que fallan.** `firstName('Ing. Carlos Ruiz')` → «Carlos»; lo mismo con «Lic.», «Dr.», «Dra.», «Mtro.», «Mtra.», «C.P.»; `firstName('Ing.')` → «Ing.» (no queda vacío). La nota de vincular dice «Si este teléfono aún no está vinculado» y agrega «Si ya lo vinculó, solo marque.» El formulario de corrección abierto desde Marcar muestra «Día: jueves 12 de noviembre».
- [ ] **Step 2–4:** Falla → implementar → verde.
- [ ] **Step 5: Commit** (tres, uno por comportamiento).

### Cierre del frente

- [ ] `composer ci:check`, `npm run types:check`, `npm run test:js` en verde.
- [ ] Recorrido: centro «Sucursal» sin zona ni pantalla desde el celular y desde el panel; horario con entrada y un retardo; claro, oscuro, 375 px.
- [ ] Preguntar «¿Creo el PR?».
