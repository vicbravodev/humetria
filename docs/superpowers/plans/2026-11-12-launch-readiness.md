# Listos para lanzar: índice de planes (cierre de la auditoría de uso real)

> **Para quien ejecuta:** este archivo es el índice. Cada frente tiene su plan (`2026-11-12-launch-<frente>.md`) y se ejecuta con
> `superpowers:subagent-driven-development` o `superpowers:executing-plans`, en su propio worktree (`composer worktree:setup`) y su
> propia rama. Un frente = un PR. El PR se abre solo cuando Victor dice «crea el PR».

**Fuente:** auditoría de uso real del 12 nov 2026 (commit `5c9722e7`), 5 personas simuladas, 94 hallazgos con ID
(SA-01..19, H-01..15, HR-01..16, C-01..23, E-01..21) más la lista de gaps funcionales. Cada hallazgo se verificó contra el código
antes de planear (sección «Verificación»).

**Veredicto de la verificación:** 75 confirmados · 16 intencionales (documentados en spec o criterio) · 0 falsos completos
(6 sub-afirmaciones falsas) · 1 de entorno · 2 no verificables. Un hallazgo nuevo que la auditoría no vio: **N-01** (el portal
del trabajador da 404 en empresas sin NOM-037).

## Frentes

| # | Frente (plan) | Rama | Qué cierra | Tamaño | Riesgo si no se hace |
|---|---|---|---|---|---|
| F0 | [Infraestructura](2026-11-12-launch-infra.md) | `fix/launch-infra` | E-03, SA-02, E-06 | S | 502 en producción detrás de nginx |
| F1 | [Veracidad NOM-035](2026-11-12-launch-compliance-nom035.md) | `fix/launch-compliance-nom035` | H-01..H-07, H-09, H-10, H-12, H-13, H-15, E-13, E-20, C-20 (represalias), gaps de expediente, firma 7.7 y política por centro | L | El cliente enseña a la STPS un «Cumple» falso |
| F2 | [Veracidad Ley Silla y NOM-037](2026-11-12-launch-compliance-seating-telework.md) | `fix/launch-compliance-seating-telework` | C-01, C-02, C-06, C-07, C-08, C-10, C-11, C-12, C-16, C-18, C-20 (concordancia), C-21 | L | Igual que F1, en las otras dos normas |
| F3 | [Portal y encuesta del trabajador](2026-11-12-launch-worker-portal.md) | `fix/launch-worker-portal` | N-01, E-02, E-04, E-07, E-08, E-10, E-11, E-12, E-18, E-19, E-21, C-03, C-15, C-17, H-11, H-14 | L | El trabajador no llega a sus herramientas; Ley Silla con portada de NOM-035 |
| F4 | [Reglas de RH](2026-11-12-launch-hr-rules.md) | `fix/launch-hr-rules` | HR-01, HR-02, HR-04..HR-07, HR-09..HR-12, HR-14 (plurales), HR-15, HR-16, H-08, C-13, C-19, E-17a, gap de Panel operativo | L | Finiquito con fecha equivocada; salario bajo el mínimo |
| F5 | [Asistencia](2026-11-12-launch-attendance.md) | `fix/launch-attendance` | HR-03, E-01, HR-13, HR-14 (saludo y aviso), E-14, E-15 | M | Un centro donde nadie puede marcar; sin retardos |
| F6 | [Consola: correcciones](2026-11-12-launch-console-fixes.md) | `fix/launch-console-fixes` | SA-01, SA-06..SA-19, HR-08 (texto) | M | Asignar plan quita módulos sin avisar |
| F7 | [Consola: operar la plataforma](2026-11-12-launch-console-operations.md) | `feature/launch-console-operations` | SA-03, SA-04, SA-05, gaps de plataforma y de cliente | L | Operar exige terminal y base de datos |

No cabe en un solo PR: son ocho trabajos sin relación entre sí (CLAUDE.md: «nunca dos trabajos sin relación en el mismo PR»).
Orden por riesgo para el lanzamiento: F0 → F1 → F2 → F3 → F4 → F5 → F6 → F7.

## Dependencias y oleadas

```
Oleada 1   F0 infra ──┐
           F1 nom035 ─┼─ en paralelo (3 worktrees)
           F2 silla ──┘
Oleada 2   F3 portal ─┐
           F4 rh ─────┼─ en paralelo, con la oleada 1 ya en main
           F5 asist. ─┘
Oleada 3   F6 consola (correcciones) → F7 consola (operar)   en secuencia, mismo terreno
Oleada 4   Re-auditoría con las mismas 5 personas sobre main
```

- **F0 va primero dentro de la oleada 1** (medio día): toca `bootstrap/app.php` y `resources/js/app.tsx`, que F3 vuelve a tocar.
- **F3 espera a F2** porque los dos tocan Telework (F2 el panel, F3 `OpenPortal` y la portada del portal) y a F0 por `bootstrap/app.php`.
- **F7 espera a F6**: comparten `routes/tenancy.php`, `ResolveTenant.php`, `staff-navigation.ts` y las páginas de la consola.
- F4 y F5 no dependen de nadie; van en la oleada 2 solo para no pasar de 3 worktrees a la vez (una persona revisa).
- Dentro de cada frente el trabajo es **secuencial** (tareas pequeñas agrupadas; la suite completa solo al cierre).

### Qué toca cada frente (para no pisarse)

| Frente | Módulos backend | Frontend | Migraciones (prefijo reservado) |
|---|---|---|---|
| F0 | `bootstrap/app.php`, `App\Http\Middleware\SecurityHeaders` | `resources/js/app.tsx` | ninguna |
| F1 | Campaigns, Tenancy (`Support/Nom035*`, `RoutePage`), Disclosure (`Requirements`, `CompanyDisclosureStanding`), Results (`ReportBuilder`, `ReportDetailsRequest`), Interventions (`ActionRequest`) | `components/campaigns/*` (menos `survey/`), `components/results/*`, `components/interventions/*`, `components/disclosure/*`, `pages/disclosure-public/center.tsx`, `components/tenancy/work-center-card.tsx`, página nueva `pages/tenancy/nom035-dossier.tsx` | `2027_01_10_*` (campaigns) |
| F2 | Seating, Telework (todo menos `Actions/OpenPortal`, `PortalController` y notificaciones) | `components/seating/*`, `components/telework/*` (menos `portal/`), `components/humetria/inspection-*.tsx`, `components/tenancy/applies-seating.tsx`, `onboarding-center.tsx` | ninguna |
| F3 | Telework (`OpenPortal`, `PortalController`, `TokenPortalAccess`), Campaigns (`SurveyController`), Leave (`PortalVacationsPages`, `SendPortalLinks`), notificaciones al trabajador (una línea en 14 archivos), `App\Http\ErrorPage`, `bootstrap/app.php` (toast 429) | `components/campaigns/survey/*`, `components/engagement/survey/*`, `components/telework/portal/*`, `components/complaints-public/*`, `components/disclosure/workers-shell.tsx`, `components/leave/request-form.tsx`, `send-links-bar.tsx` | ninguna |
| F4 | Organization, Leave (panel de RH, listeners, `RecordTermination`), Compensation, Engagement, `App\Concerns\SearchesText`, Tenancy (`DashboardPage`, `PanelUrgencies`) | `components/organization/*`, `components/compensation/*`, `components/leave/*` (panel), `command-palette*.tsx`, `lib/palette-commands.ts`, `lib/mobile-tabs.ts`, `lib/option-search.ts` | `2027_01_14_*` (employees) |
| F5 | Attendance | `components/attendance/*`, `lib/attendance/*`, `hooks/use-zone-picker-map.ts` | `2027_01_16_*` (attendance) |
| F6 | Billing, Tenancy (consola, `ResolveTenant`, `Module`, `ModuleSource`, `InvitationEmail`, `LimitMessage`), Assistant, `App\Http\Impersonation` | `components/billing/*`, `components/tenancy/tenant*`, `pages/tenancy/index.tsx`, `company.tsx`, `components/assistant/humi-dock.tsx`, `lib/limits.ts` | ninguna |
| F7 | Tenancy (consola nueva), Demos, kernel (`App\Models\AuditEntry` solo lectura, `User`) | `pages/tenancy/*` nuevas, `components/demos/*`, `lib/staff-navigation.ts` | `2027_01_12_*` (demos, users) |

### Archivos compartidos peligrosos

| Archivo | Quién lo toca | Cómo se evita el conflicto |
|---|---|---|
| `bootstrap/app.php` | F0 (línea del middleware `Link`), F3 (toast 429) | F0 entra a main antes de que F3 empiece |
| `lang/es/validation.php` (`attributes`) | F1, F4, F5, F6, F7 | Cada frente agrega sus llaves en un solo commit al final de su bloque alfabético; el conflicto es de líneas vecinas y se resuelve al rebasar. Donde se pueda, `attributes()` en el FormRequest en vez de la llave global (F1 lo hace con `due_on`) |
| `tests/Unit/PaginatedListsTest.php`, `tests/Feature/PaginatedListsTest.php` | F1 (expediente), F3 (`sections` del portal), F6 (catálogos del SAT), F7 (bitácora, usuarios, plan) | Cada uno agrega su entrada con su razón, no reordena; F1 y F3 no coinciden en oleada, F6 y F7 van en secuencia |
| `resources/js/lib/app-navigation.ts` | F7 (enlace «Plan y uso») | Solo F7 |
| `tests/Feature/RouteSecurityTest.php` (lista pública) | Ninguno (decisión 4: sin autoservicio de recuperación) | Si la decisión 4 cambia, la agrega F3 con su razón |
| `routes/web.php` | Ninguno | Las rutas nuevas van en el archivo del módulo (`routes/tenancy.php`, `routes/demos.php`); no se crea `routes/platform.php` |
| `resources/js/lib/staff-navigation.ts`, `nav-sections.ts` | F7 | Solo F7 |
| `resources/js/lib/mobile-tabs.ts`, `command-palette*.tsx` | F4 | Solo F4 |
| `components/humetria/inspection-*.tsx`, `lib/route-stages` | F2 | F1 no los toca: su expediente nuevo cambia solo `dossierHref` en `RoutePage.php` |
| `app/Modules/Tenancy/Http/Middleware/ResolveTenant.php` | F6 (solo lectura, impersonación), F7 (prop de plan) | En secuencia |
| `resources/views/vendor/notifications/email.blade.php` | F3 | Solo F3 |
| `docs/normas/nom-035/criterios.md` | F1 (C-57 nuevo) | Solo F1 |
| Migraciones | F1, F4, F5, F7 | Prefijos reservados arriba; tablas distintas |
| Rutas generadas de Wayfinder (`resources/js/actions`, `routes`) | Todos los que agregan rutas | No se commitean a mano: se regeneran en cada rama |

## Verificación de los hallazgos

Estados: **C** confirmado · **I** intencional (con cita) · **F** falso · **E** entorno · **NV** no verificable.
«Parcial» indica qué parte del hallazgo no se sostiene.

### Matriz de trazabilidad (todos los ID)

| ID | Estado | Frente · tarea | Nota |
|---|---|---|---|
| SA-01 | C | F6 · T1 | El comportamiento es el del spec de monetización §4.2; el texto del diálogo miente |
| SA-02 | C | F0 · T2 | Horizon compila su plantilla con `new Function`: necesita `'unsafe-eval'` solo en `/horizon` |
| SA-03 | C | F7 · T1 | |
| SA-04 | C | F7 · T2 | Crear el primer superadmin por CLI es intencional (CLAUDE.md) y se queda |
| SA-05 | C | F7 · T3 | |
| SA-06 | C | F6 · T2 | |
| SA-07 | I | F6 · T3 | Spec invite-owner «Cambiar de owner»: el anterior pierde acceso. Se agrega el aviso en el diálogo y un correo |
| SA-08 | C | F6 · T6 | El 403 es intencional (spec Humi «Cuotas y costo»); el dock visible no |
| SA-09 | C (parcial) | F6 · T4 | Solo el denominador; «0 de 2» fue una captura anterior al alta de centros |
| SA-10 | C | F6 · T4 | |
| SA-11 | C (parcial) | F6 · T5 | El aviso de tope sí sale antes del formulario; falta desactivar el botón y un contacto |
| SA-12 | I | F6 · T7 | Spec company-url, decisiones 3 y 4. Decisión 14: acotar a la empresa |
| SA-13 | C | F6 · T5 | |
| SA-14 | C | F6 · T2 | La tabla se desplaza, no se corta; se ocultan columnas en celular |
| SA-15 | C | F6 · T8 | |
| SA-16 | C | F6 · T8 | |
| SA-17 | C | F6 · T8 | |
| SA-18 | C | F6 · T8 | |
| SA-19 | C | F6 · T8 | |
| H-01 | C | F1 · T1, T2, T3 | Decisión 1 (criterio de «vigente») |
| H-02 | C | F1 · T4 | Contradice C-52; el spec omitió «entregado» en D7 por descuido |
| H-03 | C (parcial) | F1 · T5 | «Completo / Solo lo que tienes» sí filtra (`?ready=1`). Decisión 2 |
| H-04 | C | F1 · T6 | Además cubre C-03 de `criterios.md` (recordar plantilla completa), que no estaba en la UI |
| H-05 | C | F1 · T7 | |
| H-06 | C | F1 · T8 | |
| H-07 | C (parcial) | F1 · T9 | El servidor sí hereda el responsable; el campo se ve vacío |
| H-08 | C | F4 · T4 | Decisión 34 |
| H-09 | C | F1 · T10 | |
| H-10 | C | F1 · T10 | |
| H-11 | C | F3 · T7 | |
| H-12 | C | F1 · T9 | |
| H-13 | C | F1 · T10 | Es el mismo problema que E-13 |
| H-14 | I | F3 · T7 | Spec campañas «Respuestas»: avance solo en `localStorage`, se borra al enviar. Solo se agrega un aviso de equipo compartido |
| H-15 | C | F1 · T10 | |
| HR-01 | C | F4 · T1 | Decisión 7 |
| HR-02 | C | F4 · T2 | Decisión 6 |
| HR-03 | C | F5 · T2, T3 | El spec de asistencia lista «tolerancias» y nunca se construyó. Decisión 5 |
| HR-04 | C | F4 · T6 | |
| HR-05 | C | F4 · T3 | Lo anterior a `tracking_since` es intencional (C-21 de vacaciones) |
| HR-06 | I | F4 · T8 | Dos fuentes por diseño (spec calculadora, objetivo 2). Solo aviso. Decisión 36 |
| HR-07 | C | F4 · T8 | Está en Compensación, no en Vacaciones |
| HR-08 | I | F6 · T6 | Spec Humi «Objetivo» y «Fuera de alcance». Solo se corrige la respuesta. Decisión 8 |
| HR-09 | C (parcial) | F4 · T3 | Sí avisa a quien pide y a quien aprueba; el botón «Aprobar» de RH no debería salir |
| HR-10 | C | F4 · T7 | |
| HR-11 | C | F4 · T5 | |
| HR-12 | C (parcial) | F4 · T4 | «Un error por fila» es falso (el archivo de prueba traía uno por fila); falta el diff |
| HR-13 | C | F5 · T4 | |
| HR-14 | C | F4 · T9 (plurales) · F5 · T5 (saludo, aviso) · F3 · T6 (usted en correos) | |
| HR-15 | I | F4 · T8 | `ensure()` es idempotente y está documentado; se corrige el conteo y la etiqueta |
| HR-16 | I | F4 · T9 | Diseño V4-Menu-Roles, anterior a los módulos de operación. Se agrega la pestaña «Operación» |
| C-01 | C | F2 · T1, T2 | |
| C-02 | C | F2 · T3 | Contradice el propio docblock de `RouteStages` |
| C-03 | C | F3 · T4 | |
| C-04 | I | No se hace | NOM-037 C-19 (decisión A3) y C-20: se publica en cada centro en uso y el acuse es de todo el centro |
| C-05 | I | No se hace (decisión 19) | Diseño NOM-037, decisión del usuario 10; criterio C-17 |
| C-06 | C (parcial) | F2 · T5 | Validar con desfavorables es por diseño (C-11, C-12); falta la observación obligatoria |
| C-07 | C | F2 · T5 | |
| C-08 | I | F2 · T5 | C-24: quien nunca se capacitó está en plazo 12 meses. Solo texto |
| C-09 | I | No se hace | Diseño Ley Silla, decisión 5 del diseñador; C-18. La pantalla ya lo avisa |
| C-10 | C | F2 · T6 | |
| C-11 | C | F2 · T4 | |
| C-12 | C | F2 · T4 | |
| C-13 | NV | F4 · T10 | Probable dato sembrado sin voltear invitaciones. Se agrega el test que falta y el cast |
| C-14 | I | No se hace (decisión 21) | Diseño clima §6: encuestas por empresa; acotar audiencia erosiona el anonimato |
| C-15 | C | F3 · T8 | El ruteo es por rol; la promesa se reescribe |
| C-16 | C | F2 · T6 | |
| C-17 | C | F3 · T4 | |
| C-18 | C | F2 · T6 | |
| C-19 | C | F4 · T10 | |
| C-20 | C (parcial) | F1 · T10 (represalias) · F2 · T5 (concordancia) | Usted/tú no se pudo ubicar en una pantalla concreta; lo cubre F3 · T6 |
| C-21 | C | F2 · T6 | |
| C-22 | NV | No se hace | El reporte no dice qué pantalla; las migas revisadas son coherentes. Se revisa en la re-auditoría |
| C-23 | C | No se hace antes de lanzar (decisión 22) | |
| E-01 | C | F5 · T1 | RH sí ve el aviso en Resumen y Centro; falta al enviar enlaces y en el mensaje al trabajador |
| E-02 | C | F3 · T2 | Limitación documentada en el spec del portal de política |
| E-03 | C | F0 · T1 | |
| E-04 | C | F3 · T5 | |
| E-05 | I | No se hace | Spec campañas «Respuestas» y C-18: el servidor nunca recibe un cuestionario parcial |
| E-06 | C | F0 · T3 | |
| E-07 | C (parcial) | F3 · T5 | C-07 y C-28 ya prevén explicar antes y canalizar; falta una línea al terminar |
| E-08 | C (parcial) | F3 · T3 | Saldos sí tiene buscador. Decisión 4 |
| E-09 | I | No se hace (decisión 18) | Spec organigrama, «Permisos» y decisión 12 |
| E-10 | C | F3 · T9 | |
| E-11 | C | F3 · T9 | |
| E-12 | C | F3 · T6 | |
| E-13 | C | F1 · T10 | Igual que H-13 |
| E-14 | I | F5 · T5 | La página no sabe quién es hasta la firma (spec asistencia). Solo texto |
| E-15 | C | F5 · T5 | |
| E-16 | E | No se hace | `SaveLeaveType` ya rechaza el duplicado (commit `2fe74d05`); la fila del demo es anterior |
| E-17 | C (parcial) | F4 · T10 | 17a (FormRequest sin `authorize`) confirmado; 17b es falso: el parámetro es `?center=` y da 404 |
| E-18 | I | F3 · T8 | Spec quejas: la clave no se recupera ni vive en el navegador. Se agrega la frase que el spec pide y «Compartir». Decisión 23 |
| E-19 | C | F3 · T8 | |
| E-20 | C | F1 · T10 | |
| E-21 | C | F3 · T2 | La separación es intencional; falta el puente |
| N-01 | C (nuevo) | F3 · T1 | `OpenPortal` rechaza el enlace si la empresa no tiene NOM-037: 404 en Vacaciones, Ley Silla, Política y Clima |

### Gaps funcionales

| Gap | Estado | Frente · tarea | Decisión |
|---|---|---|---|
| Usuarios de plataforma (lista, búsqueda, reset, 2FA) | C | F7 · T2 | 12 |
| Visor de bitácora | C | F7 · T1 | 11 |
| Pipeline de demos | C | F7 · T3 | 13 |
| Métricas (MRR, uso, último acceso) | C | F7 · T6 | 10 |
| Catálogo de planes editable | I | No se hace | 9 · spec monetización: el catálogo es versionado en `config/billing.php` |
| Aviso al owner por suspensión o módulos | C | F7 · T4 | 16 |
| Solicitud de ampliación | C | F6 · T5 | 17 |
| El cliente no ve su plan ni sus límites | C | F7 · T5 | 15 · sin estados de cuenta |
| Exportar empresas | C (parcial) | No se hace | El CSV de cartera ya lista las empresas con cuenta |
| Cambiar roles dentro de una empresa sin impersonar | I | No se hace | Spec company-url, objetivo 2 |
| Baja con fecha efectiva y motivo | C | F4 · T1 | 7 |
| Ficha del empleado con historial | C | No se hace antes de lanzar | 25 |
| Retardos y faltas | C | F5 · T2, T3 (retardos) | 5 · las faltas automáticas no |
| Búsqueda de personas | C | F4 · T5, T6 | |
| Centro de notificaciones | C | No se hace antes de lanzar | 26 |
| Panel operativo | C | F4 · T11 (mínimo) | 27 |
| Humi con lectura de datos | I | No se hace antes de lanzar | 8 |
| Un solo portal del trabajador | C | F3 · T1, T2 | 3 |
| Recuperar el enlace por sí mismo | C | F3 · T3 (por RH) | 4 |
| Orientación tras la Guía I | C | F3 · T5 | 24 |
| Aviso cuando el centro no puede marcar | C | F5 · T1 | |
| Expediente de inspección real (NOM-035) | C | F1 · T5 | 2 |
| Firma y fecha en el informe 7.7 | C | F1 · T8 | 32 |
| Regla de completitud de la evaluación | C | F1 · T1 | 1 |
| Política 5.1 por centro | I (parcial) | F1 · T10 | Publicar ya elige centros; falta preseleccionar el centro de `?center=` |
| Aviso de periodicidad 7.9 | F | No se hace | Ya existe: `RemindNextEvaluations`, diario 08:15, 60 días antes |
| Comisión mixta con integrantes sin cuenta | I | No se hace antes de lanzar | 19 |
| Audiencia acotada y piloto en clima y pulso | I | No se hace | 21 |
| Registro de incidentes de desconexión | C (ausente) | No se hace | 28 · fuera de la tabla 10.3 de la NOM-037 |
| `/workers` solo NOM-035 | I | No se hace | 29 · spec del portal de política: «hoy no se hace» |
| Tiempos de atención de quejas | C (parcial) | No se hace antes de lanzar | 30 · el spec pide conteos, no tiempos |

Nada queda fuera de esta matriz. Lo marcado «No se hace» lleva su razón aquí o en la decisión citada.

## Decisiones abiertas

Cada plan está escrito con la opción **recomendada**. Las marcadas ⚠️ cambian el alcance de forma importante si se elige la alternativa.

| # | Decisión | Recomendación (lo planeado) | Alternativas y costo |
|---|---|---|---|
| 1 ⚠️ | ¿Cuándo una evaluación NOM-035 cerrada con baja participación cuenta como vigente? | No se bloquea el cierre. Cerrar con faltantes exige confirmar; la evaluación queda vigente pero la ruta, la inspección, la tarjeta del centro y el método del informe dicen «contestaron X de Y». Se escribe como criterio C-57 | Umbral (p. ej. menos de 80 % = «incompleta», no vigente): +M, toca `Nom035Stages`, `CompanyCampaignRecords::cycle()` y `CompanyEvaluationSchedule`; la norma no fija un número, así que el umbral sería nuestro |
| 2 ⚠️ | Expediente NOM-035 | Página imprimible por centro, ordenada como la tabla de inspección, con el estado y el detalle de cada renglón y el enlace a cada evidencia imprimible (informe, canalización, Programa, difusión) (M) | a) Solo renombrar la tarjeta a «Expediente de difusión» (S). b) Un solo PDF con todo, incluido el registro 5.8 c con nombres: +L, dos contratos nuevos (Results, Responses) |
| 3 ⚠️ | Portal único del trabajador | La portada de `/portal/{token}` se vuelve neutra y lista las herramientas que la empresa tiene; se quita el requisito de NOM-037 (M) | Mover `PortalAccess` a Organization y rehacer el portal con menú: L, después del lanzamiento |
| 4 | Recuperar el enlace del portal | RH lo reenvía por persona desde Empleados y desde Saldos (etiqueta corregida). Sin autoservicio | Autoservicio `POST /portal/recover` por correo con respuesta uniforme y throttle: +M, ruta pública nueva, hay que decidir cómo se resuelve la empresa |
| 5 ⚠️ | Retardos y tolerancia | Hora de entrada opcional por día y una tolerancia por empresa; el retardo es informativo en la semana y en el reporte | Faltas automáticas, acumulados y reglas de descuento: +L, y entra a terreno de nómina |
| 6 | Salario bajo el mínimo | Aviso que no bloquea, contra el salario mínimo general vigente, mencionando la zona fronteriza | Bloquear: rompe jornadas reducidas. Zona por centro de trabajo: +M |
| 7 | Baja de una persona | Fecha efectiva obligatoria (hoy o anterior, por omisión hoy) y motivo de un catálogo corto; el finiquito de vacaciones usa esa fecha | Bajas programadas a futuro: +M (job diario, estados intermedios) |
| 8 ⚠️ | Humi con lectura de datos operativos | No antes de lanzar. Se corrige la respuesta para que mande a la pantalla de Humetria y no a «el sistema de tu empresa» | Dos herramientas de lectura (vacaciones y asistencia) por contratos: +M/L y más costo de IA por empresa |
| 9 | Catálogo de planes editable | No: sigue en `config/billing.php` (spec de monetización) | CRUD de planes con versiones: L |
| 10 | Métricas de plataforma | Página «Resumen» mínima: empresas por estado, ingreso mensual contratado, demos por estado y último acceso por empresa (columna `last_login_at`). Es la última tarea de F7 y se puede cortar | Nada (usar el CSV de cartera): 0. Tablero con series en el tiempo: L |
| 11 | Visor de bitácora | Página de plataforma, paginada, con filtros por evento, empresa y fecha, solo superadmin; pestaña «Bitácora» en la ficha de la empresa | Visor para el owner de cada empresa: +M, después |
| 12 | Usuarios de plataforma | Buscar usuarios; enviar enlace de restablecimiento; quitar 2FA (auditado); dar y quitar soporte. Crear superadmin sigue por CLI | Alta de superadmin por UI: +S, más superficie de ataque |
| 13 | Demos | Estado (nueva, contactada, convertida, descartada), nota interna, «Crear empresa» prellenado y correo a los superadmins al llegar una | Pipeline con responsable y recordatorios: +M |
| 14 | Impersonación de un consultor | Acotar la sesión a la empresa desde la que se impersonó (otras dan 404) | Dejarlo como dice el spec: 0 |
| 15 | El cliente ve su plan | Página de solo lectura «Plan y uso» para el owner: plan, herramientas, límites y uso | Con estados de cuenta y pagos: +L |
| 16 | Aviso al owner | Correo al suspender, poner en solo lectura, reactivar y al quitar herramientas | Solo aviso dentro de la app: S, pero no lo ve quien ya no puede entrar |
| 17 | Contacto de soporte | `config('humetria.support_email')` y enlace `mailto:` en los avisos de tope, solo lectura y bloqueo | Formulario de solicitud de ampliación: +M |
| 18 | El viewer exporta el organigrama | Se queda (spec: el viewer ve nombre y puesto) | Pedir `manage-org-chart` para exportar: S |
| 19 ⚠️ | Comisión con integrantes sin cuenta | Después del lanzamiento | Tabla `committee_members` con `user_id` opcional: M/L, toca Telework y Seating |
| 20 | Política NOM-037 por centro | No (criterio C-19, decisión A3) | `work_center_ids` opcional al publicar: M |
| 21 | Audiencia acotada o piloto en clima y pulso | No | Por centro con mínimo reforzado: M, riesgo de anonimato |
| 22 | Encuesta sin cookies de sesión (C-23) | Después del lanzamiento. El modelo de amenaza excluye al operador de la base, y cambiar el flujo de errores de la mejor pantalla del producto antes de lanzar es riesgo sin beneficio para RH | Hacerlo ahora como quejas y `/workers`: M |
| 23 | Clave de la queja | Agregar la frase que el spec pide y un botón «Compartir» (`navigator.share`), sin guardar nada | Descargar un `.txt`: contradice el spec (la clave no vive en el dispositivo) |
| 24 | Orientación tras la Guía I | Una línea fija para todas las personas, sin revelar el resultado | Mensaje distinto a quien requiere valoración: revela el resultado en pantalla |
| 25 | Ficha del empleado | Después del lanzamiento; ⌘K lleva a Empleados filtrado | Página `employees/{employee}` con datos, jefe e historial: M |
| 26 | Centro de notificaciones | Después del lanzamiento | Canal `database` y campana: L |
| 27 | Panel operativo | Una urgencia nueva en el Panel: solicitudes de vacaciones por responder | Panel propio de operación: M |
| 28 | Incidentes de desconexión | No (fuera de la tabla 10.3) | Nuevo tipo de aviso: M |
| 29 | `/workers` con NOM-037 y Ley Silla | No (spec) | M |
| 30 | Tiempos de atención de quejas | Después del lanzamiento | Mediana sobre casos cerrados con mínimo de grupo: M |
| 31 | Régimen fiscal y uso de CFDI | `Select` con los catálogos del SAT como constantes | Tabla de catálogos: M |
| 32 | Informe 7.7 | Fecha de emisión y línea de firma solo en la impresión | Firma electrónica: fuera de alcance |
| 33 | Recordatorios seguidos | Bloqueo de 12 horas (`config('campaigns.resend_cooldown_hours')`) y «último envío hace X» | Solo confirmación extra: S |
| 34 | `genero=M` en el CSV | Se rechaza por ambiguo con un error que dice qué escribir | Tomarlo como Masculino: cambia en silencio archivos que hoy importan como Mujer |
| 35 | Dependencias nuevas | **Ninguna** en los ocho planes | — |
| 36 | Prima vacacional en Compensación | Aviso cuando la política de Vacaciones difiere, sin acoplar el cálculo | Contrato `Leave` → `Compensation`: M |

## Criterios de salida para lanzar

1. F0 a F6 en `main` con el job `ci` en verde. F7 puede lanzarse hasta su tarea 4; las tareas 5 y 6 pueden entrar la semana siguiente.
2. `composer ci:check` en verde sobre `main` después del último merge.
3. Ninguna pantalla de las tres normas muestra «Cumple» o «Hecho» para un requisito sin registros: lo fijan los tests de F1 y F2.
4. Encabezados de respuesta por debajo de 4 KB en Panel, encuesta y portal (test de F0) y una prueba manual detrás de nginx con buffers por defecto.
5. Cada pantalla tocada revisada en claro, oscuro y 375 px contra su pantalla de referencia.
6. **Re-auditoría de usabilidad** con las mismas 5 personas y el mismo brief (`ux/BRIEF.md`), sobre una base recién sembrada
   (`demo-nom035`), con la cola en Redis y el build de producción. Se pide a cada persona que repita los pasos de sus hallazgos Altos.
   Sale bien si: 0 hallazgos Críticos, 0 Altos, y ningún Medio repetido de esta lista salvo los marcados «No se hace».
7. Las decisiones 1, 2, 3, 5 y 8 respondidas por Victor (por omisión, la recomendación).
8. La mutación no es criterio de salida: corre el domingo siguiente; las clases nuevas declaran `mutates()`.

## Notas de entorno (no son trabajo de producto)

- El `.env` local tenía `QUEUE_CONNECTION=database`; quedó en `redis`. Hay 67 jobs viejos en la tabla `jobs`.
- Los datos `QA-*` de la auditoría siguen en `demo-nom035`; la re-auditoría parte de una base nueva.
- E-16 es una fila anterior a la regla que ya existe.
