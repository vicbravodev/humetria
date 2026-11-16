# Empresa demo: una empresa creíble, sembrada desde la consola, para vender en vivo

Fecha: 2026-11-16 · Estado: **implementado** (operación: `docs/operations/demo.md`)

## Intención

Humetria ya está en producción y la venta se decide en la presentación. Hoy el dueño del producto no tiene qué enseñar:
una empresa vacía no vende, y la de un cliente real no se puede mostrar. Se necesita **una empresa ficticia, mediana y
coherente, que parezca una empresa real que ya usa Humetria**, que el dueño prepara desde la consola antes de cada
presentación, y con la que **el prospecto también participa desde su teléfono**: contesta una encuesta, entra a su portal,
pone una queja, marca asistencia.

## Metas

- Un botón en la consola, «Preparar demo», deja la empresa lista; otro, «Reiniciar», la regresa a ese estado.
- **Una sola historia** que cruza todos los módulos y cuyos números cuadran entre ellos (la misma plantilla, los mismos
  centros, los mismos riesgos en el diagnóstico, el programa de intervención, el clima, las denuncias).
- Las fechas son **relativas a hoy**: la demo no envejece.
- Una **sala de demostración** en la consola: lo que se comparte en vivo (entrada como dueño, enlaces, QR) en una pantalla.
- Una **puerta** (un QR) con la que cada persona del público recibe su propio trabajador demo y entra a su portal.
- Cero correos reales y cero contaminación de facturación, métricas y alertas.

## No metas

- Que un cliente se registre solo en la demo (el alta de empresas sigue siendo por demo).
- Varias empresas demo, o una demo personalizada por prospecto (decidido: una genérica).
- Sembrar passkeys: la asistencia en vivo se marca con el dispositivo real de quien participa.
- Imitar a una empresa o marca real.

## Antimetas (fracasaría aunque «funcione»)

- Un estado que el producto real nunca produce (respuestas ligadas a una persona, un grupo por debajo del mínimo que se
  muestra, números que no cuadran). Por eso la historia pasa por las **acciones reales**.
- Un correo que llegue a una dirección de verdad, o una demo contada como cliente en cobros, MRR o conteos.
- Un reporte de la demo que alguien pueda confundir con un diagnóstico real ante la STPS.
- Una puerta abierta de forma permanente.

## Restricciones del proyecto que mandan

- Monolito modular: otro módulo no importa modelos, acciones ni enums ajenos. La historia vive **fuera de `app/Modules`**
  (como el `Nom035EvaluationSeeder` existente, en `database/seeders`), y el módulo `Demos` solo ve un contrato.
- **Faker es `require-dev`** y el Dockerfile instala con `--no-dev`: en producción no existe `fake()`, y toda factory lo
  llama. La historia no puede usar factories ni `ScenarioFactory`. Ver «Decisiones suyas».
- Anonimato: las respuestas anónimas no llevan llave a empleado, invitación ni token; mínimo de 5 por grupo después de
  cada filtro. Nada de la puerta liga a una persona con una respuesta.
- Multi-tenancy: toda tabla de negocio lleva `tenant_id`; la demo es una empresa más, aislada por las mismas reglas.
- Una ruta pública nueva se agrega a la lista de `RouteSecurityTest` con su razón, lleva throttle, CSP y `noindex`.
- TDD estricto, Larastan en max, sin dependencias nuevas sin aprobación, un solo PR.

## Enfoques considerados

| | Qué es | Cuesta | Por qué sí / no |
|---|---|---|---|
| **A (elegido)** | Preparar con las acciones reales y fechas relativas, más una sala y una puerta | Más código | Lo que se ve es lo que el producto produce; no se desactualiza con migraciones |
| B | Restaurar una copia guardada de la base | Poco | Se rompe con cada migración y se salta anonimato y mínimos |
| C | Todo con inserts o factories | Poco | Nada garantiza que los números cuadren entre módulos |

El usuario eligió A y pidió que el prospecto participe desde su propio teléfono.

## Diseño

### 1. La empresa demo

Una columna `tenants.is_demo` (boolean, no asignable en masa, `NOT NULL default false`) y un índice único parcial que
permite **una sola** empresa demo. Solo la acción que prepara la demo la enciende. Efectos de la marca:

- **Cobros y métricas:** sin cuenta ni suscripción (`Billing`); fuera de `RenewSubscriptions`, estados de cuenta,
  `PlatformOverview`, `RealUsage` y de los conteos de empresas de la consola. Su estado es siempre «activa», con todos los
  módulos y sin límites que estorben.
- **Correo:** nada sale. Los trabajadores demo usan un dominio reservado (`@demo.humetria.test`, `.test` es reservado por
  RFC 6761) y un listener de `MessageSending` cancela todo mensaje cuyos destinatarios sean todos de ese dominio y lo loguea
  (`demo.mail_blocked`). Además, los jobs de resúmenes y recordatorios se saltan la empresa demo.
- **Reportes:** los PDF y las páginas de resultados llevan una marca «DEMO».
- **Entrada:** se entra por la impersonación que ya existe, desde la sala (como dueño o como RH), sin contraseña compartida.

### 2. La historia (una empresa, todos los módulos)

**Manufacturas Alba del Bajío, S.A. de C.V.** (nombre y RFC ficticios; el RFC con formato que no valida en el SAT).
~180 personas en 4 centros, dimensionados para ejercer los umbrales de NOM-035:

| Centro | Personas | Guías | Papel en la historia |
|---|---|---|---|
| Sucursal Monterrey | 15 | I | el mayor con solo Guía I |
| Oficinas Querétaro | 30 | I, II | hogar de Ley Silla y teletrabajo |
| Centro de distribución | 51 | I, III | el menor con Guía III |
| Planta Querétaro | 84 | I, III (muestra) | donde ocurre el problema |

El hilo: hace ~6 meses una primera evaluación NOM-035 cerró y mostró riesgo alto en **producción, turno 2 de la planta**
(rotación y horas extra). RH lanzó un programa de intervención (acciones hechas, en curso, una vencida, una por empezar),
y una encuesta de pulso reciente muestra que el clima mejora. La asistencia refleja las horas extra de ese turno; hay
quejas en el canal con estados distintos; vacaciones con saldos y solicitudes pendientes; compensación con un escenario de
contrato colectivo y su costo; políticas publicadas con acuses (~77 %: las ~40 personas reservadas para la puerta quedan pendientes para que cada visitante tenga algo que confirmar en vivo); Ley Silla con evaluación de riesgo y medidas en
las oficinas; NOM-037 con ~12 teletrabajadores y su lista de verificación. Humi responde sobre estos datos.

Cada módulo cuenta su parte con **números derivados de la misma plantilla**: un test compara, por ejemplo, el total de
trabajadores del organigrama con los de cada centro, los respondentes de cada campaña con sus invitaciones, y los grupos
con el mínimo de 5.

**Datos vivos:** un pulso de clima abierto (~60 % contestado) con **invitaciones pendientes reservadas** para el
público, una campaña NOM-035 abierta igual, el canal de denuncias activo, un centro con checador de pantalla activado y
un teléfono por vincular. Lo que el público conteste en vivo se suma a lo sembrado y mueve el avance.

**Personas:** nombres, puestos y correos escritos a mano y deterministas (sin Faker). El reinicio reproduce exactamente la
misma historia, corrida a la fecha de hoy.

### 3. Preparar y reiniciar

Un contrato en `Demos` (`DemoWorkspace`) con `prepare()` y `reset()`; la implementación vive fuera de `app/Modules` y
recorre una clase de historia por módulo, cada una usando las **acciones reales** (lanzar, contestar por enlace, cerrar,
registrar marca) con el reloj corrido al pasado, como ya hace `Nom035EvaluationSeeder`. Corre en un job de cola, con
progreso visible en la consola y un candado: nunca dos a la vez (una corrida de más de 30 minutos se da por abandonada y
su job, si llega tarde, no hace nada). Los jobs (`PrepareDemo`, `ResetDemo`) van en su propia cola `demo` de la conexión
`redis-long` (supervisor `supervisor-demo` de Horizon), cuyo `retry_after` supera su límite de 10 minutos; no relanzan
su error: la señal es `demo.run_failed`. `reset()` conserva la fila de la empresa, borra todo lo demás de la demo
(incluido lo que el público haya creado en vivo: quejas, dispositivos vinculados, respuestas) y vuelve a preparar. Un
test comprueba que, tras borrar, no queda ninguna fila con el `tenant_id` de la demo en ninguna tabla de negocio, salvo
la bitácora.

**Excepción a las tablas de solo inserción (decisión del usuario, sensible para la seguridad).** Una migración cambia
los triggers de las 14 tablas de solo inserción que la demo escribe para que permitan **DELETE solo de las filas de la
empresa con `is_demo`**; UPDATE y TRUNCATE siguen bloqueados para todas, y `audit_entries` no se toca.

### 4. La sala de demostración (consola)

Una página de la consola (`/admin/demo`), solo para superadmin (gate `manage-demo`; a cualquier otro, soporte incluido,
404), con el estado de la demo (preparada, qué tan vieja, cuántos
trabajadores libres quedan) y lo que se comparte en vivo:

- «Entrar como dueño» y «Entrar como RH».
- QR y enlace del **canal de denuncias** de la demo y de la **pantalla de checador** del centro. El enlace de la
  pantalla se emite al pulsar, se muestra una vez y deja el checador sin activar hasta que se abre; cuando la pantalla
  ya muestra su código no se emite otro hasta reiniciar.
- La **puerta**: abrir o cerrar una sesión de demostración (2 horas por omisión), con su QR.
- Reiniciar.

### 5. La puerta

`GET demo/{code}`: pública, con throttle. Solo responde mientras haya una sesión abierta desde la consola y solo entrega
trabajadores de la empresa demo. Al escanear, asigna al visitante un **trabajador demo libre** del grupo reservado
para el público (un cookie firmado recuerda cuál, para que si recarga conserve el suyo) y lo redirige a su **portal
personal** (`portal/{token}`), la entrada única que ya existe: desde ahí contesta la encuesta, ve resultados, entra al
canal de quejas y vincula su teléfono para marcar. Cada trabajador se entrega **una sola vez hasta el siguiente
reinicio** (no hay «liberar abandonados»: su enlace del portal no rota y llegaría a un segundo visitante); sin
trabajadores libres, muestra «sala llena» y la sala sugiere reiniciar. La puerta registra la asignación sin ligarla a
ninguna respuesta. El canal de denuncias de la demo acepta 60 envíos por hora y 120 al día por dirección (un salón
comparte la Wi-Fi); el tope diario del canal (200) no cambia.

### 6. Seguridad y privacidad

La puerta es el único punto nuevo expuesto: sesión con vencimiento, código aleatorio, throttle por dirección, rechaza
toda empresa que no sea la demo, respuestas sin llave a la persona (los tests lo afirman sobre las props de cada página
que ve RH, como siempre), `X-Robots-Tag: noindex`. Una prueba afirma que los datos de la demo nunca aparecen en
las listas, conteos ni correos de ninguna otra empresa, y que otra empresa no puede leer ni modificar la demo.

## Pruebas

Unit/Feature: preparar es idempotente; los números cuadran entre módulos; los umbrales de NOM-035 por centro; sin correos
reales y `demo.mail_blocked` logueado; la demo no aparece en facturación, métricas ni conteos; reinicio sin filas
huérfanas; la puerta (sesión cerrada, vencida, sin trabajadores libres, otra empresa, throttle); anonimato en las props de
cada página nueva; autorización de la sala (solo superadmin, 404 antes de validar). Browser: prueba de humo de la sala
(`visitPage()`), y un flujo largo de la puerta al portal en celular. Cobertura ≥ 90 %.

## Decisiones suyas (ya respondidas)

1. **Sin Faker en producción.** La historia se escribe a mano con acciones reales; `fakerphp/faker` no cambia de lugar.
2. **Humi activo** en la demo, con el tope de uso normal de la empresa.
3. **Entrada:** se exenta a la empresa demo de los bloqueos de impersonación de Compensación y Humi (no tiene datos
   reales), y la sala permite entrar como dueño o como RH. Sin un login propio nuevo.

## Decisiones mías (corríjalas)

- **Reiniciar conserva la empresa y reconstruye su contenido.** La fila de la empresa (y con ella su slug, el código del canal
  de denuncias y el QR del checador) no cambia. Los trabajadores se recrean con **ids deterministas** (derivados de una
  clave estable por persona), de modo que su enlace del portal sigue siendo el mismo tras cada reinicio.
- **Bitácora (`audit_entries`) es de solo inserción:** preparar con las acciones reales escribe filas que no se pueden
  borrar. La regla de «no queda ninguna fila» del reinicio **exceptúa la bitácora**; el constructor puede evitar que
  preparar la escriba si es fácil. Crecen unas pocas filas por reinicio y no se muestran fuera de la consola.
- **Sin «liberar abandonados»** (cambió al construir): cada trabajador de la puerta se entrega una vez por preparación;
  reiniciar es la única manera de rellenar la puerta.

- Nombre de la empresa, tamaños de centros, y la narrativa de «turno 2 de la planta».
- Sesión de la puerta de 2 horas y un grupo reservado de ~40 trabajadores para el público.
- Dominio de correo `@demo.humetria.test` y el bloqueo por listener de `MessageSending`.
- Marca «DEMO» en reportes.

## Queda al constructor

El orden de las clases de historia, qué módulos necesitan viajar en el tiempo (y cómo), el diseño exacto del candado y
del progreso del job, los textos de la sala y su diseño (parte de la pantalla de consola más parecida), y la lista exacta
de tablas que borra el reinicio.
