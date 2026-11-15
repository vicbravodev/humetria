# SEO, vista previa al compartir y medición de la landing

* **Author:** Victor Jesus Bravo de la Peña
* **Date:** 2026-11-14
* **Estado:** revisado por los siete Goldfishes. El dueño tomó las opciones por defecto el 2026-11-14. Queda un dato por confirmar: el valor de `APP_URL` en producción.

## Overview

La landing de `humetria.com.mx` es la única página pública de marketing. Hoy el servidor la entrega vacía: `<title>Humetría</title>`, sin descripción, sin `<h1>`, sin imagen para compartir, sin canonical, sin datos estructurados y sin sitemap. El contenido solo existe después de ejecutar JavaScript.

**Hipótesis.** La landing, más una página nueva de aviso de privacidad, debe llegar a buscadores, redes sociales y asistentes de IA con su título, descripción, imagen para compartir, canonical, datos estructurados y sitemap. `www` redirige al dominio sin `www`. Todo lo demás lleva `noindex`. Google Analytics 4 carga solo en la landing. Cada solicitud de demo guarda de dónde llegó la persona. Los píxeles de Meta y LinkedIn quedan fuera.

**Para quién.** El dueño, que va a pagar anuncios y quiere saber cómo llega la gente y si regresa, y que el enlace se vea profesional en cualquier red o asistente de IA.

**Lo que el dueño ya decidió.** Solo la landing actual, sin páginas nuevas por herramienta. Ninguna página con token debe indexarse. Acepta un proceso Node más en producción y dependencias nuevas si hacen falta.

## Hechos verificados

Leídos en el código o comprobados con `curl` en local. Nadie corrió un build de SSR ni comprobó nada contra producción.

- `curl http://localhost/` devuelve el HTML sin título propio, sin descripción y sin `<h1>`. El título y la descripción se ponen en el cliente (`resources/js/pages/landing.tsx:23-28`).
- `config/inertia.php:21` dice `ssr.enabled => true`, pero no hay bundle de SSR y ni `Dockerfile` ni `compose.production.yaml` arrancan ese proceso.
- `public/robots.txt` permite todo y no apunta a un sitemap.
- Hay 78 nombres de ruta sin autenticación (`tests/Feature/RouteSecurityTest.php`). La cabecera `X-Robots-Tag: noindex` ya se pone a mano en cuatro respuestas y en una meta de `resources/views/app.blade.php:7-9`. Login, encuestas, portales y la pantalla del reloj no llevan nada.
- Todas las respuestas de Laravel pasan por `app/Http/Middleware/SecurityHeaders.php`, con una CSP basada en nonce. Los archivos estáticos (`robots.txt`, `/video/*`, `/build/*`) los sirve nginx y no pasan por ahí.
- "Iniciar sesión" en la landing es un `<Link>` de Inertia (`landing-header.tsx:57`, `landing-footer.tsx:45`): se pasa al login sin recargar el documento.
- El formulario de demo dice "Usamos tus datos solo para contactarte." y no enlaza ningún aviso de privacidad (`demo-request-form.tsx:119-121`).
- Existe un brief de rediseño de la landing sin commitear (`docs/rediseno-landing/BRIEF.md`).

## Analysis

### Lo que la revisión cambió de la hipótesis

1. **La cabecera (`<head>`) debe salir de Blade, no del proceso Node.** Los siete revisores coinciden. Si título, descripción e imagen dependen del SSR, una caída de Node deja la página sin etiquetas y sin aviso: `HttpGateway.php:63-88` devuelve `null` ante cualquier fallo y el respaldo es `<title>Humetría</title>`. Con las etiquetas en `app.blade.php`, "se ve profesional al compartir" queda resuelto sin Node. La descripción se quita de `landing.tsx` para no tener dos. El canonical y `og:url` salen de `config('app.url')` sin query, porque los anuncios llegan con `?utm_…`.

2. **`noindex` debe ser la regla y la landing la excepción.** Los siete coinciden. `SecurityHeaders` pone `X-Robots-Tag: noindex, nofollow` en toda respuesta, salvo las rutas de una lista corta. Así una ruta nueva no puede olvidarlo. `robots.txt` no bloquea nada: si bloqueara el rastreo, el robot no leería el `noindex`, y el archivo publicaría los prefijos de las URLs con token.

3. **Una sola lista de páginas indexables.** La misma lista decide qué no lleva `noindex`, qué va en el sitemap, dónde carga Analytics y qué pasa por SSR. Cuatro listas separadas se desincronizan. El patrón ya existe en `app/Http/PageFrame.php` con su prueba.

4. **"Analytics solo en la landing" no se cumple con la navegación actual.** Como el paso al login no recarga el documento, el script de Google y la CSP abierta seguirían vivos en el login y en todo `/admin`, y Analytics podría enviar URLs con el nombre de la empresa. El arreglo: los dos enlaces de salida de la landing pasan a `<a href>` normal, el script lo emite Blade con nonce solo en la ruta `home`, y los orígenes de Google entran a la CSP solo ahí.

5. **El referrer puede ser un enlace con token.** Una invitación o un reporte vencido ofrece un enlace al inicio. Si la landing lee `document.referrer`, puede ser la URL con token, y acabaría en Google o guardada junto al nombre de quien pide la demo. El arreglo: guardar solo el host y solo si es externo, y pasar el referrer vacío a Analytics cuando viene del propio dominio.

6. **Nada de lo propuesto contestaba "¿regresan los que llegan por anuncio?".** Analytics separa visitantes nuevos de recurrentes, pero solo los liga a una campaña si cada anuncio lleva UTM y hay un evento de conversión. Hace falta un evento `generate_lead` al enviar el formulario y una convención fija de UTM. La medición es por navegador y aproximada. La consola de demos es la cifra oficial de solicitudes; Analytics es la del tráfico.

### Lo que cuesta entregar el cuerpo ya renderizado (SSR)

Sin SSR, un rastreador que no ejecuta JavaScript ve las etiquetas pero no el texto de la página. Con SSR lo ve. Los revisores encontraron cinco obstáculos, cuatro leídos en el código y uno sin comprobar:

- **Construir el bundle lo enciende para todas las páginas.** Con `enabled => true`, las páginas con token y el panel mandarían sus props a Node, incluido el usuario. Hay que limitarlo con `Inertia::disableSsr()` a la lista indexable, en el mismo commit que crea el bundle.
- **Rompe la suite de PHP.** Las pruebas bloquean peticiones HTTP externas y el intento de SSR lanza una excepción. Hay que apagarlo por variable de entorno en `phpunit.xml`.
- **`app.tsx` no carga en Node.** Llama a `document.querySelector` en el nivel superior (`resources/js/app.tsx:15-19`).
- **Diferencias entre servidor y cliente.** El servidor siempre elige tema claro y siempre emite `autoplay`; dos pruebas de navegador existentes fallarían.
- **Sin comprobar: las animaciones probablemente violan la CSP.** `motion` escribe el estado inicial (`opacity: 0`) como atributo `style`, y la CSP no admite estilos en atributo. Afecta al `<h1>` y a varias secciones. Las salidas son pasar esos estados a clases CSS o abrir `style-src-attr` solo en la landing.

Además, con SSR el HTML trae el texto de una sola de las nueve herramientas, porque la sección monta un panel a la vez y una prueba lo exige.

En producción suma un servicio en `compose.production.yaml`, Node en la imagen, un `timeout` corto para que un Node colgado no retenga la landing, y un aviso cuando deje de renderizar (hoy nadie se enteraría).

**Desacuerdo entre revisores.** Uno lo incluye como segunda capa con criterio de corte. Dos piden primero una prueba corta (construir, arrancar Node y abrir `/` con la CSP real). Uno lo deja en los últimos commits para poder quitarlo. Dos lo aplazan: es lo más caro, sirve a la meta menos segura y hay un rediseño de la landing en camino que obligaría a repetirlo.

### Lo que el dueño no estaba viendo

- **Una sola página tiene un techo bajo en búsqueda.** Puede salir bien por la marca. No puede competir por nueve temas con un título y un `<h1>`, y ni el SSR ni el sitemap lo cambian.
- **La cookie de Analytics viaja a las páginas que prometen "sin cookies".** Se guarda para todo el dominio, así que el navegador la enviaría al buzón de quejas y a la página de trabajadores aunque la app no la lea. No hay arreglo barato. Es una decisión del dueño (ver abajo).
- **Redirigir `www` puede invalidar llaves de acceso.** El identificador de las llaves es el host de `APP_URL` (`config/fortify.php:148`, `WebAuthnCeremony.php:285-288`). Si producción usa hoy `www`, cambiarlo deja sin llave a quien ya la registró, incluidos los teléfonos del reloj checador.
- **Faltan dos contenidos que no son código.** El texto del aviso de privacidad y la imagen de 1200×630. Analytics no debe salir antes que el aviso.
- **Las redes guardan la vista previa por URL.** La imagen lleva versión en el nombre (`og-v1.png`) para poder cambiarla. Sin comprobar.

### Resultado ideal

Quien pega el enlace en WhatsApp, LinkedIn o un asistente de IA ve título, descripción e imagen correctos aunque Node esté caído. Ninguna página con token puede indexarse, ni las que se agreguen después. El dueño ve por campaña cuánta gente llega y cuántas demos salen, sin que Google reciba una URL del panel ni un enlace con token.

## Goals vs. Non-Goals

**En alcance**

- Cabecera desde Blade para la landing: título, descripción corta (cerca de 155 caracteres; hoy mide unos 280), canonical, Open Graph, Twitter card, `es-MX`, y JSON-LD (`Organization` y `WebSite`) con nonce.
- Imagen para compartir estática, 1200×630, con versión en el nombre.
- `noindex` por defecto en `SecurityHeaders`, con la lista de páginas indexables y su prueba. Indexable solo en producción.
- Sitemap estático en `public/` y línea `Sitemap:` en `robots.txt`.
- Página de aviso de privacidad, enlazada en el pie y bajo el formulario.
- Google Analytics 4 con `gtag.js` directo, solo en la ruta `home`, solo con ID de medición configurado, sin cargar para quien tiene sesión iniciada. Evento `generate_lead` al enviar la demo.
- Procedencia de la solicitud de demo: `utm_source`, `utm_medium`, `utm_campaign` y host del referente externo. Una fila "Llegó por" en el detalle de la consola.
- Redirección de `www` al dominio sin `www`.

**Fuera de alcance**

- Páginas nuevas por herramienta o norma, blog o guías.
- Píxeles de Meta y LinkedIn, Tag Manager y Google Signals.
- Banner de consentimiento de cookies (sujeto a lo que diga quien redacte el aviso).
- Filtros o totales por campaña en la consola de demos.
- `llms.txt`.
- Indexar el login, los videos de `/media/films` o cualquier página con token.

**En alcance con criterio de corte:** el cuerpo renderizado (SSR), solo para la landing y en los últimos commits. Antes se hace una prueba corta: construir el bundle, arrancar Node y abrir `/` con la CSP real en claro y oscuro. Si la prueba obliga a reescribir las animaciones o a abrir la CSP fuera de la landing, el trabajo se entrega sin SSR y se retoma con el rediseño.

## Alternatives Weighed

- **Etiquetas desde el `<Head>` de React con SSR.** Rechazada por el autor: una caída de Node deja la página sin etiquetas en silencio.
- **SSR para todas las páginas.** Rechazada por el autor: manda a Node las props de páginas con token y del panel.
- **Generar la landing como HTML al construir (prerender).** Rechazada por el autor: el nonce de la CSP cambia en cada petición, el plugin de Inertia no lo ofrece y el build no tiene `.env` ni base de datos.
- **`noindex` como meta en Blade o ruta por ruta.** Rechazada por el autor: ya está repartido en cinco sitios y una ruta nueva lo puede olvidar. No cubre respuestas que no son HTML ni los 404.
- **`Disallow` en `robots.txt` para las rutas privadas.** Rechazada por el autor: el robot no leería el `noindex` y el archivo listaría los prefijos de las URLs con token.
- **Sitemap generado por una ruta o un paquete.** Rechazada por el autor: con una o dos URLs un archivo estático hace lo mismo sin ruta ni dependencia.
- **Google Tag Manager.** Rechazada por el autor: abre más orígenes en la CSP y permite inyectar scripts sin pasar por el repo.
- **Guardar el referrer completo en la solicitud de demo.** Rechazada por el autor: puede ser un enlace con token.
- **Capturar la procedencia en un middleware del grupo `web`.** Rechazada por el autor: tocaría las páginas con token; se captura solo en `home` y en `POST /demo`.
- **Redirección `www` en un middleware de la app.** Rechazada por el autor a favor de Dokploy: el middleware no ve los archivos estáticos (`robots.txt`, sitemap, imagen). El canonical fijo cubre a los buscadores desde el primer día.
- **Partir el trabajo en tres PRs** (presentación y `noindex`; Analytics; SSR). Rechazada por el autor: la regla del proyecto es un PR por trabajo pedido. El SSR, si entra, va en los últimos commits para poder retirarlo.
- **Analytics sin cookies.** Rechazada por el autor: evita la cookie en las páginas "sin cookies", pero no mide regresos, que es lo que el dueño pidió medir.
- **Aplazar el SSR hasta el rediseño de la landing.** Rechazada por el autor: los asistentes de IA son una meta explícita del dueño y, sin SSR, un rastreador que no ejecuta JavaScript no ve el texto. Queda como salida si la prueba corta falla.

## Key Decisions & Staging for Next Steps

### Orden de construcción

Cada pieza aporta sin las siguientes.

1. Lista de páginas indexables y `noindex` por defecto.
2. Página de aviso de privacidad y sus enlaces.
3. Cabecera desde Blade con imagen y JSON-LD.
4. Sitemap y `robots.txt`.
5. Procedencia de la demo.
6. Analytics y cambio de CSP.
7. Cuerpo renderizado, si entra.

La redirección de `www` se configura en Dokploy, fuera del repo.

### Decisiones tomadas

El dueño aceptó estas opciones el 2026-11-14.

1. **SSR del cuerpo.** Entra, en los últimos commits y solo para la landing, después de la prueba corta y con el criterio de corte descrito en el alcance.
2. **La cookie de Analytics en las páginas "sin cookies".** Se acepta y se declara en el aviso. Se comprueba que ni la app ni el proxy registran la cabecera `Cookie`.
3. **Texto del aviso de privacidad.** El autor redacta un borrador con lo que el código recaba de verdad y el dueño lo pasa por su abogado. Analytics se despliega con el ID vacío hasta que el aviso esté publicado.
4. **Imagen para compartir.** Una hecha ahora con el design system, en claro. El rediseño la reemplaza subiendo la versión.
5. **Procedencia de la demo.** Primer toque, guardado en la sesión al entrar a la landing. No necesita código en el cliente y dura lo que la sesión (120 minutos).
6. **Plataformas de anuncios.** Solo Google Ads, enlazado a Analytics, sin guardar `gclid`.
7. **Si el SSR deja de renderizar.** Una línea de log con evento fijo, sin correo.
8. **Rastreadores de IA.** Se permite todo, como hoy.
9. **Las nueve herramientas escritas en el HTML sin JavaScript.** No. Se acepta el límite hasta el rediseño.
10. **Banner de consentimiento.** No se pone, sujeto a lo que diga quien revise el aviso.

### Dato que falta

**Valor de `APP_URL` en producción, y si hay llaves de acceso registradas con `www`.** Hay que mirarlo antes de configurar la redirección. No bloquea el resto del trabajo: la redirección se hace en Dokploy, fuera del repo.

### Tareas fuera del repo, en orden

1. Confirmar `APP_URL`.
2. Configurar `www` con certificado y redirección en Dokploy.
3. Desplegar con el ID de Analytics vacío y verificar con `curl`.
4. Publicar el aviso de privacidad.
5. Crear la propiedad de Analytics, poner el ID y subir la retención de datos.
6. Dar de alta Search Console por DNS y enviar el sitemap.
7. Refrescar la vista previa en los depuradores de las redes.
8. Lanzar los anuncios con UTM apuntando al dominio sin `www`.

### Sin comprobar

Lo siguiente es comportamiento externo que ningún revisor verificó: qué rastreadores de IA ejecutan JavaScript, cuánto tiempo guardan las redes una vista previa, el alcance exacto de la cookie de Analytics, los orígenes que Analytics necesita en la CSP, y lo que la ley mexicana exige sobre cookies. Los orígenes se toman de la guía de Google al escribir el PR; lo legal lo confirma quien revise el aviso.
