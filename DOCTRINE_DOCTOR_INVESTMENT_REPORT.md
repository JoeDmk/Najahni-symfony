# Doctrine Doctor Investment Report

## Scope

Entities reviewed in full before analysis:

- `src/Entity/InvestmentOpportunity.php`
- `src/Entity/InvestmentOffer.php`
- `src/Entity/InvestmentContract.php`
- `src/Entity/InvestmentContractMessage.php`
- `src/Entity/ContractMilestone.php`
- `src/Entity/InvestorProfile.php`

Related parent mapping updated to resolve one investment warning cleanly:

- `src/Entity/Projet.php`

## Installation Evidence

Requested packages were installed and the bundle was registered manually in `config/bundles.php` because it was not present after install.

Installed packages:

- `ahmed-bhs/doctrine-doctor`
- `webmozart/assert`

Bundle registration:

- `AhmedBhs\DoctrineDoctor\DoctrineDoctorBundle::class => ['dev' => true]`

Explicit cache clear succeeded with increased memory:

```text
// Clearing the cache for the dev environment with debug true

[OK] Cache for the "dev" environment (debug=true) was successfully cleared.
```

## Baseline Evidence

### 1. Doctrine schema validation before fixes

```text
Mapping
-------

[OK] The mapping files are correct.

Database
--------

[SKIPPED] The database was not checked for synchronicity.
```

### 2. Doctrine Doctor baseline on an investment route

Profile route used:

- `GET /investissement/opportunities` redirected to `GET /login`

Baseline profiler counts:

- Total issues: `118`
- Critical: `4`
- Warnings: `63`
- Info: `51`

Baseline investment-related warnings found by Doctrine Doctor:

- Property type mismatch on non-null columns represented as nullable PHP properties:
  - `InvestmentOpportunity::$targetAmount`
  - `InvestmentOpportunity::$createdAt`
  - `InvestmentOpportunity::$updatedAt`
  - `InvestmentOffer::$proposedAmount`
  - `InvestmentOffer::$createdAt`
  - `InvestmentOffer::$updatedAt`
  - `InvestmentContract::$createdAt`
  - `InvestmentContract::$updatedAt`
  - `InvestmentContractMessage::$createdAt`
  - `ContractMilestone::$createdAt`
  - `InvestorProfile::$createdAt`
  - `InvestorProfile::$updatedAt`
- `InvestmentContract::$messages`: `orphanRemoval=true` without `cascade=['persist']`
- `InvestmentOpportunity::$offers`: composition warning implying orphan removal should be explicit
- Delete-consistency warning involving `InvestmentOpportunity::$project`

Baseline documented investment security concerns from entity review:

- `InvestmentOffer::$paymentIntentId`
- `ContractMilestone::$paymentIntentId`
- `InvestmentContract::$investorSignatureHash`
- `InvestmentContract::$entrepreneurSignatureHash`
- `InvestmentContract::$investorSignatureImage`
- `InvestmentContract::$entrepreneurSignatureImage`

These were documented for the report. Doctrine Doctor did not raise them directly on the sampled request.

Baseline documented environment configuration findings from Doctrine Doctor:

- MySQL/PHP timezone mismatch
- SYSTEM timezone usage in MySQL
- Missing SQL strict mode settings
- MySQL timezone tables not loaded
- Small InnoDB buffer pool
- Collation consistency recommendations

These are environment-level findings, not investment-entity mapping defects.

## Fixes Applied

### Entity mapping fixes

- Converted non-null mapped properties from nullable PHP types to non-null types in the investment entities.
- Added explicit `nullable: false` on non-nullable columns for consistency.
- Added explicit Doctrine index metadata where safe and non-duplicative:
  - `InvestmentOffer`: `investor_id`, `opportunity_id`
  - `InvestmentContractMessage`: `contract_id`, `sender_id`
  - `ContractMilestone`: `contract_id`
- Added `cascade: ['persist']` to `InvestmentContract::$messages`.
- Added `orphanRemoval: true` to `InvestmentOpportunity::$offers`.
- Added `cascade: ['remove'], orphanRemoval: true` to `Projet::$opportunities` so project deletion semantics match the investment opportunity composition model.

### Targeted SQL changes

No targeted `ALTER TABLE` statements were applied.

Reason:

- The required fixes in this pass were metadata-level mapping corrections.
- Final `doctrine:schema:validate --skip-sync` remained clean.
- Direct low-level index introspection via local MySQL client tooling was not reliable in this environment because the installed MySQL client could not load its authentication plugin.

## Summary Table

| Issue type | Entity affected | Description | Status |
| --- | --- | --- | --- |
| Integrity | `InvestmentOpportunity` | `targetAmount`, `createdAt`, `updatedAt` were nullable in PHP while mapped non-null in Doctrine | Fixed |
| Integrity | `InvestmentOffer` | `proposedAmount`, `createdAt`, `updatedAt` were nullable in PHP while mapped non-null in Doctrine | Fixed |
| Integrity | `InvestmentContract` | `createdAt`, `updatedAt` were nullable in PHP while mapped non-null in Doctrine | Fixed |
| Integrity | `InvestmentContractMessage` | `createdAt` was nullable in PHP while mapped non-null in Doctrine | Fixed |
| Integrity | `ContractMilestone` | `createdAt` was nullable in PHP while mapped non-null in Doctrine | Fixed |
| Integrity | `InvestorProfile` | `createdAt`, `updatedAt` were nullable in PHP while mapped non-null in Doctrine | Fixed |
| Integrity | `InvestmentContract` | `messages` used `orphanRemoval=true` without `cascade=['persist']` | Fixed |
| Integrity | `InvestmentOpportunity` | `offers` composition relationship lacked explicit `orphanRemoval=true` | Fixed |
| Integrity | `Projet` / `InvestmentOpportunity` | Parent-side project to opportunity composition was not explicit in ORM | Fixed |
| Configuration | `InvestmentOffer` | Explicit foreign-key index metadata added for `investor_id` and `opportunity_id` | Fixed |
| Configuration | `InvestmentContractMessage` | Explicit foreign-key index metadata added for `contract_id` and `sender_id` | Fixed |
| Configuration | `ContractMilestone` | Explicit foreign-key index metadata added for `contract_id` | Fixed |
| Security | `InvestmentOffer`, `ContractMilestone` | Payment intent identifiers are sensitive operational data and should not be exposed in serialization/logging | Documented |
| Security | `InvestmentContract` | Signature hashes and signature images are sensitive and should be protected from accidental serialization/logging | Documented |
| Configuration | Database environment | Timezone mismatch, strict mode, timezone tables, and InnoDB tuning findings are environment-level rather than entity-level | Documented |
| Integrity | Investment entities with timestamps | Missing blameable/audit fields (`createdBy`, `updatedBy`) are advisory info-level profiler findings | Documented |
| Integrity | `ContractMilestone`, `InvestmentContract`, `InvestmentOffer` | Public timestamp setters are advisory info-level profiler findings | Documented |

## After Evidence

### 1. Final doctrine schema validation

```text
Mapping
-------

[OK] The mapping files are correct.

Database
--------

[SKIPPED] The database was not checked for synchronicity.
```

### 2. Final Doctrine Doctor counts on the same investment route

- Total issues: `106`
- Critical: `4`
- Warnings: `51`
- Info: `51`

Delta after investment fixes:

- Total issues reduced from `118` to `106`
- Warnings reduced from `63` to `51`

### 3. Final investment-entity state

Remaining investment findings in Doctrine Doctor are now informational only:

- Missing blameable/audit fields on timestamped investment entities
- Public timestamp setters on milestone/signature/payment timestamp fields

No warning-level Doctrine Doctor findings remained for the reviewed investment entities after the mapping fixes.
