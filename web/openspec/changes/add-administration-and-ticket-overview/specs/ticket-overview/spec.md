# Spec Delta

## Purpose

Ofrecer desde el panel un resumen de las incidencias pendientes de cierre, agrupadas por estado y limitado a la información que cada usuario puede consultar.

## ADDED Requirements

### Requirement: Distribución por estados no cerrados
El sistema SHALL mostrar en el panel un widget para los tres roles con los recuentos de propuesta, abierta, en curso y resuelta, en ese orden y con etiquetas en español. Las incidencias cerradas quedarán excluidas. Cada estado aparecerá con valor cero cuando no tenga incidencias visibles.

#### Scenario: Distribución completa
- **WHEN** un usuario abre el panel con incidencias visibles en los cinco estados
- **THEN** el widget muestra los recuentos de los cuatro estados no cerrados y no incluye las cerradas

#### Scenario: Ausencia de incidencias
- **WHEN** no hay incidencias visibles en uno o varios de los cuatro estados
- **THEN** esos estados siguen apareciendo con valor cero, también cuando todos los recuentos son cero

### Requirement: Recuentos sujetos a visibilidad
El sistema SHALL contar para solicitantes únicamente sus propias incidencias y para técnicos y administradores todas las incidencias. La visibilidad se aplicará antes de agregar los recuentos, también al solicitar directamente el componente, sin compartir resultados entre usuarios con distinto acceso.

#### Scenario: Resumen del solicitante
- **WHEN** un solicitante consulta el widget existiendo incidencias propias y ajenas
- **THEN** cada recuento coincide únicamente con sus incidencias de ese estado

#### Scenario: Resumen del equipo
- **WHEN** un técnico o administrador consulta el widget
- **THEN** cada recuento incluye las incidencias de todos los solicitantes en ese estado

#### Scenario: Resumen anónimo
- **WHEN** una persona sin sesión intenta consultar el widget
- **THEN** no recibe ningún recuento del producto y debe iniciar sesión

### Requirement: Actualización del resumen
El sistema SHALL calcular el resumen con los datos persistidos al abrir o recargar el panel y al refrescar el widget. Una creación, transición o cierre confirmados se reflejarán en la siguiente actualización; no se exige actualización automática entre sesiones ni se ofrecerán acciones o navegación desde los recuentos.

#### Scenario: Transición confirmada
- **WHEN** una incidencia visible pasa de abierta a en curso y se actualiza el widget
- **THEN** abierta disminuye en uno y en curso aumenta en uno

#### Scenario: Cierre confirmado
- **WHEN** una incidencia visible pasa de resuelta a cerrada y se vuelve a abrir o recargar el panel
- **THEN** resuelta disminuye en uno y la incidencia deja de contribuir a los recuentos
