---
title: Gestión de usuarios
labels: [feature, web]
depends_on: []
---

## Resumen

Ofrecer a los administradores la gestión de usuarios y sus roles desde el panel de InfraTicket.

## Problema y oportunidad

Actualmente existen usuarios de prueba y roles, pero el panel no permite administrar las cuentas. La operación necesita dar altas, corregir información y retirar usuarios sin depender de cambios técnicos.

## Objetivos

- Dar autonomía a los administradores para mantener los usuarios.
- Asignar el rol adecuado a cada cuenta.
- Restringir la gestión de cuentas a los administradores.

## Usuarios afectados

- Administradores: consultar, crear, editar y eliminar usuarios.
- Técnicos y solicitantes: sus cuentas y roles pueden ser gestionados, sin acceso a esta capacidad.

## Alcance

- Listado, alta, edición y eliminación de usuarios desde el panel.
- Asignación de los roles administrador, técnico o solicitante.
- Acceso exclusivo para administradores.
- Capacidad independiente del resto del backlog.

## Fuera de alcance

- Registro público de cuentas e invitaciones.
- Nuevos roles o permisos configurables.
- Gestión de incidencias y asignaciones.

## Requisitos de producto

- Solo un administrador puede consultar y ejecutar las operaciones de gestión de usuarios, incluido el acceso directo.
- El alta y la edición permiten asignar uno de los tres roles existentes.
- El listado permite identificar al usuario y su rol.
- La eliminación permite retirar usuarios conforme a las restricciones que se acuerden para preservar el acceso y la información relacionada.

## Criterios de éxito

- Un administrador puede mantener las cuentas desde el panel.
- Los roles asignados se reflejan en el acceso correspondiente de cada usuario.
- Técnicos y solicitantes no acceden a la gestión de usuarios.

## Riesgos y preguntas abiertas

- ¿Qué información se solicita al crear o editar una cuenta y cómo se establece su contraseña inicial?
- ¿Se impide eliminar o degradar al último administrador?
- ¿Puede un administrador eliminar su propia cuenta?
- ¿Qué ocurre con las incidencias, asignaciones y comentarios de un usuario eliminado cuando existan esas capacidades?
