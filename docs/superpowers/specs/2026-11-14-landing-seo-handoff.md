# HANDOFF — Revisión arquitectónica integral del plan SEO, SSR, previews y analítica de Humetria

*   **Proyecto:** Humetria
*   **Fecha:** 14 de noviembre de 2026
*   **Estado:** Solicitud de rediseño técnico del plan existente
*   **Destinatario:** Claude Code / agente de arquitectura
*   **Modalidad:** Investigación del repositorio, revisión crítica y elaboración de un nuevo diseño. No implementar todavía.

Texto del propietario, guardado tal como lo entregó.

## 1. Contexto y decisión del propietario

Existe un documento de diseño denominado «SEO, vista previa al compartir y medición de la landing», fechado el 14 de noviembre de 2026.

El documento es extenso e incluye objetivos, criterios de aceptación, arquitectura, alternativas, riesgos, aproximadamente 73 intervenciones sobre archivos y un plan detallado de pruebas.

El diseño contiene numerosas decisiones correctas que deben conservarse. Sin embargo, el propietario rechaza expresamente una de sus decisiones arquitectónicas fundamentales:

No utilizaremos un bloque `<noscript>` con una segunda representación textual de la landing como sustituto del server-side rendering.

Humetria está construida con Laravel, Inertia y React. Inertia dispone de capacidades oficiales de SSR precisamente para resolver el problema de entregar HTML renderizado desde el servidor.

No tiene sentido mantener dos representaciones del mismo contenido comercial, una en React y otra en Blade, cuando podemos utilizar la capacidad nativa de nuestro stack.

La decisión arquitectónica queda establecida:

La landing pública deberá utilizar SSR real de Inertia, renderizando los mismos componentes React que utiliza el navegador y conservando su hidratación e interactividad.

Esto no implica activar SSR indiscriminadamente sobre toda la plataforma.

El objetivo es utilizarlo de forma selectiva, segura, mantenible y operativamente robusta.

### Instrucción principal

Debes revisar, corregir y fortalecer el plan existente, no crear una implementación paralela ni proponer una migración de framework.

No debes comenzar a programar hasta presentar el diseño revisado y recibir aprobación.

## 2. Objetivos irrenunciables

El diseño revisado debe garantizar lo siguiente:

1.  El HTML inicial de `https://humetria.com.mx/` contiene el contenido real de la landing, generado mediante SSR de Inertia.
2.  El contenido proviene de los componentes React existentes, sin textos duplicados manualmente en Blade o `<noscript>`.
3.  Las personas que utilizan Chrome, Safari u otros navegadores conservan la experiencia visual, las animaciones, la navegación, el formulario y la interactividad.
4.  React hidrata correctamente el documento SSR sin errores ni diferencias inesperadas entre servidor y cliente.
5.  Los buscadores, redes sociales y extractores automatizados pueden leer el título, la descripción, las herramientas y los contenidos principales sin ejecutar JavaScript.
6.  Las páginas privadas, administrativas, empresariales y con tokens no se envían al proceso SSR.
7.  La política CSP permanece estricta, sin relajaciones globales.
8.  Las vistas previas de WhatsApp, LinkedIn y otras plataformas funcionan con metadatos reales.
9.  GA4 mide visitas y conversiones sin exponer información confidencial.
10. La atribución de anuncios funciona de manera útil para un SaaS B2B.
11. El servicio SSR funciona de manera supervisada dentro de la infraestructura Docker Compose, Dokploy y Traefik.
12. La compilación, el despliegue, las pruebas y la observabilidad detectan fallos de SSR antes de que afecten silenciosamente a producción.

El éxito no se determina únicamente por tener pruebas verdes.

El criterio de éxito es comprobar el comportamiento real en producción.

## 3. Fase obligatoria de investigación del repositorio

Antes de diseñar la solución, realiza una inspección completa del proyecto.

### 3.1. Versiones y compatibilidad

Identifica las versiones reales de:

*   Laravel y PHP.
*   `inertiajs/inertia-laravel`.
*   `@inertiajs/react`.
*   React y React DOM.
*   Vite y su integración con Laravel.
*   Node.js utilizado en build y producción.
*   Librerías de animación.
*   Dependencias que puedan comportarse de manera diferente durante SSR.

Consulta la documentación oficial correspondiente a las versiones instaladas.

No asumas que la configuración de Inertia v3 es compatible sin cambios con v2.

Tampoco actualices Inertia, React o Vite automáticamente para resolver el problema.

Si consideras necesaria una actualización, documenta primero por qué y qué riesgos introduce.

### 3.2. Arquitectura actual

Inspecciona, como mínimo:

*   `resources/js/app.tsx`.
*   `resources/js/pages/landing.tsx`.
*   Todos los componentes de `resources/js/components/landing/`.
*   `resources/views/app.blade.php`.
*   `config/inertia.php`.
*   `vite.config.ts` o equivalente.
*   `package.json`.
*   `Dockerfile`.
*   `compose.production.yaml`.
*   Configuración de nginx.
*   Middleware globales y de Inertia.
*   `HandleInertiaRequests`.
*   `SecurityHeaders`.
*   Configuración de CSP y nonces.
*   Rutas públicas y privadas.
*   `PageFrame`.
*   `CLAUDE.md`.
*   Flujos de despliegue y pruebas.

Verifica específicamente la afirmación del plan anterior: que SSR aparece habilitado en la configuración, pero no se construye el bundle ni se ejecuta el servidor de renderizado en producción.

No tomes esa afirmación como verdad definitiva hasta comprobar el código.

### 3.3. Auditoría de componentes incompatibles con SSR

Inspecciona los componentes de la landing buscando:

*   Acceso a `window`, `document`, `navigator`, `localStorage` o `sessionStorage` durante renderizado.
*   Hooks que dependen de APIs del navegador.
*   Valores no deterministas, como fechas, números aleatorios o IDs inconsistentes.
*   Lectura de tema claro/oscuro antes de la hidratación.
*   Animaciones con estilos inline.
*   Componentes cuyo estado inicial deja el texto invisible.
*   Videos y reproductores dependientes del DOM.
*   Importaciones con efectos secundarios.
*   Componentes que se renderizan mediante portales.
*   Cualquier incompatibilidad con `renderToString` e hidratación.

Elabora una matriz con archivo, problema, causa, severidad y corrección propuesta.

## 4. Nueva arquitectura SSR

### 4.1. SSR nativo de Inertia

La solución debe utilizar el mecanismo oficialmente soportado por la versión instalada de Inertia.

Investiga y diseña:

*   Entry point SSR.
*   Renderizado React en Node.
*   Configuración de Vite.
*   Compilación del bundle cliente.
*   Compilación del bundle SSR.
*   Resolución de componentes.
*   Inicialización de providers.
*   Tratamiento de layouts.
*   Integración con el encabezado HTML.
*   Hidratación cliente.
*   Configuración del servidor SSR.
*   Mecanismo para excluir rutas.
*   Gestión de errores.

No copies ejemplos de la documentación sin adaptarlos al proyecto.

### 4.2. SSR selectivo y deny-by-default

La aplicación tiene muchas rutas que no deben necesitar SSR.

El diseño debe establecer una política explícita:

Solo las páginas públicas comerciales autorizadas utilizan SSR.

Inicialmente:

*   `home`: SSR habilitado.
*   `privacy-notice`: SSR habilitado.

Las demás rutas permanecen con su comportamiento actual, salvo autorización expresa.

Esto incluye:

*   `/admin`.
*   Dashboard empresarial.
*   Consola superadmin.
*   Encuestas.
*   Buzón de quejas.
*   Portales de trabajadores.
*   Reportes compartidos.
*   Enlaces con token.
*   Reloj checador.
*   Cualquier otra ruta privada o pública no autorizada.

No basta con excluir `/admin/*`; debemos cubrir las rutas con tokens y todas las excepciones futuras.

La política debe poder comprobarse automáticamente y ser compatible con la lista de rutas indexables.

No confundas indexabilidad con autorización para SSR: son responsabilidades diferentes, aunque inicialmente compartan las mismas dos páginas.

### 4.3. Aislamiento de datos

El plan anterior señala que Inertia comparte `auth.user` y otras propiedades globales.

Debes revisar este comportamiento.

El objetivo es evitar que las peticiones al proceso SSR transporten innecesariamente:

*   Datos de usuarios autenticados.
*   Información de empleados.
*   Datos de empresas o tenants.
*   Tokens privados.
*   Información de encuestas.
*   Datos de reportes.
*   Cookies o encabezados sensibles que no sean necesarios.
*   Credenciales o secretos.

El SSR de marketing debe trabajar con propiedades públicas, explícitas y mínimas.

Revisa también si el bundle SSR importa módulos privados que no necesita y si existen dependencias que ejecutan código durante su importación.

Considera el riesgo de estado compartido entre solicitudes dentro de un proceso Node de larga duración.

Añade pruebas con solicitudes consecutivas y concurrentes para detectar contaminación de estado entre visitantes.

## 5. Eliminar la duplicación del contenido

La sección 8 del diseño anterior, «Texto de la landing sin JavaScript», debe reemplazarse por completo.

### Queda rechazado

*   Crear `resources/views/partials/landing-text.blade.php`.
*   Mantener manualmente un segundo H1.
*   Duplicar las nueve herramientas en Blade.
*   Duplicar párrafos o encabezados de React.
*   Sincronizar dos representaciones mediante pruebas que comparan cadenas.
*   Desactivar SSR deliberadamente para sustituirlo con `<noscript>`.

### Nuevo comportamiento esperado

La respuesta HTML debe contener los componentes reales renderizados por React en el servidor.

Debe incluir, como mínimo:

*   El H1 auténtico de la landing.
*   Sus encabezados H2 principales.
*   La propuesta de valor.
*   Las nueve herramientas con sus nombres y descripciones relevantes.
*   El contenido explicativo de las secciones.
*   Los enlaces públicos de navegación.
*   Los llamados a la acción.
*   La estructura semántica necesaria para comprender el producto.

Si una sección utiliza tabs, carruseles o paneles que montan un único elemento a la vez, estudia cómo conseguir que el HTML SSR exponga adecuadamente las nueve herramientas sin romper el diseño.

Puede ser necesario mejorar la semántica de los componentes React existentes.

No se admite crear contenido oculto destinado exclusivamente a rastreadores.

La información que reciben personas y sistemas automatizados debe ser coherente.

### Prueba fundamental

Realiza una solicitud HTTP sin ejecutar JavaScript y analiza el cuerpo HTML.

La prueba debe demostrar que los textos relevantes aparecen en los nodos HTML renderizados por SSR.

No basta con encontrar esas frases en:

*   JSON serializado de Inertia.
*   `data-page`.
*   Scripts JavaScript.
*   Metadatos.
*   `<noscript>`.
*   Contenido duplicado que el visitante nunca puede consultar.

Esta distinción es un requisito de aceptación obligatorio.

## 6. Hidratación, animaciones y CSP

El plan anterior rechazó SSR parcialmente porque determinados componentes y animaciones utilizan estilos inline incompatibles con la CSP.

Eso no constituye una justificación suficiente para rechazar SSR. Es un problema técnico que debemos resolver.

### 6.1. Hidratación

Investiga cómo inicializar correctamente React cuando recibe HTML SSR.

Los criterios son:

*   Sin hydration mismatches.
*   Sin reemplazos completos e innecesarios del DOM.
*   Sin duplicaciones de elementos.
*   Sin pérdida de estado de formularios.
*   Sin parpadeos visuales importantes.
*   Sin diferencias inesperadas de tema.
*   Sin errores en consola.
*   Sin cambios relevantes en accesibilidad.

Prueba tema claro y oscuro, escritorio y móvil.

### 6.2. Animaciones

Revisa especialmente las animaciones que inicializan contenido con `opacity: 0`.

El texto principal no debe quedar invisible si JavaScript no se ejecuta.

Adopta un enfoque de mejora progresiva:

El HTML SSR es legible por defecto; JavaScript agrega la experiencia animada.

Explora clases CSS, transiciones controladas y estados activados después de la hidratación, sin introducir divergencias de renderizado.

Respeta `prefers-reduced-motion`.

### 6.3. CSP estricta

Conserva la política actual de seguridad.

No aceptes automáticamente:

*   `unsafe-inline` global.
*   `unsafe-eval`.
*   Apertura indiscriminada de dominios.
*   Eliminación de nonces.
*   Desactivar CSP para SSR.

Si una librería requiere una excepción, primero investiga una corrección compatible con CSP.

Cualquier excepción debe tener alcance mínimo, justificación técnica, pruebas y documentación.

Revisa especialmente `style-src`, `style-src-attr`, scripts generados, nonces de Laravel/Vite y comportamiento durante hidratación.

## 7. Metadatos, SEO y vista previa social

La idea de metadatos completos del plan anterior se conserva.

Lo que debe reconsiderarse es su implementación.

### Fuente de verdad

Evalúa si Inertia SSR puede generar de manera confiable los metadatos utilizando su componente `Head`.

Esta debería ser la solución preferente si cumple todos los requisitos.

No mantengas una versión independiente de los mismos metadatos en Blade y otra en React.

Si resulta indispensable generar alguna etiqueta desde el documento raíz de Laravel, justifica esa excepción y garantiza una sola fuente de datos.

### Metadatos requeridos

*   Título SEO.
*   Meta description.
*   Canonical absoluto.
*   Open Graph.
*   Twitter/X Card.
*   Idioma y locale.
*   Imagen social 1200×630.
*   Dimensiones y descripción de la imagen.
*   Datos estructurados de organización y sitio web.
*   URL consistente con `APP_URL`.
*   Canonical sin parámetros UTM.

Valida que no aparezcan títulos o etiquetas duplicados después de la hidratación.

Comprueba también los encabezados y metadatos de la página de privacidad.

### Validación real

Prueba la respuesta HTTP con:

*   `curl`.
*   Un parser HTML sin JavaScript.
*   Un navegador con JavaScript deshabilitado.
*   Playwright con JavaScript habilitado.
*   Un extractor independiente que no ejecute JavaScript.

Cuando sea posible, verifica las vistas previas con las herramientas oficiales de LinkedIn y Meta.

No confundas un resultado de prueba local con que Google ya haya indexado la página.

## 8. SSR en Docker Compose y Dokploy

Esta es una de las partes que el plan anterior evita, pero ahora debe quedar diseñada exhaustivamente.

### 8.1. Estrategia de ejecución

Compara formalmente:

*   Alternativa A: proceso SSR dentro de un contenedor existente, gestionado por un supervisor.
*   Alternativa B: servicio SSR separado y privado dentro de Docker Compose.

Recomienda una sola solución considerando:

*   Complejidad operacional.
*   Aislamiento de procesos.
*   Seguridad.
*   Consumo de memoria y CPU.
*   Capacidad de reinicio automático.
*   Disponibilidad.
*   Consistencia de versiones.
*   Escalabilidad futura.
*   Diagnóstico de fallos.
*   Compatibilidad con Dokploy.

No introduzcas un servicio externo administrado si el SSR puede resolverse correctamente dentro de nuestra infraestructura.

### 8.2. Seguridad del servicio

El servidor SSR no debe exponerse directamente a internet mediante Traefik.

Debe estar disponible únicamente para los servicios internos que lo necesiten.

Evalúa restricciones de red, usuario no privilegiado, sistema de archivos, variables de entorno y acceso a secretos.

No permitas que el proceso SSR tenga más privilegios de los necesarios.

### 8.3. Construcción

El Dockerfile debe producir los bundles cliente y SSR correspondientes a la misma revisión de código.

No aceptes una arquitectura donde Laravel utilice un bundle nuevo y Node conserve uno anterior.

Define:

*   Etapas de compilación.
*   Dependencias de ejecución.
*   Versión de Node.
*   Ubicación del bundle.
*   Variables necesarias.
*   Estrategia de publicación.
*   Reinicio del servicio.
*   Rollback.

### 8.4. Salud y recuperación

Diseña comprobaciones de:

*   Proceso activo.
*   Puerto accesible internamente.
*   Bundle existente.
*   Bundle ejecutable.
*   Capacidad real de renderizar la landing.
*   Respuesta dentro del timeout definido.

No basta con que el puerto esté abierto.

Revisa si la versión instalada admite `inertia:check-ssr` u otro mecanismo oficial de comprobación.

Establece health checks, reinicio automático y alertas.

### 8.5. Fallos de SSR

El diseño anterior identifica correctamente un riesgo: si el servicio SSR falla, Inertia puede volver a entregar HTML sin el cuerpo renderizado.

Ese comportamiento silencioso es inaceptable como condición normal de producción para una landing dependiente de SEO.

Diseña explícitamente una política de degradación.

Compara las ventajas y desventajas de devolver CSR temporalmente, responder con un error de disponibilidad o adoptar otro mecanismo seguro.

Considera disponibilidad para personas, consecuencias SEO, tiempos de recuperación y privacidad.

No selecciones una estrategia sin justificarla.

Sea cual sea la elegida, debe existir una señal observable de que SSR falló y una prueba que demuestre el comportamiento.

## 9. Privacidad e indexabilidad

Conserva la arquitectura de `IndexablePages` y `X-Robots-Tag: noindex, nofollow` por defecto.

Revisa su interacción con SSR.

### Criterios

*   Solo rutas autorizadas y respuestas 200 de producción pueden indexarse.
*   Los errores 404 y 500 conservan las políticas pertinentes.
*   Las rutas con tokens nunca se convierten accidentalmente en páginas indexables.
*   El sitemap contiene únicamente páginas canónicas autorizadas.
*   `robots.txt` no impide que un buscador lea una directiva `noindex` relevante.
*   Los recursos estáticos servidos por nginx se auditan de forma separada.
*   Una ruta nueva no puede activar SSR ni indexación por omisión.

Recuerda que `noindex` no reemplaza autenticación, autorización ni protección de tokens.

Incluye pruebas de regresión de estas propiedades.

## 10. Google Analytics y medición comercial

Esta parte del diseño original debe conservarse, pero requiere una segunda revisión crítica.

### 10.1. Instrumentación

Mantén como punto de partida GA4 con `gtag.js` directo, sin incorporar Google Tag Manager automáticamente.

Revisa que:

*   Analytics se cargue únicamente donde está autorizado.
*   Las CSP incluyan únicamente los orígenes necesarios.
*   Las navegaciones Inertia no produzcan medición duplicada.
*   Una visita a la landing desde otra ruta se registre cuando corresponde.
*   Salir hacia login, privacidad o páginas privadas no continúe generando eventos de navegación sensibles.
*   El SSR no ejecute scripts de Analytics en Node.
*   Los metadatos SSR no incluyan identificadores de visitantes.

### 10.2. Privacidad

Revisa el comportamiento real de:

*   `page_location`.
*   `page_referrer`.
*   Cookies `_ga`.
*   Parámetros UTM.
*   Identificadores de campañas.
*   Navegación entre rutas del mismo dominio.
*   URLs privadas con tokens.

No basta con sanitizar solamente `page_referrer`.

Define qué datos se pueden enviar a terceros y qué datos deben excluirse siempre.

El diseño debe evaluar si la utilización de un mismo dominio para marketing, encuestas y buzones introduce un riesgo adicional de correlación.

No impongas una migración de subdominios como condición automática: analiza alternativas y deja una recomendación fundamentada.

### 10.3. Consentimiento

Revisa la integración con el aviso de privacidad y los requisitos legales aplicables en México.

Evalúa Consent Mode y una arquitectura preparada para consentimiento sin asumir que todas las jurisdicciones exigen el mismo banner.

La implementación de Analytics no se activa hasta completar las verificaciones legales y técnicas.

## 11. Atribución para Google Ads y ventas B2B

El diseño anterior guarda un primer toque dentro de una sesión de Laravel de 120 minutos de inactividad.

Esto puede ser insuficiente para un ciclo comercial B2B.

Reevalúa la estrategia de atribución.

La nueva propuesta debe distinguir:

*   Primer canal conocido de adquisición.
*   Último canal conocido.
*   Campaña asociada a la conversión.
*   Visitas directas.
*   Visitas recurrentes.
*   Conversiones sin atribución.
*   Identificadores de clic publicitario, cuando corresponda.

Compara mecanismos de persistencia que respeten la privacidad y los periodos de conservación definidos.

Considera UTMs y, si se justifican, identificadores de Google Ads como `gclid`, `gbraid` y `wbraid`.

No agregues campos solo porque existan: explica su utilidad real, el nivel de identificación, la retención y el impacto legal.

No conviertas una visita sin procedencia en una visita directa por defecto.

### Conversión `generate_lead`

La conversión debe asociarse a una solicitud efectivamente guardada.

Evalúa:

*   Reintentos.
*   Doble clic.
*   Respuestas tardías.
*   Duplicación de eventos.
*   Pérdida de eventos.
*   Bloqueadores de Analytics.
*   Fallo de red.
*   Navegación después del envío.
*   Validación en GA4 DebugView.
*   Importación de conversiones a Google Ads.

No asumas que proporcionar un identificador al evento garantiza deduplicación automática en GA4; verifica los mecanismos reales del proveedor.

No envíes nombres, correos, teléfonos ni contenido de solicitudes como parámetros de Analytics.

## 12. Pruebas y criterios de aceptación

El nuevo diseño debe conservar el enfoque TDD y las pruebas de mutación.

Además, debe introducir pruebas que demuestren la utilidad efectiva del SSR.

### A. Pruebas de HTML inicial

La respuesta de la landing debe contener:

*   Un H1 real.
*   Los H2 principales.
*   Nombres de las nueve herramientas.
*   Descripciones de las herramientas.
*   Contenido comercial relevante.
*   Enlaces rastreables.
*   Metadatos completos.
*   Datos estructurados válidos.

Los contenidos deben proceder de SSR y no exclusivamente de datos serializados.

### B. Pruebas sin JavaScript

Abrir la landing con JavaScript deshabilitado.

Verificar que:

*   Los textos principales se muestran.
*   No quedan ocultos por animaciones.
*   El diseño permite leer el contenido.
*   Los enlaces de navegación básicos funcionan.
*   No aparecen bloques duplicados.
*   La página explica correctamente qué hace Humetria.

No se exige que todas las interacciones complejas funcionen sin JavaScript, pero sí que el contenido informativo esencial sea accesible.

### C. Pruebas de hidratación

Con JavaScript habilitado:

*   Sin hydration mismatches.
*   Sin errores de React.
*   Sin violaciones CSP.
*   Sin pérdida del formulario.
*   Sin duplicación de títulos.
*   Sin diferencias inesperadas de tema.
*   Sin cambios incorrectos de layout.

Ejecutar las pruebas en escritorio, móvil, tema claro y tema oscuro.

### D. Pruebas de seguridad

Verificar que:

*   El SSR no recibe props sensibles.
*   No existen contaminaciones de datos entre solicitudes.
*   Las rutas privadas no usan SSR.
*   La CSP mantiene sus restricciones.
*   Las URLs con token no llegan a Analytics.
*   Los headers de indexación son correctos.
*   Las rutas nuevas son seguras por defecto.

### E. Pruebas de infraestructura

Simular:

*   SSR activo.
*   SSR detenido.
*   SSR lento.
*   Bundle ausente.
*   Bundle incorrecto.
*   Reinicio del contenedor.
*   Despliegue de nueva versión.
*   Rollback.
*   Fallo de comunicación Laravel–Node.

Cada caso debe tener un resultado esperado y una verificación observable.

### F. Pruebas de SEO y previews

Realizar comprobaciones reales sobre producción:

*   `curl` del HTML inicial.
*   Parser sin JavaScript.
*   Open Graph.
*   Canonical.
*   Sitemap.
*   Robots.
*   Redirección `www`.
*   Certificado HTTPS.
*   Search Console.
*   Preview de LinkedIn y Meta.
*   Lectura mediante extractores automatizados.

Añadir una prueba sintética posterior al despliegue que falle si el H1 o las herramientas dejan de aparecer en el HTML SSR.

### G. Rendimiento

Definir un presupuesto inicial para:

*   Tiempo de renderizado SSR.
*   Tiempo de respuesta HTTP.
*   Memoria del proceso Node.
*   Tamaño del HTML.
*   Tamaño de bundles.
*   LCP.
*   INP.
*   CLS.

Separar métricas de servidor y métricas reales de experiencia.

No asumir que SSR mejora automáticamente todos los indicadores de rendimiento.

## 13. Observabilidad

Humetria ya dispone de un enfoque fuerte de logging y observabilidad.

El SSR debe integrarse a ese enfoque, no convertirse en una excepción.

Proponer métricas y registros de:

*   Solicitudes SSR.
*   Renderizados exitosos.
*   Errores de renderizado.
*   Latencia.
*   Timeouts.
*   Uso de memoria.
*   Reinicios.
*   Fallos de hidratación detectables.
*   Estado del servicio.
*   Versión del bundle desplegado.
*   Disponibilidad del contenido SEO.

No registrar props completas, cookies, tokens ni datos sensibles.

Incluir alertas útiles para errores repetidos o una degradación sostenida del SSR.

## 14. Restricciones de implementación

Se mantienen las reglas de `CLAUDE.md`, incluidas:

*   Un PR por trabajo.
*   TDD.
*   Pruebas de mutación.
*   Análisis estático.
*   Prohibición de debilitar pruebas existentes.
*   Límite de 100 líneas por función.
*   Uso obligatorio del design system.
*   Rutas en inglés.
*   Coherencia con las convenciones del repositorio.

Si el nuevo diseño exige cambiar un comportamiento aprobado previamente, debe identificarse expresamente y solicitar autorización.

No se permiten soluciones rápidas que comprometan seguridad, mantenibilidad o separación entre tenants.

No crear una segunda aplicación pública si puede resolverse correctamente dentro de Inertia.

No reemplazar Inertia por Next.js, Astro, WordPress u otro framework.

No agregar un renderizador propio si la integración oficial de Inertia cumple los requisitos.

## 15. Revisión de las decisiones originales

El nuevo documento debe incluir una tabla que examine todas las alternativas A–P del diseño anterior.

Clasifica cada una como:

*   Conservar: sigue siendo válida sin modificaciones importantes.
*   Modificar: sigue siendo útil, pero requiere ajuste.
*   Reemplazar: queda sustituida por la nueva arquitectura SSR.
*   Descartar: ya no corresponde a los objetivos actuales.

La alternativa B, SSR de Inertia, deja de estar rechazada.

Ahora constituye la decisión arquitectónica principal aprobada por el propietario.

Debes rehacer sus riesgos, costos, diseño operativo y criterios de aceptación.

La alternativa basada en `<noscript>` queda descartada.

También debes revisar los apartados Non-Goals, Future Improvements, Risks & Mitigations y el Detailed Implementation Plan, porque sus dependencias cambian.

SSR ya no es una mejora futura. Es parte del alcance central.

## 16. Entregable esperado

Genera una nueva versión completa y coherente del documento de diseño.

No entregues únicamente un listado de recomendaciones.

La nueva versión debe contener:

1.  Resumen ejecutivo: problema, decisión y arquitectura propuesta.
2.  Investigación del repositorio: versiones, hallazgos y evidencia.
3.  Arquitectura SSR: flujo Laravel → Inertia → Node SSR → HTML → hidratación React.
4.  Estrategia selectiva: rutas habilitadas y rutas excluidas.
5.  Plan de compatibilidad: componentes, temas, animaciones, videos y CSP.
6.  Metadatos: fuente única de verdad y generación HTML.
7.  Infraestructura: Docker, Compose, Dokploy, salud, despliegue y rollback.
8.  Seguridad: privacidad, datos compartidos, tokens y aislamiento.
9.  Analytics: medición, navegación, consentimiento y conversiones.
10. Atribución: diseño de adquisición para campañas y demos.
11. Riesgos: probabilidad, impacto y mitigación.
12. Plan detallado de implementación: archivos reales que se crearán o modificarán, orden y dependencias.
13. Matriz de pruebas: unitarias, Feature, navegador, integración y producción.
14. Criterios de aceptación: resultados observables.
15. Preguntas abiertas: decisiones que requieren autorización del propietario.
16. Diferencias contra el plan anterior: qué cambia, qué se mantiene y por qué.

No reutilices automáticamente los números de archivo ni las rutas de la propuesta anterior sin comprobar el estado actual del repositorio.

Cada cambio propuesto debe incluir:

*   Qué problema resuelve.
*   Por qué es necesario.
*   Qué archivos afecta.
*   Qué riesgos introduce.
*   Qué pruebas lo validan.
*   Cómo se revierte.
*   Cómo se comprueba en producción.

### Clasificación de prioridades

*   **P0 — Bloqueantes:** SSR real, HTML accesible, hidratación, CSP, exclusión de rutas sensibles, operación fiable y ausencia de fugas de datos.
*   **P1 — Requeridos para lanzamiento comercial:** metadatos, previews, canonical, sitemap, privacidad, GA4 y atribución confiable.
*   **P2 — Mejoras posteriores:** páginas SEO por herramienta, optimización comercial adicional, contenidos especializados y ampliaciones de analítica.

Las prioridades deben justificarse. No conviertas elementos realmente bloqueantes en trabajo futuro solo para reducir el tamaño del PR.

## 17. Condiciones para aprobar el nuevo diseño

No consideraremos completo el diseño revisado mientras no responda satisfactoriamente estas preguntas:

*   **Renderizado:** ¿un lector automatizado puede leer directamente el HTML de la landing y entender qué hace Humetria sin ejecutar JavaScript?
*   **Duplicación:** ¿el contenido tiene una sola fuente real de verdad?
*   **Experiencia:** ¿la interfaz mantiene sus funcionalidades y se hidrata sin errores?
*   **Seguridad:** ¿los componentes privados y los datos sensibles permanecen fuera de SSR?
*   **CSP:** ¿puede ejecutarse el sistema sin debilitar la política de seguridad?
*   **Operación:** ¿qué ocurre exactamente si Node se detiene durante una visita o un despliegue?
*   **Despliegue:** ¿cómo garantizamos que Laravel y el servicio SSR utilizan bundles de la misma versión?
*   **Indexabilidad:** ¿qué impide que una ruta nueva o una respuesta errónea termine siendo indexable?
*   **Marketing:** ¿Google Analytics y Google Ads pueden medir adquisición y conversiones sin exponer información privada?
*   **Verificación:** ¿cómo sabemos desde fuera del contenedor que el HTML real de producción contiene el contenido esperado?

Si alguna respuesta depende de un supuesto no comprobado, debe identificarse como pregunta abierta y acompañarse de una prueba propuesta.

## 18. Instrucción final para el agente

No implementes todavía.

Primero:

1.  Lee el documento original completo.
2.  Investiga el código real del repositorio.
3.  Contrasta las decisiones con la documentación oficial de las versiones instaladas.
4.  Identifica contradicciones, riesgos y dependencias ocultas.
5.  Rediseña la propuesta en torno al SSR nativo de Inertia.
6.  Conserva las decisiones correctas del documento anterior.
7.  Fortalece las pruebas, la operación y los criterios de aceptación.
8.  Presenta el nuevo documento completo para revisión.

No intentes simplificar artificialmente el diseño.

El propietario prioriza la arquitectura correcta, la seguridad, el rendimiento, la mantenibilidad y la capacidad de crecer sobre reducir unas horas de implementación.

Resultado esperado: un plan de ingeniería listo para ejecutarse mediante TDD, compatible con la arquitectura actual de Humetria, con SSR real, sin contenido duplicado y con garantías verificables de funcionamiento en producción.
