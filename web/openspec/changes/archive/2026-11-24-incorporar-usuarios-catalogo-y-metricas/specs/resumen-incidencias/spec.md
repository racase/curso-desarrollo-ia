# Spec Delta

## Purpose

Ofrecer a cada usuario una lectura rápida y comprensible de sus incidencias no cerradas, agrupadas por estado y alineadas con sus permisos de consulta.

## ADDED Requirements

### Requirement: Resumen de estados no cerrados
El sistema SHALL mostrar en el dashboard un widget «Incidencias no cerradas» con los recuentos de Propuesta, Abierta, En curso y Resuelta en ese orden. SHALL excluir Cerrada, presentar cero para estados sin casos y explicar que el resumen incluye todos los estados salvo Cerrada. No SHALL ofrecer acciones ni enlaces desde los recuentos.

#### Scenario: Distribución completa
- **WHEN** existen incidencias accesibles en los cinco estados
- **THEN** se muestran los cuatro recuentos no cerrados con sus etiquetas y no se incluyen las cerradas

#### Scenario: Ausencia de incidencias
- **WHEN** no hay incidencias accesibles no cerradas
- **THEN** los cuatro estados permanecen visibles con valor cero

### Requirement: Recuentos según visibilidad
El sistema SHALL mostrar el widget a los tres roles autenticados. Los solicitantes SHALL ver exclusivamente cifras de sus propias incidencias; técnicos y administradores SHALL ver las del conjunto. Los filtros del listado no SHALL alterar el universo del widget. No SHALL exponer cifras a personas sin sesión.

#### Scenario: Solicitante aislado
- **WHEN** dos solicitantes con incidencias distintas consultan sus dashboards
- **THEN** cada uno obtiene únicamente los recuentos correspondientes a sus propias incidencias

#### Scenario: Visión de equipo
- **WHEN** un técnico o administrador consulta el dashboard después de haber filtrado un listado
- **THEN** obtiene los recuentos globales no cerrados, independientes de esos filtros

#### Scenario: Acceso anónimo
- **WHEN** una persona sin sesión intenta abrir el dashboard o solicitar los datos del widget
- **THEN** no recibe recuentos y se exige autenticación

### Requirement: Actualización y presentación legible
El sistema SHALL calcular los recuentos al cargar o recargar el dashboard y actualizarlos cada 30 segundos mientras el widget permanezca visible. SHALL reflejar las operaciones confirmadas antes de cada actualización, sin sumar datos de otros usuarios. Las cifras SHALL acompañarse siempre de etiquetas legibles y conservar su relación con ellas en móvil y al ampliar al 200%.

#### Scenario: Incidencia creada o cambiada de estado
- **WHEN** se confirma un alta o una transición y el usuario recarga o llega la siguiente actualización visible
- **THEN** los recuentos reflejan la operación; una transición a Cerrada retira el caso del resumen

#### Scenario: Cambio de usuario
- **WHEN** una sesión termina y otro usuario accede al panel
- **THEN** el widget recalcula las cifras con los permisos del nuevo usuario sin reutilizar cifras de la sesión anterior

#### Scenario: Consulta estrecha y ampliada
- **WHEN** el dashboard se utiliza a 320px de ancho o con ampliación del 200%
- **THEN** todos los estados y cifras siguen siendo legibles sin depender del color ni causar desbordamiento horizontal de página
