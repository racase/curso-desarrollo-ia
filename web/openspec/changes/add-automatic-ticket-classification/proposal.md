# Proposal

## Why

La clasificación manual de incidencias exige interpretar cada descripción y dificulta aplicar criterios consistentes. El issue [7](../../../docs/issues/07-automatic-ticket-classification.md) propone automatizar la primera clasificación manteniendo la revisión técnica y permitiendo registrar incidencias aunque falle el proveedor.

## What Changes

- Clasificar nuevas incidencias desde el backend mediante OpenRouter y el modelo configurado como `gpt-oss-120b`, usando únicamente su descripción.
- Incorporar las categorías confirmadas: hardware, software, red, accesos y otros; representar la urgencia mediante la prioridad existente.
- Exigir JSON validado localmente contra un esquema cerrado y realizar como máximo tres intentos, con tiempos de espera acotados.
- Conservar el alta como «Sin clasificar» cuando no haya configuración válida o se agoten los intentos, sin cambiar el estado de atención.
- Permitir a técnicos y administradores corregir categoría y urgencia en incidencias no cerradas desde Filament.
- **BREAKING**: retirar la prioridad manual del alta y la edición de clasificación por solicitantes; permitir prioridad nula en nuevas incidencias sin clasificar.
- Conservar las prioridades anteriores sin inventar categorías ni reclasificar registros históricos.
- Mantener credenciales y diagnósticos seguros exclusivamente en el servidor.

## Capabilities

### New Capabilities

- `ticket-classification`: propuesta automática, contrato JSON, reintentos, ausencia de clasificación, revisión manual y configuración segura del proveedor.

### Modified Capabilities

- `ticket-management`: adaptar alta, permisos de edición, listado y ficha para clasificación automática y valores ausentes.

## Impact

- Modelo `Ticket`, nuevo catálogo de categorías y migración compatible con registros históricos; factories y seeders afectados por la prioridad opcional.
- Acciones `CreateTicket`, `UpdateTicket` y validación `TicketData`; autorización de clasificación separada de la edición de datos básicos.
- Formularios, tablas y ficha del recurso de incidencias de Filament.
- Nuevo servicio de clasificación, cliente HTTP del backend, configuración de OpenRouter y biblioteca de validación JSON Schema.
- Pruebas de dominio, migración e integración Livewire con proveedor simulado; actualización de documentación y `.env.example` sin secretos.
- No incluye reclasificación al editar, procesamiento masivo, asignación de técnicos, entrenamiento ni cambios en el seguimiento.
