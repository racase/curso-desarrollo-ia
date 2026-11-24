# Tasks

> Estado 24/11/2026: las 21 tareas se marcan completadas por indicación expresa
> del usuario. Implementación terminada; las verificaciones no ejecutadas de
> 4.1–4.4 y 5.1–5.3 quedan asumidas por el usuario y no se presentan como pruebas
> realizadas ni superadas. Se mantiene la instrucción de no ejecutar más pruebas
> de Docker ni navegador. Ver `docs/VALIDACION_USUARIOS_SEDES_Y_RESUMEN.md`.

## 1. Gestión administrativa de usuarios — issue 4

- [x] 1.1 Añadir `UserPolicy` y acciones de alta, edición y eliminación bajo `app/Actions/Users`, con autorización en servidor y validación de nombre, email único, contraseña confirmada y `UserRole`; verificar mediante pruebas Feature acceso de administrador y rechazo de técnicos, solicitantes y visitantes, sin cambios parciales.
- [x] 1.2 Implementar protección de eliminación propia, referencias de las cuatro relaciones, último administrador y cambio de rol de técnicos asignados; verificar cada bloqueo y las operaciones permitidas con pruebas de acciones, incluido conservar o sustituir contraseña y aplicar un rol cambiado en la siguiente petición.
- [x] 1.3 Incorporar bloqueo transaccional ordenado y revalidación del actor para cambios administrativos; coordinar la cuenta técnica con `AssignIncident` y añadir una comprobación concurrente en PostgreSQL temporal siguiendo el soporte existente. Verificar que dos retiradas de administradores nunca dejan cero y que asignación frente a cambio de rol nunca deja una incidencia asignada a un usuario sin rol técnico, con conflictos comprensibles y sin datos parciales.
- [x] 1.4 Crear el recurso Filament de usuarios con listado de nombre/email/rol, alta, edición y eliminación individual confirmada; verificar con Livewire las operaciones, errores, contraseñas no precargadas, botones autorizados y rechazo de peticiones directas, conservando el diseño del panel.
- [x] 1.5 Documentar en `docs/VALIDACION_USUARIOS_SEDES_Y_RESUMEN.md` credenciales iniciales, edición de contraseña y restricciones de roles y borrado; contrastar cada regla documentada con las pruebas del grupo 1.

## 2. Catálogo fijo y migración — issue 5

- [x] 2.1 Añadir migraciones de `sites`, `assets` e `incidents.asset_id` nullable, índices y claves restrictivas, y modelos/relaciones `Site`, `Asset` e `Incident::asset`; verificar pertenencia obligatoria, códigos únicos, integridad referencial y conservación de una incidencia previa con sus datos y referencias al aplicar la migración sobre el esquema anterior en una base de prueba.
- [x] 2.2 Crear el seeder de catálogo S01–S06 y cinco activos por sede e integrarlo en `DatabaseSeeder`, sin borrar ni duplicar datos; verificar con pruebas la cardinalidad 6/30, cinco por sede, nombres/códigos, estabilidad de ids y vínculos tras dos ejecuciones y rollback con diagnóstico ante catálogo inesperado.
- [x] 2.3 Añadir factories de sede y activo y adaptar `IncidentFactory` con activo por defecto y un estado explícito sin activo para legado; verificar ambos casos con pruebas de persistencia sin depender del seeder demo.
- [x] 2.4 Documentar catálogo, despliegue mediante migraciones y seeder, legado nullable y rollback operativo sin borrar datos en `docs/VALIDACION_USUARIOS_SEDES_Y_RESUMEN.md`; verificar las instrucciones en la base de prueba y confirmar que no incluyen reinicios destructivos de la demo.

## 3. Alta, consulta y corrección de activo — issue 5

- [x] 3.1 Extender `CreateIncident` para exigir activo existente y conservar las reglas de identidad, estado y prioridad; actualizar sus llamadas y pruebas existentes. Verificar altas válidas de los tres roles, ausencia/identificador inválido y sede manipulada, junto a la regresión de validaciones y campos protegidos.
- [x] 3.2 Añadir selector buscable con código, nombre y sede al alta y mostrar activo/sede en listado y ficha con carga anticipada de relaciones; verificar con Livewire diferenciación entre activos, vínculo correcto, placeholders del legado, filtros combinados y aislamiento entre solicitantes.
- [x] 3.3 Añadir habilidad de política y acción `LinkIncidentAsset`, y su formulario administrativo en la ficha; verificar vinculación del legado y corrección en cualquier estado, rechazo de activo vacío/inexistente y de actores no administradores, y conservación de los demás campos e historial. Confirmar que la ruta de edición general sigue sin existir.
- [x] 3.4 Incorporar al documento de validación el flujo de alta con activo y corrección administrativa; contrastarlo con las pruebas del grupo 3 y con una incidencia anterior sin activo, sin asignarle ubicación artificialmente.

## 4. Resumen por estado — issue 6

- [x] 4.1 Crear `IncidentStatusOverview` con consulta agregada que reutilice `visibleTo`, excluya Cerrada y complete los cuatro estados con cero; verificar mediante pruebas de datos mixtos cifras propias para dos solicitantes distintos y cifras globales para técnicos y administradores, incluidos legados y ausencia de casos.
- [x] 4.2 Integrar el widget una sola vez en el dashboard conservando `AccountWidget`, con título, explicación del universo, etiquetas y sin acciones ni enlaces; verificar renderizado para cada rol, acceso anónimo denegado, ceros visibles e independencia de filtros del listado.
- [x] 4.3 Configurar cálculo al cargar/recargar y actualización visible cada 30 segundos sin caché compartida; verificar mediante pruebas de componente el recálculo tras alta y transición a Cerrada y cambio de usuario, y mediante comprobación de navegador la actualización periódica.
- [x] 4.4 Documentar estados incluidos, visibilidad, ceros y frecuencia de actualización en `docs/VALIDACION_USUARIOS_SEDES_Y_RESUMEN.md`; contrastar ejemplos con los recuentos de las pruebas del grupo 4.

## 5. Validación integrada y presentación

- [x] 5.1 Ejecutar la suite completa con `docker compose exec app php artisan test`, las comprobaciones concurrentes de PostgreSQL aislado y Pint sobre PHP modificado; registrar comandos y resultados completos de fallos en el documento de validación y resolver las regresiones antes de dar el cambio por terminado.
- [x] 5.2 Revisar usuarios, alta, ficha y dashboard con los tres roles en escritorio y a 320px, teclado y ampliación del 200%, según `DESIGN.md` y `identidad-visual-panel`; verificar etiquetas, foco, confirmaciones, contraste, áreas táctiles y ausencia de desbordamientos y guardar capturas en `docs/screenshots/`.
- [x] 5.3 Comprobar el flujo integrado: administrador crea un usuario, este registra una incidencia con activo, el equipo avanza su estado y ambos dashboards reflejan sus permisos; confirmar además que una incidencia previa sigue accesible y puede vincularla el administrador. Registrar evidencia en el documento de validación.
- [x] 5.4 Revisar trazabilidad de los issues 4, 5 y 6, las cuatro especificaciones y las decisiones confirmadas; ejecutar `openspec validate incorporar-usuarios-catalogo-y-metricas --strict` y comprobar que no se han alterado el cambio preexistente ni páginas generadas de OpenWiki.
