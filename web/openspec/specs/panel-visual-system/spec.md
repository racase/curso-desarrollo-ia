# panel-visual-system Specification

## Purpose

Ofrecer una identidad visual coherente y accesible en el panel de InfraTicket, adaptando el sistema definido en DESIGN.md a la consulta y gestión diaria desde escritorio y móvil.

## Requirements

### Requirement: Identidad compartida del panel
El panel SHALL mostrar la marca InfraTicket, la familia Rubik con alternativas del sistema, la paleta violeta y los radios y espaciados de DESIGN.md en acceso, navegación, dashboard y recursos de incidencias, usuarios, sedes y activos, incluidas sus tablas, formularios, fichas y diálogos.

#### Scenario: Recorrido coherente
- **WHEN** un usuario visita el acceso, el dashboard y los recursos permitidos para su rol
- **THEN** encuentra la misma tipografía, jerarquía de títulos y tratamiento de controles y superficies

#### Scenario: Fuente no disponible
- **WHEN** no se puede cargar Rubik
- **THEN** el contenido permanece visible y utilizable con una fuente del sistema

### Requirement: Temas claro y oscuro
El panel SHALL conservar la selección de tema de Filament y su preferencia persistente. El tema claro mostrará superficies blancas y texto violeta oscuro; el oscuro usará fondos #1f1633, superficies #150f23 y texto blanco con secundarios legibles. El tema se aplicará también a menús, modales y notificaciones.

#### Scenario: Cambio de tema
- **WHEN** el usuario cambia de claro a oscuro y recarga una página del panel
- **THEN** se conserva su selección y todos los controles corresponden a esa polaridad

### Requirement: Jerarquía de acciones y acentos
El panel SHALL presentar las acciones principales con fondo #150f23 y texto blanco en claro, y fondo blanco y texto #1f1633 en oscuro. Los botones usarán mayúsculas visuales, 14px y radio de 8px. El lima #c2ef4e quedará reservado a un acento de marca por viewport, sin fondos de botones ni texto de cuerpo. Las acciones destructivas conservarán su diferenciación semántica.

#### Scenario: Acción principal
- **WHEN** un usuario abre un formulario en cualquiera de los temas
- **THEN** distingue la acción de guardar de cancelar y eliminar por su jerarquía y etiqueta

### Requirement: Interacciones accesibles
El panel SHALL ofrecer estados visibles de hover, pulsación, foco, error, carga y deshabilitado. Texto normal activo y secundario tendrán contraste mínimo 4.5:1 y texto grande 3:1; límites necesarios de controles y foco tendrán 3:1 respecto al entorno. Los errores, estados y prioridades conservarán etiquetas textuales. El foco de teclado permanecerá visible sin depender solo del color.

#### Scenario: Formulario inválido
- **WHEN** un usuario envía un formulario incompleto usando teclado
- **THEN** conserva foco visible y puede identificar y leer los errores junto a sus campos en ambos temas

#### Scenario: Confirmación y carga
- **WHEN** se abre una confirmación o se procesa una acción
- **THEN** sus botones, mensajes e indicadores mantienen contraste, estados y navegación por teclado

### Requirement: Uso en pantallas pequeñas
El panel SHALL permitir navegar y operar desde 375px de ancho y con zoom al 200%, sin desbordamiento horizontal de página. Las tablas anchas podrán desplazarse dentro de su contenedor. Formularios y tarjetas se adaptarán a una columna cuando sea necesario y los controles táctiles tendrán al menos 44px de alto.

#### Scenario: Consulta móvil
- **WHEN** un usuario abre una tabla, una ficha y un formulario a 375px
- **THEN** puede desplegar la navegación, consultar columnas mediante desplazamiento local y alcanzar todas las acciones sin recortes

### Requirement: Conservación de los flujos existentes
El cambio visual SHALL conservar rutas, textos en español, visibilidad por rol, validación y operaciones existentes. El resumen seguirá mostrando propuesta, abierta, en curso y resuelta, incluidos los ceros, sin navegación ni acciones nuevas desde los recuentos.

#### Scenario: Permisos y resumen
- **WHEN** solicitante, técnico y administrador consultan el panel con el nuevo tema
- **THEN** reciben los mismos datos y acciones autorizadas y los mismos recuentos que antes del cambio visual
