# Valoración de la resolución

Cambio OpenSpec: [valorar-resolucion-incidencias](../openspec/changes/archive/2026-11-25-valorar-resolucion-incidencias/proposal.md). Validado el 25 de noviembre de 2026.

## Comportamiento

- El solicitante puede enviar una única puntuación entera de 1 a 5 estrellas desde la ficha de una incidencia propia cerrada con técnico del cierre. El envío es voluntario y definitivo; la ficha conserva puntuación y fecha para su propietario.
- Técnicos y administradores no pueden valorar, aunque hayan creado la incidencia. El equipo no recibe puntuaciones individuales mediante esta funcionalidad.
- La atribución se captura al cerrar, conjuntamente con estado e historial. Reasignar después no mueve las puntuaciones entre técnicos.
- El dashboard del administrador muestra «Valoraciones por técnico»: media a un decimal sobre 5 y número de valoraciones. Excluye incidencias sin puntuación y muestra «Sin valoraciones» cuando corresponde. Actualiza al cargar y cada 30 segundos mientras está visible, con paginación de 5, 10 o 25 filas.
- Incluye técnicos actuales y cuentas conservadas como técnico del cierre; las que cambian de rol aparecen como «Histórico». La atribución histórica impide eliminar la cuenta, pero no añade restricciones al cambio de rol.
- Un formulario que queda obsoleto porque otra pestaña ya envió la valoración muestra un aviso y conserva el envío confirmado.

## Migración y despliegue

La migración `2026_11_24_120000_add_resolution_rating_to_incidents_table.php` añade referencia al técnico del cierre, puntuación y fecha, con índice y restricciones de integridad. No crea valoraciones ni altera fechas existentes. En SQLite utiliza adiciones nativas para conservar comentarios, historial y las claves foráneas anteriores sin reconstruir la tabla.

Para incidencias que ya estaban cerradas, utiliza el técnico asignado durante la migración. Es una aproximación: el sistema no dispone de historial de asignaciones para reconstruir al técnico original del cierre. Las cerradas sin técnico quedan sin posibilidad de valorar, incluso si se asignan después. Las no cerradas capturan el técnico al cerrarse en el futuro.

Aplicar con una breve ventana sin escrituras concurrentes y con el código nuevo disponible antes de reabrir el acceso:

```bash
docker compose exec app php artisan down
docker compose exec app php artisan migrate --force
npm run build
docker compose exec app php artisan up
```

En una reversión de código, conservar las nuevas columnas y los datos. Ejecutar el `down` de esta migración elimina las puntuaciones, fechas y atribuciones; requiere aceptar esa pérdida y guardar una copia previamente. La reversión se ha comprobado únicamente en bases de prueba, no sobre datos de uso.

## Validación automatizada

| Comprobación | Resultado |
|---|---|
| `docker compose exec app php artisan test` | 102 pruebas, 1.260 aserciones; todas pasan |
| `docker compose exec app php tests/Support/check-postgres-concurrency.php` | Migración, inicialización histórica y reversión correctas en base temporal PostgreSQL; conserva comentarios e historial |
| Carreras del mismo script | Asignación, transición, doble valoración y cierre frente a reasignación: todas pasan |
| Pint sobre los 16 archivos PHP del cambio | Formato aplicado; comprobaciones posteriores de los archivos ajustados correctas |
| `npm run build` | Compilación correcta |

`pint --dirty` no puede detectar Git en el contenedor porque el directorio `.git` está fuera de `web/` y no se monta en `/app`. Se ejecutó Pint sobre la lista explícita de archivos PHP modificados y nuevos. No se alteró la configuración global de Git.

La compilación inicial encontró dependencias nativas de Windows en `node_modules`. Se instaló el componente nativo de Linux sin guardar cambios en `package.json` ni en el lockfile y se recompilaron los assets.

Las salidas completas, incluidos los fallos corregidos, se conservaron durante la sesión en `/tmp/infraticket-rating-*.log`. Resultados finales: `infraticket-rating-suite-complete.log`, `infraticket-rating-postgres-complete.log`, `infraticket-rating-build-final.log` y los logs de Pint.

## Comprobación de interfaz

Se utilizó `agent-browser` contra la aplicación local con las cuentas de demostración. Se creó una incidencia cerrada de prueba y se envió una puntuación de 2 estrellas: la acción desapareció, la ficha mostró el valor y el dashboard administrativo pasó a «2,0 / 5» con una valoración.

Se comprobaron ancho de 320px y ampliación CSS del 200%, ausencia de desbordamiento horizontal de página, etiquetas del árbol de accesibilidad, grupo de opciones sin selección inicial, foco visible y navegación con flechas. Los cinco controles de estrellas tienen una altura de 44px. La comprobación del árbol de accesibilidad no sustituye una prueba auditiva con un lector de pantalla real.

- [Diálogo del solicitante en móvil](screenshots/valoracion-solicitante-movil.png).
- [Diálogo del solicitante al 200%](screenshots/valoracion-solicitante-200.png).
- [Resumen del administrador en móvil](screenshots/valoraciones-administrador-movil.png).
- [Resumen del administrador al 200%](screenshots/valoraciones-administrador-200.png).

Los puertos habituales 8000 y 5432 estaban ocupados. Se utilizó un fichero Compose temporal externo al repositorio para publicar la app en `http://localhost:18000/admin` y PostgreSQL en 55432, sin detener los servicios que ocupaban los puertos originales. La configuración versionada del proyecto conserva sus puertos habituales. La incidencia de comprobación se mantiene como dato de demostración en este entorno local de pruebas.
