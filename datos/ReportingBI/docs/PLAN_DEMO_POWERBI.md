# Plan: demo de Power BI de principio a fin sobre un Navision simulado (`datos/ReportingBI/`)

## Objetivo
Demostrar el ciclo completo de un informe de Power BI con un agente y Spec-Driven Development:

```
issue funcional → proposal OpenSpec (basada en el origen de datos real) → implementación
(vistas SQL + modelo semántico + medidas + informe) → prueba → despliegue en el servicio Power BI
```

Hay dos issues funcionales (*Control económico de obra* y *Plazos y avance*) sobre la base de datos de un ERP tipo Navision (NAV clásico). Esa base corre en contenedores y la usan **a la vez el agente y Power BI Desktop**. La integración con Power BI se hace con el **MCP oficial de Microsoft** allí donde existe; el resto se hace de la forma más sencilla posible.

**Datos:** 100 % ficticios, de una empresa constructora inventada que se llama `CONSTRUCTORA DEMO`.

## Decisiones

### Origen de datos: SQL Server en Docker con esquema tipo NAV
- NAV clásico guarda sus datos en SQL Server, y Power BI se conecta ahí directamente. Simular esa base es fiel a la realidad, ligero y multiplataforma.
- Microsoft no publica imagen Docker de NAV clásico, y Business Central solo funciona con contenedores Windows de más de 15 GB.
- Lo difícil de NAV forma parte del ejercicio: tablas con el prefijo de empresa, opciones guardadas como enteros, fechas vacías como `1753-01-01` y tablas personalizadas del rango 50000.

### Qué hace cada pieza en Power BI

| Paso | Herramienta | Por qué |
|---|---|---|
| Explorar el origen de datos | Agente con `sqlcmd` dentro del contenedor (`docker compose exec`) | Es lo más sencillo y no hace falta otro MCP. Entra con un usuario con permisos limitados. |
| Modelo semántico: tablas, relaciones, medidas y tabla de fechas | **MCP oficial `@microsoft/powerbi-modeling-mcp`** (preview) contra Power BI Desktop abierto o contra la carpeta PBIP | Es el MCP oficial y sirve justo para esto. Los cambios quedan en TMDL, versionados en Git. |
| Probar las medidas | El mismo MCP: ejecuta DAX y lo compara con SQL obra a obra | Así la prueba es automática y repetible. |
| Visuales del informe | Power BI Desktop, a mano | **Ningún MCP oficial crea páginas ni visuales.** El agente puede editar los JSON de PBIR, pero es frágil; queda como extra opcional. |
| Despliegue | Botón **Publicar** de Power BI Desktop en un área de trabajo de la licencia | Es lo más sencillo y funciona con una licencia Pro. Automatizarlo (API REST de Fabric o `fabric-cicd`) es un extra opcional que depende del tenant. |
| Prueba en el servicio (opcional) | MCP remoto de Power BI (`https://api.fabric.microsoft.com/v1/mcp/powerbi`, preview) | Consulta el modelo ya publicado. Necesita que el administrador del tenant lo tenga habilitado. |

### Actualización de datos en el servicio
Si el modelo se publica en modo importación, el servicio se queda con una copia de los datos y **no hace falta gateway** para enseñar el informe. Para que el servicio pueda refrescar contra el contenedor local hace falta instalar un **gateway de datos local** (vale el modo personal) en el mismo equipo. Lo dejamos como paso opcional.

## Estructura de `datos/ReportingBI/` (construida y probada el 23/11/2026)

```
datos/ReportingBI/
  README.md                       # requisitos, arranque, conexión y tablas (sin desvelar las situaciones sembradas)
  AGENTS.md                       # reglas del agente: NAV, db/query.sh, vistas rpt, PBIP/TMDL, MCP, prueba
  .env.example                    # contraseñas de SA y del usuario reporting (hay valores por defecto)
  .gitignore                      # caché y ajustes locales del PBIP
  docker-compose.yml              # SQL Server 2022 Developer, puerto 14330 en el host
  db/entrypoint.sh                # arranca SQL Server y crea NAVDEMO la primera vez
  db/query.sh                     # SQL con el usuario reporting: fichero, entrada estándar o argumento
  db/init/01_schema.sql           # generado: tablas NAV de dos empresas + esquema rpt vacío
  db/init/02_seed.sql             # generado: datos
  db/init/03_security.sql         # login reporting: lectura en dbo y vistas solo en rpt
  tools/generate_seed.py          # generador determinista (solo biblioteca estándar)
  docs/issues/01-control-economico.md
  docs/issues/02-plazos-avance.md
  docs/DEMO_POWERBI.md            # guion paso a paso de principio a fin
  docs/formador/NOTAS.md          # situaciones sembradas y trampas de NAV
  docs/formador/VALORES_ESPERADOS.md  # generado: cifras de control por obra
  .codex/config.toml, .mcp.json, .opencode/opencode.json   # MCP de modelado activo; remoto comentado
  sql/rpt/                        # vacío: aquí el agente crea las vistas durante la demo
  openspec/                       # lo crea `openspec init`
  Obras.pbip, Obras.SemanticModel/, Obras.Report/   # se crean durante la demo
```

**Lo aprendido al probarlo en Windows con Podman:**
- El servidor es `127.0.0.1,14330`, no `localhost`: Podman sobre WSL publica el puerto solo en IPv4.
- Con 2 GB en la máquina de Podman, SQL Server arranca. La documentación recomienda 4 GB.
- El agente pasa el SQL a `db/query.sh` por la entrada estándar o en un fichero: como
  argumento, las comillas se pierden al pasar por compose en Windows. La ruta va sin `/`
  inicial, porque Git Bash la reescribe.
- El MCP oficial (versión 1.0.1) arranca con `cmd /c npx` y expone 22 herramientas.
  Bloquea las herramientas hasta que se acepta la licencia.

**Usuario `reporting`:** lo usan tanto el agente como Power BI. Puede leer las tablas NAV pero no modificarlas, y solo puede crear objetos en el esquema `rpt`. Así el agente no puede estropear los datos de origen.

## Esquema simulado de NAV (`01_schema.sql`), empresa `CONSTRUCTORA DEMO$`

| Tabla | Uso | Columnas clave |
|---|---|---|
| `Job` | Obras | No_, Description, Bill-to Customer No_, Starting Date, Ending Date, Status (0 Planificación, 1 Oferta, 2 Abierta, 3 Terminada), Person Responsible, Global Dimension 1 Code (delegación), Global Dimension 2 Code (tipo de obra) |
| `Job Task` | Capítulos | Job No_, Job Task No_, Description, Job Task Type, Indentation |
| `Job Planning Line` | Presupuesto y planificación | Planning Date, Type, Quantity, Total Cost (LCY), Line Amount (LCY), Line Type (0 Budget, 1 Billable, 2 Both) |
| `Job Ledger Entry` | Movimientos reales | Entry Type (0 Usage = coste, 1 Sale = certificación), Posting Date, Total Cost (LCY), Line Amount (LCY), Document No_ |
| `Customer`, `Resource`, `Dimension Value` | Maestros | |
| `Job Progress Entry` (personalizada, 50000) | Avance de obra | Job No_, Job Task No_, Measurement Date, Completion % |
| `Job Milestone` (personalizada, 50001) | Hitos | Job No_, Code, Description, Planned Date, Actual Date (`1753-01-01` si no se ha cumplido) |

**Datos:** 10 obras de enero de 2025 a octubre de 2026, 3 delegaciones (Norte, Centro y Sur), 4 jefes de obra y 10 capítulos por obra (9 de ejecución y uno de costes indirectos). Además hay una segunda empresa de pruebas, `CONSTRUCTORA DEMO PRUEBAS`, con datos basura.

**Situaciones sembradas** (solo en las notas del formador):
- una obra con sobrecoste en Estructura;
- una obra con obra ejecutada pendiente de certificar;
- una obra retrasada con hitos incumplidos;
- una obra terminada con margen negativo;
- una línea de presupuesto con fecha vacía.

## Los dos issues funcionales
Siguen el formato de `datos/InfraData/docs/PROMPT_BACKLOG.md`: Contexto, Historia de usuario, Alcance, Fuera de alcance, Criterios de aceptación «Dado… cuando… entonces…» y Notas.

Describen **qué** quiere negocio, no cómo se hace. Los criterios de aceptación describen las situaciones que el informe tiene que mostrar («dada una obra con un capítulo que cuesta más de lo previsto…») sin nombrar obras concretas ni tablas: buscar el origen es trabajo del proposal, y qué obra cumple cada situación solo está en las notas del formador.

1. **Control económico de obra** (`01-control-economico.md`). Por obra y capítulo: presupuesto de coste y de venta, coste real, producción, certificado, obra pendiente de certificar, margen previsto y real, y desviación de coste. Página resumen, detalle obra × capítulo, evolución mensual y filtros por delegación, tipo de obra, jefe de obra y estado.
2. **Plazos y avance** (`02-plazos-avance.md`). Avance planificado frente a real, desviación de avance e índice de plazo, curva S, hitos con su estado y días de retraso, y ranking de obras por desviación de avance. Usa los mismos filtros. **Amplía el modelo del issue 1**, así que enseña cómo evoluciona un modelo con un segundo cambio de OpenSpec.

## Flujo de principio a fin (para cada issue)
1. **Arranque:** `docker compose up -d`. Abres el agente en `datos/ReportingBI/` y Power BI Desktop en el mismo equipo.
2. **Proposal (Agente + OpenSpec):** `/openspec:proposal` a partir del issue. El agente explora la base con `sqlcmd` y escribe:
   - `proposal.md`;
   - `design.md`, con el mapeo de cada KPI a las tablas NAV y la lista de vistas `rpt`;
   - `tasks.md`;
   - la spec del informe con sus criterios.

   Tú revisas y apruebas.
3. **Implementación, capa SQL (Agente):** crea las vistas `rpt.*` en `sql/rpt/`, las aplica a la base y comprueba que cuadran con las tablas NAV.
4. **Implementación, modelo:**
   1. **Tú, en Power BI Desktop:** solo la primera vez, te conectas al esquema `rpt` y guardas como `Obras.pbip`.
   2. **Agente, con el MCP de modelado conectado a Desktop:** añade relaciones, tabla de fechas y medidas con su descripción, en formato español.
5. **Implementación, informe (Tú, en Power BI Desktop):** montas las páginas siguiendo la lista de visuales de `tasks.md`. Revisas el `git diff` de TMDL y PBIR.
6. **Prueba (Agente, con el MCP):** ejecuta DAX contra las medidas y lo compara con SQL. La diferencia tiene que ser 0. Además comprueba que las situaciones sembradas aparecen. Deja el resultado en el cambio de OpenSpec.
7. **Despliegue (Tú):** **Publicar** desde Desktop en el área de trabajo de la demo y abrir el informe en el servicio. Opcional: prueba con el MCP remoto sobre el modelo publicado.
8. **Cierre:** `/openspec:archive` y commit.

## Verificación antes del curso
- La base arranca desde cero (`docker compose down -v`, luego `up -d`) y el usuario `reporting` puede leer `dbo` pero no escribir en él.
- El generador es determinista: dos ejecuciones dan el mismo `02_seed.sql`.
- El MCP de modelado conecta con Desktop desde Codex y desde Claude Code.
- Hacer la demo completa con los dos issues, cronometrada, y apuntar en `docs/formador/NOTAS.md` los tiempos y los errores del agente.
- Publicar con la licencia y abrir el informe en el servicio.

## Requisitos previos del equipo
- Windows con Power BI Desktop. Opcionalmente, activar las previews de PBIP/TMDL/PBIR si la versión aún las tiene como preview.
- Docker Desktop.
- Node.js 22 o superior, para el MCP con `npx`.
- Licencia de Power BI (Pro o superior) y un área de trabajo para la demo.

## Pendiente de confirmar con la licencia
- Tipo de licencia y de área de trabajo: Pro, Premium por usuario o capacidad de Fabric. De eso depende si se puede automatizar el despliegue y usar XMLA.
- Si el tenant permite el MCP remoto de Power BI y el gateway personal.

## Fuera de alcance
- Navision o Business Central real.
- Despliegue automatizado por CI/CD y refresco programado en el servicio (quedan como extras).
- Datos reales de ninguna empresa.
