# Resultados de NOM-035: agregados, granulares, informe 7.7 y enlace temporal

Fecha: 2026-10-30 · Rama: `nom-035-results` · Sigue a `2026-10-29-nom-035-campaigns-design.md`.

## Qué y por qué

Hoy la plataforma aplica la NOM-035 y califica cada cuestionario, pero RH no ve nada. Este trabajo cierra la NOM-035:

1. **Resultados** de una evaluación cerrada: agregados del centro y granulares (por categoría, dominio, área y
   pregunta), siempre como «cuántos cuestionarios quedan en cada nivel», nunca un promedio (C-14).
2. **Guía I**: conteos y la **lista de canalización** (personas que requieren valoración clínica, num. 5.5 y 5.8 c),
   solo para roles autorizados.
3. **Informe del num. 7.7**, imprimible y guardable como PDF desde el navegador.
4. **Enlace temporal** para que la empresa (gente sin cuenta en Humetría) revise el informe: dura de 15 minutos a
   48 horas, se puede revocar y cada apertura queda en la bitácora.

## Decisiones

Las aceptó el usuario («con las propuestas por defecto», 2026-10-30):

- **Granular** = desglose por categoría, dominio, área y pregunta, con el mínimo de 5 por grupo. Nunca por persona en
  las Guías II y III: no existe la llave (C-22). Por persona, solo la Guía I.
- **El enlace temporal lo abre gente sin cuenta**; lo genera quien gestiona campañas (owner y RH). Muestra el informe
  (resultados agregados) y **nunca** la Guía I.
- **Duraciones fijas**: 15 min, 30 min, 1 h, 24 h, 36 h y 48 h; 48 h es el máximo. Revocable. Cada apertura va a la
  bitácora.
- **Reporte**: página de informe con estilos de impresión; «Imprimir o guardar PDF» usa el navegador. Sin
  dependencias nuevas.
- **Informe 7.7**: RH captura objetivo, actividades, conclusiones, recomendaciones y responsable (nombre y cédula, la
  cédula opcional: «en su caso»). Lo demás se arma solo.
- **Mínimo de respondentes**: 5 para todas las empresas (`config('results.min_group')`).

Decisiones del implementador (revisables):

- **Los resultados de II/III solo existen con la evaluación cerrada.** Mientras está abierta, RH ve quién contestó;
  si además viera los resultados moverse, sabría qué contestó la última persona. Al cerrar ya no cambian.
- **Supresión complementaria.** Un área se muestra solo si tiene al menos 5 cuestionarios **y** lo que queda oculto
  suma 0 o al menos 5; si no, se ocultan también las áreas visibles más chicas hasta cumplirlo. Sin esto, «centro
  menos áreas visibles» revelaría al grupo pequeño.
- **Filtro por área**: aplica a la calificación final, las categorías y los dominios, y solo con áreas visibles. Las
  preguntas se muestran solo del centro completo (por área, la resta entre centro y área revelaría grupos pequeños).
- **Una pregunta** se muestra solo si la contestaron al menos 5 (las de los bloques filtro, clientes y ser jefe, las
  contesta una parte).
- **Dimensiones**: no se muestran. La norma no les da niveles y sumarlas es lo que la STPS prohíbe (C-14).
- **Guía I**: la ven owner y RH (permiso `view-trauma-screenings`); el rol de consulta no. Está disponible desde el
  lanzamiento: es identificada a propósito y la canalización no debe esperar al cierre.
- **Consulta de los trabajadores (7.8)**: el informe imprimible es lo que se publica o entrega; no hay acceso
  propio para trabajadores en este trabajo.
- **Enlace inválido, vencido o revocado** da la misma página «no disponible» (404), que no nombra a la empresa. Una
  empresa bloqueada tampoco abre su enlace.

## Lo que ve cada quien

| | owner | RH | consulta | enlace temporal |
|---|---|---|---|---|
| Resultados II/III (agregados y granulares) | sí | sí | sí | solo el informe |
| Informe 7.7 | sí, lo edita | sí, lo edita | sí | sí |
| Guía I: conteos y lista de canalización | sí | sí | no | no |
| Generar y revocar enlaces | sí | sí | no | — |

## Arquitectura

Nuevo módulo `Results`. Lee de otros módulos solo por sus contratos:

- `Campaigns\Contracts\CampaignRecords::find(id): ?CampaignRecord`: estado, guías, plantilla, muestra, fechas y
  conteos de invitados y contestados por guía.
- `Responses\Contracts\ResponseReader`: las respuestas anónimas de una guía en una campaña (área y respuestas, nada
  más), los conteos de Guía I y la página de tamizajes que requieren valoración.
- `Tenancy\Contracts\WorkCenters::profile(id): ?WorkCenterProfile`: razón social, nombre, domicilio y actividad
  principal (7.7 a).
- `Instruments\Contracts\Instruments`: calificar cada cuestionario y los textos de las preguntas.
- `Organization\Contracts\Roster::findMany`: nombres y áreas de la lista de canalización.

Tablas (con `tenant_id` y llaves compuestas, como las demás):

- `result_reports`: lo que RH captura del informe, uno por campaña.
- `result_shares`: enlaces temporales. Solo el hash del token (sha256), `expires_at`, `revoked_at`, `created_by`.
  Un `check` limita la vigencia a 48 horas.

Un único servicio (`Results\Support\RiskDistribution`) arma todo agregado y aplica el mínimo y la supresión
complementaria. Las páginas y el informe solo pasan por él.

## Rutas

Dentro de la empresa (`/admin/{tenant}`, `routes/results.php`):

- `GET campaigns/{campaign}/results` → `results/show` (filtro `?area=`).
- `GET campaigns/{campaign}/report` → `results/report`; `PUT` guarda lo capturado (manage-campaigns).
- `GET campaigns/{campaign}/screenings` → `results/screenings` (view-trauma-screenings), paginada.
- `POST campaigns/{campaign}/shares`; `DELETE campaigns/{campaign}/shares/{share}` (manage-campaigns).

Pública: `GET /report/{token}` → `results/shared-report` o `results/share-unavailable`. Con throttle, `noindex` y en
la lista pública de `RouteSecurityTest`.

## Bitácora y logs

Van a la bitácora: `results.viewed`, `results.report_viewed`, `results.screenings_viewed`, `results.share_created`,
`results.share_revoked` y `results.share_opened`. A los logs van `result_report.saved`, `result_share.created` y
`result_share.revoked`. Nunca una respuesta, nunca el token.

## Pruebas

- Mínimo de 5 en 4/5, supresión complementaria (10+2, 5+5+2, 6+3+3), pregunta con 4/5 respuestas y filtro por un
  área oculta.
- Distribuciones contra respuestas armadas a mano, cada nivel.
- Otra empresa da 404 en cada ruta; el rol de consulta no ve la Guía I (404 en la lista, sin conteos en las props).
- Las props de cada página no traen respuestas (`answers`) ni ids de respuestas.
- Enlace: 15 min y 48 h exactos, uno vencido, uno revocado, uno de otra empresa, una empresa bloqueada y una duración
  fuera del catálogo.
- Número de consultas fijo en la página de resultados.
- Navegador: cada página nueva con `visitPage()`.
