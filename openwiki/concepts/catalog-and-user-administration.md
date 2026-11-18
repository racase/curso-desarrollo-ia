---
type: administrative concept
title: Users, Sites, and Assets Administration
description: Administrative users can manage users, sites, and assets through policy-checked action classes. This page documents normalization, relationship and role safeguards, transactional locking, and the boundary between implemented administration and functionality that remains outside these resources.
tags: [administration, users, sites, assets, authorization, data-integrity]
verified:
  - by: openwiki/0.9.0
    at: 2026-11-18T11:00:29.223Z
sources:
  - id: openwiki-source-4f6260238b6d89d0622a1bdb
    resource: repo://web/app/Actions/Catalog/DeleteCatalogRecord.php
  - id: openwiki-source-62182e6b9b04fee41389cf29
    resource: repo://web/app/Actions/Catalog/SaveCatalogRecord.php
  - id: openwiki-source-9ecc92c8d62703d8b003bfc1
    resource: repo://web/app/Actions/Users/CreateUser.php
  - id: openwiki-source-64aa5ad4300a52a71e22539d
    resource: repo://web/app/Actions/Users/DeleteUser.php
  - id: openwiki-source-2562393cb7deec0694e65a34
    resource: repo://web/app/Actions/Users/UpdateUser.php
  - id: openwiki-source-1d836e3df4c7fe7ac77f0e31
    resource: repo://web/app/Actions/Users/UserAdministration.php
  - id: openwiki-source-4db4ea6a870b09325aa2707d
    resource: repo://web/app/Actions/Users/UserData.php
  - id: openwiki-source-f8eca4fab567157f93122e9a
    resource: repo://web/app/Filament/Resources/Assets/AssetResource.php
  - id: openwiki-source-320b6a484d48818c0c30eaf9
    resource: repo://web/app/Filament/Resources/Sites/SiteResource.php
  - id: openwiki-source-c56e0d59db5fcdd2b0a741fb
    resource: repo://web/app/Filament/Resources/Users/UserResource.php
  - id: openwiki-source-0b3d2a6d0ddfd981fa8df7c8
    resource: repo://web/app/Models/Asset.php
  - id: openwiki-source-84c840336c242d74b3af6b95
    resource: repo://web/app/Models/Site.php
  - id: openwiki-source-c2105fb3faea365263cbb238
    resource: repo://web/app/Models/User.php
  - id: openwiki-source-da5ad0cf068f6506430c967b
    resource: repo://web/app/Policies/AssetPolicy.php
  - id: openwiki-source-4af58c3dad37a9ea980c5522
    resource: repo://web/app/Policies/SitePolicy.php
  - id: openwiki-source-e5a826e16017480f0121f3c3
    resource: repo://web/app/Policies/UserPolicy.php
  - id: openwiki-source-a52eb9a5cce976bdddc758ac
    resource: repo://web/tests/Feature/AdministrationMigrationsTest.php
  - id: openwiki-source-9c23471785babf36902ad408
    resource: repo://web/tests/Feature/SitesAndAssetsTest.php
  - id: openwiki-source-96abce6135a92d09d31a58d7
    resource: repo://web/tests/Feature/UserManagementTest.php
generated: { by: "openwiki/0.9.0", at: "2026-11-18T11:00:29.223Z" }
---

# Users, Sites, and Assets Administration

The application currently implements three administrative resources in the Filament panel: users, sites, and assets. Users are visible only to administrators, while all authenticated panel users can view sites and assets; create, edit, and delete operations for sites and assets are administrator-only. The resources expose list, create, view, and edit pages, but bulk deletion is disabled by the policies. These are implemented catalog and user-management capabilities, not a promise that every related domain object has a management screen. [UserResource](../../web/app/Filament/Resources/Users/UserResource.php), [SiteResource](../../web/app/Filament/Resources/Sites/SiteResource.php), [AssetResource](../../web/app/Filament/Resources/Assets/AssetResource.php), [UserPolicy](../../web/app/Policies/UserPolicy.php), [SitePolicy](../../web/app/Policies/SitePolicy.php), [AssetPolicy](../../web/app/Policies/AssetPolicy.php)

## Resource relationships

A site has many assets. Each asset belongs to one site and has many tickets, and its display label includes the current site's name. The catalog action layer therefore treats `site_id` as a required, existing site reference and supports moving an asset by saving a different site. User references are held by ticket requester and technician fields, ticket comments, and ticket status history; those references are intentionally not cascaded away when a user is removed.

```mermaid
erDiagram
    SITE ||--o{ ASSET : contains
    ASSET ||--o{ TICKET : referenced_by
    USER ||--o{ TICKET : requests_or_works
    USER ||--o{ TICKET_COMMENT : authors
    USER ||--o{ TICKET_STATUS_CHANGE : authors
```

This diagram shows the relationships that affect catalog and user deletion safeguards.

## Action-layer write flow

Filament resources provide the entrypoints, but the important invariants live in action classes. `SaveCatalogRecord` and `DeleteCatalogRecord` run inside database transactions, reload existing records with a row lock, and authorize against the reloaded actor and record. User actions use the same transactional pattern plus the named `users` administration lock, which serializes competing user mutations and refreshes the actor from the database before authorization.

```mermaid
flowchart TD
    start["Administrative mutation"] --> kind{"User or catalog record?"}
    kind -->|User| userlock["Lock users administration row"]
    userlock --> reloaduser["Reload actor and target with locks"]
    reloaduser --> usergate["Authorize user policy"]
    kind -->|Site or asset| reloadrecord["Reload catalog record with lock"]
    reloadrecord --> cataloggate["Authorize catalog policy"]
    usergate --> validate["Validate and enforce references and roles"]
    cataloggate --> validatecatalog["Normalize and validate catalog data"]
    validate --> commit["Commit transaction"]
    validatecatalog --> commit
```

This flow summarizes the action-layer locking, authorization, validation, and commit boundary.

## Users

`CreateUser`, `UpdateUser`, and `DeleteUser` are the supported mutation entrypoints. `UserData` trims names and email, lowercases email, validates the `UserRole` enum, requires a 12-character password on creation, and hashes passwords through the model's cast. On update, an empty password leaves the existing password unchanged. Invalid data raises a validation error before persistence.

Every user mutation first calls `UserAdministration::lock`, which obtains the sole `users` row in `administration_locks` with `lockForUpdate()` and reloads the actor. This matters for stale in-memory actors: a caller whose role was changed after loading cannot use the old role to authorize a mutation. The user action then authorizes through `UserPolicy`; only administrators may view or mutate users. The lock is held inside the transaction, so concurrent user administration observes a consistent ordering.

Two role invariants protect operational continuity:

- At least one administrator must remain. Deleting the last administrator or changing the last administrator to another role fails validation.
- A technician with any non-closed ticket cannot be demoted. The ticket must first be reassigned or closed. Closing the ticket permits the role change; the historical technician reference is retained.

Deletion is also constrained by database relationships and explicit preflight checks. A user referenced as a ticket requester or technician, comment author, or status-change author cannot be deleted. The action converts foreign-key constraint failures into validation errors as a second line of defense. An unreferenced user can be deleted; there is no cascade that silently removes ticket history.

## Sites and assets

`SaveCatalogRecord` is the shared site/asset save action. It trims `code` and `name`, uppercases codes, enforces required strings and maximum lengths, and enforces unique codes. Assets additionally require an integer `site_id` that exists in `sites`; the site is shared-locked and rechecked before the asset is saved, so a disappearing selection is reported as a validation failure rather than creating a dangling reference. Existing assets can be moved between sites by submitting another valid `site_id`.

`DeleteCatalogRecord` authorizes the specific site or asset and refuses deletion when a site still has assets or an asset still has tickets. It also translates relevant SQL integrity errors into validation errors. Thus deletion is non-cascading: a free asset and its now-free site can be removed, while referenced catalog records remain intact.

## What is implemented, and what is not implied

The source and focused feature tests establish working creation, editing, deletion, normalization, role validation, asset movement, and relationship protection for these resources. They do not establish a general-purpose administration UI for tickets, comments, status history, or arbitrary reference cleanup. Those objects participate in safeguards, but their participation should not be read as an implemented management feature here.

The migration tests additionally verify that the `administration_locks` row exists after migrations, that asset/site relationships survive migration reversal and reapplication, and that user-email normalization refuses collisions without changing existing accounts. These are persistence prerequisites for the action-layer guarantees, not user-facing features.

## Focused verification

- `web/tests/Feature/UserManagementTest.php` covers normalization and password behavior, invalid fields, authorization, last-administrator protection, referenced-user deletion, technician demotion, and stale-actor rejection.
- `web/tests/Feature/SitesAndAssetsTest.php` covers normalized uniqueness, required site references, asset movement, non-cascading deletion, and authorization for catalog writes.
- `web/tests/Feature/AdministrationMigrationsTest.php` covers the administration lock row and migration reversibility for user normalization and asset-ticket relationships.
