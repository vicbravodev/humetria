# Tenancy: el superadmin da de alta y administra empresas

Fecha: 2026-10-27. Estado: propuesta.

## Intención

Humetría no tiene registro libre: una empresa (tenant) existe porque el superadmin la dio de alta después de su demo.
Este PR crea el módulo `Tenancy` con su primer modelo, `Tenant`, y una consola "Empresas" en `/admin`, solo para el
superadmin, donde se dan de alta, se listan, se editan, se activan y se desactivan empresas.

Es el segundo paso del orden acordado para Tenancy: primer superadmin (#28) → **el superadmin crea la empresa** →
invitar al owner → que acepte → resolver la empresa desde la URL → centros de trabajo → el owner invita usuarios →
el superadmin crea superadmins.

## Decisiones del usuario

- **Datos de la empresa:** razón social, nombre comercial y RFC, los tres obligatorios. El domicilio no va aquí: vive en
  cada centro de trabajo.
- **Alta:** formulario propio en la consola "Empresas". No se crea desde una solicitud de demo.
- **RFC:** se valida su formato (12 caracteres para persona moral, 13 para persona física) y es **único** en toda la plataforma.
- **URL:** la empresa se identifica con un slug legible (`/admin/grupo-acme/…`) que sale del nombre comercial.
- **Alcance:** crear, listar, editar, activar, desactivar y reactivar.
- **Prueba:** siempre de **14 días**. Empieza **cuando el owner entra por primera vez**, no al crear la empresa. Mientras
  tanto la empresa está "sin iniciar". Las pruebas **no se alargan**.
- **Al terminar la prueba** la empresa se bloquea sola.
- **Activar:** cuando el cliente paga, el superadmin la activa **hasta una fecha que él define**. Al llegar esa fecha se
  bloquea sola. El superadmin puede desactivarla antes, y puede reactivarla (con una fecha nueva) si vuelven a pagar.
- **Bloqueo total:** una empresa bloqueada no deja hacer nada a sus usuarios, ni siquiera ver resultados anteriores. Un
  modal grande dice solo que contacten al administrador de Humetría.
- **Desactivar no borra nada:** los datos se conservan (la NOM-035 exige conservar la evidencia, num. 10.4).

## Objetivos

1. El superadmin da de alta una empresa y la ve en la lista con su estado.
2. El superadmin corrige razón social, nombre comercial, RFC o slug.
3. El superadmin activa la empresa hasta una fecha, la desactiva antes de tiempo o la reactiva.
4. El estado de cada empresa se calcula siempre bien, incluidos los bordes de fecha (día 14 exacto, el día de "activa hasta").

## No objetivos (llegan en otros PRs)

- Invitar al owner y que entre: lo que inicia la prueba (pone `trial_started_at`) llega con ese PR.
- Rutas `/admin/{slug}/…`, resolver la empresa desde la URL, `tenant_id` en `Context` y el **modal de bloqueo**: llegan
  con el PR "resolver la empresa desde la URL". Mientras tanto ninguna persona entra a una empresa, así que el bloqueo
  solo se ve en la consola.
- Crear la empresa desde una solicitud de demo.
- Borrar empresas, buscar o filtrar en la lista.
- Contratos públicos del módulo (`Contracts/`): nadie los consume todavía.
- Cobro, facturación o recordatorios de vencimiento.

## Anti-objetivos

- Que una empresa vencida aparezca como activa por un error de zona horaria o de "menor que" contra "menor o igual que".
- Que un slug choque con una ruta de la app (`/admin/settings`, `/admin/login`…): rompería esa empresa o esa ruta
  cuando lleguen las rutas por empresa.
- Que alguien que no es superadmin sepa siquiera que la consola existe (debe recibir 404, no 403).
- Perder datos al desactivar.

## Ciclo de vida

```
Sin iniciar ──(el owner entra por 1.ª vez)──► En prueba (14 días) ──(sola)──► Prueba terminada ┐
     │                                           │                                            │
     └──────────────(superadmin: Activar hasta F)┴──────────► Activa ──(llega F, sola)──► Vencida
                                                               │  ▲                           │
                                                (Desactivar)   ▼  └──(Activar hasta F nueva)──┘
                                                          Desactivada ──(Activar hasta F)──► Activa
```

Bloqueadas: **Prueba terminada**, **Vencida** y **Desactivada**. "Desactivar" también sale de **Sin iniciar** y
**En prueba** (ver abajo).

## Decisiones de Claude (revisables)

### Datos

- Módulo `app/Modules/Tenancy`, modelo `Tenant`, tabla `tenants`. La tabla `tenants` no lleva `tenant_id` ni global scope:
  es dato de la plataforma.
- Llave primaria **UUID** (`audit_entries.tenant_id` ya es `uuid`). Consecuencia para un PR posterior: cuando los roles
  por empresa usen los *teams* de spatie/laravel-permission, su migración `add_teams_fields` debe crear `team_id`
  como `uuid`, no `unsignedBigInteger`. Este PR no toca spatie.
- Columnas: `legal_name`, `trade_name`, `rfc` (único), `slug` (único), `trial_started_at`, `active_until` y
  `deactivated_at`, estas tres `timestampTz` nullable, más `timestamps`.
- El estado **no se guarda**: se calcula de esas tres fechas contra la hora actual, en un solo lugar (un enum
  `TenantStatus` y un método del modelo). Así nada tiene que correr a medianoche para vencer una prueba. Prioridad:
  1. `deactivated_at` con valor → **Desactivada**.
  2. `active_until` con valor → **Activa** si `now < active_until`; si no, **Vencida**.
  3. `trial_started_at` vacío → **Sin iniciar**.
  4. `now < trial_started_at + 14 días` → **En prueba**; si no, **Prueba terminada**.
- Los 14 días son una constante del módulo, no configuración: el usuario dijo "siempre".
- Integridad en la base: `unique` en `rfc` y en `slug`, `not null` en los tres datos obligatorios y un `check` del formato del RFC.

### RFC

- Se guarda en mayúsculas y sin espacios. Formato: 3 letras (persona moral) o 4 (persona física) de `A-Z`, `Ñ` o `&`,
  6 dígitos de una fecha AAMMDD **válida** y 3 caracteres alfanuméricos de homoclave. No se valida el dígito verificador.
- Si el RFC ya existe, el mensaje lo dice sin revelar a qué empresa pertenece.

### Slug

- Al crear, se propone a partir del nombre comercial (`Str::slug`: "Grupo Acme, S.A." → `grupo-acme-sa`). El
  formulario lo muestra prellenado y el superadmin lo puede cambiar antes de guardar.
- Al editar, cambiar el nombre comercial **no** cambia el slug solo. El slug se edita a mano; el formulario avisa que las
  URLs anteriores dejarán de funcionar.
- Reglas: minúsculas, dígitos y guiones, entre 3 y 50 caracteres, único, y que no esté en una lista de palabras
  reservadas que incluye los segmentos que ya usa `/admin` (`settings`, `login`, `logout`, `demo-requests`,
  `companies`, `two-factor-challenge`, `password`, `email`, `register`, `user`…) y los que ya se ven venir (`api`, `admin`,
  `superadmin`, `billing`). Un test recorre las rutas registradas bajo `/admin` y falla si alguna no está reservada.

### Activar, desactivar, reactivar

- **Activar** (sirve también para reactivar o cambiar la vigencia): el superadmin elige una fecha "activa hasta", que
  tiene que ser posterior a hoy. Se guarda como el **final de ese día en hora de la Ciudad de México**, convertido a UTC.
  Se borra `deactivated_at`. Se puede activar en cualquier estado, también "Sin iniciar" (pagó por adelantado).
- **Desactivar:** pone `deactivated_at`. Está disponible en cualquier estado que no esté bloqueado: Sin iniciar, En
  prueba y Activa. Así el superadmin puede cortar una prueba (por ejemplo, un prospecto que abusa). Es de un solo
  sentido: la única salida es Activar con fecha, porque la prueba no se reanuda ni se alarga. El Dialog de
  confirmación lo dice.
- Activar no toca `trial_started_at`: la prueba nunca vuelve a empezar.

### Consola

- Rutas bajo `/admin/companies`, con nombre y autenticación: `companies.index`, `companies.store`,
  `companies.update`, `companies.activate`, `companies.deactivate`. El `{tenant}` de la consola se resuelve por UUID,
  no por slug: la consola es de la plataforma, no la URL de la empresa.
- Autorización: un Gate `manage-tenants` que siempre niega con `denyAsNotFound()`. El superadmin pasa por el
  `Gate::before` que ya existe. Es el mismo patrón que `view-demo-requests`.
- Una sola página Inertia, `tenancy/index`: una tabla paginada de 25 (nombre comercial, razón social, RFC, estado con
  `Badge` y fecha relevante: "Prueba hasta 10 nov 2026", "Activa hasta 31 dic 2026", "Sin iniciar"…). Crear, editar,
  activar y desactivar van en `Dialog` sobre esa misma página. Vacía: "Aún no hay empresas".
- Enlace "Empresas" en el sidebar, visible solo para el superadmin, junto a "Solicitudes de demo".
- Textos en español, tuteando. Fechas con el formato de la consola de demos ("27 oct 2026").

### Logs y bitácora

- Eventos `tenant.created`, `tenant.updated`, `tenant.activated` (con `active_until`) y `tenant.deactivated`. Los
  cuatro van también a la bitácora con `RecordAudit`: son pocos y afectan el acceso de un cliente.
- La bitácora de estos eventos lleva `tenant_id` **nulo**: son acciones de la plataforma, no de la empresa, y la
  empresa se encuentra por `subject_type`/`subject_id`. No se pone `tenant_id` en `Context` dentro de la consola.
  Así, cuando exista la bitácora de cada empresa, su owner no verá las acciones internas de Humetría, salvo que se decida
  mostrarlas (eso se puede añadir leyendo por `subject_id`, sin reescribir filas).
- **Ni el log ni la bitácora guardan valores de campos.** Un RFC de 13 caracteres es de una persona física (dato
  personal), y `audit_entries` es de solo inserción: lo que entra no se puede borrar. `metadata` lleva solo los
  **nombres** de los campos que cambiaron (`['changed' => ['rfc', 'slug']]`) y, en `tenant.activated`, `active_until`.
  `rfc` se agrega a las llaves de `RedactSensitiveData`, con su test.

### Pruebas

- **Unit (con `mutates()`):** cálculo del estado con todos los bordes: el instante exacto de los 14 días, un segundo
  antes y uno después; el instante de `active_until`; desactivada con una vigencia futura; activa sobre una prueba
  terminada. Validación del RFC: 12 y 13 válidos, 11 y 14 inválidos, fecha imposible (`ABC991332XY1`), `Ñ` y `&`, minúsculas
  que se normalizan.
- **Feature:** el superadmin crea, edita, activa y desactiva (props, base de datos, log y bitácora). Un usuario sin
  rol y uno sin sesión reciben 404 o la redirección al login en cada ruta. RFC y slug duplicados, slug reservado,
  fecha de activación en el pasado o hoy. Las props de la página no traen más de lo que la tabla muestra.
- **Browser:** `visitPage('tenancy/index', …)` en claro y oscuro, escritorio y móvil; crear una empresa desde el Dialog
  y verla en la lista; vacío.
- `tests/Feature/RouteSecurityTest.php` y `ArchitectureTest` siguen en verde sin excepciones nuevas.

### Commits (un comportamiento cada uno)

1. Modelo `Tenant`, migración y cálculo del estado.
2. Consola: listar empresas (página, ruta, sidebar).
3. Crear empresa (RFC y slug validados).
4. Editar empresa.
5. Activar y reactivar hasta una fecha.
6. Desactivar.

## Lo que se deja al constructor

- Nombres internos de clases, acciones y DTOs, siguiendo la estructura de módulos de `CLAUDE.md`.
- La composición exacta de componentes en `resources/js/components/tenancy/`, respetando los límites del linter.
- El orden de las columnas de la tabla y el texto exacto de los avisos, dentro del tono del design system.
