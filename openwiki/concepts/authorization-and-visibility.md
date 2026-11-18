---
type: authorization concept
title: Roles, Policies, and Record Visibility
description: Explains how users authenticate into the Filament /admin panel and how feature policies separately govern tickets, catalog records, and user administration. Documents requester ticket scoping, technician and administrator capabilities, and the safeguards around changing or deleting users.
tags: [authorization, roles, visibility, administration, tickets]
verified:
  - by: openwiki/0.9.0
    at: 2026-11-18T11:00:29.223Z
sources:
  - id: openwiki-source-62182e6b9b04fee41389cf29
    resource: repo://web/app/Actions/Catalog/SaveCatalogRecord.php
  - id: openwiki-source-d9fa2bb5d42eb9027959a690
    resource: repo://web/app/Actions/Tickets/CreateTicket.php
  - id: openwiki-source-388c100ddb6e59fd583f8aa9
    resource: repo://web/app/Actions/Tickets/UpdateTicket.php
  - id: openwiki-source-9ecc92c8d62703d8b003bfc1
    resource: repo://web/app/Actions/Users/CreateUser.php
  - id: openwiki-source-64aa5ad4300a52a71e22539d
    resource: repo://web/app/Actions/Users/DeleteUser.php
  - id: openwiki-source-2562393cb7deec0694e65a34
    resource: repo://web/app/Actions/Users/UpdateUser.php
  - id: openwiki-source-49695d2b39c79ac76e63cb74
    resource: repo://web/app/Enums/UserRole.php
  - id: openwiki-source-979125d787135603a6c28a4f
    resource: repo://web/app/Filament/Resources/Tickets/Pages/ViewTicket.php
  - id: openwiki-source-ecda663629b44f55a371fac6
    resource: repo://web/app/Filament/Resources/Tickets/RelationManagers/CommentsRelationManager.php
  - id: openwiki-source-44c27c5b47b45b92f5082281
    resource: repo://web/app/Filament/Resources/Tickets/TicketResource.php
  - id: openwiki-source-633ad0cb516ed605c16e120f
    resource: repo://web/app/Models/Ticket.php
  - id: openwiki-source-c2105fb3faea365263cbb238
    resource: repo://web/app/Models/User.php
  - id: openwiki-source-da5ad0cf068f6506430c967b
    resource: repo://web/app/Policies/AssetPolicy.php
  - id: openwiki-source-4af58c3dad37a9ea980c5522
    resource: repo://web/app/Policies/SitePolicy.php
  - id: openwiki-source-27b5dc429e7a1a35845d2416
    resource: repo://web/app/Policies/TicketPolicy.php
  - id: openwiki-source-e5a826e16017480f0121f3c3
    resource: repo://web/app/Policies/UserPolicy.php
  - id: openwiki-source-59d1ed75b8b43cada50ae6c5
    resource: repo://web/app/Providers/Filament/AdminPanelProvider.php
  - id: openwiki-source-9c23471785babf36902ad408
    resource: repo://web/tests/Feature/SitesAndAssetsTest.php
  - id: openwiki-source-b56351221b0ef2c1fac7cd27
    resource: repo://web/tests/Feature/TicketManagementTest.php
  - id: openwiki-source-96abce6135a92d09d31a58d7
    resource: repo://web/tests/Feature/UserManagementTest.php
generated: { by: "openwiki/0.9.0", at: "2026-11-18T11:00:29.223Z" }
---

# Roles, Policies, and Record Visibility

InfraTicket has three roles: `admin`, `technician`, and `requester`. The role is an enum cast on `User`, and the application deliberately separates **panel entry** from **what a person may see or do after entry**. This distinction is important: authentication gets a user into the `/admin` panel, while policies and query scopes decide whether a particular feature or record is available.

## Panel authentication is not feature authorization

The Filament panel has the id `admin`, is served at `/admin`, and enables the built-in login page. Its `Authenticate` middleware protects panel requests. `User::canAccessPanel()` currently returns `true` for every authenticated user, so all three roles can sign in. It does not grant them equivalent access inside the panel; resource actions and application actions still invoke the relevant policy.

```mermaid
flowchart TD
    SignIn["User signs in at /admin"] --> Authenticated{"Panel authentication succeeds?"}
    Authenticated -->|No| Denied["Request denied"]
    Authenticated -->|Yes| Feature["Choose a feature or record"]
    Feature --> Policy["Evaluate feature policy"]
    Policy -->|Allowed| Operation["Read or perform operation"]
    Policy -->|Denied| Forbidden["Operation denied"]
```

This shows the boundary between entering the panel and making a feature-level authorization decision.

The `UserPolicy` is the gate for user administration: only administrators may list, view, create, update, or delete users. Its `deleteAny()` remains false, so bulk user deletion is not enabled even though an administrator may delete an individual user when the user-management action's invariants permit it.

## Ticket visibility and capabilities

Ticket visibility has two cooperating layers:

1. `TicketResource::getEloquentQuery()` applies the model's `visibleTo()` scope to the panel's list and record query.
2. `TicketPolicy::view()` checks the individual record as a second boundary.

For a requester, both layers restrict tickets to rows whose `requester_id` is that user's id. A requester therefore cannot list or open another requester's ticket; this is not merely a UI hiding rule. Technicians and administrators have no additional `visibleTo()` restriction and can see all tickets. `viewAny()` and `create()` are true for every authenticated role, so each role can enter the ticket feature and create a ticket; creation uses the acting/session user as the requester rather than trusting a submitted requester identity.

The ticket policy then applies operation-specific rules:

| Operation | Administrator | Technician | Requester |
| --- | --- | --- | --- |
| View tickets | All tickets | All tickets | Own tickets only |
| Create | Yes | Yes | Yes |
| Update | Any non-closed ticket | Any non-closed ticket | Own ticket only while `Proposed` or `Open` |
| Assign | Any non-closed ticket | Any non-closed ticket | No |
| Transition status | Any non-closed ticket | Only a ticket assigned to that technician, while non-closed | No |
| Comment | Any visible, non-closed ticket | Any visible, non-closed ticket | Own, non-closed ticket |
| Delete | No | No | No |

The policy treats `Closed` as terminal for updates, assignment, transitions, and comments. Assignment is available to any non-requester, whereas status transition is intentionally narrower for technicians: the technician must be the ticket's `technician_id`; administrators are not subject to that assignment check. Ticket status and priority are enum-cast model state, and update actions reload and lock the current database row before authorizing, preventing an authorization decision based on a stale status.

The same policy is used by the ticket page's assign and transition actions and by the comments relation manager. The model scope protects collection retrieval, while the policy protects record operations; both must be maintained when extending ticket access.

## Sites and assets

Sites and assets are readable by every authenticated role: their `viewAny()` and `view()` methods return true. Only administrators may create, update, or delete an individual site or asset. `deleteAny()` is false for both resources, so bulk deletion is not authorized.

The catalog actions call the policy through `Gate` before writing. They also validate and normalize catalog data, and deletion rejects records that are still referenced rather than cascading through tickets. The focused feature tests cover administrator maintenance, validation, referenced-record protection, and rejection of requester and technician writes. See [Catalog and User Administration](catalog-and-user-administration.md) for the broader catalog lifecycle.

## User administration safeguards

User policy authorization is necessary but not sufficient for a mutation. The user actions lock the acting user and target row, then authorize against the current actor and record. This prevents a stale administrator object from continuing to perform administrative work after its role has changed.

Additional domain safeguards preserve usable identity and history:

- At least one administrator must remain; the last administrator cannot be deleted or demoted.
- A technician with any non-closed assigned ticket cannot be changed to another role until those tickets are reassigned or closed.
- A user referenced as a ticket requester or technician, or as a comment or status-history author, cannot be deleted. Unreferenced users may be deleted by an authorized administrator.
- User creation and updates normalize identity fields and hash passwords; password and remember-token fields are hidden from serialized user data.

These checks belong in the user actions rather than in the policy alone because they require current database state and cross-record invariants. They are transactional and translate related-record failures into validation errors, preserving the records instead of silently cascading. The corresponding feature tests cover role changes, last-administrator protection, stale actors, and referenced users. See [Catalog and User Administration](catalog-and-user-administration.md) for the operational user-management flow.

## Extension guidance and invariants

When adding a role or feature:

- Update `UserRole` and every feature policy that makes role decisions; panel access should not be used as a substitute for a feature policy.
- For collection-backed resources, apply record scoping in the Eloquent query and retain record-level policy checks. A policy alone does not replace a correctly scoped list query.
- Recheck both the policy and the action/service layer. Actions may reload, lock, validate, or enforce cross-record constraints that a direct policy call does not express.
- Preserve requester isolation: any new ticket list, relation, export, or lookup must use the same ownership rule and must not expose another requester's ticket.

For the ticket state and write workflow, see [Ticket Lifecycle](../workflows/ticket-lifecycle.md). For the application's boundary between the panel and domain operations, see [System Boundaries](../architecture/system-boundaries.md).
