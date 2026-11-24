# Manual de operación: SSR de la landing y medición

Para quien opera Humetría en Dokploy. Todo lo de aquí se comprobó contra los archivos del repositorio; lo que solo se puede
ver en producción está en la última sección, como verificación manual. El diseño completo está en
`docs/superpowers/specs/2026-11-14-landing-seo-design-v2.md`.

## Índice

1. [Qué corre en producción](#1-qué-corre-en-producción)
2. [Variables](#2-variables)
3. [Primer encendido, en orden](#3-primer-encendido-en-orden)
4. [Apagar y encender el SSR](#4-apagar-y-encender-el-ssr)
5. [Despliegue y rollback](#5-despliegue-y-rollback)
6. [Qué significa cada señal](#6-qué-significa-cada-señal)
7. [Qué hacer ante `ssr.render_failed`](#7-qué-hacer-ante-ssrrender_failed)
8. [Medir con PostHog](#8-medir-con-posthog)
9. [Verificaciones manuales pendientes](#9-verificaciones-manuales-pendientes)

## 1. Qué corre en producción

`compose.production.yaml` tiene cinco servicios. Los que importan aquí:

- `web`, `horizon` y `scheduler`: la imagen `ghcr.io/vicbravodev/humetria` (objetivo `app` del `Dockerfile`).
- `ssr`: la imagen `ghcr.io/vicbravodev/humetria-ssr` (objetivo `ssr`). Node ejecuta el bundle de Inertia; no tiene `.env`, base de
  datos, puertos ni Traefik, y corre como usuario `node`, de solo lectura, con 384 MB y sin permisos extra. Vive en la red
  `ssr-internal` (`internal: true`), que solo comparte con `web`; `web` lo alcanza por el alias `humetria-ssr` en el puerto 13714.
- Las dos imágenes se construyen del mismo commit en `.github/workflows/deploy.yml` y llevan dos etiquetas: `latest` y `sha-<commit>`.
  Las dos van siempre a la misma `HUMETRIA_TAG`.

Solo se renderiza en Node lo que dice `App\Http\SsrPages` (la landing, el aviso de privacidad, cada herramienta, la guía y el blog) y solo para visitantes sin sesión.
Si Node no responde, la página sale igual: `web` responde 200 con las etiquetas del `<head>` y React la pinta en el navegador.
`web` no depende de `ssr`: nunca se cae por él.

El blog (`blog.index` y `blog.show`) sí pasa por Node, igual que la landing y las páginas de cada herramienta: son páginas de Inertia
con el header y el pie de la landing, y el texto del artículo sale en el HTML que Node renderiza (`tests/Ssr/BlogSsrTest.php`).
Si Node no responde, la página sale con las etiquetas del `<head>` (incluido el JSON-LD `Article`, con sus fechas) y React la pinta
en el navegador: ese es el único momento en que un rastreador sin JavaScript no vería el cuerpo del artículo. El roadmap es una
página de Inertia sin SSR.

## 2. Variables

Se ponen en la pestaña Environment del servicio de Dokploy. La plantilla es `.env.production.example`; la lista completa, `.env.example`.

| Variable | Valor en producción | Qué hace |
| :-- | :-- | :-- |
| `INERTIA_SSR_ENABLED` | `true` | Interruptor del SSR (`config/inertia.php`). Por omisión es `false`. |
| `INERTIA_SSR_URL` | `http://humetria-ssr:13714` | Dónde está Node, por la red interna. |
| `INERTIA_SSR_TIMEOUT` | `2` | Segundos que `web` espera a Node antes de servir sin el render. Un render real tarda unos 5 ms. |
| `INERTIA_SSR_ENSURE_BUNDLE` | `false` | **Obligatoria en `web`.** El bundle vive en la imagen `ssr`, no en `web`; con `true` (el valor por omisión) Inertia no encuentra `bootstrap/ssr` y nunca llama a Node, sin avisar. |
| `HUMETRIA_TAG` | vacía (`latest`) o `sha-<commit>` | Etiqueta de las dos imágenes. Ver la sección 5. |
| `HUMETRIA_IMAGE` | omitida | Repositorio de la imagen `app`, sin etiqueta. |
| `HUMETRIA_SSR_IMAGE` | omitida | Repositorio de la imagen `ssr`, sin etiqueta. |
| `HUMETRIA_PULL_POLICY` | omitida (`always`) | Política de descarga de las dos imágenes. |
| `POSTHOG_PROJECT_TOKEN` | vacía hasta la sección 8 | Token público de proyecto de PostHog (`phc_…`), nunca la llave personal (`phx_…`). |
| `POSTHOG_CONSENT_DEFAULT` | `denied` | Mientras sea `denied` no hay medición ni la cookie `humetria_acq`. |

Ojo con el nombre: el paquete de Inertia lee `INERTIA_SSR_ENSURE_BUNDLE_EXISTS`, pero `config/inertia.php` del proyecto lee
`INERTIA_SSR_ENSURE_BUNDLE`, y esa es la que vale. Poner la otra no hace nada.

Variables que no son de este servicio pero intervienen: `APP_URL` (Q12 del diseño: sigue sin confirmar el valor en Dokploy; solo
bloquea la redirección de `www`) y el secreto de GitHub `DOKPLOY_WEBHOOK_URL`, el webhook "Deploy" de Dokploy: sin él, `deploy.yml`
publica las imágenes pero no redespliega ni comprueba el sitio. El paso lo llama como un push de GitHub a `main` y falla si Dokploy
no responde «Compose deployed successfully»: un POST vacío recibe «Branch Not Match» y no despliega nada.

## 3. Primer encendido, en orden

1. En Dokploy, con el servicio detenido o antes de fusionar: agrega a Environment `INERTIA_SSR_URL`, `INERTIA_SSR_TIMEOUT`,
   `INERTIA_SSR_ENSURE_BUNDLE=false`, `POSTHOG_CONSENT_DEFAULT=denied` y `POSTHOG_PROJECT_TOKEN` vacía.
2. Agrega al final `INERTIA_SSR_ENABLED=true`. Se puede dejar para después (en `false`, o sin definir, la landing se pinta en el
   navegador como antes), pero entonces el paso de comprobación de `deploy.yml` fallará hasta que se encienda: espera
   `X-Rendered-By: ssr`. Con `true` desde el primer despliegue no hay ventana: si `ssr` aún no está, `web` sirve sin el render.
3. Fusiona a `main`. Con el CI en verde, `deploy.yml` construye y publica las dos imágenes, llama al webhook de Dokploy y corre
   `node scripts/synthetic/check-landing.mjs --expect-revision <commit> --wait 600`.
4. Dokploy reemplaza los contenedores. `web` migra al arrancar; `ssr` tarda hasta 20 segundos en pasar a `healthy`.
5. Mira la pestaña Logs de `web`: una línea `ssr.rendered` por cada visita anónima a `/`.
6. `.github/workflows/synthetic.yml` empieza a correr solo cada 15 minutos; no hay que activarlo.

## 4. Apagar y encender el SSR

- **Apagar**: `INERTIA_SSR_ENABLED=false` en Environment y reiniciar `web` (la configuración se cachea al arrancar; `horizon` y
  `scheduler` no hacen falta). La landing vuelve a pintarse en el navegador; `X-Rendered-By` pasa a `csr`.
- **Efecto secundario esperado**: `check-landing.mjs` exige `X-Rendered-By: ssr`, así que `synthetic.yml` falla cada 15 minutos
  mientras esté apagado y GitHub manda correo. Si el apagado va a durar, deshabilita el flujo `synthetic` en la pestaña Actions
  y vuelve a habilitarlo al encender.
- **Encender**: `INERTIA_SSR_ENABLED=true` y reiniciar `web`.
- Apagar el contenedor `ssr` sin tocar `web` también es seguro: la primera visita falla (`ssr.render_failed`, tipo `connection`),
  abre el cortacircuitos y durante 30 segundos ninguna visita intenta Node.

## 5. Despliegue y rollback

Cada merge a `main` con el CI en verde publica `latest` y `sha-<commit>` para `humetria` y `humetria-ssr`, y Dokploy descarga `latest`.
Durante el reemplazo hay unos segundos con `web` y `ssr` de versiones distintas: React se recupera y la comprobación sintética lo
ve hasta que `build-revision` (Node) y `app-revision` (PHP) coinciden.

**Rollback**: en Environment pon `HUMETRIA_TAG=sha-<commit anterior>` (un commit que ya publicó `deploy.yml`, con las dos imágenes) y
despliega. `web`, `horizon`, `scheduler` y `ssr` vuelven juntos a esa versión. Comprueba que `build-revision` y `app-revision` de
`https://humetria.com.mx/` sean ese commit. Mientras `HUMETRIA_TAG` esté fija, los siguientes merges publican imágenes nuevas
pero no se despliegan, y el paso de comprobación de `deploy.yml` falla por la revisión: es lo esperado. Para volver a avanzar,
borra `HUMETRIA_TAG` (o pon `latest`) y despliega.

No se puede volver a un commit anterior a la primera imagen `humetria-ssr`: esa etiqueta no existe. Ante la duda, apaga el SSR
(sección 4) en lugar de fijar una etiqueta.

## 6. Qué significa cada señal

**`X-Rendered-By`** (solo en `/` y `/privacy-notice`; `App\Http\Middleware\AnnounceRenderMode`):

| Valor | Significa |
| :-- | :-- |
| `ssr` | La respuesta trae el render de Node. |
| `csr` | No lo trae: el SSR está apagado, Node falló o se saltó por el cortacircuitos, o quien pide tiene sesión (siempre `csr`: es lo normal). |

Para comprobarlo: `curl -sI https://humetria.com.mx/ | grep -i x-rendered-by`, sin cookies.

**Logs** (JSON a stderr; pestaña Logs de `web` en Dokploy):

| Línea | Cuándo | Contexto |
| :-- | :-- | :-- |
| `ssr.rendered` | Node respondió a un render (nivel info, una por visita). | `status`, `duration_ms`. Lo normal: unos 5 ms. |
| `ssr.render_failed` | Falló un render (nivel warning). Una línea por tipo y minuto; las demás suman al contador `ssr:failed:count` del caché. | `type` (`connection`, `render`, `component-resolution`, `browser-api`, `unknown`), `component`, `path` sin query, `hint`. Nunca props, pila ni cookies. |

Un `connection` además abre el cortacircuitos (`ssr:circuit-open` en el caché, 30 segundos): en ese lapso no hay intentos ni líneas, y
`X-Rendered-By` es `csr`. Un `ssr.rendered` con `status` 4xx o 5xx tampoco trae render.

**Healthcheck de `ssr`**: `docker ps` o Dokploy muestran `healthy` cuando Node renderiza de verdad la landing (el `<h1>` y las nueve
herramientas), no solo cuando el puerto está abierto. Docker no reinicia un contenedor solo por estar `unhealthy`: lo hace el
vigilante de la imagen (`scripts/ssr/supervisor.mjs`), que repite el render cada 30 segundos y, tras tres fallos seguidos, sale con
código 1 para que `restart: unless-stopped` lo levante. Sus líneas (`ssr probe failed…`, `ssr bundle exited…`) salen en los logs de `ssr`.

**Comprobación externa**: `scripts/synthetic/check-landing.mjs`, desde GitHub Actions, no desde el servidor.

- `deploy.yml` la corre después de cada despliegue con `--expect-revision <commit> --wait 600` (reintenta hasta 10 minutos).
- `synthetic.yml` la corre cada 15 minutos. Es la única alarma del SSR: Node fallando no se nota en el sitio por diseño. Si falla,
  GitHub manda correo, con una línea por problema.
- Comprueba: 200, `X-Rendered-By: ssr`, sin `X-Robots-Tag` en `/`, el `<h1>`, las nueve herramientas en el HTML, canonical, `og:*`, y que
  `build-revision` y `app-revision` sean iguales (y el commit esperado).
- A mano: `node scripts/synthetic/check-landing.mjs --url https://humetria.com.mx/`.

## 7. Qué hacer ante `ssr.render_failed`

1. Ve el `type` en los logs de `web`. Si hay `connection`: mira `ssr` (`docker ps`, logs de `ssr`).
   - `unhealthy` o reiniciándose: el vigilante lo está levantando; espera un minuto y revisa que `X-Rendered-By` vuelva a `ssr`.
   - No existe o no arranca: probablemente la imagen equivocada (por ejemplo la `app` en el servicio `ssr`: no tiene el bundle) o
     una `HUMETRIA_TAG` que no existe para `humetria-ssr`. Corrige y despliega. Mientras tanto la landing funciona sin el render.
2. `render`, `component-resolution` o `unknown` apuntan a un bundle de otra versión que `web` (revisa `build-revision` contra
   `app-revision`) o a un error del código. Despliega de nuevo las dos imágenes del mismo commit o haz rollback (sección 5).
3. Si no se resuelve rápido: apaga el SSR (sección 4). La landing sigue sirviéndose.
4. Para ver cuántos fallos se acumularon: `sail artisan tinker` → `Cache::get('ssr:failed:count')` (en producción, `php artisan tinker` en `web`).

## 8. Medir con PostHog

Con `POSTHOG_PROJECT_TOKEN` (`phc_…`) y `POSTHOG_CONSENT_DEFAULT=granted` la landing mide a quien no ha iniciado sesión; con
`denied` (el valor por omisión) el documento no lleva el bloque `analytics-config`, `posthog-js` no se descarga, la CSP no abre el
origen de PostHog y no se escribe la cookie `humetria_acq`. Solo las páginas públicas medidas (la landing, la página de cada
herramienta y la guía de jornada: `Analytics::measuredRoutes()`): el panel, las encuestas y los portales no cargan PostHog.
El servidor reporta sus errores con solo el token (ver "Errores").

### Reglas de privacidad de la medición

- PostHog (nube US, `posthog-js` en su build, sin scripts externos) arranca solo en el documento de la landing
  (`App\Http\Analytics::measuredRoutes()`: `home`, `tools.*` y `guides.workday-2027`, sin sesión, token `phc_…` válido) y solo si `App\Http\Consent` lo permite.
- Sin autocapture, sin grabación de sesión (salvo `POSTHOG_LANDING_REPLAY`, abajo), sin encuestas ni flags (salvo, con `POSTHOG_LANDING_REPLAY`, la petición que trae la configuración de replay del proyecto), sin perfil de persona, sin cookie propia (almacenamiento
  local) y sin IP. A PostHog no se le manda nada de la persona: la dirección sin más query que las `utm_*` y, de un referente de
  este sitio, solo su origen (`resources/js/lib/analytics.ts` lo impone en `before_send`).
- Solo los eventos del catálogo de abajo; uno nuevo se agrega aquí. Un solo escucha de clics (`use-landing-measurement`).
  Medir el panel (`/admin`), las encuestas o los portales es un trabajo aparte con su revisión de privacidad.
- Excepción: la grabación de la visita a la landing y la medición de su velocidad, solo con `POSTHOG_LANDING_REPLAY=true` (el
  proyecto necesita Session Replay encendido). El servidor lo avisa con `replay: true` en el bloque `analytics-config`, que ya solo
  existe con token, consentimiento `granted` y sin sesión (nadie con cuenta se graba en la landing), y abre `us-assets.i.posthog.com`
  en `connect-src` para leer la configuración de replay del proyecto. Esa configuración llega con la petición de flags, así que con replay la
  landing deja `advanced_disable_flags` en `false`: apagada, el grabador no arranca y cada evento trae `$recording_status: disabled`. Con la bandera encendida la landing descarga, **después del evento
  `load`** para no competir con el primer pintado, la build `posthog-js/full/no-external` (trae el grabador y `web-vitals`; la ligera
  no trae ninguno), con una sola instancia y un solo `$session_id` para que cada grabación se ligue a sus eventos. Graba anónimo
  (`person_profiles: 'never'`, sin IP, sin consola, sin cabeceras ni cuerpos de red, `maskAllInputs: true`: lo escrito en el formulario
  de demo sale enmascarado) y manda `$web_vitals` (LCP, INP, CLS, FCP) sin `network_timing`. El texto de la landing es público y no se
  enmascara; un bloque que no deba verse lleva la clase `ph-no-capture`. Cambiar esto exige actualizar el aviso de privacidad
  (`tests/Feature/PrivacyNoticeMatchesCodeTest.php`).
- Excepción: la grabación anónima del panel de una empresa (`resources/js/lib/session-recording.ts`), solo con
  `POSTHOG_SESSION_RECORDING=true`. Una segunda instancia de PostHog (`posthog-js/full/no-external`, el grabador dentro del
  bundle) que se descarga en la primera pantalla grabable: hay empresa, sesión propia y nadie suplanta, y la pantalla no está
  en `UNRECORDED` (quejas, Guía I por acontecimiento, lista de Guía I, Humi, cuenta personal); el dock de Humi lleva
  `ph-no-capture`. Muestra la interfaz y oculta los datos de la empresa (`resources/js/lib/recording-mask.ts`): todo texto que
  contenga un valor de las props de la página (nombres, empresa, quien inició sesión), lo que tenga forma de correo, teléfono,
  CURP, RFC o monto, las opciones de búsqueda y lo marcado `ph-mask` (las iniciales); además todos los campos y todo atributo
  que no sea de forma (`maskAttribute`); el reproductor
  ve la pantalla por su componente, no por su dirección; solo sale `$snapshot`, con id aleatorio en memoria, sin perfil ni
  IP. La CSP abre en `connect-src` `us-assets.i.posthog.com` para leer la configuración de replay del proyecto (JSON).
- La procedencia de una solicitud de demo vive en la sesión (`RememberDemoAttribution`: `utm_*` y el host del referente externo,
  nunca una URL). Con consentimiento `granted` también el identificador del último clic en un anuncio (`gclid`, `gbraid` o
  `wbraid`), que va a la solicitud y se borra a los 90 días (`demos:forget-ad-clicks`, cada noche). La cookie `humetria_acq` (cifrada, 90 días) solo se escribe y se lee con
  consentimiento `granted`. Lo que diga el aviso de privacidad sobre cookies coincide con el código
  (`tests/Feature/PrivacyNoticeMatchesCodeTest.php`).
- Todo enlace que sale de la landing o llega a ella es un `<a href>`, no un `<Link>` de Inertia (salvo el logo de la cabecera,
  que va a la misma página): PostHog y su CSP viven en un documento, no en una visita de Inertia.
- Un `report()` nuevo en `bootstrap/app.php` va después del de PostHog (ver "Errores").

### Eventos de la landing

Todos sin datos de la persona; sus propiedades son identificadores o números (`cleanProperties`). En el marcado se declaran con
`data-track="evento"` y `data-track-target="id"`.

| Evento | Propiedad | Cuándo |
|--------|-----------|--------|
| `$pageview` | `$current_url` limpia | Al abrir la landing |
| `cta_click` | `target`: `header_demo`, `hero_demo`, `hero_how_it_works`, `sticky_demo`, `guide_tool`, `guide_demo`, `pricing_demo` | Clic en un llamado a la demo o a "cómo funciona" |
| `tool_page_click` | `target`: herramienta | Clic en la tarjeta de una herramienta (landing) o en otra herramienta (su página) |
| `nav_click` | `target`: id de la sección | Clic en el menú de la cabecera |
| `sign_in_click` | `target`: `header` | Clic en "Iniciar sesión" |
| `family_click` | `target`: familia | Familia en el héroe |
| `film_control`, `film_chapter`, `video_control` | `target` | Controles de los videos |
| `privacy_area_selected` | `target`: área | Demo de privacidad |
| `privacy_notice_click` | `target`: `footer`, `compliance`, `demo_form` | Clic hacia el aviso de privacidad |
| `section_viewed` | `section`: id | Cada sección vista (40 % visible), una vez |
| `scroll_depth` | `percent`: 25, 50, 75, 100 | Cada profundidad, una vez |
| `demo_form_started` | — | Primer foco en el formulario |
| `demo_form_submitted` | — | Cada envío |
| `demo_form_error` | `field`: nombre del campo | Un campo que el servidor rechazó |
| `generate_lead` | — | La solicitud quedó guardada (navegador, una vez por solicitud) |
| `demo_request_stored` | `profile`, `workforce`, `tools_count`, `utm_*`, `referrer_host` | Lo mismo, dicho por el servidor: no lo frena un bloqueador |

Con `POSTHOG_LANDING_REPLAY` también salen `$snapshot` (la grabación) y `$web_vitals` (`$web_vitals_LCP_value`, `INP`, `CLS`, `FCP`);
ninguno lleva datos de la persona. Para ver dónde se cae la gente: en PostHog, el embudo «Landing → demo» (`$pageview` → `scroll_depth`
25 % → `demo_form_started` → `demo_request_stored`) y, para el primer pintado, `$web_vitals_LCP_value` por dispositivo.

`generate_lead` y `demo_request_stored` cuentan lo mismo; la diferencia entre ambos es lo que bloquean los navegadores. La conversión
principal del embudo es `demo_request_stored`.

### Enlaces con campaña (UTM)

Sin UTM, PostHog ve la visita como directa y no hay forma de saber qué anuncio o publicación trajo cada demo. Todo enlace a la
landing que se difunda (anuncios, correos, publicaciones, firma) lleva `utm_source` (dónde: `google`, `linkedin`, `correo`),
`utm_medium` (cómo: `cpc`, `social`, `email`) y `utm_campaign` (qué: `nom-035`, `registro-de-jornada-2027`), en minúsculas y con
guiones; `utm_term` y `utm_content` los pone Google Ads con su sufijo de URL final. Solo esas llegan a PostHog
(`Analytics::pageLocation`). Una solicitud de prueba lleva `utm_medium=test` para poder excluirla de los números.

### Conversiones para Google Ads

Google Ads aprende de las demos por importación de clics fuera de línea, sin etiqueta de Google en la landing:

1. La solicitud guarda el identificador del último clic (ver arriba); el `gclid` solo llega con el etiquetado automático de la cuenta.
2. En la consola de demos, **Conversiones para Google Ads (CSV)** descarga todas las solicitudes con `gclid` de los últimos 90 días,
   con la hora de la Ciudad de México y el nombre de `GOOGLE_ADS_CONVERSION_NAME` (debe ser idéntico al de la acción de conversión).
3. Se sube en Google Ads → Objetivos → Conversiones → Cargas. Subir el archivo entero cada semana no duplica: Google descarta la
   conversión con el mismo clic, nombre y hora.
4. Los clics de iOS que llegan con `gbraid` o `wbraid` y sin `gclid` (frecuentes en Demand Gen) salen en otro archivo,
   **Conversiones de iOS (CSV)**, con las columnas `GBRAID` y `WBRAID` y un solo identificador por fila. Confirma en la primera carga
   que Google lo acepta; si no, súbelo por Data Manager, que sí toma esas columnas.
5. Una solicitud de prueba con un `gclid` inventado se borra antes de exportar: Google la rechaza, pero ensucia el archivo.
6. La solicitud guarda además la palabra clave del sufijo de URL final (`utm_term`, `utm_content` y `matchtype`), y el evento
   `demo_request_stored` la lleva, para saber qué palabra trajo cada demo. PostHog conserva `matchtype` en la dirección del pageview.

### Páginas por herramienta

Cada herramienta tiene su página (`App\Http\ToolPages`: `/registro-de-jornada`, `/nom-035`, `/nom-037`, `/ley-silla`, `/buzon-de-quejas`,
`/compensacion`, `/vacaciones-y-permisos`, `/organigrama`, `/clima-laboral`) y hay una guía, `/guias/registro-de-jornada-2027`. Su texto
vive en `App\Http\ToolContent\*` y lo legal sale de `docs/normas/`. Se renderizan en Node, son indexables, están en `sitemap.xml`,
guardan la procedencia como la landing y se miden con los mismos eventos. Su JSON-LD: `SoftwareApplication`, `FAQPage`, `VideoObject`
y `BreadcrumbList` (la guía, `Article`). El formulario de demo de cada una vuelve a la misma página (campo `page`).

Cada página lleva capturas reales de la empresa demo en `public/images/tools` (`{captura}-light.webp` y `-dark.webp`; las del
celular, `{captura}-mobile.webp`), 1520×1100 recortadas al área de contenido. Para refrescarlas: prepara la demo dentro de una prueba
de navegador temporal, toma las pantallas a 1440×900 en claro y oscuro, recórtalas sin la barra lateral y conviértelas a WebP.

### Errores

- **Navegador** (solo la landing): `window.onerror` y las promesas sin atrapar llegan como `$exception` con el mensaje enmascarado
  (correos y números largos) y a lo sumo 200 caracteres. PostHog → Error tracking.
- **Servidor**: todo lo que Laravel reporta (también los `QueryException` y fallos de correo, y los jobs fallidos) sale como
  `$exception` desde `App\Support\PostHogExceptions`, por la cola y a mejor esfuerzo: clase, archivo, línea, traza sin argumentos,
  `request_id` y nombre de ruta. Nunca el mensaje, la URL ni el usuario. Si PostHog falla queda `posthog.send_failed` en el log y nada más.
- El orden en `bootstrap/app.php` importa: el reporte a PostHog va antes de los `report()` que devuelven `false`.

### Poner o comprobar la medición

1. Que el aviso de `/privacy-notice` esté revisado y publicado (sus secciones siguen marcadas "pendiente de revisión legal").
2. En Environment: `POSTHOG_PROJECT_TOKEN=phc_…` (el token público, nunca la llave personal `phx_…`) y `POSTHOG_CONSENT_DEFAULT=granted`;
   reinicia `web` y `horizon` (la cola envía los errores del servidor).
3. En la landing sin sesión, con la consola abierta: aparece el bloque `analytics-config`, sin violaciones de la CSP, la única conexión
   es `us.i.posthog.com` y no se escribe ninguna cookie de PostHog (usa el almacenamiento local). Con sesión o en `/admin/login`, nada.
4. En PostHog → Activity: llega `$pageview` con `$current_url` sin más query que las `utm_*` y sin `$referrer` de otro sitio. Navega,
   baja y pide una demo de prueba: deben aparecer `cta_click`, `section_viewed`, `scroll_depth`, `generate_lead` y `demo_request_stored`.
5. Errores: en el navegador, `setTimeout(() => { throw new Error('prueba') })` en la consola de la landing debe aparecer en Error tracking.

Con `granted`, la medición es la decisión del sitio y no hay banner: el visitante no elige. Si el abogado pide que sí elija, es un
trabajo nuevo (`App\Http\Consent` es el único lugar donde se leería esa decisión). Para volver atrás: `denied` y reiniciar `web`
(los errores del servidor siguen saliendo mientras haya token: se apagan dejando `POSTHOG_PROJECT_TOKEN` vacío).

La procedencia de una demo se guarda solo en la sesión (120 minutos de inactividad); quien llega por un anuncio y pide la demo días
después aparece como "Sin dato", lo que no significa tráfico directo.

## 9. Verificaciones manuales pendientes

Docker Compose no corre en el CI del repositorio. Estas verificaciones se hacen a mano, en este orden, antes del primer lanzamiento y
tras cada cambio de `compose.production.yaml`:

1. **Variables y despliegue** (sección 3). El paso de `deploy.yml` en verde; `curl -sI https://humetria.com.mx/` con `x-rendered-by: ssr`.
2. **Revisiones**: las metas `build-revision` y `app-revision` del HTML de `/` son el commit desplegado.
3. **Red**: desde `web`, `ssr` responde (`docker exec <web> curl -fsS http://humetria-ssr:13714/health`); desde `ssr`, no sale a internet;
   Traefik no tiene ruta al servicio `ssr` (pestaña Domains solo con `web`).
4. **Reinicio de `ssr`** (`docker restart <ssr>`): unos segundos de `csr` y vuelta a `ssr` sin intervención.
5. **Imagen equivocada** (arranca el servicio `ssr` con la imagen `humetria` a propósito, en un entorno de prueba): `ssr` no arranca y `web`
   sigue sirviendo en `csr`, sin 5xx.
6. **Rollback** con `HUMETRIA_TAG=sha-<commit anterior>`: las dos imágenes vuelven juntas.
7. **Memoria**: `docker stats` de `ssr`, esperado menos de 200 MB en reposo (el límite es 384).
8. **Redirección de `www`**: `curl -sI https://www.humetria.com.mx/` da 301 a `https://humetria.com.mx/` conservando ruta y query. Depende de Q12 (`APP_URL`).
   Redirigir `www` invalida las llaves de acceso (passkeys) registradas en ese host: confírmalo antes.
9. **Logs del proxy**: antes de activar PostHog, revisa que los logs de nginx y de Traefik no guarden la cookie `humetria_acq`.
10. **Vistas previas**: depurador de LinkedIn, el de Meta y WhatsApp con `https://humetria.com.mx/`.
11. **Sin JavaScript**: `lynx -dump https://humetria.com.mx/` lee el título, la descripción y las nueve herramientas.
12. **Search Console**: inspecciona la URL, confirma que es indexable y envía `https://humetria.com.mx/sitemap.xml`. Es la única comprobación de que Google
    indexa; nada de lo anterior la sustituye.
13. **Alerta**: dispara `synthetic.yml` a mano (Actions, Run workflow) y confirma que corre en verde; con el SSR apagado de prueba, que falla y llega el correo.
