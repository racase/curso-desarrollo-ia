# Repository Guidelines

## Estructura y flujo de trabajo

ReportingBI desarrolla informes de Power BI sobre NAV clásico para `CONSTRUCTORA DEMO`. Sigue el ciclo: issue → propuesta OpenSpec → vistas SQL → modelo semántico → informe → prueba → publicación.

- `docs/issues/`: requisitos y criterios de aceptación.
- `openspec/`: propuestas, especificaciones y evidencias de validación.
- `sql/rpt/`: vistas de reporting; se crean durante el desarrollo.
- `db/init/`: esquema y datos generados; no editar directamente.
- `tools/generate_seed.py`: generador de datos ficticios.
- `Obras.SemanticModel/` y `Obras.Report/`: modelo TMDL e informe PBIR cuando se creen.

## Comandos de desarrollo

Ejecuta desde esta carpeta:

```bash
docker compose up -d
docker compose ps
docker compose exec -T db bash db/query.sh -f sql/rpt/01_dim_obra.sql
```

Los comandos arrancan SQL Server, comprueban su estado y aplican una vista. Espera al estado `healthy`. Con Podman, sustituye `docker compose` por `podman compose`. Pasa SQL mediante fichero o entrada estándar para conservar las comillas en Windows. Conserva la ruta relativa `db/query.sh`.

## Estilo y convenciones

Escribe documentación, especificaciones y medidas en español. No hay un formateador ni linter configurado; conserva el estilo existente.

Usa un fichero `NN_nombre.sql` por vista, con `CREATE OR ALTER VIEW [rpt].[Nombre]`, dimensiones `Dim...` y hechos `Fact...`. Encierra identificadores SQL entre corchetes. Confirma la empresa real consultando `[Company]`; no inventes significados de opciones numéricas.

Las vistas convierten fechas vacías `1753-01-01` a NULL e importes a `decimal(18,2)` con signo de negocio. Sitúa agregaciones pesadas en SQL y cálculos dependientes del filtro en DAX.

## Modelo y pruebas

Usa el MCP `powerbi-modeling` para relaciones, medidas, calendario y consultas DAX. La persona debe aceptar su licencia. Antes de editar TMDL directamente, pide cerrar Power BI Desktop. No modifiques `Obras.Report/` sin petición expresa.

Cada medida pertenece a su tabla de hechos y describe el criterio de la spec. Usa formatos `#,0.00 €` y `0.0 %`.

No hay framework de pruebas ni cobertura configurados. Contrasta vistas y medidas contra NAV, obra a obra: SQL frente a DAX debe dar diferencia cero. Guarda la comparación en OpenSpec y verifica apertura y actualización en Desktop.

## Commits, revisiones y configuración

El historial usa mensajes breves, generalmente en español, sin prefijos obligatorios. Describe el cambio concreto. Incluye en cada PR el issue, alcance y evidencias SQL/DAX; añade capturas si cambia el informe.

Conecta a `127.0.0.1,14330`, base `NAVDEMO`, mediante `reporting`. No escribas en `dbo` ni guardes credenciales. Usa parámetros `Servidor` y `BaseDatos` en el modelo.
