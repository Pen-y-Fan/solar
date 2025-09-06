# Implementing CQRS for Complex Operations in Solar

Last updated: 2025-09-06 16:17

This document explains how and why to introduce the Command–Query Responsibility Segregation (CQRS) pattern to the Solar project, and provides a step-by-step, checkbox-driven plan to implement it incrementally with minimal disruption to existing Laravel/Filament code.

## Why CQRS here?

Solar already moved toward cleaner architecture: domain Actions, Value Objects, and Repositories. CQRS builds on that by making a strict separation between:

- Queries: read-only operations that return data and cause no side-effects.
- Commands: intentful requests to change system state. They return success/failure information, not domain data models.

Benefits for Solar:

1) Clarity of intent and boundaries
   - Every state change is an explicit Command. It documents business intent (e.g., GenerateStrategy, ImportAgileRates) and makes side-effects discoverable and testable.
   - Queries become simple, composable read paths that can be optimized for Filament UI needs without risking accidental writes.

2) Safer UIs (Filament) and services
   - Filament screens often fetch and display Eloquent models directly. With CQRS, you keep that convenience for reads, but route every state change through a Command handler. That reduces accidental writes in display logic and centralizes validation/transactions.

3) Easier performance tuning
   - Read models (or just dedicated query services) can aggregate/denormalize data for fast dashboards/tables without coupling to write-side invariants. You can later introduce caching per-query without touching command code.

4) Stronger testability and observability
   - Command handlers encapsulate validations, transactions, domain events, and integrations. They are trivial to unit-test and to instrument (logging/metrics) in one place.

5) Incremental adoption with current code
   - Many of your Domain Actions already align with command handling, returning a standardized ActionResult. We can wrap/rename and progressively migrate with near-zero churn in callers.

## How CQRS works with Filament (direct Eloquent access)

Filament typically works directly with Eloquent for listing, filtering, and editing. In CQRS, we keep reads simple and can continue to expose Eloquent (or simple DTOs) to Filament for queries. For writes, we introduce a clear seam:

- Keep reads:
  - Filament tables and widgets can query Eloquent or dedicated Query classes. For complex aggregations, use Query objects/services so the read side is optimized and reusable.

- Route writes through Commands:
  - Filament Actions (e.g., the existing GenerateAction) construct and dispatch a Command (e.g., GenerateStrategyCommand) rather than mutating directly. The handler performs validation, transactions, domain updates, and emits events.

In practice this is a small change to UI actions: instead of calling a domain action directly, they send a command to a handler. For simple CRUD using Filament Forms, you can keep model saving but consider moving complex multi-aggregate updates to Commands.

## Architectural mapping to current code

- Domain Actions (write-heavy): map to Command Handlers.
  - Example: App\Domain\Strategy\Actions\GenerateStrategyAction ≈ GenerateStrategyCommandHandler.
  - The standardized ActionResult already matches command outcomes.

- Repositories (read data access): stay on the write side for aggregate loading, or expose separate read-facing Query services for complex listings. Your EloquentInverterRepository is a good foundation; we can add Query classes for dashboard/Filament data.

- Filament UI Actions: construct Commands and dispatch via a Command Bus (or directly resolve handler) instead of invoking run().

## Core concepts to introduce

- Command objects: immutable DTOs representing an intent to change state (e.g., GenerateStrategyCommand { periodFilter }).
- Command handlers: single-responsibility services that validate, wrap work in transactions, call domain services/repositories, and return ActionResult.
- Command bus (optional to start): a simple interface to dispatch commands to handlers. We can begin with direct DI-resolution and add a bus later.
- Query objects/services: dedicated classes for complex reads (e.g., StrategyPerformanceQuery, InverterConsumptionQuery) returning DTOs or arrays tailor-made for UI.

## Step-by-step implementation plan (tasks)

Use this checklist to implement CQRS incrementally. Each item is a small, verifiable step.

- [ ] 1. Establish naming and directories
  - [ ] Create app/Application/Commands and app/Application/Queries namespaces.
  - [ ] Define contracts: Command interface (marker) and CommandHandler interface handle(Command): ActionResult.
  - [ ] Define a simple CommandBus interface and a synchronous implementation (optional in first pass).

- [ ] 2. Introduce a first command for an existing complex operation
  - [ ] Create GenerateStrategyCommand with required inputs (e.g., period filter).
  - [ ] Create GenerateStrategyCommandHandler that delegates to current GenerateStrategyAction (or inlines logic) and returns ActionResult.
  - [ ] Add validation rules/Value Objects mapping inside handler; ensure database transaction where appropriate.
  - [ ] Add domain events/logging from handler (e.g., StrategyGenerated).

- [ ] 3. Update a Filament Action to use the command
  - [ ] Modify app/Filament/Resources/StrategyResource/Action/GenerateAction.php to dispatch GenerateStrategyCommand via handler/bus instead of calling ->run().
  - [ ] Keep UI messaging based on ActionResult (success/failure/messages).

- [ ] 4. Carve out read-side queries for complex listings
  - [ ] Identify Filament tables/widgets that run complex joins/aggregations.
  - [ ] Create Query classes (e.g., InverterConsumptionByTimeQuery) that return plain arrays/DTOs optimized for the UI.
  - [ ] Update controllers/widgets to use Query classes where it simplifies or optimizes reads; keep simple reads on Eloquent.

- [ ] 5. Migrate additional domain actions to commands
  - [ ] Wrap App/Domain/Energy/Actions/AgileImport as ImportAgileRatesCommand(+Handler).
  - [ ] Wrap App/Domain/Energy/Actions/AgileExport as ExportAgileRatesCommand(+Handler).
  - [ ] Wrap App/Domain/Energy/Actions/Account as SyncOctopusAccountCommand(+Handler).
  - [ ] Ensure all these handlers return ActionResult and include validation/transactions.

- [ ] 6. Introduce testing around commands and queries
  - [ ] Unit tests for each CommandHandler (happy path, validation failure, domain errors).
  - [ ] Unit/Feature tests for Query classes (assert correct data shaping, performance where feasible).
  - [ ] Update existing tests to prefer sending commands over calling actions directly.

- [ ] 7. Add a lightweight CommandBus (optional but recommended)
  - [ ] Define CommandBus implementation with a map of Command => Handler in a service provider.
  - [ ] Add middleware/pipeline hooks (logging, timing, authorization) as needed.
  - [ ] Replace direct handler resolution in UI/HTTP with bus->dispatch($command).

- [ ] 8. Document developer workflow
  - [ ] Update docs/actions.md to reference commands: when to create a Command vs an Action.
  - [ ] Provide examples for Filament actions using commands and for controllers using queries.

- [ ] 9. Incremental rollout & toggles
  - [ ] Adopt commands for new complex writes immediately; migrate existing features opportunistically.
  - [ ] Keep a temporary compatibility layer (Actions delegating to handlers) to avoid wide changes.
  - [ ] Update docs/tasks.md checkboxes for "Implement CQRS pattern for complex operations".

## Example sketch: Generate Strategy via CQRS

Command:

- GenerateStrategyCommand { string $period }

Handler responsibilities:

- Validate period (Value Object or rule set).
- Begin transaction; compute strategy via repository/query data; persist Strategy model updates.
- Emit StrategyGenerated event; return ActionResult::success with a message.

Filament Action change (conceptual):

- Before: new GenerateStrategyAction($repo)->run()
- After: $bus->dispatch(new GenerateStrategyCommand(period: $period))

Result mapping remains identical, so UI notifications stay the same.

## Data shaping and read models

You can keep Eloquent models visible to Filament for standard CRUD and simple listing. For complex dashboards or cross-aggregate reads, add Query classes that:

- Accept input filters; call repositories; return DTOs tailored to the component.
- Are side-effect free; optionally cached.
- Are covered by simple unit tests.

## Validation, transactions, and events in handlers

- Validation: either via Value Objects or Validator::make inside handlers. Fail fast; return ActionResult::failure with messages.
- Transactions: wrap multi-aggregate writes in DB::transaction to enforce invariants.
- Events: dispatch domain or integration events after successful commit for decoupled reactions.

## Acceptance criteria for completing the Phase 1 CQRS task

- [ ] At least one complex operation (Generate Strategy) is migrated to Command + Handler and integrated with Filament.
- [ ] A minimal Command interface and (optionally) CommandBus exist and are bound in the container.
- [ ] At least one Query class is in use by a Filament listing/widget, or a plan is documented if all reads are already simple.
- [ ] Tests exist for the new handler(s) and any query classes.
- [ ] docs/actions.md is updated with CQRS guidance, and docs/tasks.md CQRS checkboxes can begin to be checked progressively.

## Incremental strategy recap

Start small by wrapping one existing Action with a Command and CommandHandler. Keep the Action as a shim that delegates to the handler so no other code breaks. Gradually migrate other complex writes. Use Query classes where reads are heavy or reused. This delivers CQRS benefits without a big-bang rewrite and plays well with Filament’s Eloquent-centric UX.
