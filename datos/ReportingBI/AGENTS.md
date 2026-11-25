# AGENTS.md — ReportingBI

Informes de Power BI sobre la base de datos de un ERP Navision (NAV clásico) de una
constructora ficticia, `CONSTRUCTORA DEMO`. La base corre en un contenedor de SQL Server y
la usan a la vez el agente y Power BI Desktop. El ciclo de trabajo es: issue → proposal de
OpenSpec → vistas SQL → modelo semántico → informe → prueba → publicación.

Idioma: documentación, specs, nombres de medidas y mensajes, en español. SQL y TMDL siguen
las convenciones de este fichero.

## Mapa

| Ruta | Contenido |
|---|---|
| `docs/issues/` | Issues funcionales: el punto de partida de cada cambio |
| `db/init/` | Esquema y datos de la base NAV. **No se tocan.** Los genera `tools/generate_seed.py` |
| `db/query.sh` | Ejecuta SQL con el usuario `reporting` dentro del contenedor |
| `sql/rpt/` | Vistas de reporting, una por fichero: aquí trabajas |
| `Obras.pbip`, `Obras.SemanticModel/`, `Obras.Report/` | Proyecto de Power BI (aparece en la demo) |
| `openspec/` | Specs y cambios de OpenSpec (aparece con `openspec init`) |

## Base de datos

- SQL Server en un contenedor, base `NAVDEMO`, en `127.0.0.1,14330` desde el equipo
  (usa la IP: con Podman, `localhost` puede no responder).
- Arranque: `docker compose up -d`. Si el equipo usa Podman, cambia `docker compose` por
  `podman compose` en todos los comandos.
- Consultas: siempre a través de `db/query.sh`, ejecutado desde esta carpeta. El SQL va en un
  fichero o por la entrada estándar; pasarlo como argumento solo vale para consultas sin
  comillas, porque en Windows se pierden al pasar por compose.

  ```bash
  # Un fichero
  docker compose exec -T db bash db/query.sh -f sql/rpt/01_dim_obra.sql
  # Por la entrada estándar (bash)
  docker compose exec -T db bash db/query.sh <<'SQL'
  SELECT TOP 5 [No_], [Description] FROM [CONSTRUCTORA DEMO$Job];
  SQL
  ```

  ```powershell
  # Por la entrada estándar (PowerShell): here-string con comillas simples
  @'
  SELECT TOP 5 [No_], [Description] FROM [CONSTRUCTORA DEMO$Job];
  '@ | docker compose exec -T db bash db/query.sh
  ```

- Escribe la ruta del script tal cual, `db/query.sh`, sin `/` delante: Git Bash en Windows
  reescribe las rutas que empiezan por `/`.

- El usuario `reporting` lee todas las tablas y solo puede crear vistas en el esquema `rpt`.
  No intentes escribir en `dbo` ni pedir la contraseña de `sa`.
- Pide resultados agregados o `TOP n`; no vuelques tablas enteras.

### Cómo es una base de NAV

- Cada tabla se llama `[EMPRESA$Tabla]`. Hay dos empresas (tabla `[Company]`): averigua
  cuál es la real antes de usarla.
- Los nombres de columna conservan espacios y cambian `.` y `%` por `_` (`[No_]`,
  `[Completion _]`). Usa siempre corchetes.
- Ningún campo es NULL: un texto vacío es `''` y una fecha vacía es `1753-01-01`.
- Las opciones se guardan como enteros (`Status`, `Entry Type`, `Line Type`...). Si no sabes
  qué significa un valor, pregúntalo o anótalo como pregunta abierta; no lo inventes.
- Los importes son `decimal(38,20)` y en algunos movimientos van con signo negativo.
- Puede haber tablas a medida del partner además de las estándar.

## Vistas de reporting (`sql/rpt/`)

- Un fichero por vista, `NN_nombre.sql`, con `CREATE OR ALTER VIEW [rpt].[Nombre]`.
  Aplícalo con `db/query.sh -f`.
- Las vistas traducen NAV a negocio: quitan el prefijo de empresa, filtran la empresa real,
  pasan las opciones a texto, las fechas `1753-01-01` a NULL y los importes a `decimal(18,2)`
  con el signo de negocio.
- Modelo en estrella: dimensiones (`Dim...`) y hechos (`Fact...`) con claves explícitas.
- Las agregaciones pesadas van en SQL; DAX hace los cálculos que dependen del filtro.
- Después de crear una vista, comprueba con una consulta que cuadra con las tablas de origen
  (totales por obra) y enseña el resultado.

## Power BI (PBIP, TMDL y PBIR)

- `Obras.SemanticModel/definition/` es el modelo en TMDL; `Obras.Report/` es el informe en PBIR.
- Usa el **MCP de Power BI** (`powerbi-modeling`) para el modelo: conectarte al modelo abierto
  en Power BI Desktop, crear relaciones, medidas y tabla de fechas, y ejecutar DAX.
  La primera vez el servidor pide aceptar su licencia: pídesela a la persona, no la aceptes tú.
- Si editas ficheros TMDL directamente, Power BI Desktop tiene que estar **cerrado**: no
  recarga cambios externos y los sobrescribe al guardar. Pide que lo cierren.
- Cada medida lleva como descripción el criterio de la spec del que sale.
- Medidas con nombres de negocio en español y formato español (`#,0.00 €`, `0.0 %`).
- Las medidas van en la tabla de hechos a la que pertenecen. No crees tablas de medidas sin preguntar.
- El servidor y la base de datos se leen de parámetros (`Servidor`, `BaseDatos`), no se
  escriben en cada tabla. **No guardes credenciales en ningún fichero.**
- No toques `Obras.Report/` salvo que la tarea lo pida expresamente: los visuales los monta
  la persona en Power BI Desktop.

## Prueba

Una medida está terminada cuando su valor, calculado con DAX mediante el MCP, coincide con
la misma cifra calculada en SQL sobre las tablas de NAV, obra a obra. Deja la tabla de
comparación en el cambio de OpenSpec.

## Terminado significa

- Las vistas de `sql/rpt/` se aplican sin errores y cuadran con el origen.
- El proyecto se abre y se actualiza en Power BI Desktop sin errores.
- Las medidas cumplen los criterios de aceptación del issue y la prueba DAX frente a SQL da diferencia 0.
- No hay credenciales en ningún fichero.
