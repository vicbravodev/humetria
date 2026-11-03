# 03 · UI del panel de RH

## Referencia visual

Maquetas hechas con los tokens del design system. Son la referencia de apariencia; si algo difiere del texto de abajo, manda la imagen y avísame.

- `referencia-visual/panel-rh.png`: el panel acoplado en la página de una campaña, con una respuesta en streaming (contexto de pantalla, pasos de herramienta, tarjeta de resultados, botón Detener).
- `referencia-visual/estados-panel.png`: estado vacío, pensando, límite o error, y respuesta lista en modo oscuro; además del lanzador y las cuatro expresiones.
- `referencia-visual/*.html`: el mismo contenido como HTML estático, con medidas, colores y espaciados exactos para consultar.

Los textos de las maquetas (cifras, nombres de campañas) son ejemplos.

Meta: que Humi se sienta como un colega que sabe, no como una ventana de chat pegada. Todo sobre el design system de Humetría (Figtree, jade, radio 10 px, tú, sin exclamaciones ni emoji). Sin sombras, salvo lo que flota sobre la página (`shadow-float`): el lanzador, los menús y, si el panel se usa como hoja flotante, el panel. Acoplado a la página lleva solo borde.

## Tokens

Mapean 1:1 a las variables de shadcn. Claro / oscuro:

| Token | Claro | Oscuro |
|---|---|---|
| primary | `#0B7A6F` | `#3CCFBD` |
| primary-foreground | `#FFFFFF` | `#0F1716` |
| background | `#FBFDFC` | `#0F1716` |
| foreground | `#14211F` | `#F1FAF8` |
| card | `#FFFFFF` | `#141E1C` |
| accent | `#E5F1EE` | (el del design system) |
| muted-foreground | `#56635F` | `#9DAEAA` |
| border | `#DCE5E2` | `#26332F` |
| input (borde de controles) | `#7E8C88` | `#62756F` |
| destructive | `#B0463C` | `#EC8F84` |
| riesgo | jade (nulo, bajo), arena (medio), terracota (alto, muy alto) | — |

Si el repo ya tiene el `globals.css` del design system, úsalo tal cual y no dupliques valores. `assets/components/humi.css` solo añade las variables `--humi-*` y la animación.

## Superficies

1. **Lanzador**: botón flotante abajo a la derecha con el avatar de Humi (48 px). Atajo `Ctrl/Cmd + J`. Muestra un punto de aviso solo cuando hay algo proactivo (ver "Se siente inteligente").
2. **Panel lateral** (shadcn `Sheet`, 420 px en escritorio; pantalla completa en móvil): conversación con contexto de la pantalla actual.
3. **Página completa** `/humi`: historial a la izquierda (agrupado por fecha, con búsqueda), conversación a la derecha. El panel tiene el enlace "Abrir en página completa".

## Anatomía del chat

- **Encabezado**: avatar de Humi, "Humi", subtítulo con el contexto ("Campaña Planta Apodaca"), menú (nueva conversación, abrir en página completa, borrar).
- **Estado vacío**: Humi en `hola`, una línea de presentación y 3 o 4 sugerencias **según la pantalla** (en una campaña: "¿Cómo va la participación?", "¿Dónde está el riesgo más alto?"; en el resumen: "¿Qué me falta para estar al día?"). Las sugerencias son botones, no texto decorativo.
- **Mensaje del usuario**: burbuja a la derecha, color `accent`, texto `foreground`.
- **Mensaje de Humi**: avatar de 24 px a la izquierda, sin burbuja (texto sobre el fondo), markdown sanitizado, tablas con estilo del design system, listas, código en línea. Enlaces a pantallas internas como chips.
- **Pie del composer**: textarea que crece hasta 6 líneas, `Enter` envía, `Shift+Enter` salto de línea, botón enviar que se convierte en **Detener** mientras hay stream. Debajo, en `muted-foreground`: "Humi puede equivocarse. Verifica las cifras antes de reportarlas."

## Estados de Humi (el personaje comunica el estado)

| Momento | Expresión | Detalle |
|---|---|---|
| En reposo y bienvenida | `hola` | Parpadeo suave opcional |
| Usuario escribiendo | `escucha` | Cambia al enfocar el composer |
| Pensando o consultando herramientas | `calcula` | Con el texto del paso ("Revisando resultados de Planta Apodaca") |
| Respuesta terminada con resultado | `listo` | Los trazos del arco reflejan el nivel si la respuesta trae un riesgo |
| Error | `escucha` con mensaje claro | Sin dramatizar |

Las animaciones respetan `prefers-reduced-motion` (ya lo hace `humi.css`).

## Streaming

- El texto aparece conforme llega. Sin cursor parpadeante; un punto pequeño de `primary` al final del texto mientras fluye, estático con reduced motion.
- Renderiza markdown incremental sin saltos: no re-renderices todo el mensaje en cada token (memoiza los bloques ya cerrados).
- **Pasos de herramientas** como líneas compactas sobre la respuesta: "Consultando avance de campañas" que pasan a "Consulté avance de campañas" al terminar. Colapsables.
- **Tarjetas ricas**: cuando una herramienta devuelve `render`, el front pinta el componente (medidor de riesgo de 5 segmentos con color, texto y etiqueta; barras de avance; tabla compacta) en lugar de prosa. Cada tarjeta tiene su enlace "Ver en el panel".
- Auto-scroll anclado al final solo si el usuario no subió; si subió, muestra un botón "Ir al final".
- **Detener** corta el stream, deja el texto parcial y lo marca como detenido.
- Tras terminar: acciones por mensaje (copiar, pulgar arriba o abajo con motivo opcional, regenerar la última respuesta).

## Se siente inteligente

- **Contexto**: el panel manda qué entidad ves (campaña, centro, norma). El servidor lo valida. Humi lo usa sin que lo repitas ("esta campaña").
- **Sugerencias de seguimiento**: tras cada respuesta, 2 o 3 chips ("Ver por área", "Armar plan de acción"). Pueden salir de una llamada barata de salida estructurada o de reglas por herramienta.
- **Aviso proactivo (opcional, tras la primera versión)**: un punto en el lanzador cuando, por ejemplo, una campaña lleva días sin avanzar o vence un plazo. Se calcula por reglas, no por LLM.
- **Citas**: cada cifra viene de una herramienta y lleva su enlace. Si Humi no tiene el dato, lo dice y propone dónde verlo.
- **Respuestas cortas primero**: una frase con la conclusión y luego el detalle.

## Errores y bordes

- Sin conexión, tiempo agotado, límite de frecuencia, presupuesto agotado, IA apagada para el tenant: cada uno con mensaje propio y acción (reintentar, "Pídele a tu administrador…").
- El mensaje del usuario que falló se conserva con botón "Reintentar".
- Conversación sin acceso (404): vuelve al estado vacío con aviso.

## Accesibilidad

- Región `aria-live="polite"` que se actualiza al cierre de cada oración o al terminar, no token por token.
- Todo operable con teclado; foco visible sólido (sin sombra); el foco entra al composer al abrir el panel y vuelve al lanzador al cerrarlo.
- Contraste AA en ambos modos (los tokens ya lo cumplen). El riesgo nunca depende solo del color: segmento, texto y color.
- Objetivos táctiles de 44 px mínimo en móvil.

## Copy de ejemplo (tú, sin exclamaciones)

- Presentación: "Soy Humi. Te ayudo a ver cómo va tu cumplimiento y a decidir qué sigue."
- Sin datos suficientes: "Todavía no hay suficientes respuestas en este grupo para mostrar resultados."
- Límite: "Alcanzaste el límite de mensajes por ahora. Puedes seguir en unos minutos."

## Qué entregar

Componentes en la carpeta de componentes del repo: `HumiLauncher`, `HumiPanel`, `HumiThread`, `HumiMessage`, `HumiToolStep`, `HumiComposer`, `HumiSuggestions`, `HumiResultCard`, más el hook que envuelve el cliente de chat. Humi (`assets/components/Humi.tsx` o `Humi.vue`) es la pieza base del avatar.
