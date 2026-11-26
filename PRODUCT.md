# Product

## Register

product

## Users

- **RH, dueños y consultores** de empresas en México. Usan el panel (`/admin`) para cargar a su gente, lanzar aplicaciones (NOM-035, NOM-037, Ley Silla, clima y pulso, asistencia, vacaciones, denuncias, compensación), seguir su avance y presentar resultados ante la STPS. Trabajan en escritorio, con tiempo limitado y sin ser especialistas en la norma.
- **Personas trabajadoras** sin cuenta. Contestan encuestas y usan su portal (`/me/{slug}`) desde el celular, muchas veces en planta y con poca práctica digital. Les preocupa que su respuesta sea realmente confidencial.
- **Superadmin** de la plataforma: demos, cobranza y rentabilidad.

Tarea principal por pantalla: RH ve qué aplicaciones requieren atención y abre cada una para consultar su estado y las acciones disponibles; la persona trabajadora contesta o consulta lo suyo sin perderse.

## Product Purpose

SaaS multi-tenant de herramientas de RH. La empresa carga a su gente, la plataforma invita y recolecta de forma confidencial, y RH recibe un diagnóstico listo para actuar y para presentar ante la STPS. Éxito: RH cumple sin ser experto, la gente contesta con confianza y nadie puede ligar una respuesta anónima con una persona.

## Brand Personality

Tres palabras: **clara, cercana y rigurosa**.

- **Clara** en lo que explica y en el siguiente paso que le pide a la persona.
- **Cercana** en el lenguaje de RH en México y en el trato a quienes responden desde su celular. El panel tutea; la encuesta trata de «usted».
- **Rigurosa** al comunicar resultados, privacidad y alcance de cada herramienta: muestra el estado real de una aplicación, explica qué información se puede consultar y cuida lo que promete sobre anonimato o cumplimiento.

Español de México, sin signos de exclamación ni emoji.

White label: las páginas de una empresa llevan su logo y su acento (la consola lo configura); Humetría queda como el
sello «hecho con humetría». Tipografía y disposición son siempre las de Humetría.

## Anti-references

Qué no debe parecer:

- Infantil.
- Frío, gris corporativo.
- Cargado de información.
- Color sin jerarquía: el color de marca tiene carácter y los colores de estado se reservan para información que de verdad importa.
- Plantilla genérica de SaaS con IA: titulares válidos para cualquier empresa, tarjetas idénticas para todos los módulos, etiquetas decorativas, mockups que apenas muestran el producto.
- Portal de trámites: menús que reflejan la estructura del sistema, formularios que piden todo de golpe, lenguaje normativo donde la persona necesita una instrucción sencilla.

Referencias a imitar (panel):

- **Linear**: jerarquía de listas, filtros cerca de los datos, consistencia entre pantallas. Aplica al seguimiento de aplicaciones y a los resultados por centro de trabajo.
- **Stripe Dashboard**: pasar de un resumen operativo al detalle de un registro.

Landing (registro marca, por tarea): demostración de producto con un flujo real en pantalla, por ejemplo una persona responde desde el celular y RH consulta el avance.

## Design Principles

1. **Una cosa a la vez.** Cada pantalla responde una pregunta y ofrece una acción primaria.
2. **Estado antes que decoración.** Lo primero que se ve es qué requiere atención y cuál es el siguiente paso.
3. **Confidencialidad visible y prometida con precisión.** Se dice antes de la primera pregunta, y nunca se promete más anonimato o cumplimiento del que el sistema garantiza.
4. **Calma, no alarma.** El riesgo se comunica con texto, posición y color sobrio, nunca solo con rojo.
5. **Lenguaje de persona, no de norma.** Instrucciones sencillas donde se actúa; el texto normativo vive donde se consulta.

## Accessibility & Inclusion

- Meta: WCAG 2.2 AA. Texto ≥ 4.5:1, controles ≥ 3:1, áreas táctiles ≥ 44 px, texto de la encuesta ≥ 16 px.
- No depender solo del color: el riesgo usa texto, posición y color.
- Respetar `prefers-reduced-motion`; la encuesta nunca avanza sola.
- Probado con axe en las pruebas de navegador; claro y oscuro.
