# Demo: del issue al informe publicado en Power BI

Ciclo completo con un agente (Codex o Claude Code) y OpenSpec, sobre la base de datos de un
Navision simulado:

```
issue → proposal → vistas SQL → modelo (MCP de Power BI) → informe → prueba → publicación → archivo
```

Se hace dos veces: el **issue 01** (control económico) crea el modelo y el **issue 02**
(plazos y avance) lo amplía. Duración aproximada: 90 minutos el issue 01 y 45 el 02.

En cada paso se indica quién actúa: **(Agente)** o **(Tú, en Power BI Desktop)**. Los
prompts son orientativos: el objetivo es enseñar el flujo, no copiar texto.

## 0. Preparación (antes de la sesión)

1. Base de datos levantada y sana (ver `README.md`):

   ```bash
   cd datos/ReportingBI
   docker compose up -d          # con Podman: podman compose up -d
   docker compose ps             # estado "healthy"
   ```

2. Power BI Desktop actualizado. En **Archivo → Opciones → Características en versión
   preliminar**, activa si aparecen el guardado como proyecto (`.pbip`), el formato TMDL y el
   formato PBIR. En versiones recientes ya vienen activados.
3. OpenSpec inicializado en esta carpeta (`openspec init`), si no se hace en clase.
4. Agente abierto **dentro de `datos/ReportingBI/`**. Comprueba que ve el MCP:
   - Codex: `/mcp` muestra `powerbi-modeling`.
   - Claude Code: aprueba el servidor de `.mcp.json` la primera vez y compruébalo con `/mcp`.
5. La primera vez que el agente use el MCP, el servidor pide aceptar su licencia (EULA).
   Léela y acéptala tú; no dejes que el agente la acepte por su cuenta.
6. Área de trabajo creada en el servicio Power BI para la demo, por ejemplo «Demo ReportingBI».

## 1. Leer el issue (Tú)

Abre `docs/issues/01-control-economico.md` y coméntalo: habla de negocio (producción,
certificación, desviación) y no dice ni una tabla. Encontrar el origen de los datos es parte
del trabajo.

## 2. Explorar el origen de datos (Agente)

```text
Lee AGENTS.md y docs/issues/01-control-economico.md. Antes de proponer nada, explora la base
de datos NAVDEMO con db/query.sh: qué empresas hay, qué tablas tiene la empresa real, qué
significan los campos que necesita el issue y qué valores toman las opciones (Status, Entry
Type, Line Type...). Busca también tablas que no sean estándar de Navision.
No crees nada todavía. Resúmeme el mapa: indicador del issue → tabla y campo, con las dudas
que tengas.
```

Lo interesante es ver al agente descubrir las rarezas de NAV: la empresa de pruebas, los
signos de las certificaciones, las fechas `1753-01-01`, las líneas de presupuesto que no son
facturables y la tabla a medida de avance.

## 3. Proposal de OpenSpec (Agente)

```text
opsx propose: informe de control económico de obra, a partir de docs/issues/01-control-economico.md
y de lo que hemos visto en la base de datos.
- design.md: mapeo de cada indicador a tablas y campos de NAV, lista de vistas rpt (modelo en
  estrella) y definición exacta de producción, margen y desviación.
- spec: un requisito por indicador, con escenarios Dado/Cuando/Entonces sacados del issue.
- tasks.md: vistas SQL, modelo con el MCP, medidas, páginas del informe (lista de visuales),
  prueba DAX frente a SQL y publicación.
```

**Revisa tú el proposal antes de seguir.** Es el punto de control de la demo: corrige las
definiciones si no te convencen (por ejemplo, si la desviación compara coste con presupuesto
total en lugar de con lo producido).

## 4. Vistas de reporting (Agente)

```text
opsx apply: implementa solo las tareas de las vistas SQL. Un fichero por vista en sql/rpt/,
aplícalas con db/query.sh -f y comprueba que cuadran con las tablas de NAV obra a obra.
Enséñame la tabla de comprobación.
```

## 5. Crear el proyecto de Power BI (Tú, en Power BI Desktop)

Solo la primera vez:

1. **Obtener datos → SQL Server**: servidor `127.0.0.1,14330`, base de datos `NAVDEMO`, modo
   **Importar**.
2. Autenticación **Base de datos**: usuario `reporting`, contraseña `Informes#2026`. Si avisa
   de que la conexión no está cifrada, acepta.
3. Selecciona **solo las vistas del esquema `rpt`** y pulsa **Cargar**.
4. **Archivo → Guardar como → Archivos de proyecto de Power BI (*.pbip)** →
   `datos/ReportingBI/Obras.pbip`.
5. **Deja Power BI Desktop abierto**: el agente trabaja sobre el modelo abierto a través del MCP.

## 6. Modelo semántico con el MCP (Agente)

```text
opsx apply: tareas del modelo. Conéctate con el MCP de Power BI al modelo 'Obras' abierto en
Power BI Desktop y:
- crea las relaciones del modelo en estrella y una tabla de fechas marcada como tal;
- crea las medidas del design, cada una en su tabla de hechos, con la descripción sacada del
  requisito de la spec, nombre en español y formato español;
- pasa el servidor y la base de datos a parámetros (Servidor, BaseDatos).
Al terminar, lista lo que has creado.
```

En Power BI Desktop, guarda (Ctrl+S) y enseña el `git diff` de `Obras.SemanticModel/`: las
medidas, relaciones y descripciones aparecen como texto TMDL versionable.

## 7. Informe (Tú, en Power BI Desktop)

Monta las páginas siguiendo la lista de visuales de `tasks.md`, por ejemplo:

- **Resumen**: tarjetas de presupuesto de venta, coste real, producción, certificado y margen
  real; tabla por obra con pendiente de certificar y margen real (formato condicional en
  negativos); segmentadores de delegación, tipo de obra, jefe de obra y estado.
- **Detalle por capítulo**: matriz obra × capítulo con la desviación de coste y formato
  condicional de color de fondo.
- **Evolución**: líneas mensuales de coste real, producción y certificado.

Guarda y enseña el `git diff` de `Obras.Report/`.

> Ningún MCP oficial crea visuales. Como extra, se le puede pedir al agente que añada una
> página editando los JSON de PBIR con Desktop cerrado; funciona, pero es frágil.

## 8. Prueba (Agente)

```text
opsx apply: tarea de prueba. Con el MCP, ejecuta una consulta DAX que devuelva todas las
medidas por obra. Calcula las mismas cifras en SQL sobre las tablas de NAV (no sobre las
vistas rpt) y compáralas obra a obra. Guarda la tabla de comparación en el cambio de
OpenSpec y dime qué obras incumplen algún criterio del issue (desviación, pendiente de
certificar, margen negativo).
```

Tiene que dar diferencia 0 en todas las medidas. Comenta las obras que destaca el agente:
son las situaciones que esconden los datos.

Después, `opsx verify` para comprobar el cambio contra la spec.

## 9. Publicación (Tú)

1. En Power BI Desktop: **Inicio → Publicar** → área de trabajo de la demo.
2. Abre el informe en app.powerbi.com y repasa los criterios de aceptación del issue.
3. El modelo se ha publicado con los datos importados: no hace falta gateway para enseñarlo.
   Para refrescar desde el servicio contra el contenedor haría falta un gateway de datos
   local en este equipo (fuera de la demo).

Opcional, si el tenant lo permite: prueba sobre el modelo publicado con el MCP remoto de
Power BI (descomentar `powerbi-remote` en `.codex/config.toml`):

```text
Con el MCP remoto de Power BI, consulta el modelo semántico 'Obras' del área de trabajo
'Demo ReportingBI' y dime el margen real y el pendiente de certificar por obra.
Compáralo con la tabla de prueba del paso 8.
```

## 10. Cierre (Agente)

```text
opsx archive del cambio del informe de control económico.
```

Commit con las vistas, el proyecto PBIP y los cambios de OpenSpec.

## 11. Issue 02: plazos y avance

Mismo ciclo, más corto, que **amplía el modelo existente**:

1. **(Agente)** Explorar `docs/issues/02-plazos-avance.md` frente a la base: tienen que salir
   las tablas a medida de avance y de hitos, y la línea de presupuesto sin fecha.
2. **(Agente)** `opsx propose` del cambio, con una spec que modifica la del issue 01 donde haga falta.
3. **(Agente)** Vistas nuevas en `sql/rpt/`.
4. **(Tú)** En Power BI Desktop, añade las vistas nuevas al modelo (**Obtener datos** o
   **Transformar datos → Nuevo origen**) y guarda.
5. **(Agente, MCP)** Relaciones y medidas: avance planificado, avance real, desviación de
   avance, índice de plazo, hitos retrasados y días de retraso.
6. **(Tú)** Página **Plazos**: curva S (planificado frente a real por mes), tabla de hitos con
   semáforo y ranking de obras por desviación de avance.
7. **(Agente)** Prueba DAX frente a SQL, `opsx verify`.
8. **(Tú)** Publicar de nuevo (reemplaza el modelo y el informe).
9. **(Agente)** `opsx archive`.

## Problemas frecuentes

| Síntoma | Causa y solución |
|---|---|
| El contenedor se reinicia o se para al arrancar | Poca memoria: SQL Server necesita 2 GB. Con Podman: `podman machine set --memory 4096` |
| Power BI no conecta con `localhost,14330` | Usa `127.0.0.1,14330`: Podman publica el puerto solo en IPv4 |
| `db/query.sh: No such file or directory` con una ruta `C:/Program Files/Git/...` | La ruta se escribió con `/` delante y Git Bash la reescribió. Usa `db/query.sh` |
| El SQL llega sin comillas | Se pasó como argumento. Pásalo por la entrada estándar o en un fichero |
| El agente no ve el MCP | Abre el agente dentro de `datos/ReportingBI/`; en Codex, confía en la carpeta |
| El MCP no encuentra el modelo | Power BI Desktop tiene que tener `Obras.pbip` abierto |
| Los cambios del agente en TMDL desaparecen | Se editaron ficheros con Desktop abierto. Con Desktop abierto, todo por el MCP |

## Qué anotar para el curso

- Tiempo real de cada paso.
- Qué rarezas de NAV encontró el agente solo y cuáles hubo que señalarle.
- Correcciones que hiciste al proposal.
- Errores del MCP o del TMDL y cómo se resolvieron.
- Capturas: mapa del paso 2, proposal, `git diff` del modelo, informe en Desktop y en el servicio.
