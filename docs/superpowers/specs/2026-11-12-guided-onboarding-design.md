# Primeros pasos guiados (módulo `Onboarding`): diseño

Fecha: 2026-11-12 · Estado: **borrador**, pendiente de aprobación de Victor.

## Para qué

Hoy una empresa nueva entra al panel, contesta la primera vez de sus centros (`tenancy/onboarding`) y después queda sola
frente a un menú con muchas herramientas. Queremos un acompañamiento **opcional** que la lleve de la mano por lo mínimo
para sacarle valor a Humetria, que resalte en la pantalla lo que hay que tocar, que la acompañe hasta terminar y que
luego desaparezca.

## Decisiones (Victor, 2026-11-12)

1. **Solo para el owner** de la empresa. Ni `hr` ni `viewer` lo ven.
2. **Por empresa, siempre.** Cada empresa tiene su propio avance. Un owner de N empresas (p. ej. un consultor) tiene N
   onboardings: puede hacer el de una y saltar los demás.
3. **Siempre omitible.** Se puede cerrar en cualquier momento y retomar después.
4. **Solo mientras la empresa no esté configurada.** Si ya tiene hechos todos sus pasos, no aparece, aunque nunca haya visto
   el onboarding. Las empresas que ya existen lo ven solo si les falta algo.
5. **Pasos que cambian según el plan.** Salen de los módulos que tiene la empresa (`CompanyModules`, #94).
6. **Persistente sin estorbar.** Siempre está a la vista mientras falte algo, pero como un botón pequeño, no como un modal.
7. **Humi es parte del recorrido.**
8. **Dependencias nuevas permitidas** si el resultado lo justifica: se agrega `driver.js`.

## Experiencia

### Bienvenida (una vez por empresa)

La primera vez que el owner entra al panel de una empresa con pasos pendientes ve un diálogo corto:

> **Te acompañamos en tus primeros pasos**
> Son 5 pasos y unos 15 minutos. Puedes dejarlo cuando quieras y retomarlo desde el botón de abajo.
> [Empezar] [Ahora no]

Cualquiera de las dos respuestas marca la bienvenida como vista y ya no vuelve a salir en esa empresa. **Empezar** abre
el panel de pasos. **Ahora no** deja solo el botón.

### El botón "Primeros pasos"

- Es flotante, abajo a la derecha y **a la izquierda del botón de Humi**, para que las dos ayudas vivan juntas. Muestra
  un aro de avance y "2 de 5". En el celular va a la misma altura que Humi, encima de la barra inferior.
- Al tocarlo se despliega un panel (popover) con la lista: cada paso con su estado (hecho o pendiente), una línea de
  qué es y un botón **Guíame**.
- Debajo de la lista: **Ocultar primeros pasos**. Lo quita del todo para esa empresa y avisa: *"Puedes retomarlos desde tu
  menú de usuario"*.
- El menú de usuario tiene **Primeros pasos** mientras falte algo. Si estaba oculto, lo vuelve a mostrar.
- Cuando el último paso se completa, el panel muestra un cierre (*"Listo: Humetria ya está trabajando para ti"*) y el
  botón desaparece para siempre en esa empresa.

### "Guíame": el recorrido con spotlight

**Guíame** navega a la pantalla del paso y, al cargar, oscurece el resto y resalta el control que hay que usar (por ejemplo, el
botón *Importar empleados*), con una burbuja que explica qué hacer. Algunos pasos tienen 2 o 3 burbujas (el botón, el campo,
el resultado). El recorrido:

- **No avanza solo** al paso siguiente de la lista. Un paso se marca hecho cuando **los datos** lo dicen (ya hay
  empleados), no por haber visto las burbujas. Al volver al panel, el paso aparece palomeado.
- Se puede cerrar con la X, con Esc o tocando fuera.
- Si el control no está en la pantalla (otra vista, otro tamaño), la burbuja se muestra centrada sin spotlight. Nunca
  se queda colgado.

## Los pasos

Cada paso es una pregunta sobre los datos de la empresa:

| # | Paso | Hecho cuando | Se lee con |
|---|------|--------------|------------|
| 1 | **Configura tus centros de trabajo** | todo centro activo tiene contestada su primera vez | `Tenancy\Contracts\WorkCenters` (+ método nuevo si hace falta) |
| 2 | **Carga a tu gente** | la empresa tiene al menos 1 persona activa | `Organization\Contracts\Headcount::ofCompany()` |
| 3 | **Lanza tu primera evaluación** (cambia según el plan) | la herramienta principal del plan tiene algo lanzado (ver abajo) | contrato del módulo de esa herramienta |
| 4 | **Invita a alguien de tu equipo** | hay otro miembro o una invitación pendiente | `Tenancy\Contracts\Members` (+ invitaciones pendientes) |
| 5 | **Conoce a Humi** | el owner ya le hizo al menos una pregunta en esa empresa | `Assistant`: contrato nuevo |

**Paso 3 según el plan.** Se toma la primera herramienta que la empresa tenga, en este orden:

| Módulo | Texto del paso | Hecho cuando |
|--------|----------------|--------------|
| `nom035` | Lanza tu primera evaluación NOM-035 | hay una campaña en curso o cerrada (`CampaignRecords`) |
| `nom037` | Arranca la ruta de teletrabajo (NOM-037) | algún centro ya declaró si tiene teletrabajo (`TeleworkApplicability`) |
| `seating` | Arranca la ruta de Ley Silla | algún centro ya declaró (`SeatingApplicability`) |
| `attendance` | Configura el registro de jornada | la asistencia está puesta en marcha (contrato a definir en el plan) |

Si la empresa no tiene ninguno de estos módulos, el paso 3 no existe y el onboarding es de 4 pasos. Las otras herramientas
del plan aparecen al pie del panel como *"También tienes: NOM-037, Ley Silla"*, con un enlace a cada una y sin palomita.

El paso de Humi solo aparece si Humi está disponible para la empresa (`AiAccess::available()`).

"Revisa tus resultados" **no** es un paso: depende de que una campaña cierre, lo que tarda semanas, y el onboarding se
quedaría abierto todo ese tiempo.

## Humi dentro del recorrido

1. **Paso 5:** el spotlight resalta el botón de Humi y propone una pregunta de ejemplo (*"¿Qué me falta para cumplir con la
   NOM-035?"*). Tocarla abre Humi con la pregunta escrita, y el owner la envía.
2. **Humi conoce los pasos.** Una herramienta nueva de solo lectura, `GetFirstSteps`, devuelve los pasos de la empresa y su
   estado, sin datos personales. Si el owner pregunta *"¿qué sigue?"* o *"¿cómo cargo a mi gente?"*, Humi contesta con el
   paso pendiente y un enlace a su pantalla. La herramienta usa el `AssistantScope` como las demás y solo responde si la
   persona es owner.

## Arquitectura

### Módulo `Onboarding`

```
app/Modules/Onboarding/
  Contracts/FirstSteps.php        forUser(tenantId, userId): ?FirstStepsData (null = no se muestra)
  Data/FirstStepsData.php         pasos, estado de la bienvenida, oculto, otras herramientas
  Data/FirstStep.php              key, done, href (sin textos: los textos viven en el front)
  Actions/BuildFirstSteps.php     arma los pasos leyendo los contratos de otros módulos
  Actions/MarkWelcomed.php  Actions/HideFirstSteps.php  Actions/ShowFirstSteps.php  Actions/CompleteFirstSteps.php
  Models/OnboardingState.php      BelongsToCurrentTenant
  Http/Controllers/OnboardingStateController.php
  Policies/  Providers/
routes/onboarding.php             POST welcome, hide, show bajo admin/{tenant}/onboarding/*
```

- **Lo único que se guarda** es `onboarding_states`: `id` (uuid), `tenant_id`, `user_id`, `welcomed_at`, `hidden_at`,
  `completed_at` (`timestampTz`), `unique(tenant_id, user_id)` y llaves foráneas. El avance **no se guarda**: se calcula
  de los datos en cada carga, así nunca se desincroniza.
- `completed_at` se escribe la primera vez que todos los pasos aparecen hechos, para no volver a calcularlos después.
  Desde ese momento `forUser()` regresa `null` con una sola consulta.
- **Prop compartida:** `HandleInertiaRequests` agrega `firstSteps` como closure perezosa (igual que `inCourse`) y solo dentro
  de una empresa. El kernel depende del contrato, no de internos. Fuera de una empresa, o si no eres owner, vale `null`.
- **Costo:** el número de consultas de `BuildFirstSteps` se fija en un test (`expectsDatabaseQueryCount`). Con
  `completed_at` puesto, cuesta 1 consulta.
- **Logs:** `onboarding.welcomed`, `onboarding.hidden`, `onboarding.shown` y `onboarding.completed`, con `tenant_id` y
  `user_id` en el contexto. No va a la bitácora: no es una acción sensible.
- **Rutas:** las tres POST con nombre (`tenant.onboarding.*`), autenticación y una Policy que exige ser owner. Las de otra
  empresa dan 404.

### Contratos nuevos en otros módulos

Se confirman en el plan, contra el código:
- `Assistant`: `hasAsked(userId): bool` sobre la empresa actual.
- `Tenancy`: si `WorkCenters` no expone ya si la primera vez está contestada, un método para eso. También las invitaciones
  pendientes en `Members`.
- `Attendance`: si está puesta en marcha.

### Frontend

```
components/onboarding/
  first-steps-launcher.tsx    botón flotante con aro de avance (junto a Humi)
  first-steps-panel.tsx       popover con la lista, "también tienes" y ocultar
  first-step-item.tsx         un paso: estado, texto, Guíame
  first-steps-welcome.tsx     diálogo de bienvenida
  first-steps-done.tsx        cierre
hooks/use-first-steps-tour.ts   arranca el spotlight al llegar a la página del paso
lib/onboarding/steps.ts         textos, iconos y burbujas de cada paso (data-tour, título, cuerpo)
lib/onboarding/tour-session.ts  qué recorrido está activo entre navegaciones (sessionStorage con try/catch)
types/onboarding.ts
```

- **driver.js** (MIT, unos 5 KB, sin dependencias) dibuja el spotlight y las burbujas. Su popover se viste con
  `popoverClass` y CSS con nuestros tokens (claro y oscuro). Su CSS se importa por Vite, así que no hay `<style>` inline.
  Fija los estilos por CSSOM, que la CSP permite; los tests de navegador lo confirman con `cspViolations()`.
- Los controles que señala el recorrido llevan `data-tour="…"` (p. ej. `data-tour="import-employees"`). Nunca se
  ancla a clases CSS.
- **Accesibilidad:** el panel es un popover de Radix (foco, Esc, `aria-expanded`). Las burbujas de driver.js se usan con
  teclado (Esc y flechas), y el aro de avance tiene un texto equivalente ("2 de 5 pasos"). Con
  `prefers-reduced-motion` no hay animación.
- **Design system:** el panel reusa las piezas del rediseño (tarjeta, palomitas de lista, estado vacío con siguiente paso)
  y se revisa en claro, oscuro y a 375 px.

## Privacidad y seguridad

- Ningún paso lee respuestas de encuestas ni nada individual: solo existencia y conteos (hay personas, hay campaña).
- Humi recibe con `GetFirstSteps` solo llaves y estados.
- Ningún `tenant_id` viene del request. La empresa sale de la ruta, como en el resto del panel.

## Pruebas

| Capa | Qué se prueba |
|------|---------------|
| Unit (Pest) | `BuildFirstSteps`: cada paso en hecho y en pendiente, paso 3 según cada combinación de módulos, sin módulos de evaluación (4 pasos), Humi apagado (sin paso 5); `mutates()` |
| Feature | prop `firstSteps`: `null` para `hr` y `viewer`, `null` con todo hecho (y escribe `completed_at`), presente y por empresa (owner de 2 empresas: estados independientes); rutas de bienvenida, ocultar y mostrar (owner sí, otros 403, otra empresa 404); aislamiento de `OnboardingState` entre empresas; logs; número de consultas |
| Assistant | `GetFirstSteps` con scope de owner y de no owner; `ToolRegistryTest` sigue sin pedir empresa |
| Vitest | `lib/onboarding/*`: el orden y los textos de los pasos según las llaves, la sesión del recorrido (y sin `sessionStorage`) |
| Browser | owner nuevo: bienvenida → Empezar → Guíame en "Carga a tu gente" → spotlight sobre el botón → cerrar; Ahora no → solo el botón; ocultar → retomar desde el menú; 375 px; sin errores de JS ni de CSP, con axe |

## Fuera de alcance

- Recorridos para `hr` o `viewer`, y tours de cada herramienta más allá del primer paso.
- Medir el embudo del onboarding (analítica de producto).
- Que el superadmin edite los pasos desde la consola.

## Orden de construcción (un PR, commits atómicos)

1. Módulo `Onboarding` con la tabla, el modelo, el contrato y `BuildFirstSteps` con sus pasos.
2. Contratos nuevos en Tenancy, Organization, Assistant y Attendance que pidan los pasos.
3. Prop compartida y rutas de bienvenida, ocultar y mostrar.
4. `driver.js`, el botón, el panel, la bienvenida y el cierre.
5. Los recorridos con `data-tour` en cada pantalla.
6. Humi: el paso 5 y la herramienta `GetFirstSteps`.
7. Tests de navegador y revisión visual.
