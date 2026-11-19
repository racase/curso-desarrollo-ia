# InfraTicket — demo web

Aplicación Laravel + Filament + PostgreSQL que usaremos como base de la demo web.
Permite registrar incidencias, asignarlas a técnicos, seguir sus estados y compartir comentarios desde el panel.

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

## Incidencias

Entra en **Incidencias** (`/admin/incidents`) para crear una solicitud con título,
descripción y prioridad. Toda alta se hace en nombre propio, comienza en **propuesta**,
sin técnico y con prioridad **media** por defecto. Puedes elegir baja, media, alta o crítica.
El título admite 1–255 caracteres y la descripción 1–10000, sin contar espacios exteriores.

El listado muestra título, estado, prioridad y solicitante. Los filtros de estado y prioridad
se pueden combinar; la ficha muestra también descripción, técnico, historial y comentarios.
Los solicitantes solo acceden a sus incidencias, incluso mediante URL directa. Técnicos y
administradores pueden consultar todas.

| Operación | Quién puede realizarla |
|---|---|
| Crear una incidencia | Cualquier usuario, para sí mismo |
| Asignar o cambiar de técnico | Administrador, eligiendo un usuario con rol técnico |
| Autoasignarse una incidencia libre | Técnico |
| Avanzar de estado | Administrador o técnico asignado |
| Publicar comentarios | Cualquier usuario con acceso a la incidencia, en los cinco estados |

El ciclo es **propuesta → abierta → en curso → resuelta → cerrada**. Para pasar a en curso
es obligatorio tener técnico. Un administrador puede pasar de propuesta a abierta sin
asignación. No se permiten saltos, retrocesos ni reapertura. Asignar no cambia el estado;
cada avance guarda estado anterior, nuevo, usuario y fecha en el historial de la ficha.
Las confirmaciones obsoletas se rechazan con un aviso para actualizar la ficha.

Los comentarios son texto plano de 1–5000 caracteres, muestran autor y fecha y se leen en
orden cronológico, con paginación. No se pueden editar ni eliminar, tampoco por un
administrador. No hay adjuntos, notificaciones ni comentarios privados. Las incidencias
tampoco tienen edición o borrado general; el seguimiento usa las acciones de la ficha.

Las migraciones son aditivas: para actualizar una instalación existente ejecuta
`docker compose exec app php artisan migrate`. No hace falta recrear la base de datos.
Los usuarios referenciados como solicitante, técnico, actor o autor no pueden borrarse;
la futura gestión de usuarios (issue 04) deberá conservar esta atribución.

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

Los tests habituales usan SQLite en memoria, así que no tocan los datos de PostgreSQL.

Para comprobar los bloqueos y las carreras con dos conexiones reales de PostgreSQL:

```bash
docker compose exec app php tests/Support/check-postgres-concurrency.php
```

Esta comprobación crea una base temporal aleatoria `infraticket_concurrency_*`, aplica las
migraciones, verifica una sola autoasignación y una sola transición ganadoras y elimina
exclusivamente esa base al terminar. Usa la configuración PostgreSQL del contenedor y
requiere permiso de creación de bases (incluido en el usuario de la demo). No migra ni
reinicia la base de demo. Las credenciales se leen del entorno, sin imprimirse.

Si tienes el contenedor ya arrancado pero no el subcomando Compose, puedes usar
`docker exec infraticket-app php artisan test`, `docker exec infraticket-app vendor/bin/pint`
y `docker exec infraticket-app php tests/Support/check-postgres-concurrency.php`.

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
- Alta, consulta, asignación, seguimiento y comentarios de incidencias con permisos por rol.
- Tests de autenticación, modelos, acciones y páginas Livewire en `tests/Feature/`.
- Planificación en `openspec/changes/archive/2026-11-19-gestionar-incidencias/`.

No hay, a propósito:

- Gestión de usuarios desde el panel.
- Sedes y activos, ni widget de resumen por estado.

## Configuración de agentes

| Ruta | Lo usa | Contenido |
|---|---|---|
| `AGENTS.md` | Codex y OpenCode | Instrucciones del proyecto: mapa, comandos, convenciones |
| `.agents/skills/` | Codex y OpenCode | Skills del proyecto (vacío de momento) |
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
