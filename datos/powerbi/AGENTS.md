# AGENTS.md — Informe de Power BI (datos/powerbi/)

Proyecto de Power BI guardado como PBIP: el modelo semántico está en TMDL (texto) y el
informe en formato PBIR (JSON). Lee también `../AGENTS.md`.

## Mapa

- `*.pbip`: fichero que abre el proyecto en Power BI Desktop.
- `*.SemanticModel/definition/`: modelo en TMDL.
  - `expressions.tmdl`: parámetros y consultas compartidas de Power Query.
  - `tables/*.tmdl`: una tabla por fichero, con sus columnas, medidas y la consulta que la carga.
  - `relationships.tmdl`: relaciones entre tablas.
- `*.Report/`: páginas y visuales del informe.

## Reglas

- Las métricas del informe salen de la especificación. Cada medida lleva como descripción
  (líneas `///` justo encima) el requisito de la especificación del que sale.
- Las medidas van en el TMDL de la tabla a la que pertenecen. No crees tablas de medidas
  sin preguntar.
- Nombres de medidas en español y legibles por el usuario de negocio, por ejemplo
  «Completitud %». Formato numérico en español.
- El origen de datos se lee de los parámetros de `expressions.tmdl` (servidor y base de
  datos). No escribas servidor ni base de datos a mano en cada tabla.
- No guardes credenciales en ningún fichero.
- No toques `*.Report/` salvo que la tarea lo pida expresamente.
- Las agregaciones pesadas van en la vista SQL, no en DAX.
- Respeta la sangría y el formato del TMDL existente: un TMDL mal formado impide abrir el
  proyecto en Power BI Desktop.

## Antes de editar

- Si Power BI Desktop tiene el proyecto abierto, pide que lo cierren antes de editar los
  ficheros: Desktop no recarga los cambios externos y los sobrescribe al guardar.

## Terminado significa

- El proyecto se abre en Power BI Desktop sin errores y se actualiza contra la base de datos.
- Los valores de las medidas coinciden con los criterios de aceptación de la especificación.
