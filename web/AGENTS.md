# Repository Guidelines

## Estructura del proyecto

InfraTicket es una aplicación de demostración con Laravel 13, Filament 5 y PostgreSQL. Trabaja únicamente en `web/`; el proyecto hermano `datos/` es independiente.

- `app/`: modelos, enumeraciones y proveedores; `app/Providers/Filament/AdminPanelProvider.php` configura el panel `/admin`.
- `app/Filament/`: ubicación prevista para nuevos recursos, páginas y widgets.
- `database/`: migraciones, factories y seeders.
- `routes/`: rutas de la aplicación.
- `resources/` y `public/`: vistas, estilos, JavaScript y archivos públicos.
- `tests/Feature/` y `tests/Unit/`: pruebas de integración y unitarias.
- `docker/`: scripts de arranque; `docs/`: documentación complementaria.

## Desarrollo y comandos

Ejecuta estos comandos desde `web/`. Docker Desktop o Podman permiten trabajar sin instalar PHP localmente; con Podman, sustituye `docker` por `podman`.

- `docker compose up -d --build`: construye e inicia la aplicación y PostgreSQL.
- `docker compose logs -f app`: muestra el arranque; espera a ver `InfraTicket ready` y abre `http://localhost:8000/admin`.
- `docker compose exec app php artisan test`: ejecuta las pruebas.
- `docker compose exec app vendor/bin/pint`: aplica el formato PHP.
- `docker compose down`: detiene los servicios.

Para modificar los assets con Node instalado, `npm install` instala dependencias, `npm run dev` inicia Vite y `npm run build` genera los assets de producción.

## Estilo y nombres

Usa código, nombres de archivos y commits en inglés; documentación y textos de interfaz en español. Sigue `.editorconfig`: UTF-8, finales LF, cuatro espacios y salto final; YAML usa dos espacios, excepto los archivos Compose, que usan cuatro. Aplica Laravel Pint al PHP modificado. Conserva las convenciones existentes: clases `PascalCase`, métodos `camelCase` y métodos de prueba `test_descriptive_behavior`.

## Pruebas

El proyecto utiliza PHPUnit 12 y pruebas Livewire para Filament. Nombra los archivos `*Test.php`. Usa factories y `RefreshDatabase` cuando corresponda. Las pruebas emplean SQLite en memoria, sin modificar PostgreSQL. No hay un umbral de cobertura configurado; cubre los comportamientos nuevos o corregidos. Para una prueba concreta, añade `--filter=AdminLoginTest` al comando de pruebas.

## Commits y pull requests

El historial emplea Conventional Commits, como `docs: update course title in README` y `chore: initialize OpenSpec`. Crea commits pequeños y centrados. En cada PR, explica el cambio, enlaza la incidencia si existe, indica las comprobaciones ejecutadas y adjunta capturas cuando cambie la interfaz.

## Configuración y seguridad

No leas ni modifiques archivos `.env`; consulta `.env.example` como referencia. No incluyas secretos en commits. Los usuarios de demostración son solo para desarrollo. Evita comandos como `migrate:fresh` salvo que la tarea autorice eliminar los datos locales.
