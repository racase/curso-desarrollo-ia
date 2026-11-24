# Validación de usuarios, sedes y resumen

Cambio: `incorporar-usuarios-catalogo-y-metricas` (issues 4, 5 y 6).

## Estado de la entrega

Implementación de los tres bloques completada. El usuario solicitó detener las
pruebas de Docker y navegador el 24/11/2026 y realizar él mismo la validación final.
Los resultados de abajo corresponden únicamente a ejecuciones anteriores a esa
instrucción. Las nuevas pruebas del widget están escritas pero no ejecutadas.
Quedan pendientes la suite final, Pint, la concurrencia tras las migraciones de
catálogo, la revisión visual y el flujo integrado. Por petición posterior del usuario, las 21 tareas de OpenSpec se marcan completadas; ese cierre administrativo no acredita las verificaciones pendientes. No se ha archivado el cambio.

## Usuarios

El acceso inicial de demostración es `admin@infraticket.test`, contraseña `password`.
También existen `tecnico1@infraticket.test`, `tecnico2@infraticket.test`,
`solicitante1@infraticket.test` y `solicitante2@infraticket.test`, con la misma contraseña.
El seeder conserva las cuentas existentes. Estas credenciales solo son para la demo local.

Solo administradores pueden listar, crear, editar y eliminar cuentas. Las nuevas
contraseñas requieren confirmación y al menos 12 caracteres. En edición, dejar la
contraseña vacía conserva la anterior; el formulario nunca precarga el hash.
Nombre y email se recortan y el email se normaliza a minúsculas.

No se permite eliminar la propia cuenta ni cuentas referenciadas como solicitante,
técnico, autor de comentario o actor de historial. Debe quedar al menos un
administrador. Un técnico asignado conserva su rol hasta reasignar todos sus casos,
incluidos los cerrados. Los cambios de rol se aplican en la siguiente petición y
las acciones releen al actor dentro de la transacción.

Evidencia: `UserManagementTest` (5 pruebas, 42 aserciones), `UserPanelTest`
(3 pruebas, 62 aserciones) y `tests/Support/check-user-concurrency.php`.
La comprobación concurrente crea y elimina exclusivamente una base PostgreSQL
temporal: retirada simultánea de roles, borrado cruzado y asignación frente a
cambio de rol producen un ganador y un rechazo, sin romper las invariantes.

## Catálogo y despliegue

El catálogo fijo contiene S01–S06 (Sede 01–06) y S01-A01…S06-A05
(Activo 01…05 por sede). No hay mantenimiento de inventario en el panel.
`SiteAssetCatalogTest` verifica las identidades, pertenencias, 6/30 registros,
repetición estable, rollback transaccional ante datos inesperados y restricciones.
Las factories no dependen del seeder; `Incident::factory()->withoutAsset()`
representa el legado.

Despliegue conservando datos:

```sh
docker-compose exec -T app php artisan migrate --force
docker-compose exec -T app php artisan db:seed --class=SiteAssetSeeder --force
```

El arranque habitual ya ejecuta migraciones y `DatabaseSeeder`, que incorpora
el catálogo. Comprobar 6 sedes y 30 activos antes de aceptar nuevas altas. Ante
un catálogo inesperado, el seeder explica el conflicto y revierte su transacción;
no elimina registros para forzar el total.

Las incidencias anteriores conservan `asset_id = null`, solicitante, técnico,
estado, comentarios e historial. Para rollback operativo, restaurar el código
anterior manteniendo tablas y columna nuevas. Un rollback estructural requiere
exportar previamente los vínculos; no se usa como procedimiento de vuelta atrás.
La prueba aplica las migraciones sobre el esquema anterior con una incidencia,
comentario e historial existentes y confirma su conservación. El alta SQLite
emplea `ALTER TABLE ADD COLUMN` nativo para evitar reconstrucciones con cascadas.

## Alta y corrección de activo

Todo rol selecciona un activo obligatorio y buscable, identificado por código,
nombre y sede. La sede deriva exclusivamente del activo; se ignora un vínculo
de sede enviado por el cliente. Las incidencias anteriores muestran «Sin activo»
y «Sin sede». Solo un administrador puede usar «Vincular o corregir activo» en
la ficha, incluso cerrada. La acción conserva los demás campos, asignación,
comentarios e historial; la edición general continúa sin ruta ni autorización.
`IncidentAssetTest` cubre acciones y componentes (5 pruebas, 129 aserciones,
ejecutadas correctamente antes de detener la validación).

## Resumen

«Incidencias no cerradas» muestra Propuesta, Abierta, En curso y Resuelta, en ese
orden y con cero cuando no hay casos. Cerrada se excluye. Los solicitantes ven
solo sus registros; técnicos y administradores ven todos. Los filtros del listado
no afectan al resumen, que no tiene enlaces ni acciones. Se calcula al cargar y
recargar, y cada 30 segundos mientras está visible, sin caché compartida ni promesa
de tiempo real. Los legados sin activo también cuentan. Se registra una sola vez
mediante el descubrimiento de widgets existente y conserva `AccountWidget`.
`IncidentOverviewTest` deja preparados los casos de dos solicitantes, ambos roles
de equipo, ceros, alta, cierre y cambio de usuario; su ejecución queda pendiente.

## Validación pendiente a cargo del usuario

Tras aplicar migraciones y catálogo, ejecutar desde `web/`:

```sh
docker-compose exec -T app php artisan test
docker-compose exec -T app php tests/Support/check-postgres-concurrency.php
docker-compose exec -T app php tests/Support/check-user-concurrency.php
docker-compose exec -T app vendor/bin/pint --test
```

Revisar usuarios, alta, ficha y dashboard con los tres roles a 320px y escritorio,
teclado, ampliación del 200%, foco de las confirmaciones y actualización visible
de los recuentos a los 30 segundos. Guardar las capturas en `docs/screenshots/`.
Comprobar el flujo: administrador crea una cuenta solicitante, esta registra una
incidencia con activo, un técnico se la asigna y avanza su estado, y ambos dashboards
reflejan los universos autorizados. Abrir también una incidencia anterior sin
activo y vincularla como administrador.

## Trazabilidad

| Issue | Especificación | Implementación |
| --- | --- | --- |
| 4 | gestion-usuarios | UserPolicy, Actions/Users, Resources/Users y comprobación PostgreSQL aislada |
| 5 | sedes-activos | Site, Asset, migraciones, SiteAssetSeeder y factories |
| 5 | incidencias | CreateIncident, LinkIncidentAsset, política, listado y ficha |
| 6 | resumen-incidencias | IncidentStatusOverview, agregado visibleTo y polling visible de 30 segundos |

Se conserva el tema existente basado en `DESIGN.md`; los formularios añaden
etiquetas, ayuda persistente y autocompletado `new-password`, sin precargar hashes.
OpenWiki no se ha modificado. Su workflow local programa actualizaciones diarias
y admite ejecución manual; la configuración es preexistente y no se toma como
evidencia de una regeneración completada de estos cambios locales sin publicar.

`openspec validate incorporar-usuarios-catalogo-y-metricas --strict`: correcto.
`git diff --check`: correcto. Estas comprobaciones son estáticas; no sustituyen
las pruebas pendientes.

## Comandos y resultados anteriores a la interrupción

En este equipo se usa `docker-compose` (v2.37.3), equivalente a `docker compose`;
el subcomando `docker compose` no está instalado. Ejecutar desde `web/`.

- Base inicial: `docker-compose exec -T app php artisan test --compact`: 39 pruebas,
  532 aserciones, todas correctas.
- `docker-compose exec -T app php artisan test --compact --filter UserManagementTest`:
  5 pruebas, 42 aserciones, correctas.
- `docker-compose exec -T app php artisan test --compact --filter UserPanelTest`:
  3 pruebas, 62 aserciones, correctas tras separar las validaciones de formulario y acción.
- `docker-compose exec -T app php tests/Support/check-user-concurrency.php`:
  demote, delete y technician: un ganador, un conflicto comprensible, invariantes conservadas.
- `docker-compose exec -T app php artisan test --compact --filter SiteAssetCatalogTest`:
  5 pruebas, 111 aserciones, correctas.
- `docker-compose exec -T app php artisan test --compact --filter IncidentAssetTest`:
  5 pruebas, 129 aserciones, correctas.

### Fallos encontrados y corregidos

Las salidas completas conservadas de los primeros intentos están en
`catalog-test-output.txt` e `incident-asset-test-output.txt`. El catálogo necesitó
eliminar explícitamente el índice al revertir y usar `ADD COLUMN` nativo en SQLite
para conservar las referencias. La prueba de migración parte del esquema anterior
y compara datos persistidos. En las pruebas de activo se corrigieron el montaje
directo de una acción oculta y la comprobación de claves de opciones agrupadas.

Primer intento de `UserPanelTest`: Filament detiene la validación del formulario
antes de llamar a la acción; la prueba de email duplicado ahora usa nombre válido.

```text
FAILED Tests\Feature\UserPanelTest > validation and role changes are enforced in the panel
Component missing error: data.email
Failed asserting that false is true.
at vendor/livewire/livewire/src/Features/SupportValidation/TestsValidation.php:86
82: PHPUnit::assertTrue($errors->isNotEmpty(), 'Component has no errors.');
85: if (is_null($value)) {
86: PHPUnit::assertTrue($errors->has($key), "Component missing error: $key");
+5 vendor frames
tests/Feature/UserPanelTest.php:48
Tests: 1 failed, 2 passed (53 assertions)
Duration: 6.42s
```

Primer intento concurrente: el actor eliminado por la operación ganadora producía
un error de registro inexistente. Ahora se reevalúa autorización con actor nullable.

```text
demote: one winner, one understandable conflict; invariants preserved.
RuntimeException
Expected one winner and one conflict: ["In Builder.php line 677:\n                                                  \n  No query results for model [App\\Models\\User] 3","accepted"]
at tests/Support/check-user-concurrency.php:28
24: function check(bool $condition, string $message): void
26: if (! $condition) {
28: throw new RuntimeException($message);
tests/Support/check-user-concurrency.php:88
```
