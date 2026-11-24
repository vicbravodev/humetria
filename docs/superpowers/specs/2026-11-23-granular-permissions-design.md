# Permisos granulares por empresa: diseño

Estado: implementado en la rama `claude/granular-permissions-system-c2dc19` (2026-11-24), sin PR; las decisiones que
quedan de Victor están al final («Pendiente de Victor»). Fecha del diseño: 2026-11-23.

## Intención

Hoy cada persona tiene un solo rol fijo por empresa (`TenantRole`: owner, hr, viewer, committee, complaints). Para un SaaS
eso es poco: cada empresa reparte el trabajo distinto. El dueño de la empresa debe poder **usar los roles de siempre como
plantillas, crear los suyos y darle permisos sueltos a una persona**, desde un panel.

Los permisos ya existen (unos 30, p. ej. `manage-vacations`, `view-attendance`, `manage-members`), cada módulo los consulta
con `Gate`/`can()` y Spatie los resuelve por rol dentro del *team* de la empresa. Lo que falta es el panel y dejar de
depender de que el rol sea un enum.

## Metas

- El owner crea, edita y borra roles propios de su empresa, y los asigna.
- El owner da permisos adicionales a una persona, además de los de su rol.
- Los 5 roles actuales siguen existiendo como plantillas del sistema.
- Una sola forma de limitar a alguien: permisos (se acaba la lista de rutas de `LimitRestrictedRoles`).

## No metas

- Quitar a una persona un permiso que su rol sí trae (solo se suma; si necesita menos, otro rol).
- Delegar `manage-members` ni nada que dependa de ser owner.
- Permisos por centro de trabajo o por área (hoy nada es por centro; sigue igual).
- Roles de plataforma (superadmin, soporte): no se tocan.

## Antiobjetivos (aunque «funcione», es un fracaso)

- Un permiso dado en la empresa A que valga en la B (fuga entre tenants).
- Un rol propio que dé acceso a una página que nadie protegió (ver «Cobertura»).
- Que dar un permiso sensible (resultados, Guía I, quejas) quede sin advertencia ni bitácora.
- Romper el anonimato: ningún permiso nuevo abre respuestas individuales.

## Decisiones (confirmadas por Victor el 2026-11-23)

1. **Se suma, no se resta.** Permisos de la persona = permisos de su rol + los suyos.
2. **Solo el owner administra** roles y permisos. `manage-members` no se delega.
3. **Fuera del panel:** ser owner, facturación y lo que depende del plan o de los módulos contratados. Si el módulo no está
   contratado, el permiso aparece apagado con la razón.
4. **`committee` y `complaints` pasan a ser roles normales por permisos.** `LimitRestrictedRoles` se reescribe para limitar
   por permisos, no por rutas.
5. **Los permisos sensibles** (ver resultados, Guía I, quejas) **llevan advertencia al darse** y van a la bitácora.
6. **Los 5 roles actuales son plantillas del sistema**: no se editan, se copian y se ajustan. Lo que una migración futura
   agregue a una plantilla no llega a las copias.

## Enfoques considerados

- **A (elegido): roles de empresa en Spatie.** Los roles propios son filas de `roles` con `tenant_id`; los permisos de una
  persona, filas de `model_has_permissions` con `tenant_id`. `TenantRole` queda solo como plantillas.
  Casi no cambian los `Gate`. Costo: la UI muestra el rol efectivo, no el enum.
- **B: tablas propias.** Ignora Spatie (ya instalado, regla del proyecto) y cambia todo. Descartado.
- **C: solo permisos por persona.** Rápido, pero no escala a empresas de decenas de personas. Descartado.

## Lo que ya soporta la base (verificado)

- `roles` tiene `tenant_id` y el índice único `(tenant_id, name, guard_name)`; `model_has_roles` y `model_has_permissions`
  también llevan `tenant_id` (migración `2026_10_29_300000`). Un permiso directo ya es por empresa.
- Las migraciones de permisos nuevas reparten a roles con `tenant_id` nulo: llegan solo a las plantillas, coherente con (6).
- Uso de `TenantRole` fuera de su enum: unos 14 archivos de `app/` (no cientos; los 378 contaban tests y JS). Los más
  importantes: `CompanyRoles`, `RoleMatrix`, `LimitRestrictedRoles`, `ChangeMemberRole`, `MemberController`, `AcceptInvitation`,
  `InviteMemberRequest`, `Tenant` (relación del owner), `HomeController` (a dónde llega cada quien), `TenantInvitation`
  (cast de `role`) y `MemberSummary::$role`.

## Diseño

### Catálogo de permisos

Hoy los permisos viven solo como filas sembradas por migraciones. Se crea un catálogo en PHP (`Tenancy/Support`, una lista de
`PermissionDefinition`: nombre, etiqueta es-MX, módulo, descripción, `sensitive`, y su condición: módulo contratado y tipos de
cuenta donde aplica). El panel lo lee. Dos pruebas lo atan: cada fila de `permissions` está en el catálogo y viceversa
(un permiso nuevo sin entrada rompe CI); y el catálogo filtra por tipo de cuenta (una union no ve permisos de cumplimiento,
igual que hoy `LimitUnionToCompensation` y `assignableRoles()`).

Permisos de plataforma y los reservados al owner (`manage-members`, privacidad/mínimo por grupo, plan) quedan marcados
`ownerOnly`: no aparecen como interruptor.

### Roles propios

- Una fila de `roles` con `tenant_id` de la empresa, **nombre interno generado** (p. ej. `custom_<uuid>`) y una columna nueva
  `label` (es-MX, lo que escribe el owner). Así un rol llamado «owner» o «hr» jamás choca con las plantillas (Spatie busca por
  nombre en el team **o** en el global).
- Nueva columna `based_on` (nullable; clave de la plantilla de origen) solo informativa.
- El owner crea desde una plantilla o en blanco, edita permisos, renombra, borra. No se puede borrar un rol con personas
  asignadas (se piden reasignar primero). Límite de roles por empresa: 20 (configurable en `config/`, decisión mía).
- El rol de la persona sigue siendo **uno solo** por empresa (`syncRoles` con un rol, como hoy).

### Permisos directos por persona

`givePermissionTo` dentro del team de la empresa. `CompanyRoles` gana `grant`/`revoke`/`directPermissionsOf`. Al quitar a la
persona de la empresa o cambiar a un rol de otro tipo de cuenta, se limpian sus permisos directos de esa empresa.

### Cobertura: ninguna ruta sin permiso

Hoy owner/hr/viewer ven todo por omisión y solo los restringidos tienen lista de rutas. Al pasar a permisos, un rol propio con
pocos permisos **abriría** toda página sin `Gate`. Por eso:

- Se mide: cada ruta `tenant.*` debe exigir al menos un permiso. Una prueba estilo `RouteSecurityTest` lo hace cumplir
  (con una lista de excepciones, cada una con su razón: p. ej. cuenta personal, selector de empresa).
- Páginas hoy sin permiso propio (revisadas en `routes/company.php` y sus controladores): el tablero (`tenant.dashboard`),
  centros de trabajo (lectura), «¿te aplica?» y rutas/expediente NOM-035 (`tenant.nom035.*`, `tenant.work-centers.route`),
  `tenant.plan`. Cada una recibe un permiso: `view-dashboard` (todos los roles plantilla lo traen; el tablero solo muestra
  tarjetas de lo que la persona puede ver), `view-work-centers`, `view-nom035-route`; `plan` y `privacy` quedan `ownerOnly`.
  **Propuesta mía:** las plantillas existentes reciben exactamente lo que hoy ven, para que nadie pierda ni gane acceso.
- Adonde llega alguien con pocos permisos: `startRoute()` pasa de depender del rol a «la primera herramienta cuyo permiso
  `view-*`/`manage-*` tenga». Si no tiene ninguno, una página «Aún no tienes acceso a nada» con a quién pedirlo.
- `LimitRestrictedRoles` se reemplaza por un middleware que exige el permiso de la ruta (declarado junto a la ruta o por
  `Gate` del controlador; el detalle lo decide el plan, pero la prueba de cobertura es obligatoria).

### Reglas que hoy dependen del rol y se reexpresan

- **Conflicto en quejas** (`EndAssignmentsOnRoleConflict`, criterio C-45): hoy, quien pasa a `hr` u `owner` deja de atender
  quejas contra RH o la dirección. Se reexpresa por permisos: tener `manage-organization` equivale a «es RH» (queja contra RH)
  y ser owner a «es dirección». `MemberRoleChanged` pasa a llevar el rol efectivo (id/clave) **y** se emite también cuando
  cambian los permisos directos (`MemberPermissionsChanged`, mismo listener). *Esta equivalencia es decisión mía: confírmala.*
- **Owner:** se mantiene como plantilla especial, único por empresa (los índices únicos actuales siguen). No se copia, no se
  asigna desde el panel de roles; se cambia como hoy.
- **Tipo de cuenta:** `TenantType::allows($role)` pasa a «el rol (plantilla o propio) solo contiene permisos que el tipo de
  cuenta admite». Una union solo puede tener owner y roles con permisos de compensación.
- **Invitaciones:** `TenantInvitation.role` pasa a referenciar el rol (id) y puede traer permisos extra. El texto del correo
  usa la etiqueta del rol.
- **`RoleMatrix`** («Qué puede hacer cada quien») se genera del catálogo y los roles reales de la empresa, no de una constante.

### Pantallas (parten de `members` e `invite-member`, mismo marco)

- **Roles** (`/admin/{tenant}/roles`): lista (plantillas del sistema con candado + roles propios, conteo de personas). Editor:
  permisos agrupados por módulo, interruptores, advertencia en los sensibles, razón en los apagados por plan.
- **Miembros:** el selector de rol incluye los propios; sección «Permisos adicionales» con los mismos interruptores y el
  resultado efectivo (rol + extras, con de dónde viene cada permiso).
- Estados vacíos con siguiente paso; claro/oscuro/375 px; prueba de humo `visitPage()` por cada página nueva.

### Privacidad, bitácora y Humi

- Dar o quitar un permiso, crear/editar/borrar un rol y asignar escriben log (`tenant_role.created`, `tenant_member.permissions_changed`…)
  y `RecordAudit`; los sensibles marcan `sensitive` en la bitácora. Nada de respuestas ni personas ligadas a respuestas.
- Los permisos sensibles lo son por catálogo (`view-trauma-screenings`, `handle-complaints`, resultados individuales, etc.).
- Humi: sabe qué puede hacer la persona que pregunta y cuántos roles propios hay (conteos), nunca quién tiene qué en detalle
  más allá de lo que ya ve el owner. Conexión por contrato público y criterios en `docs/normas`/README del módulo, mismo PR
  (`ModuleCoverageTest`).

### Migración de datos

Sin clientes aún (memoria `humetria-no-customers-yet`): se permite cambio rompedor. Una migración: agrega `label`/`based_on`
a `roles`, siembra los permisos nuevos de cobertura en las plantillas con lo que hoy ven, y no toca asignaciones existentes.

## Pruebas (TDD, acotadas mientras se trabaja)

Feature: crear/editar/borrar rol propio (camino feliz, nombre que choca con «owner», rol con personas, otro tenant → 404,
no-owner → 403); permiso directo suma al del rol, no quita; la empresa B no ve el permiso dado en A (por consulta y por URL);
cobertura de rutas `tenant.*`; destino de aterrizaje; conflicto de quejas por permisos; union solo ve permisos de
compensación; catálogo ↔ tabla `permissions`; sensibles escriben bitácora; props de Inertia sin respuestas individuales.
`expectsDatabaseQueryCount` en miembros y roles. Browser: humo de roles y de miembros; un flujo largo crítico (crear rol → asignar → la
persona entra y ve solo lo suyo).

## Lo que queda al constructor

Nombres exactos de clases y rutas, forma de declarar el permiso de cada ruta, textos finos de UI, el límite de roles por
empresa, y el orden de los commits (un comportamiento por commit).

## Decisiones del constructor (tras la revisión, mías)

- **Quién manda en cada capa.** El permiso (rol + extras) es una capa; el módulo contratado y el tipo de cuenta son otra, y ya
  existen (`LimitToEnabledModules`, `LimitUnionToCompensation`). Ninguna sustituye a la otra: un rol con `manage-vacations`
  sigue recibiendo 404/403 si Leave no está contratado. Un permiso guardado de un módulo apagado se ignora al evaluar y se
  muestra apagado en el panel. El owner sigue pasando por su plantilla (es un rol de Spatie, no una excepción de `Gate::before`,
  que es solo del superadmin).
- **Cobertura por ruta, no por controlador.** Cada ruta `tenant.*` declara su permiso como middleware (`can:`) en su archivo
  de rutas; el `Gate` de FormRequests/controladores se queda como segunda barrera. Las rutas que hoy solo protege el FormRequest
  se reajustan en este mismo PR: es la parte más grande del trabajo y por eso la prueba de cobertura es obligatoria. GET exige
  `view-*` y escribir exige `manage-*`.
- **Plantillas sin pérdida de acceso.** Una prueba compara, para viewer/committee/complaints, las rutas que `allowedRoutes()`
  permite hoy contra las que abre su juego de permisos nuevo; se corre antes de borrar `allowedRoutes()`. Las páginas de cuenta
  (`settings.*`, salir, selector de empresa) quedan fuera, como hoy.
- **Roles con invitaciones pendientes.** Cuentan como personas asignadas: no se borra el rol hasta cancelar o reasignar esas
  invitaciones. Editar un rol que ya tiene personas y agregarle un permiso sensible lleva la misma advertencia y bitácora, y
  queda registrado qué personas lo reciben; no se manda correo.
- **Lista de sensibles.** Se cierra en el catálogo (campo `sensitive`) y su prueba la fija: trauma/Guía I, quejas
  (contenido y atención), resultados NOM-035 con detalle, compensación y vista de personas. El plan la enumera.
- **Plataforma y demo.** Soporte ve la página de roles solo de lectura; el superadmin pasa por `Gate::before`. Suplantar a un
  owner no permite administrar roles (entra en `ForbidWhileImpersonating`). El seeder demo no necesita roles propios.

## Para revisar (mis decisiones)

1. Equivalencia `manage-organization` = «es RH» y owner = «es dirección» en el conflicto de quejas.
2. Permisos nuevos de cobertura (`view-dashboard`, `view-work-centers`, `view-nom035-route`) y que las plantillas queden con
   exactamente el acceso actual.
3. Máximo 20 roles propios por empresa.
4. Un solo rol por persona, más permisos extra (sin múltiples roles).

## Pendiente de Victor (al cerrar la implementación, 2026-11-24)

Ya construido así; cada punto se cambia si Victor decide otra cosa. Ninguno está confirmado.

1. **Conflicto de quejas (C-45):** `manage-organization` = «es RH» y owner = «es dirección». Implementado: el owner cuenta
   solo como dirección (no deja las quejas contra RH) y RH es quien tiene `manage-organization`. — pendiente de Victor.
2. **Permisos nuevos de cobertura** `view-dashboard`, `view-work-centers` y `view-nom035-route`, con las plantillas en
   exactamente el acceso que tenían (`TemplateAccessUnchangedTest`). — pendiente de Victor.
3. **Máximo de 20 roles propios** por empresa (`tenancy.max_custom_roles`). — pendiente de Victor.
4. **Un solo rol por persona, más permisos adicionales** (sin varios roles). — pendiente de Victor.
5. **Lista de sensibles** (`PermissionCatalog`, `sensitive`): `view-trauma-screenings`, `handle-complaints`,
   `manage-complaint-channel`, `review-workplaces`, `view-compensation`, `manage-compensation`. — pendiente de Victor.
6. **Panel y Centros separados** en la pantalla de roles: la Comisión tiene `view-dashboard` sin `view-work-centers`, y el
   panel solo se abre con los dos. — pendiente de Victor.
7. **`assess-seating`** como permiso aparte de Ley Silla (otra opción: un nivel intermedio «Evaluar»). — pendiente de Victor.
8. **Etiquetas** «Canal de quejas» (con niveles «Atender» / «Administrar») y Humi con el nivel «Usar». — pendiente de Victor.
9. **Textos es-MX** nuevos (validaciones, avisos, correos de invitación, página «Aún no tienes acceso», criterios de
   `docs/normas/permisos/criterios.md`). — pendiente de Victor.
10. **Dos avisos de personas** en el editor de roles (el aviso de arriba y la línea del resumen «aplica a N personas»): quizá
    sobra uno. — pendiente de Victor.
11. **Soporte suplantando al dueño:** ve roles y permisos y no cambia nada (toda escritura responde 404). — pendiente de Victor.
12. **Nombre de un rol igual a la etiqueta de una plantilla** («Solo consulta»): no se permite. — pendiente de Victor.
13. **Invitar con extras sensibles** pide la misma confirmación. — pendiente de Victor.
14. **La plantilla del owner** no aparece en la lista de roles. — pendiente de Victor.
15. **«Sin acceso» elegido** se marca con relleno neutro, no con el acento. — pendiente de Victor.
16. **404 en vez de 403** cuando falta el permiso en las rutas cuyas pruebas de privacidad ya esperaban 404, y siete
    rutas que piden un solo permiso donde el controlador aceptaba uno u otro (archivos y evidencia de Ley Silla,
    canalización, aviso de teletrabajo, opciones de personas del organigrama). — pendiente de Victor.
17. **Humi** le dice al dueño los conteos de roles (cuántos propios de 20, personas por rol, cuántas con extras) y a cada
    quien su propio nivel por área, como una línea más del estado de cumplimiento. — pendiente de Victor.
