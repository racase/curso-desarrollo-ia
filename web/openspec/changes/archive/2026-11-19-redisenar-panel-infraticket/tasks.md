# Tasks

## 1. Tema, fuente y compilación

- [x] 1.1 Crear el tema en `resources/css/filament/admin/theme.css` con tokens de color, tipografía, espaciado y radios acordados; verificar que corresponde a la estructura de tema soportada por la versión instalada de Filament 5 y que `npm run build` compila.
- [ ] 1.2 Incorporar Rubik local y su licencia con fallback de sistema, registrar la fuente y el tema en el proveedor y Vite y retirar Instrument Sans si ya no se usa; verificar en navegador que CSS y fuentes responden sin errores y no solicitan servicios externos de fuentes.
- [ ] 1.3 Documentar Node y los pasos `npm ci` y `npm run build` en README para el flujo Docker; ejecutar el flujo documentado y verificar `/admin/login` sin servidor Vite activo y sin manifiesto ni assets ausentes.

## 2. Login y estructura del panel

- [ ] 2.1 Aplicar fondo violeta, formulario claro, marca InfraTicket, detalle lima único y CTA principal al login estándar; verificar visualmente acceso correcto e incorrecto y ejecutar `docker compose exec app php artisan test --filter=AdminLoginTest`.
- [ ] 2.2 Estilizar navegación, títulos y dashboard existente con superficies operativas claras y retirar el selector de modo; verificar el panel con sistema operativo claro y oscuro y preferencia oscura previamente guardada, conservando el widget de cuenta y los enlaces existentes.
- [ ] 2.3 Ajustar foco, estados activos y apertura/cierre del menú móvil; verificar por teclado el paso entre navegación y contenido a 375px y 1440px sin controles inaccesibles.

## 3. Pantallas y operaciones de incidencias

- [ ] 3.1 Aplicar el tema a listado, búsqueda, filtros, paginación y estado vacío; verificar filtros combinados, columnas legibles y tabla desplazable sin desbordar la página, y ejecutar `docker compose exec app php artisan test --filter=IncidentPanelTest`.
- [ ] 3.2 Unificar campos, etiquetas, ayudas, errores, carga y deshabilitado del alta y formulario de comentario; verificar envíos válidos e inválidos y ejecutar `docker compose exec app php artisan test --filter=IncidentCommentsPanelTest` junto a los tests de alta ya existentes.
- [ ] 3.3 Aplicar jerarquía de acciones a ficha y confirmaciones, y estilos coherentes a detalle, historial, comentarios y avisos; verificar textos extensos, orden y paginación y ejecutar `docker compose exec app php artisan test --filter=IncidentTrackingPanelTest`.
- [ ] 3.4 Revisar ficha como administrador, técnico asignado, técnico no asignado y solicitante; verificar que se mantienen acciones autorizadas, autoasignación, avance secuencial, avisos de conflicto y aislamiento de incidencias usando los tests existentes y navegador.

## 4. Validación integrada

- [ ] 4.1 Revisar login, dashboard, listado, alta, ficha y modal a 320, 375, 768 y 1440px y al 200% de zoom; verificar targets de 44px en móvil, legibilidad, ausencia de superposiciones y desplazamiento horizontal limitado a tablas.
- [ ] 4.2 Medir contraste de textos, controles activos y foco y recorrer login, filtros, formularios y modales por teclado; verificar 4.5:1 y 3:1 según la spec, mensajes asociados, foco visible y retorno al activador del modal.
- [ ] 4.3 Ejecutar `npm run build`, `docker compose exec app vendor/bin/pint --test` y `docker compose exec app php artisan test`; registrar resultados y verificar que no se requieren migraciones ni cambios en las reglas de negocio.
- [ ] 4.4 Adjuntar capturas de login, listado, alta, ficha y modal en escritorio y móvil y el resultado de revisión de accesibilidad; verificar que la evidencia cubre los escenarios de `identidad-visual-panel` y documenta cualquier limitación real.
