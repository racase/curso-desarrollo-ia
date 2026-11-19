# incidencias Specification

## Purpose

Permitir registrar y consultar incidencias con información suficiente para su atención y con acceso limitado según el rol del usuario.

## Requirements

### Requirement: Alta en nombre propio
El sistema SHALL permitir a cualquier usuario autenticado crear una incidencia para sí mismo con título, descripción y prioridad. SHALL fijar el solicitante desde la sesión, el estado inicial propuesta y la asignación vacía. SHALL ofrecer prioridad media por defecto y las opciones baja, media, alta y crítica.

#### Scenario: Alta válida para cada rol
- **WHEN** un solicitante, técnico o administrador registra título y descripción válidos sin cambiar la prioridad propuesta
- **THEN** se crea una incidencia en estado propuesta, sin técnico, de prioridad media y con el usuario autenticado como solicitante

#### Scenario: Prioridad elegida y campos protegidos
- **WHEN** un usuario crea una incidencia con prioridad crítica e intenta enviar otro solicitante, estado o técnico
- **THEN** la prioridad es crítica y el sistema conserva solicitante autenticado, estado propuesta y asignación vacía

### Requirement: Validación del alta
El sistema SHALL exigir título de 1 a 255 caracteres y descripción de 1 a 10000 caracteres, tras recortar espacios exteriores, y una prioridad válida. SHALL rechazar contenido vacío o compuesto solo de espacios, indicando los errores y sin crear la incidencia.

#### Scenario: Datos inválidos
- **WHEN** se envía título vacío, descripción de espacios, contenido sobre el límite o prioridad desconocida
- **THEN** se muestran errores en los campos afectados y no se crea ninguna incidencia

### Requirement: Consulta autorizada
El sistema SHALL permitir a solicitantes consultar exclusivamente las incidencias que crearon y a técnicos y administradores consultar todas. SHALL aplicar esta regla al listado, ficha, filtros, historial y comentarios. Las visitas sin autenticación SHALL requerir inicio de sesión.

#### Scenario: Aislamiento entre solicitantes
- **WHEN** un solicitante consulta el listado o intenta acceder directamente a la ficha de otro solicitante
- **THEN** el listado solo contiene incidencias propias y la ficha ajena no expone datos ni permite acciones

#### Scenario: Consulta del equipo
- **WHEN** un técnico o administrador consulta el listado y una ficha de cualquier solicitante
- **THEN** puede consultar la incidencia y sus datos asociados

#### Scenario: Acceso anónimo
- **WHEN** una persona sin sesión visita el listado o una ficha
- **THEN** se solicita autenticación antes de exponer información

### Requirement: Listado y ficha
El sistema SHALL mostrar en el listado título, estado, prioridad y solicitante, y en la ficha también la descripción completa. SHALL ofrecer filtros combinables por estado y prioridad aplicados exclusivamente a las incidencias accesibles. SHALL representar contenido de usuario como texto sin ejecutar marcado.

#### Scenario: Filtro de los cinco estados
- **WHEN** un usuario filtra por propuesta, abierta, en curso, resuelta o cerrada
- **THEN** el filtro reconoce cada estado y solo muestra incidencias accesibles con ese estado

#### Scenario: Filtros combinados
- **WHEN** un usuario selecciona estado abierta y prioridad alta
- **THEN** solo aparecen incidencias accesibles que cumplen ambos filtros y puede retirar los filtros para recuperar el listado accesible

#### Scenario: Sin resultados
- **WHEN** no existen incidencias accesibles que cumplan los filtros
- **THEN** aparece un estado vacío sin datos de otros usuarios

#### Scenario: Consulta del contenido
- **WHEN** un usuario abre una ficha accesible con marcado incluido en título o descripción
- **THEN** ve los cinco datos de la incidencia y el marcado se presenta como texto sin ejecutarse
