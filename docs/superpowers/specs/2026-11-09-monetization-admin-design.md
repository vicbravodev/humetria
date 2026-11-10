# Monetización, consultores y administración — diagnóstico y plan

Fecha: 9 de noviembre de 2026 · Estado: **diseño y las 14 decisiones de §8 aceptados por Victor el 9 de noviembre de 2026; nada implementado**
Fuente: handoff v1.0 del 8 de noviembre de 2026.
Base auditada: `origin/claude/agentes-secuenciales-ui-3de25c` (`a17fe1dc`, 43 commits sobre `main`; solo agrega UI de Asistencia, no toca Tenancy ni cobro).

Leyenda: **existe** · **parcial** · **falta** · **no comprobado**.
Los precios son los del handoff. «Propuesta operativa» = regla configurable aún sin confirmar.

---

## 1. Resumen

Humetría ya tiene la mitad difícil: aislamiento por empresa probado, un solo punto que decide si una empresa está
bloqueada y que respetan rutas, portales públicos y jobs, consola de superadmin, impersonación auditada, bitácora de
solo inserción y una cuota mensual de tokens de Humi por empresa.

No tiene nada comercial: ni pagador, ni plan, ni precio, ni estado de cuenta, ni pago. Tampoco hay módulos por plan
(todas las empresas ven todo), ni tope de empleados o centros, ni medición de correo o archivos, ni costo en dinero de
la IA. Y cuatro herramientas del alcance de lanzamiento no existen en el código: vacaciones, organigramas, pulso y clima.

El plan agrega **un módulo nuevo, `Billing`**, y extiende Tenancy en dos puntos (estado operativo con motivo y módulos
habilitados). No hay segunda arquitectura multi-tenant: el consultor es una cuenta pagadora más usuarios con membresía
en cada empresa cliente, que es exactamente lo que el sistema ya soporta.

---

## 2. Diagnóstico

### 2.1 Empresas, centros, acceso

| Tema | Estado | Evidencia |
|---|---|---|
| Empresa = tenant, RFC y slug únicos, con checks en BD | existe | `database/migrations/2026_10_27_200000_create_tenants_table.php` |
| Aislamiento por `tenant_id` con global scope y test por modelo | existe | `tests/Feature/TenantScopedModelsTest.php`, `BelongsToCurrentTenant` |
| Un usuario en varias empresas, con rol distinto en cada una (Spatie teams) | existe | `app/Modules/Tenancy/Models/TenantMembership.php`, `Support/CompanyRoles.php`, selector en `ResolveTenant.php:98` |
| Quien no es miembro recibe 404, superadmin incluido | existe | `app/Modules/Tenancy/Http/Middleware/ResolveTenant.php:44-49` |
| Roles por empresa: owner, hr, viewer, committee, complaints | existe | `app/Modules/Tenancy/Enums/TenantRole.php` |
| Tipo de cuenta: empresa o sindicato (solo calculadora CCT) | existe | `Enums/TenantType.php`, `Middleware/LimitUnionToCompensation.php`, `Support/ComplianceRoutes.php` |
| Centros de trabajo con archivo y restauración | existe | `Models/WorkCenter.php`, `Actions/ArchiveWorkCenter.php` |
| Tope de centros o de empleados | falta | `CreateWorkCenter`, `Organization` no consultan ningún cupo |
| Empleado activo = `deactivated_at IS NULL`; conteo por empresa | existe | `Organization/Models/Employee.php:39,67`, `Contracts/Headcount.php` (`ofCompany`) |
| Historial de plantilla (para «máximo del mes») | falta | `ReactivateEmployee.php:20` borra la fecha de baja; los eventos `EmployeeCreated/Deactivated/Moved/Reactivated` no se guardan |
| Pagador, cuenta comercial, consultor, cartera | falta | sin tablas ni clases |
| Alta de empresa solo desde la consola (nadie «reclama» por RFC) | existe | `routes/tenancy.php`, `Actions/CreateTenant.php` |
| Transferir una empresa entre pagadores | falta | — |

### 2.2 Estados y suspensión

| Tema | Estado | Evidencia |
|---|---|---|
| Estado calculado de fechas: sin iniciar, prueba (14 días), activa, prueba terminada, vencida, desactivada | existe | `Models/Tenant.php:59-76`, `Enums/TenantStatus.php` |
| Bloqueo en el panel: lectura → página de bloqueo, escritura → 423 | existe | `Middleware/BlockSuspendedTenant.php` |
| Bloqueo fuera del panel: cada portal público y cada job consulta `CompanySummary->blocked` | existe | `Support/CompanyDirectory.php:36,63`; encuesta, portales, buzón, checador, reportes compartidos, 6 digests y todos los jobs de envío lo revisan |
| Tareas programadas que **no** revisan bloqueo | parcial | `Campaigns/Actions/CloseExpiredCampaigns.php`, `Complaints` `PurgeComplaints` (cerrar y purgar en una empresa bloqueada es defendible; hay que decidirlo y fijarlo con test) |
| Sesión abierta al suspender | existe | el estado se lee en cada request; no hay caché que revocar |
| Motivo obligatorio, fecha efectiva, vista previa | falta | `Actions/DeactivateTenant.php` solo pone `deactivated_at`; audita sin motivo |
| Solo lectura (consultar y exportar, sin operar) | falta | hoy es todo o nada |
| Archivada | falta | — |
| Estado de suscripción y de documento de cobro, separados del acceso | falta | `active_until` hace de las tres cosas |

### 2.3 Consola interna y roles

| Tema | Estado | Evidencia |
|---|---|---|
| Consola: listar, buscar, filtrar por estado, crear, editar, activar hasta una fecha, desactivar, invitar owner | existe | `Http/Controllers/TenantConsoleController.php`, `resources/js/pages/tenancy/index.tsx` |
| Solicitudes de demo | existe | `app/Modules/Demos`, `routes/demos.php` |
| Entrar como owner: visible, auditado, sin acceso a casos de quejas ni a la lista de Guía I, sin tocar la cuenta personal | existe | `app/Http/Impersonation.php`, `ForbidWhileImpersonating.php:28-39`, `AppServiceProvider.php:40-42` |
| Ajustes de Humi por empresa (encendido, presupuesto, usado en el mes) | parcial | `Assistant/Http/Controllers/TenantSettingsController.php`; una empresa a la vez, sin vista de cartera ni costo |
| Roles internos | parcial | un solo rol `superadmin` que pasa todo por `Gate::before` (`AppServiceProvider.php:53`); no hay operador ni soporte |
| Bitácora de solo inserción con actor, impersonador, request | existe | `app/Actions/RecordAudit.php`, `app/Models/AuditEntry.php` |
| Cartera, cotizador, estados de cuenta, pagos, exportes | falta | — |

### 2.4 Uso y cuotas

| Recurso | Estado | Evidencia y huecos |
|---|---|---|
| **Humi** | parcial | Existe: `ai_usage` por mensaje con tokens de entrada y salida por empresa; presupuesto mensual (default 2,000,000 para todas, `config/humi.php:31`) con override por empresa; límites 10/min y 200/día por persona y 30/min por empresa; encendido por empresa. Falta: reserva atómica (`CheckAllowance.php:40` es una lectura simple, dos peticiones pasan a la vez), modelo/proveedor/caché/costo en la fila (el SDK los da y se descartan en `ChatController.php:99-100`), los tokens de un stream que falla no se registran (no hay `catch`), idempotencia, alertas 70/90/100 y tope en dinero. |
| **Correo** | falta | 28 notificaciones, todas por `mail`, encoladas en Redis/Horizon. Sin proveedor elegido (`config/mail.php` default `log`). Sin conteo por empresa, outbox, webhooks ni cuota. Solo existen un limitador global de invitaciones por minuto y 5 jobs únicos. SMS y WhatsApp: no existen. |
| **Archivos** | falta | Telework, Seating, Interventions y Disclosure guardan fotos y PDFs (8 y 20 MB máx.) en disco `local`; el `tenant_id` va en la ruta (`{tipo}/{tenant}/…`). Ninguna tabla guarda el tamaño. No hay borrado ni contabilidad. Las cargas pasan por el servidor (no hay URLs firmadas). Quejas no tiene adjuntos. |
| **Asistencia y encuestas** | no comprobado | Sin cargo por operación, correcto. Prueba de carga en cambio de turno: no existe. |

### 2.5 Herramientas del alcance de lanzamiento

| Herramienta | Estado | Módulo |
|---|---|---|
| Asistencia / jornada | existe | `Attendance` |
| NOM-035 | existe | `Campaigns`, `Instruments`, `Responses`, `Results` |
| NOM-037 | existe | `Telework` |
| Ley Silla | existe | `Seating` |
| Buzón de quejas | existe | `Complaints` |
| Políticas y acuses | existe | `Disclosure` |
| Intervenciones | existe | `Interventions` |
| Calculadora CCT | existe | `Compensation` |
| Humi | existe | `Assistant` |
| **Vacaciones** | **falta** | solo aparece como prestación dentro de Compensation |
| **Organigramas** | **falta** | — |
| **Pulso** | **falta** | — |
| **Clima laboral** | **falta** | — |

El catálogo debe nombrar las cuatro que faltan, pero no se pueden vender hasta que existan. Construirlas no es parte
de este trabajo.

---

## 3. Diseño adaptado

### 3.1 Qué va dónde

**Tenancy** conserva lo operativo: qué empresa es, si puede usarse, y qué puede usar. Se le agregan:

- `tenants.restriction` (`null | read_only | suspended | archived`), `restriction_reason`, `restricted_at`. Reemplaza a
  `deactivated_at` (migración: toda desactivada pasa a `suspended` con motivo «migrada»).
- `tenant_modules` (`tenant_id`, `module`): los módulos habilitados. Es un hecho operativo; Billing lo escribe por
  contrato cuando cambia la suscripción.
- `tenant_limits` (`tenant_id`, `resource`, `amount`, `valid_until`, `reason`): cupos vigentes (empleados, centros,
  tokens, correos, bytes) y overrides temporales.
- `CompanySummary` gana `readOnly` y `allows(Module)`. Todo lo que hoy pregunta `->blocked` ya tiene el objeto en la
  mano: el mismo patrón sirve para módulos.

**Billing** (módulo nuevo, `app/Modules/Billing`) es lo comercial. Lee de otros módulos solo por contrato
(`Tenancy\Contracts\Companies`, `Organization\Contracts\Headcount`) y nunca hace joins a sus tablas.

| Tabla | Para qué |
|---|---|
| `billing_accounts` | Pagador: `kind` (`direct`, `consultant`), razón social, RFC, contacto, notas contractuales. No lleva `tenant_id`: se lee entre empresas (entra a la lista de excepciones de `TenantScopedModelsTest` con su razón). |
| `billing_account_tenants` | Qué empresa paga qué cuenta, con `starts_on` y `ends_on`. Un índice parcial garantiza **un solo pagador vigente por empresa**; transferir = cerrar una fila y abrir otra en la misma transacción. |
| `subscriptions` | Una por empresa: `plan`, `profile` (`direct`, `channel_compliance`, `channel_integral`, `negotiated`), `catalog_version`, `status` (`trial`, `active`, `past_due`, `cancel_scheduled`, `ended`), `seats` y `centers` contratados, `starts_on`, `renews_on`, precio negociado opcional con vigencia. |
| `subscription_charges` | Cargos recurrentes extra (acompañamiento) y de una sola vez (implementación, CCT) con descripción, alcance, fecha y quién aprobó. Uno de una vez se factura una vez. |
| `statements`, `statement_lines` | Estado de cuenta por empresa y periodo. Cada línea guarda el snapshot completo: versión de catálogo, base de empleados, banda, mínimo, descuento, días, IVA y la explicación en texto. Emitido = inmutable (trigger, como `audit_entries`); se corrige con una línea de ajuste que referencia a la original. |
| `payments`, `payment_allocations` | SPEI manual: referencia, fecha, importe, evidencia. `unique(account_id, reference)` detecta el doble registro. La aplicación va a estados de cuenta concretos; un pago parcial deja saldo. |
| `usage_events` | Libro de uso durable: `tenant_id`, `resource`, `units`, `cost_cents`, `idempotency_key` único, `occurred_at`. |

**Catálogo versionado:** archivo `config/billing.php`, una entrada por versión con fecha de vigencia (planes, tarifas,
mínimos, bandas, centros incluidos, cuotas, módulos por plan, política de elegibilidad). Los precios negociados y los
overrides viven en la base, así que el día a día no toca código; una versión nueva del catálogo sí es un deploy.
`ponytail:` si llega a cambiar más de un par de veces al año, se pasa a tablas con pantalla de edición.

### 3.2 El consultor

- Una `billing_account` de tipo `consultant` paga N empresas. Cada empresa sigue siendo un tenant completo y aislado.
- La gente del consultor entra a cada empresa por **membresía**, con un rol de los que ya existen. Pagar no da acceso:
  sin membresía, 404. Así el consultor no ve quejas ni la lista de Guía I salvo que tenga ese rol en esa empresa.
- Su «cartera» es la lista de empresas que ya muestra `HomeController`, más costo y próximo importe por empresa (visible
  solo a los usuarios marcados como contacto de la cuenta).
- En el MVP, Humetría da de alta cada empresa desde la consola y el consultor la pide por fuera. Autoservicio del
  consultor: después, cuando el volumen lo pida.
- «Suspender al consultor» no existe como interruptor: es una acción en lote con vista previa que lista cada empresa, y
  cada una se suspende por separado. No abre ninguna vía alterna porque el acceso siempre es por empresa.

### 3.3 Estados

| Operativo (Tenancy) | Sale de | Panel | Portales, checador, jobs |
|---|---|---|---|
| Borrador | sin `trial_started_at` ni `active_until` | normal | normal |
| Prueba | `trial_started_at` + 14 días | normal, cuotas de prueba | normal |
| Activa | `active_until` futuro, sin restricción | normal | normal |
| Solo lectura | `restriction = read_only`, o prueba/vigencia vencida | consulta y exporta según rol; escritura 423 | bloqueados |
| Suspendida | `restriction = suspended` | página de bloqueo; exporte solo desde la consola | bloqueados |
| Archivada | `restriction = archived` | igual que suspendida; sale de las vistas y deja de cobrarse al corte | bloqueados |

El estado de la **suscripción** vive en Billing y el del **documento** en `statements.status` (`draft`, `issued`,
`partially_paid`, `paid`, `cancelled`, `adjusted`). Suspender no toca el saldo; reactivar no reinicia prueba, periodo ni
consumo, porque esos datos no están en la columna que cambia.

Cambio de comportamiento a confirmar: hoy una prueba terminada o vigencia vencida bloquea todo; la propuesta es que
caigan en **solo lectura** y que el bloqueo total sea siempre una decisión humana con motivo.

### 3.4 Orden de evaluación (backend)

1. Identidad y membresía — `ResolveTenant` (existe).
2. Estado operativo — `BlockSuspendedTenant` (existe; se le agrega solo lectura).
3. Módulo habilitado — middleware nuevo `LimitToEnabledModules`, por nombre de ruta, con un registro como
   `ComplianceRoutes` y un test que falle si una ruta `tenant.*` no pertenece a ningún módulo. Responde 404.
4. Permiso del rol — policies y `LimitRestrictedRoles` (existe).
5. Cuota y rate limit — en la acción que consume.

Fuera del panel (portal, enlace público, checador, job, comando programado) los pasos 2 y 3 se hacen con
`CompanySummary`. Un test de arquitectura exige que toda clase que llame a `Companies::enter()` consulte el resultado.

---

## 4. Matriz de permisos

### 4.1 Roles internos

Propuesta: **dos** roles, no tres. Con dos personas, «operador comercial» y «propietario» son la misma. Se parte cuando
llegue una tercera.

| Acción | superadmin | support |
|---|---|---|
| Ver cartera, estados, uso, errores, jobs | sí | sí |
| Reenviar invitación, entrar como owner (con los candados actuales) | sí | sí |
| Crear cuenta o empresa, vincular, transferir | sí | no |
| Suspender, solo lectura, archivar, reactivar | sí | no |
| Plan, precio, descuento, cargos, cuotas y overrides | sí | no |
| Registrar, aplicar o cancelar pagos; emitir o ajustar estados | sí | no |
| Casos de quejas, lista de Guía I | no (ni impersonando) | no |
| Acciones de cobro mientras impersona | no | no |

`Gate::before` sigue dando todo al superadmin; `support` recibe gates explícitos. Las rutas de Billing entran a
`ForbidWhileImpersonating`.

### 4.2 Módulos por plan (propuesta operativa)

| Módulo (`Module`) | Jornada | Cumplimiento | Integral | Canal Cumplimiento | Sindicato |
|---|---|---|---|---|---|
| `attendance` | sí | — | sí | — | — |
| `vacations`, `org_chart` (no existen) | sí | — | sí | — | — |
| `nom035` (campañas, resultados, intervenciones, difusión) | — | sí | sí | sí | — |
| `nom037` | — | sí | sí | sí | — |
| `seating` | — | sí | sí | sí | — |
| `complaints` | — | sí | sí | sí | — |
| `pulse`, `climate` (no existen) | — | sí | sí | sí | — |
| `assistant` (tokens/mes) | 250,000 | 500,000 | 2,000,000 | 500,000 | — |
| `compensation` (CCT) | aparte | aparte | aparte | aparte | sí |
| Centros incluidos | 1 | 2 | 3 | 2 (Integral canal: 3) | — |

Centros de trabajo y empleados son base de todos los planes. `TenantType::Union` se queda como está.

---

## 5. Especificación de cálculo

- Dinero en **centavos enteros** (`int`), moneda `MXN` explícita. Sin `float` en ningún importe.
- Una función pura: `Quote::for(plan, profile, seats, centers, catalogVersion, eligibleIntegralCompanies)` → líneas.
  Es la única que calcula; la usan el cotizador, la vista previa y la emisión.
- **Directo:** `max(mínimo, seats × tarifa)`. Más de 100 empleados → «requiere cotización» (sin importe), salvo precio
  negociado en la suscripción.
- **Canal Cumplimiento:** banda completa por plantilla: 1–50 $2,490 · 51–100 $3,990 · 101–250 $7,990 · 251+ cotización.
- **Canal Integral:** importe directo × 80% si la cuenta tiene ≥ 3 empresas con Integral pagado (sin pruebas,
  suspendidas ni archivadas), evaluado sobre el snapshot de la renovación. Con menos, tarifa directa.
- **Centros:** `max(0, centers − incluidos) × $149`. No entra al descuento de canal.
- **Redondeo:** una sola vez por línea, medio centavo hacia arriba. El descuento se calcula sobre la línea ya redondeada.
- **Prorrateo:** `importe × días_restantes / días_del_periodo`, con división entera y el mismo redondeo. Solo en altas a
  mitad de periodo.
- **IVA:** línea aparte, 16% configurable, sobre el subtotal.
- **Periodos:** mes natural, corte el día 1, intervalo `[inicio, fin)` en `config('app.display_timezone')`
  (America/Mexico_City; Monterrey tiene el mismo huso, no hace falta zona por contrato). Con corte el día 1 no hay
  problema de 29/30/31.
- **Cero empleados:** no activa una suscripción; una ya activa paga su mínimo.

Fixtures (sin IVA), todos del handoff §10, como dataset de Pest:

| Caso | Esperado |
|---|---|
| Directo Jornada, 20 emp., 1 centro | $1,990 |
| Directo Cumplimiento, 100, 2 centros | $4,900 |
| Directo Integral, 100, 3 centros | $9,900 |
| Directo Integral, 100, 4 centros | $10,049 |
| Canal Cumplimiento 50 / 51 / 100 / 101 / 250 | $2,490 / $3,990 / $3,990 / $7,990 / $7,990 |
| Canal Cumplimiento 251 | requiere cotización |
| Canal Cumplimiento, 5 empresas de 100 | $19,950 |
| Canal Integral elegible, 3 × 100 | $23,760 |
| Canal Integral elegible, 5 × 100 | $39,600 |
| Canal Integral, 2 × 100 | $19,800 (sin descuento) |
| Canal Integral elegible, 3 × 20 | $11,976 |
| Fronteras directo 0, 1, 50, 51, 100, 101 | mínimo, mínimo, según tarifa, …, cotización en 101 |

---

## 6. Medición, concurrencia y conciliación

Un solo mecanismo para los tres recursos: `usage_events` (durable) más un contador por empresa y periodo en Postgres.
La reserva es un `UPDATE … SET reserved = reserved + :n WHERE used + reserved + :n <= limit` — atómico sin Redis y sin
transacción abierta durante la llamada externa. `ponytail:` una fila caliente por empresa y recurso; si el checador o
Humi la contienden, se parte por recurso o se pasa a Redis con el libro como respaldo.

### 6.1 Humi

1. Antes de llamar: reservar `entrada estimada + MaxTokens × MaxSteps` (hoy 1,500 × 6). Si no cabe, 403 como hoy.
2. Al terminar (`then`): registrar el evento con tokens reales de entrada, salida, caché y razonamiento, modelo,
   proveedor y costo según la tarifa de `config/billing.php` vigente ese día; liberar el sobrante.
3. Si falla o se corta (`catch`, hoy no existe): el evento queda `pending` con la reserva como estimación; un comando
   programado lo concilia o lo confirma a las 24 h.
4. Idempotencia: llave por mensaje enviada por el cliente; un reintento con la misma llave no reserva ni cobra de nuevo.
   Si el proveedor cobró dos llamadas, van dos eventos de costo y uno solo cuenta contra la cuota.
5. Alertas 70/90/100% al owner y a la consola, una vez por umbral y periodo.
6. Tope en dinero por empresa, por cuenta pagadora y global de la plataforma; el global corta a todos y avisa.
7. Los rate limits actuales se conservan (mensajes por minuto y por día); son independientes del cupo.

La cuota es de la empresa. No se multiplica por centro ni por usuario y no hay bolsa compartida del consultor.

### 6.2 Correo

- Conteo: un listener de `NotificationSent` escribe el evento con el `tenant_id` del `Context` (ya viaja en los jobs).
  La llave idempotente es el id de la notificación, así un reintento no duplica.
- Dos bolsas: **campañas** (invitaciones, recordatorios, digests) con cuota, y **esenciales** (invitación de acceso,
  recuperación, seguridad, avisos de accidente) sin cuota. Cada notificación declara su bolsa; un test lo exige.
- Reserva antes de encolar en los envíos masivos, que ya conocen su número de destinatarios
  (`QueueSurveyInvitations`, `SendEnrollmentLinks`, `RequestAcknowledgements`, `RequestAcknowledgmentsForCenter`).
  Si no cabe, la campaña no sale y la pantalla dice cuántos faltan.
- Entregado, rebotado y webhooks: cuando se elija proveedor. Hasta entonces la métrica es «aceptado para envío».

### 6.3 Archivos

- Los cuatro módulos que guardan archivos pasan por un contrato común de Billing (`StorageMeter::reserve(bytes)` /
  `commit` / `release`) dentro de su acción de guardado. Sin URLs firmadas no hay carga huérfana: el tamaño real se
  conoce antes de escribir.
- Conciliación nocturna: como la ruta es `{tipo}/{tenant}/…`, se suma el tamaño real por prefijo y se corrige el
  contador. Eso también hace el backfill inicial, sin migrar datos.
- Lo que ocupa bytes cuenta, aunque esté «borrado» a la vista. Al exceder: se rechaza la carga nueva, se puede leer y
  exportar, no se borra nada.
- Antes de producción: mover los discos de módulos a S3/R2 (hoy `local`).

### 6.4 Empleados y centros

**Cupo contratado**, no máximo del mes. Se valida al dar de alta, reactivar, importar y al crear o restaurar un centro,
bajo `lockForUpdate` del cupo. No necesita historial, no produce cargos sorpresa y es lo que el handoff pide en §7
(«exigir aceptación antes de habilitar crecimiento»). Al llegar al tope la pantalla muestra el precio siguiente (p. ej.
canal 50 → 51: $2,490 → $3,990) y la ampliación la aprueba Humetría en la consola. Mover a alguien de centro o habilitar
un módulo no cambia la cuenta: la plantilla se cuenta una vez por empresa.

---

## 7. Plan por fases

Cada renglón es un PR completo (migración, backend, UI, tests), en este orden. Complejidad: S ≈ 1–2 días, M ≈ 3–5, L ≈ 1–2 semanas.

### Fase 1 — Administración comercial manual

| # | Trabajo | Tamaño | Depende de | Aceptación (handoff §11) |
|---|---|---|---|---|
| 1.1 | **Estados con motivo.** `restriction` + motivo + solo lectura + archivada; vista previa antes de aplicar; aviso específico si la empresa usa Asistencia; rol `support`. | M | — | 3, 4, 5, 16, 18, 19 |
| 1.2 | **Cuentas y catálogo.** `Billing`: cuentas, vínculo empresa–pagador, catálogo v1, suscripción por empresa, `Quote` con todos los fixtures, consola de cartera (cuenta → empresas → centros, costo individual y total, razón del descuento, próxima renovación), búsqueda por nombre, RFC, consultor, estado y vencimiento. | L | 1.1 | 1, 6, 7, 12, 13 |
| 1.3 | **Estados de cuenta y pagos manuales.** Generar borrador, emitir (inmutable), ajustar; registrar SPEI con evidencia, aplicar parcial o total; saldos; exportes CSV de cartera, saldos y renovaciones. | L | 1.2 | 13, 15 |
| 1.4 | **Transferencia de empresa** entre pagadores con fecha efectiva, revocación de membresías del anterior y sin doble cobro. | S | 1.2 | 17 |

Con 1.1–1.3 ya se puede vender: todo el cobro es manual y explicable.

### Fase 2 — Entitlements y uso

| # | Trabajo | Tamaño | Depende de | Aceptación |
|---|---|---|---|---|
| 2.1 | **Módulos por plan.** `tenant_modules`, middleware, `CompanySummary::allows`, navegación; portales y jobs lo respetan. | M | 1.2 | 1, 2, 11 |
| 2.2 | **Cupo de empleados y centros.** | M | 1.2 | 11, 12 |
| 2.3 | **Humi con libro y reserva.** §6.1, vista de uso y costo por empresa y por cuenta. | L | 1.2 | 8, 9 |
| 2.4 | **Correo medido.** §6.2. | M | 2.3 (reusa el libro) | 9 |
| 2.5 | **Archivos medidos.** §6.3. | M | 2.3 | 10 |

### Fase 3 — Renovaciones repetibles

| # | Trabajo | Tamaño | Aceptación |
|---|---|---|---|
| 3.1 | Comando mensual idempotente que genera los borradores del periodo (`unique(subscription_id, period_start)`), consolidado por consultor, elegibilidad sobre snapshot, prorrateos y cancelaciones programadas. Emisión siempre con revisión humana. | M | 7, 13, 14, 20 |
| 3.2 | Avisos: próximo importe, pérdida de elegibilidad antes del corte, saldo vencido; cola de revisión de morosos. La suspensión sigue manual. | S | 7 |

### Fase 4 — Preparación operativa

Prueba de carga del checador en cambio de turno (p95/p99), restauración de respaldo ensayada, discos en S3/R2,
proveedor de correo con webhooks, recorrido completo directo y canal con datos sintéticos. Pasarela y CFDI solo cuando
se elijan proveedores.

**Clientes existentes:** ninguna migración crea suscripciones pagadas. Toda empresa actual queda con todos los módulos
habilitados y sin suscripción («sin plan asignado» en la consola) hasta que se le asigne una a mano. **Reversión:** cada
PR agrega tablas y columnas; revertirlo no pierde datos operativos.

### Riesgos

- `active_until` hoy es la única palanca de acceso. En 1.2 pasa a escribirlo Billing; hasta 3.1 se sigue moviendo a mano.
- Solo lectura cambia lo que ve una empresa vencida (hoy: bloqueo total). Requiere tu confirmación.
- Cuatro herramientas del catálogo no existen; vender Jornada o Cumplimiento prometiéndolas es un riesgo comercial, no técnico.
- La reserva de Humi sobreestima (9,000 tokens por mensaje): con cuotas chicas (250,000) bloqueará antes de agotar el
  saldo real. Se ajusta la estimación, no el mecanismo.
- Sin proveedor de correo no hay costo real ni entregabilidad; la cuota de correo arranca como conteo.

---

## 8. Decisiones (aceptadas el 9 de noviembre de 2026)

Victor aceptó las 14 recomendaciones tal como están. Siguen siendo configurables (catálogo y suscripción); los importes
de implementación, CCT y ampliaciones se cotizan a mano y no tienen tarifa publicada.

1. **Módulos por plan y «acompañamiento por cuenta».** Recomiendo la matriz de §4.2 y acompañamiento **por empresa**, como cargo recurrente opcional, nunca incluido en canal.
2. **Centros incluidos.** 1 / 2 / 3 directo, 2 / 3 canal, extra $149. Recomiendo aceptarlo tal cual.
3. **Directo con más de 100 empleados.** Cotización manual con precio negociado en la suscripción. Sin tramos hasta tener tres casos reales.
4. **Tres empresas para el descuento.** Solo Integral pagado cuenta. Al perder elegibilidad: aviso y tarifa directa desde la siguiente renovación, sin periodo de gracia adicional.
5. **Plantilla facturable.** Cupo contratado (§6.4), no máximo del mes.
6. **Corte y prorrateo.** Corte el día 1; alta prorrateada por días; baja, reducción y archivo al siguiente corte, sin crédito.
7. **Cuotas.** Tokens 250,000 / 500,000 / 2,000,000; correo 8 por empleado al mes con mínimo de 500; archivos 5 GB. Ampliaciones por cotización manual.
8. **Implementación, CCT, promociones.** Cargos manuales con aprobación; sin tarifa publicada.
9. **Tras cancelar o suspender.** Solo lectura 30 días y luego archivada; datos conservados. Asistencia: **no** hay marcaje limitado durante la suspensión; el aviso al operador dice que la empresa debe registrar la jornada por otro medio.
10. **Fiscal.** Fuera del MVP. La cuenta guarda razón social, RFC, régimen, CP y uso de CFDI para cuando se conecte.
11. **Grupos y transferencias.** Grupo = una cuenta directa que paga varias empresas, cada una con su suscripción. Transferir exige confirmación del owner de la empresa y se ejecuta desde la consola.
12. **Vencimiento = solo lectura** en lugar de bloqueo total (§3.3). Recomiendo que sí.
13. **Dos roles internos** en lugar de tres (§4.1). Recomiendo que sí.
14. **Quién es owner en una empresa de consultor.** Recomiendo: el consultor es owner mientras el cliente no tenga usuarios propios; si el cliente los quiere, el cliente es owner y el consultor entra como `hr`.

---

## 9. Guion de validación

1. **Directo, 100 empleados.** Crear cuenta directa y empresa, Integral, 3 centros → cotización $9,900. Cuarto centro → la vista previa muestra $10,049 antes de aceptar.
2. **Consultor, cinco empresas.** Cumplimiento canal, 100 empleados cada una → cartera $19,950, cinco líneas de $3,990. Un usuario de la empresa A abre la URL de B → 404.
3. **Suspensión puntual.** Suspender la empresa 3 con motivo → vista previa lista solo esa. Su encuesta abierta, su checador y su job encolado dejan de operar; las otras cuatro no cambian; su estado de cuenta emitido sigue debiéndose.
4. **Reactivación.** Mismo periodo, mismo consumo de Humi, misma deuda, sin prueba nueva.
5. **Consumo agotado.** Empresa con 9,000 tokens libres y dos mensajes simultáneos → uno responde, el otro 403; alertas de 90 y 100% enviadas una vez. Repetir el POST con la misma llave no cobra dos veces.
6. **Pago parcial.** SPEI del consultor por $7,980 aplicado a dos empresas → dos pagadas, tres con saldo. Registrar la misma referencia otra vez → rechazado.
7. **Transferencia.** Pasar la empresa 5 a contratación directa con fecha del siguiente corte → el consultor pierde sus membresías ese día, la empresa conserva historial, y cada periodo tiene un solo pagador.
8. **Banda.** Empresa de canal con 50 empleados y cupo 50: el alta 51 se detiene y muestra $2,490 → $3,990.
