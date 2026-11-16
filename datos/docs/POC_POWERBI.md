# Prueba de concepto: de la spec al informe de Power BI con Codex

Objetivo: comprobar que la cadena completa funciona antes de usarla en el curso.
Base de datos en Podman → vista SQL hecha por Codex → modelo PBIP conectado a la vista →
medidas DAX escritas por Codex desde una mini spec → informe en Power BI Desktop.

Duración estimada: 45–60 minutos.

## 0. Preparación

- Contenedores de `datos/` levantados: `podman compose up -d`.
- `uv run python -m infradata check` muestra 12 sensores y 313.760 lecturas.
- En Power BI Desktop, Archivo → Opciones y configuración → Opciones → Características en
  versión preliminar: activar, si aparecen, «Opción de guardar proyecto de Power BI (.pbip)»,
  «Almacenar el modelo semántico con formato TMDL» y «Almacenar los informes con formato
  mejorado (PBIR)». Reiniciar Desktop.

## 1. Vista de reporting (Codex)

Con Codex abierto dentro de `datos/`:

```text
Crea una vista de reporting en PostgreSQL para el informe de Power BI. No modifiques
db/init/: la vista va en un fichero nuevo, sql/reporting/resumen_diario_sensor.sql, y la
aplicas con «podman compose exec -T db psql -U infradata -d infradata» leyendo ese fichero.

La vista se llama reporting.resumen_diario_sensor (crea el esquema reporting si no existe)
y tiene una fila por sensor y día (día en UTC) con: código del sensor, nombre, sede, tipo,
unidad, día, valor mínimo, máximo y medio, número de lecturas y completitud (lecturas
recibidas entre las 288 esperadas al día, de 0 a 1). Agrega en SQL con time_bucket.

Después comprueba con una consulta que la suma del número de lecturas de la vista es
igual al total de lecturas de la tabla, y enséñame el resultado.
```

## 2. Modelo conectado a la vista (tú, en Power BI Desktop)

1. Obtener datos → Base de datos PostgreSQL → servidor `localhost:5433`, base de datos
   `infradata`, modo Importar. Usuario y contraseña `infradata`.
2. Elegir solo la vista `reporting.resumen_diario_sensor` y Cargar.
3. Archivo → Guardar como → tipo «Archivos de proyecto de Power BI (*.pbip)» →
   `datos/powerbi/InfraData.pbip`.
4. **Cerrar Power BI Desktop.** Codex va a editar los ficheros y Desktop no recarga
   cambios externos.

Comprueba que existe `datos/powerbi/InfraData.SemanticModel/definition/` con ficheros
`.tmdl`. Si en lugar de eso hay un `model.bim`, falta activar TMDL (paso 0).

## 3. Medidas desde la spec (Codex)

```text
Lee powerbi/AGENTS.md y el modelo en powerbi/InfraData.SemanticModel/definition/.
No toques powerbi/InfraData.Report/.

1. Saca el servidor y la base de datos de la consulta de la tabla a dos parámetros
   en expressions.tmdl (Servidor = "localhost:5433", BaseDatos = "infradata") y haz que
   la tabla los use.

2. Añade estas medidas a la tabla de la vista, cada una con su requisito como
   descripción:

   - «Lecturas recibidas»: suma del número de lecturas.
     Requisito: número de lecturas que han llegado en el periodo y filtros elegidos.
   - «Lecturas esperadas»: 288 por cada fila (sensor y día).
     Requisito: lecturas que deberían haber llegado, una cada 5 minutos por sensor.
   - «Completitud %»: lecturas recibidas entre lecturas esperadas, en porcentaje con
     un decimal. Requisito: porcentaje de lecturas recibidas sobre las esperadas.
   - «Días incompletos»: número de filas con completitud menor que 1.
     Requisito: días en que un sensor no envió todas sus lecturas.

3. Revisa que el TMDL sigue bien formado y enséñame el diff.
```

## 4. Comprobar el resultado (tú, en Power BI Desktop)

1. Abrir `datos/powerbi/InfraData.pbip`. Si no abre, el error suele indicar el fichero y
   la línea del TMDL: pásaselo a Codex.
2. Actualizar los datos.
3. Comprobar que aparecen las cuatro medidas y que, al seleccionarlas, la descripción
   muestra el requisito.
4. Montar dos visuales:
   - Una tarjeta con «Completitud %» (todo el periodo).
   - Una matriz con el sensor en filas, el día en columnas y «Completitud %» como valor,
     con formato condicional de color de fondo. Es un mapa de calor: los huecos de
     lecturas se ven a simple vista.
5. Guardar y revisar en `git diff` qué ha cambiado en `InfraData.Report/`.

## 5. Opcional: validar las medidas con el MCP de Power BI

1. En `datos/.codex/config.toml`:

   ```toml
   [mcp_servers.powerbi]
   command = "npx"
   args = ["-y", "@microsoft/powerbi-modeling-mcp@latest", "--start"]
   ```

2. Con el proyecto abierto en Power BI Desktop, abre Codex en `datos/`. La primera vez el
   servidor pide aceptar su licencia: léela y acéptala tú.
3. Pide a Codex:

   ```text
   Conéctate con el MCP de Power BI al modelo abierto en Power BI Desktop. Ejecuta una
   consulta DAX que devuelva «Lecturas recibidas» y «Completitud %» por sensor para todo
   el periodo, y compara «Lecturas recibidas» con la misma suma hecha en SQL sobre la
   vista. Dime si coinciden sensor a sensor.
   ```

Si coinciden, la cadena completa funciona: la misma spec se comprueba en SQL y en el
modelo de Power BI.

## Qué anotar para el curso

- Tiempo real de cada paso.
- Errores del TMDL que tuvo que corregir Codex.
- Si el MCP funciona con Codex (Microsoft solo lo documenta con VS Code y Copilot).
- Capturas de los pasos 2, 3 (diff) y 4 (mapa de calor).
