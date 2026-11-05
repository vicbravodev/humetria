#!/bin/sh
# Abre los prototipos del diseño en http://localhost:8765/<board>.dc.html (agrega ?tema=oscuro)
cd "$(dirname "$0")/pantallas/fuente" && echo "Abre http://localhost:8765/V2-Panel.dc.html" && python3 -m http.server 8765
