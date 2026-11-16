# InfraTicket — demo web

Aplicación Laravel + Filament + PostgreSQL que usaremos como base de la demo web.
Incluye acceso al panel, usuarios de prueba y gestión de incidencias con visibilidad por rol.

## Requisitos

- Docker Desktop (o Docker dentro de WSL2) o Podman

Para la aplicación, nada más: PHP y Composer van dentro del contenedor y no hace falta
instalarlos en tu equipo. Node tampoco lo necesita la aplicación, pero sí Codex y OpenSpec
(ver el `README.md` de la raíz).

Con Podman, sustituye `docker` por `podman` en todos los comandos de este documento.

## Arranque

```bash
cd web
docker compose up -d --build
docker compose logs -f app     # espera a ver "InfraTicket ready"; Ctrl+C para salir
```

La primera vez tarda unos minutos: construye la imagen, instala las dependencias, crea el
`.env`, migra la base de datos y carga los usuarios. Las siguientes veces arranca en segundos.

Abre <http://localhost:8000/admin> e inicia sesión con uno de los usuarios de prueba.

Las dependencias de Composer se guardan en un volumen de Docker, no en la carpeta `vendor/` de tu
equipo, que quedará vacía. Para consultar el código de una librería:
`docker compose exec app cat vendor/<paquete>/<fichero>`.

## Usuarios de prueba

Todos con la contraseña `password`.

| Rol | Nombre | Correo |
|---|---|---|
| Administrador | Ana Admin | `admin@infraticket.test` |
| Técnico | Tomás Técnico | `tecnico1@infraticket.test` |
| Técnico | Teresa Técnica | `tecnico2@infraticket.test` |
| Solicitante | Sara Solicitante | `solicitante1@infraticket.test` |
| Solicitante | Sergio Solicitante | `solicitante2@infraticket.test` |

En **Incidencias** (`/admin/tickets`) puedes crear una propuesta con título (3–200 caracteres),
descripción (10–10.000) y prioridad (baja, media, alta o crítica; media por defecto).
El solicitante es siempre el usuario conectado. El listado permite combinar filtros por
estado y prioridad. Cada solicitante solo puede consultar sus incidencias; técnicos y
administradores pueden consultar todas.

La ficha muestra los datos de la incidencia. El solicitante puede corregir título,
descripción y prioridad mientras esté en propuesta o abierta; el equipo puede corregirlos
en cualquier estado salvo cerrada. No se permite eliminar incidencias.

### Asignación y seguimiento

Técnicos y administradores pueden asignar o reasignar una incidencia no cerrada a un
usuario con rol técnico. Asignar no cambia el estado. Una vez en curso no se puede
retirar la asignación, aunque sí sustituir al responsable.

El flujo es **propuesta → abierta → en curso → resuelta → cerrada**, sin saltos ni
reapertura. Solo el técnico asignado o un administrador puede avanzar el estado.
Un administrador puede abrir una propuesta sin asignar, pero pasar a en curso exige
técnico. La ficha conserva autor, fecha y estados de cada transición. Si otro usuario
modifica el seguimiento mientras tienes una acción abierta, actualiza la ficha y reintenta.

### Comentarios

Los usuarios con acceso pueden comentar incidencias no cerradas, incluso sin técnico
asignado. Los comentarios admiten texto plano de 1 a 5.000 caracteres, con autor y fecha
automáticos; se consultan cronológicamente y con paginación. No se editan ni eliminan.
Las incidencias cerradas conservan datos, historial y comentarios únicamente en consulta.

Los usuarios referenciados por incidencias, historial o comentarios no pueden eliminarse.
La futura gestión de usuarios deberá respetar o revisar expresamente esta restricción.

## Comandos habituales

Los comandos de PHP se ejecutan dentro del contenedor `app`:

| Para | Comando |
|---|---|
| Tests | `docker compose exec app php artisan test` |
| Estilo de código | `docker compose exec app vendor/bin/pint` |
| Cualquier comando de Artisan | `docker compose exec app php artisan ...` |
| Añadir un paquete | `docker compose exec app composer require ...` |
| Recrear la base de datos con los usuarios | `docker compose exec app php artisan migrate:fresh --seed` |
| Consola SQL | `docker compose exec db psql -U infraticket` |
| Ver el log del servidor | `docker compose logs -f app` |
| Parar todo | `docker compose down` |
| Reinstalar dependencias desde cero | `docker compose down`, `docker volume rm web_infraticket-vendor` y volver a arrancar |

Los tests usan SQLite en memoria, así que no tocan los datos de PostgreSQL.

### Pruebas aisladas y concurrencia PostgreSQL

`compose.testing.yaml` crea contenedores independientes y una base desechable llamada
`infraticket_test`. No comparte la base ni los volúmenes de datos de desarrollo. Su puerto
18000 está reservado para comprobaciones manuales; el servidor web no arranca automáticamente.

```bash
docker compose -p infraticket-test -f compose.testing.yaml up -d --build
docker compose -p infraticket-test -f compose.testing.yaml exec app composer install --no-interaction --no-scripts
docker compose -p infraticket-test -f compose.testing.yaml exec app php artisan package:discover
docker compose -p infraticket-test -f compose.testing.yaml exec app vendor/bin/phpunit
docker compose -p infraticket-test -f compose.testing.yaml exec app vendor/bin/pint --test
docker compose -p infraticket-test -f compose.testing.yaml down
```

Las pruebas PostgreSQL se ejecutan solo cuando existe `PG_TEST_HOST` y exigen que
`PG_TEST_DATABASE` sea exactamente `infraticket_test`; recrean únicamente esa base de
pruebas. Usan procesos y conexiones independientes para comprobar bloqueos de seguimiento
y cierre concurrente con comentarios, además de migración y reversión. Sin esa configuración
se omiten estas pruebas y se conserva la suite SQLite habitual.

Para ejecutar sin leer configuración local, usa `vendor/bin/phpunit`: el arranque de
pruebas utiliza únicamente la configuración PHPUnit y las variables del contenedor,
con una clave pública exclusiva de pruebas. El lanzador `artisan test` puede intentar
consultar el archivo de entorno del proyecto antes de lanzar PHPUnit.

### Alternativa: PHP instalado en tu equipo

Si ya tienes PHP 8.3+ (con `pdo_pgsql`, `pdo_sqlite` e `intl`) y Composer, puedes levantar solo
la base de datos y ejecutar la aplicación directamente:

```bash
docker compose up -d db
composer setup
php artisan serve
```

En ese caso los comandos de la tabla se usan sin el prefijo `docker compose exec app`.

## Qué hay y qué no

Hay:

- Panel de Filament en `/admin` con inicio de sesión.
- Modelo `User` con un rol (`App\Enums\UserRole`): administrador, técnico o solicitante.
- Usuarios de prueba (`database/seeders/DatabaseSeeder.php`).
- Tests del inicio de sesión (`tests/Feature/AdminLoginTest.php`).
- Alta, listado, filtros y ficha de incidencias con permisos por rol.
- Asignación, historial de estados y comentarios en la ficha.

No hay, a propósito:

- Gestión de usuarios desde el panel.
- Sedes, activos y widget de recuento.

La propuesta y tareas del cambio se encuentran en `openspec/changes/add-ticket-management/`.

## Configuración de agentes

| Ruta | Lo usa | Contenido |
|---|---|---|
| `AGENTS.md` | Codex y OpenCode | Instrucciones del proyecto: mapa, comandos, convenciones |
| `.agents/skills/` | Codex y OpenCode | Skills del flujo OpenSpec |
| `.codex/config.toml` | Codex | Ajustes de Codex (sin ajustes activos de momento) |
| `.claude/` | Claude Code | `CLAUDE.md`, que importa los `AGENTS.md`, y `settings.json` |
| `.opencode/opencode.json` | OpenCode | Ajustes de OpenCode |

El curso se hace con Codex. Ábrelo desde esta carpeta y, la primera vez, acepta confiar en
ella: si no, Codex ignora `.codex/`.

## Estructura

```
web/
├── AGENTS.md              instrucciones para los agentes
├── .agents/skills/        skills del proyecto
├── .codex/                ajustes de Codex
├── .claude/               ajustes de Claude Code
├── .opencode/             ajustes de OpenCode
├── app/
│   ├── Enums/UserRole.php
│   ├── Models/User.php
│   └── Providers/Filament/AdminPanelProvider.php   configuración del panel
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
├── tests/
├── docker/start.sh        arranque del contenedor
├── Dockerfile
├── docker-compose.yml
└── .env.example
```

Los recursos, páginas y widgets de Filament que se creen irán en `app/Filament/`.
