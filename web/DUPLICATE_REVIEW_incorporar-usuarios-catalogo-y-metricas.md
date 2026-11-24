# Revisión de duplicación: incorporar-usuarios-catalogo-y-metricas

**Revisor:** subagente local `duplicate-finder`, modelo `gpt-6.1-sol`, razonamiento `medium`.

**Alcance:** commit `a73cba58a5f75d435d5971ae802eba7e7945a9ae`, comparado con su padre `d7182a7`. Inspección estática del código añadido/modificado en `web/app`, `web/database` y `web/tests`, con comparación dirigida con código previo. El commit no modifica `web/routes` ni `web/resources`. Excluidos OpenWiki generado y cambios previos ajenos indicados.

Se confirman tres oportunidades de consolidación. Son mejoras de mantenimiento; la revisión no demuestra fallos funcionales causados por esta duplicación.

## 1. Impacto medio — Infraestructura de concurrencia PostgreSQL copiada

**Confirmado.**

**Archivos y símbolos:**

- `web/tests/Support/check-user-concurrency.php`: bloque principal, líneas 11–35, 55–88 y 97–111; función `check()`, líneas 25–30.
- `web/tests/Support/check-postgres-concurrency.php` — previo: bloque principal, líneas 11–37, 48–82 y 92–106; función `requireResult()`, líneas 27–32.
- `web/tests/Support/user-race-worker.php`: protocolo de barrera del bloque principal, líneas 17–24.
- `web/tests/Support/incident-race-worker.php` — previo: protocolo de barrera del bloque principal, líneas 17–24.

Ambos coordinadores repiten creación/configuración de una base aleatoria, migración, lanzamiento de procesos, espera de barrera, comprobación de contención mediante `pg_stat_activity`, recogida de resultados y limpieza. Los workers repiten el mismo protocolo `ready-*`/`go` con idéntico plazo y espera.

Las diferencias relevantes están en escenarios, filas bloqueadas, argumentos del worker e invariantes finales. También difieren las excepciones tratadas como conflicto: el worker de usuarios admite `AuthorizationException`, además de `ValidationException`.

**Coste:** cualquier corrección de aislamiento, plazos, diagnóstico de procesos o limpieza debe aplicarse a dos arneses y, para la barrera, a dos workers.

**Consolidación concreta:** extraer utilidades bajo `tests/Support` para el ciclo de vida de la base aislada y una carrera de dos procesos, recibiendo preparación/bloqueo, comandos de workers y comprobaciones finales. Extraer aparte la espera de barrera. Mantener explícitos los escenarios y sus excepciones esperadas.

**Riesgos:** una abstracción demasiado amplia podría ocultar el orden de bloqueo o tratar errores inesperados como conflictos válidos. Deben conservarse argumentos, excepciones y comprobaciones específicas de cada escenario.

**Validación sugerida:** ejecutar ambos scripts en PostgreSQL aislado; verificar un ganador y un conflicto por escenario, invariantes finales y limpieza de procesos, base y barrera tanto en éxito como ante un fallo inducido.

## 2. Impacto medio — Invariante del último administrador en dos acciones

**Confirmado; bloque breve, pero regla de dominio relevante.**

**Archivos y símbolos:**

- `web/app/Actions/Users/DeleteUser.php`: `DeleteUser::handle()`, líneas 20–22.
- `web/app/Actions/Users/UpdateUser.php`: `UpdateUser::handle()`, líneas 18–21.
- Contexto compartido: `web/app/Actions/Users/ManageUsers.php`, `ManageUsers::transaction()`, líneas 14–23.

Las acciones comprueban por separado si la cuenta es administradora y queda exactamente un administrador, y emiten el mismo mensaje. En actualización solo aplica si el nuevo rol deja de ser administrador; en eliminación siempre se pierde la cuenta. Las claves de error son distintas: `role` y `user`.

**Coste:** una evolución de la definición de administrador o de esta protección obliga a sincronizar ambas rutas de escritura.

**Consolidación concreta:** añadir en `ManageUsers` un guard específico, por ejemplo `ensureAdministratorCanBeRemoved(User $current, string $errorKey)`, invocado al eliminar y al abandonar el rol administrador. Mantener en `UpdateUser` la condición relativa al nuevo rol.

**Riesgos:** la comprobación debe seguir dentro de `ManageUsers::transaction()`, después de adquirir los bloqueos compartidos. No trasladarla únicamente a la política o al formulario. Conservar las claves de error y el orden de las demás protecciones.

**Validación sugerida:** ejecutar `UserManagementTest`, `UserPanelTest` y las carreras de degradación/eliminación del script de usuarios; comprobar especialmente que se permite conservar el rol administrador y degradar una cuenta cuando existe otra administradora.

## 3. Impacto bajo — Remapeo idéntico de errores de acción al formulario

**Confirmado.**

**Archivos y símbolos:**

- `web/app/Filament/Resources/Users/Pages/CreateUser.php`: `handleRecordCreation()`, líneas 19–22.
- `web/app/Filament/Resources/Users/Pages/EditUser.php`: `handleRecordUpdate()`, líneas 19–22.
- `web/app/Filament/Resources/Incidents/Pages/CreateIncident.php` — previo: `handleRecordCreation()`, líneas 19–22.

Los tres bloques capturan `ValidationException`, recorren sus errores y anteponen `data.` a cada clave. Solo cambia la acción ejecutada antes del `catch`.

**Coste:** cambios en la correspondencia entre errores de dominio y estado del formulario requieren modificar tres copias.

**Consolidación concreta:** extraer un pequeño adaptador compartido de errores para páginas Filament y utilizarlo desde los tres `catch`. No hace falta introducir una jerarquía común de páginas.

**Riesgos:** conservar todos los mensajes y claves anidadas; limitar el adaptador a formularios cuyo estado reside en `data`.

**Validación sugerida:** comprobar errores de creación y edición de usuarios —incluido `role` al degradar al último administrador— y creación de incidencias con activo ausente/inválido, verificando que aparecen en el campo correspondiente.

## Repeticiones revisadas sin recomendar abstracción

- `CreateIncident::handle()` y `LinkIncidentAsset::handle()` comparten la regla de `asset_id` (`CreateIncident.php:22,27`; `LinkIncidentAsset.php:19–20`). Es una coincidencia breve y declarativa; no justifica por sí sola otro servicio.
- `UserPolicy` ya concentra la decisión de administrador mediante `viewAny()`; sus delegaciones son convenciones explícitas de autorización.
- La selección de activos ya reutiliza `Asset::groupedOptions()`. Los formularios difieren en valor inicial y disposición.
- Relaciones Eloquent, factories y columnas comunes de las migraciones son convenciones del framework.
- Las ramas SQLite/PostgreSQL de la migración de `asset_id` preservan comportamientos específicos y no deben unificarse por similitud superficial.
- El widget reutiliza `Incident::visibleTo()`. Las expectativas explícitas del catálogo y de estados en pruebas actúan como comprobaciones independientes; compartirlas con la implementación debilitaría esas verificaciones.

## Limitaciones

Revisión estática y dirigida, sin afirmar cobertura exhaustiva. No quedan sospechas adicionales con evidencia suficiente para proponer una consolidación. No se editaron archivos de implementación ni se ejecutaron pruebas, scripts, instalaciones, Docker o navegador; toda validación mencionada queda propuesta y pendiente. El agente principal únicamente guarda este informe, sin aplicar correcciones.
