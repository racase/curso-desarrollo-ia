# Proposal

## Why

InfraTicket utiliza la apariencia estándar de Filament y todavía no refleja el lenguaje visual de `DESIGN.md`. Adaptar esa referencia al login y al panel dará identidad al producto y una presentación coherente sin alterar la gestión de incidencias.

## What Changes

- Aplicar una identidad InfraTicket con violeta profundo, Rubik, acentos lima puntuales, bordes sutiles y botones con jerarquía según el fondo.
- Rediseñar el login con fondo violeta y formulario legible; mantener el panel operativo en superficies claras con navegación de identidad violeta.
- Unificar dashboard existente, listado, alta, ficha, comentarios, historial, filtros, modales y notificaciones.
- Asegurar navegación por teclado, contraste accesible, adaptación móvil y legibilidad de contenido extenso.
- Conservar rutas, autenticación, roles, datos y operaciones existentes. Alcance confirmado: panel y login; quedan fuera landing pública, precios, funcionalidades del backlog, nuevas métricas, mascotas y reproducción de la marca Sentri.

## Capabilities

### New Capabilities

- `identidad-visual-panel`: presentación coherente y accesible del login y del panel InfraTicket, con identidad, jerarquía de acciones y adaptación a pantallas pequeñas basadas en `DESIGN.md`.

### Modified Capabilities

Ninguna. Los requisitos de `incidencias`, `seguimiento-incidencias` y `comentarios-incidencias` se conservan; el cambio afecta a su presentación.

## Impact

- Configuración de `app/Providers/Filament/AdminPanelProvider.php`, nuevo tema en `resources/css/filament/admin/`, integración con `vite.config.js` y recursos locales de fuente en `public/` si son necesarios.
- Posibles vistas o componentes propios en `resources/views/` y ajustes de presentación en `app/Filament/`, manteniendo componentes y autorizaciones de Filament.
- Compilación de assets con npm/Vite y documentación del arranque Docker: actualmente el panel no registra un tema propio ni consume las entradas genéricas de Vite.
- Verificación visual y funcional de login e incidencias para los tres roles. Sin migraciones, cambios de API ni nuevas dependencias PHP previstas; Rubik deberá distribuirse con su licencia.
