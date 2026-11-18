# Files

- [Domain Data Model and Persistence Invariants](data-model.md) - Persisted entities and relationships for users, tickets, status history, comments, sites, and assets. Documents the migration-defined foreign keys, ordering, enum values, optimistic tracking version, deletion protection, and schema evolution.
- [System Boundaries and Runtime Composition](system-boundaries.md) - Explains how the Laravel application, authenticated Filament admin panel, action and policy layers, Eloquent models, migrations, PostgreSQL container, and entrypoint compose the InfraTicket runtime. Use it to locate behavior safely and understand request, authorization, persistence, and startup boundaries.
