# Proposal

## Why

InfraTicket utiliza la apariencia predeterminada de Filament y todavía no refleja el sistema visual definido en `DESIGN.md`. Aplicarlo al panel permitirá ofrecer una identidad coherente y una interfaz legible y accesible en los flujos diarios de gestión de incidencias.

## What Changes

- Incorporar un tema compartido del panel basado en los tokens de color, tipografía, espaciado y radios de `DESIGN.md`.
- Aplicar la identidad a acceso, navegación, dashboard, incidencias, usuarios, sedes y activos, incluidos formularios, tablas, fichas, relaciones, modales y notificaciones.
- Mantener las variantes clara y oscura con superficies coherentes, acciones principales oscuras sobre claro e invertidas sobre oscuro y uso escaso del lima.
- Adaptar la referencia de marketing a una aplicación de gestión: tipografía funcional, densidad adecuada y controles accesibles y utilizables en móvil.
- Definir estados de interacción y criterios verificables de contraste, foco y adaptación responsive donde la referencia es incompleta o contradictoria.
- Quedan fuera las páginas comerciales, tarifas, mascotas, texturas de hero y nuevas funciones de negocio; se conservan marca InfraTicket, rutas, permisos y operaciones existentes.

## Capabilities

### New Capabilities

- `panel-visual-system`: identidad visual coherente del panel, temas claro y oscuro, jerarquía de acciones, estados accesibles y comportamiento responsive de sus pantallas actuales.

### Modified Capabilities

Ninguna. Se mantiene el comportamiento funcional especificado para incidencias, comentarios y seguimiento, así como el cambio existente `add-administration-and-ticket-overview`, implementado pero pendiente de archivo.

## Impact

- Tema previsto en `resources/css/filament/admin/theme.css`, registro en `app/Providers/Filament/AdminPanelProvider.php` y entrada de compilación en `vite.config.js`.
- Fuente Rubik y assets locales; posibles ajustes de presentación en `app/Filament/` y vistas propias si las extensiones públicas de Filament lo requieren.
- Compilación Vite/Tailwind existente, comprobaciones visuales y regresión de las pruebas de acceso, recursos y dashboard.
- Sin migraciones, cambios de API ni modificaciones del proyecto hermano `datos/`. Esta propuesta no modifica `DESIGN.md` ni inicia la implementación.
