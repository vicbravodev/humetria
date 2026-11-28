# Rediseño de la consola de administración: plan de construcción

Diseño: <https://claude.ai/artifact/S62HwE1En7zspjPhQPm8Ke> (15 tableros, datos de ejemplo).
Un solo PR, un commit por comportamiento (test + código), cada uno en verde. TDD estricto; tests acotados mientras se trabaja.

## Lo que ya existe (no se rehace)

- Backend de cobranza: `Billing/Actions` (`SummarizeReceivable`, `SearchAccounts`, `SendBillingNotices`, `RecordPayment`, `TransferCompany`…) y precio pactado en `Subscription` (`negotiated_price_cents`, `negotiated_valid_until`), cambios programados (`pending_*`).
- Empresas: `ActivateTenant`, `RestrictTenant`, `ReactivateTenant`, `CreateTenantFromDemo`, `GrantModule`/`RevokeModule`, `InviteOwner`.
- Entrar a una empresa: `ImpersonateOwnerController` y `impersonation-banner.tsx` (verificar qué pide hoy: motivo, vencimiento).
- Páginas: `tenancy/{overview,index,company,users,audit}`, `billing/{accounts,account,statement}`, `demos/index`.
- El catálogo de planes es **código versionado** (`Billing\Catalog`, enum `Plan`), no una tabla.

## Decisión que cambia el diseño

La pantalla «Planes y precios» queda **de solo lectura** (catálogo vigente, versión, quién tiene precio pactado y cuántos renuevan). Sin «Nuevo plan» ni «Editar plan»: cambiar el catálogo sigue siendo un cambio de código con su versión. Si Victor quiere editarlo desde la consola, es una fase aparte con migración del catálogo a base de datos.

## Ejecución: olas, carriles y subagentes

Reglas de todos los subagentes (Opus; Fable solo si algo resulta realmente difícil):
- Cada carril en su worktree y su base de datos de pruebas (`composer worktree:setup`), con rama desde el commit de la Ola 0.
- TDD estricto: test que falla por la razón correcta → código mínimo → verde. Nunca se cambia un test para que pase.
- Solo tests acotados (`--filter` o ruta), Pint, PHPStan y `npm run check`/`types:check` sobre lo tocado. Nunca la suite completa.
- Un commit por comportamiento, a nombre de Victor, sin atribución a Claude. No hacen push ni abren PR.
- Tocan solo los archivos de su carril. Si necesitan algo compartido (marco, `routes/*.php`, `nav-sections`), lo piden en su informe en lugar de editarlo.
- Informe final: commits, tests añadidos, qué quedó sin verificar.

```
Ola 0 (secuencial, yo)        →  commit 1: marco, navegación y piezas compartidas
        │
Ola 1 (paralela, 5 carriles)  →  A Empresas   B Cobranza   C Demos   D Equipo+Bitácora   E Operación
        │                        (cada carril es secuencial por dentro)
Ola 2 (secuencial, yo)        →  integrar ramas, luego 12 Avisos, 2 Hoy, 9 ⌘K, Humi, 14 cierre
```

### Ola 0 · secuencial (la hago yo)
Commit 1. Deja listas las piezas que luego reusan los carriles, para que no choquen: grupos de `nav-sections`, `components/humetria/` con pestañas con conteo, panel lateral, barra de acciones en lote y tarjeta de cifra, y el ancho completo de las páginas. Sin esto, cinco agentes inventan cinco versiones.

### Ola 1 · paralela, archivos disjuntos
| Carril | Commits, en este orden | Zona | Por qué es secuencial por dentro |
|---|---|---|---|
| **A · Empresas** | 3 Empresas → 4 Empresa 360 → 5 Entrar como soporte | `Tenancy`, `pages/tenancy/{index,company}`, `components/tenancy/` | Los tres editan `TenantConsoleController` y `company.tsx` |
| **B · Cobranza** | 6 Cobranza → 13 Planes (solo lectura) | `Billing`, `pages/billing/` | Los dos leen el catálogo y las cuentas |
| **C · Demos** | 7 Demos | `Demos`, `pages/demos/` | Un solo commit |
| **D · Equipo y Bitácora** | 8 Equipo → Bitácora | `UserConsoleController`, `AuditConsoleController`, `pages/tenancy/{users,audit}` | Comparten los permisos por rol |
| **E · Operación** | 10 Salud → 11 Mensajería | módulo nuevo para Salud; `Messaging` solo lectura | Comparten el estado de Meta y Resend |

Candados entre carriles: `routes/tenancy.php` lo tocan A y D (conflicto menor, lo resuelvo al integrar); el responsable de empresa (`owner_user_id`) y las notas internas son del carril A, y Hoy los lee después.

### Ola 2 · secuencial (yo, tras integrar)
1. Integrar las cinco ramas en esta rama, en el orden A, B, C, D, E; resolver rutas y migraciones; suite completa local antes de cualquier push.
2. 12 Avisos y mantenimiento (toca el marco de la empresa, igual que 5: va después para no chocar).
3. 2 Hoy: necesita lectores de A (pruebas, límites, sin entrar), B (saldo vencido), C (demos sin atender) y E (rechazos).
4. 9 Búsqueda ⌘K: necesita las búsquedas de A, B y D.
5. Humi y `ModuleCoverageTest` al corriente; 14 cierre: claro/oscuro, 375/768/1024/1350/2000 px, `visitPage()` y axe.

## Detalle de cada commit (referencia)

1. **Marco y navegación.** `nav-sections`: grupos Inicio / Clientes / Ventas / Plataforma; conteos en la barra (vencidas, demos nuevas); páginas a todo el ancho (quitar `max-w` en el selector de empresas). Tests: props de navegación por rol (soporte vs superadmin).
2. **Hoy.** Reemplaza `PlatformOverviewController`: cola «requiere atención» (saldo vencido, pruebas por vencer, demos sin atender > 48 h, sin entrar 30 días, empresas al 90 % de límites), cifras, ingreso mensual, embudo y actividad. Lector `Support/AttentionQueue` por módulo vía contratos (nada de joins entre módulos). `expectsDatabaseQueryCount`.
3. **Empresas como vista de trabajo.** Pestañas con conteo, columnas de uso/renovación/último acceso/responsable, vista rápida lateral, acciones en lote (asignar responsable, extender prueba, aviso). Migración: `owner_user_id` (responsable) en `tenants`. Todo paginado y buscado en servidor.
4. **Empresa 360.** Pestañas (resumen, módulos, uso, facturación, miembros, invitaciones, Humi, actividad), panel lateral, notas internas (tabla `tenant_notes`, con `tenant_id`, solo staff), zona de riesgo con motivo y escribir el nombre. Tests tenant A/B (404) y de rol.
5. **Entrar como soporte con motivo y vencimiento.** Motivo obligatorio, duración 30/60/120 min, franja con cuenta regresiva, revocación al vencer, `RecordAudit`. Nunca respuestas ni cuenta personal (`ForbidWhileImpersonating`).
6. **Cobranza.** Antigüedad de saldos (`SummarizeReceivable` por rangos), pestañas, acciones en lote (recordar, pago, promesa de pago) y cuenta con panel lateral. Migración: `promised_on` en estados de cuenta si Victor lo aprueba.
7. **Demos.** Embudo con responsable y siguiente paso; tiempo de primera respuesta; atajo a `CreateTenantFromDemo`.
8. **Equipo Humetría y Bitácora.** Equipo (roles, 2FA, entradas de soporte), invitar staff; bitácora con pestañas por tipo, detalle del cambio y exportación.
9. **Búsqueda global ⌘K.** Empresas, cuentas, personas con cuenta y acciones; respeta el rol; reusa `command-palette*`.
10. **Salud del sistema.** Lectura de Horizon, `schedule.heartbeat`, fallos de jobs, Resend/Meta; solo conteos y estados. Humi y `ModuleCoverageTest`: si la salud cambia lo que Humi sabe, eximir con razón o conectar lector.
11. **Mensajería.** Plantillas de WhatsApp, entregas, rechazos, consentimiento; sobre `Messaging`. Nunca contenido de mensajes.
12. **Avisos y mantenimiento.** Tabla `platform_announcements` (tipo, destino, inicio/fin), banner en el panel de la empresa y modo mantenimiento (solo lectura global). Correo por la `Notifications/` del módulo dueño.
13. **Planes y precios (solo lectura).** Catálogo vigente y precios pactados.
14. **Cierre.** Claro/oscuro y 375/768/1024/1350/2000 px en el navegador; `visitPage()` y axe por página nueva; `docs/` y Humi al corriente.

## Riesgos y preguntas para Victor

- Reglas propuestas, no existentes: solo lectura a los 45 días de saldo vencido (el diseño pide tu confirmación, no automático) y alertas de límites al 90 %.
- ¿Una solo PR con 14 commits es aceptable por tamaño? Se puede dejar 10–13 para el final y mergear si el CI sigue verde.
- Exportar o borrar todos los datos de una empresa (LFPDPPP) no está en el diseño final de commits: es su propia tarea con revisión legal.
- Notas internas y responsable son datos de staff: confirmar que soporte también los ve.
