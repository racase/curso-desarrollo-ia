# Verificación de administración, catálogo y resumen

Cambio: `add-administration-and-ticket-overview`. Fecha: 16 de noviembre de 2026. Issues: [#4](https://github.com/racase/curso-desarrollo-ia/issues/4), [#5](https://github.com/racase/curso-desarrollo-ia/issues/5) y [#6](https://github.com/racase/curso-desarrollo-ia/issues/6).

## Resultado

- Suite completa: **83 pruebas, 628 comprobaciones**, sin fallos ni avisos, mediante `php vendor/bin/phpunit --fail-on-warning`.
- Formato: `vendor/bin/pint --test`, 119 archivos comprobados.
- OpenSpec: `openspec validate add-administration-and-ticket-overview --strict --no-interactive`, válido.
- Migración y reversión completas verificadas en SQLite y PostgreSQL desechables; las incidencias anteriores conservan sus datos sin activo. Se corrigió la reversión de SQLite para eliminar el índice de activo antes de su columna.

## Entorno y repetición

Se utilizó Podman con `compose.testing.yaml`, proyecto `infraticket-check`. La suite combina SQLite en memoria y la base PostgreSQL desechable `infraticket_test`; no utiliza PostgreSQL de desarrollo. Para repetir, iniciar ese Compose y ejecutar PHPUnit y Pint dentro de su servicio `app`.

La revisión en navegador utilizó `http://127.0.0.1:18000/admin` con la base SQLite independiente `/tmp/infraticket-review.sqlite`. El soporte `tests/Support/preview.php setup` aplica migraciones y usuarios de demostración de forma idempotente en esa base. El servidor se inicia dentro del contenedor con `php -S 0.0.0.0:8000 -t public tests/Support/preview.php`. Ambos lanzadores omiten los archivos de entorno locales y establecen su configuración explícitamente. No se migró la base de desarrollo.

## Correspondencia con los requisitos

| Capacidad | Evidencia |
| --- | --- |
| Gestión exclusiva de administradores, credenciales válidas, contraseña opcional y cambio de permisos | `UserManagementTest` y `UserResourceTest`: operaciones, autenticación, errores por campo, formulario sin hash y accesos directos por rol. |
| Usuarios referenciados, último administrador y técnicos con asignaciones pendientes | `UserManagementTest`: cada tipo de referencia, eliminación permitida, degradación bloqueada y asignaciones cerradas conservadas. |
| Concurrencia administrativa | `PostgresAdministrationTest`: dos eliminaciones o degradaciones de los últimos administradores y asignación frente a cambio de rol, comprobando espera real del bloqueo con procesos independientes. |
| Catálogo, códigos, sede obligatoria y mantenimiento restringido | `SitesAndAssetsTest` y `SitesAndAssetsResourceTest`: normalización, unicidad, permisos de los tres roles, fichas y borrado sin cascadas. |
| Asociación opcional, edición autorizada y contexto físico | `TicketAssetsTest`: alta con/sin activo, referencias inválidas, omisión frente a retirada, permisos por estado, ficha y traslado de sede. |
| Integridad de referencias bajo concurrencia | `PostgresAdministrationTest`: eliminación del activo durante alta o edición y guardado de una referencia antes del intento de borrado, sin cambios parciales. |
| Migraciones y referencias de catálogo | `AdministrationMigrationsTest` y `PostgresAdministrationTest`: colisiones de correos sin cambios parciales, datos previos, restricciones SQL, reversión y reaplicación en ambos motores. |
| Widget, visibilidad, ceros y actualización | `TicketOverviewTest`: cuatro estados, exclusión de cerradas, solicitantes aislados, equipo completo, panel vacío, acceso anónimo y recálculo tras alta, transición, cierre y cambio de sesión. |
| Regresión | Suite completa anterior, incluyendo `PostgresTicketTest`: alta, estados, asignaciones, historial, comentarios y acceso. Su prueba de reasignación concurrente adopta el mismo orden de bloqueo que la aplicación. |

## Revisión en navegador

Se utilizó la habilidad `agent-browser` con una sesión propia y las cuentas de demostración:

1. El administrador creó la sede Madrid con código introducido en minúsculas, verificando su normalización a MAD, y el activo PC-1 asociado a esa sede.
2. El solicitante inició sesión sin enlace de gestión de usuarios, creó una incidencia seleccionando el activo por código y confirmó código, nombre y sede en su ficha. El resumen mostró una propuesta y cero en los demás estados.
3. El técnico consultó el catálogo sin acciones de crear, editar o borrar. Consultó la incidencia, se asignó como responsable y avanzó a abierta; la ficha y su historial mostraron el cambio con autor.
4. El resumen técnico actualizado mostró cero propuestas, una abierta y cero en curso y resueltas. Los recuentos no ofrecen navegación. La ruta de gestión de usuarios se comprobó también directamente con el técnico.

Los clics por coordenadas de la herramienta dejaron de activar algunos controles durante la sesión técnica. Se completó la revisión con navegación directa y activaciones DOM de los controles observados; no se eludieron políticas ni acciones del servidor. La carga de Livewire no presentó errores de consola; el historial se comprobó tras desplazarlo a la vista para activar su carga diferida. Esta revisión no sustituye a las pruebas de aislamiento por usuario, realizadas en la suite.

Capturas:

- [Activo creado por el administrador](screenshots/administration-and-ticket-overview/admin-asset.png).
- [Ficha con activo del solicitante](screenshots/administration-and-ticket-overview/requester-ticket.png), capturada antes de cargar su historial diferido.
- [Resumen del solicitante](screenshots/administration-and-ticket-overview/requester-overview.png).
- [Catálogo de solo lectura del técnico](screenshots/administration-and-ticket-overview/technician-catalogue.png).
- [Seguimiento con historial cargado](screenshots/administration-and-ticket-overview/technician-tracking.png).
- [Resumen técnico tras la transición](screenshots/administration-and-ticket-overview/technician-overview.png).

## Incorporación al entorno de desarrollo

Aplicar las migraciones pendientes con el procedimiento habitual, sin recrear la base. La normalización de correos se detiene si detecta cuentas que colisionan; resolver esos datos antes de volver a migrar. El catálogo comienza vacío y la referencia a activo es opcional. Para revertir en un entorno con datos útiles, detener escrituras y exportar catálogo y asociaciones antes de retirar el esquema.
