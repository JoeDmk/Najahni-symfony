# PHPStan Investment Module Report

## Scope

Static analysis was run only on the investment slice:

- `src/Controller/InvestmentController.php`
- `src/Controller/InvestmentContractController.php`
- `src/Controller/InvestmentAdvancedController.php`
- `src/Service/Investment/`

The goal was to:

1. capture a reproducible baseline,
2. fix the most important investment-module errors,
3. prove the improvement with before/after evidence,
4. document the stricter level 8 findings for follow-up work.

## Tooling

- PHPStan version: `2.1.51`
- PHP version: local workspace environment
- Memory limit required for stable analysis: `1G`

## Commands Used

Baseline level 5 capture:

```powershell
vendor/bin/phpstan analyse src/Controller/InvestmentController.php src/Controller/InvestmentContractController.php src/Controller/InvestmentAdvancedController.php src/Service/Investment/ --configuration=phpstan.neon --no-progress --memory-limit=1G 2>&1 | Tee-Object -FilePath var/phpstan-investment-level5-before.txt
```

Post-fix level 5 validation:

```powershell
vendor/bin/phpstan analyse src/Controller/InvestmentController.php src/Controller/InvestmentContractController.php src/Controller/InvestmentAdvancedController.php src/Service/Investment/ --configuration=phpstan.neon --no-progress --memory-limit=1G 2>&1 | Tee-Object -FilePath var/phpstan-investment-level5-after.txt
```

Level 8 documentation run:

```powershell
vendor/bin/phpstan analyse src/Controller/InvestmentController.php src/Controller/InvestmentContractController.php src/Controller/InvestmentAdvancedController.php src/Service/Investment/ --configuration=phpstan.neon --no-progress --memory-limit=1G 2>&1 | Tee-Object -FilePath var/phpstan-investment-level8.txt
```

## Before / After Evidence

### Level 5 baseline before fixes

- Evidence file: `var/phpstan-investment-level5-before.txt`
- Result: `[ERROR] Found 23 errors`

### Level 5 after fixes

- Evidence file: `var/phpstan-investment-level5-after.txt`
- Result: `[OK] No errors`

### Improvement summary

- Errors before: `23`
- Errors after: `0`
- Net reduction: `23`
- Reduction rate: `100%`

## Fixes Applied

### 1. Typed authenticated user handling in controllers

Problem:
- Symfony `getUser()` returns a broad type (`UserInterface|null`), but the investment repositories and entity setters require concrete `App\Entity\User`.

Fix:
- Added a local `requireUser(): User` helper to the investment controllers that needed concrete user values.
- Replaced repository calls and entity assignments that were passing raw `getUser()` results.

Files changed:
- `src/Controller/InvestmentController.php`
- `src/Controller/InvestmentAdvancedController.php`

### 2. Nullability cleanup in investment flows

Problem:
- Several branches used nullable-safe access where the surrounding flow had already established the object as present.

Fix:
- Removed unnecessary nullsafe access in accepted/rejected offer notification text.
- Removed an unnecessary nullsafe `createdAt` formatting path in contract message serialization.
- Simplified a dead boolean branch in contract activity scoring.

Files changed:
- `src/Controller/InvestmentController.php`
- `src/Controller/InvestmentContractController.php`

### 3. Repository typing fix for entrepreneur opportunity creation

Problem:
- The controller called a non-existent `ProjetRepository::findByUser()` method through a generic Doctrine repository lookup.

Fix:
- Narrowed the repository to `ProjetRepository` and switched to the existing `findByUserWithFilters($user)` method.

File changed:
- `src/Controller/InvestmentController.php`

### 4. Portfolio timeline typing fix

Problem:
- PHPStan could not resolve the shape of the timeline array passed to `usort()`.

Fix:
- Added an explicit list-array shape annotation and a typed comparator.

File changed:
- `src/Controller/InvestmentController.php`

### 5. Service-level mixed/null cleanup

Problem:
- Static analysis flagged ambiguous mixed-array access in the economic API service and unnecessary nullable handling in the Stripe service.

Fix:
- Tightened World Bank payload checks with `is_array`, `array_key_exists`, and `is_numeric`.
- Simplified Stripe API error extraction to the actual nullable branch.

Files changed:
- `src/Service/Investment/EconomicApiService.php`
- `src/Service/Investment/StripePaymentService.php`

## Level 8 Findings (Documented, Not Fully Fixed)

After raising `phpstan.neon` to level 8, the same scoped analysis produced:

- Evidence file: `var/phpstan-investment-level8.txt`
- Result: `[ERROR] Found 124 errors`

These are mostly stricter typing and API-shape issues rather than immediate runtime regressions. They were documented for follow-up instead of being mass-fixed in this pass.

### Main categories at level 8

1. Request input typing
- Many controller calls pass raw `$request->query->get()` or `$request->request->get()` values directly into `trim()`, `strtoupper()`, `strtotime()`, entity setters, and CSRF validation.
- Level 8 requires explicit narrowing from `bool|float|int|string|null` to the exact scalar type expected.

2. Nullable relation chains
- Several controllers call methods through relations PHPStan still considers nullable, such as `getOpportunity()`, `getProject()`, or `getContract()`.
- These need local guards or small helper methods to make the non-null business assumptions explicit.

3. Missing array-shape / iterable-value annotations
- Methods returning arrays or accepting broad `array` parameters now need value-type annotations.
- Examples include controller helper methods and contract serialization helpers.

4. Untyped objects returned from Doctrine lookups
- A number of repository results are inferred as `object` without stronger local typing, which leads to `Call to an undefined method object::...` reports.
- These can be fixed by adding local variable annotations, stronger repository return types, or dedicated repository methods.

5. Existing controller helper debt
- Some investment helper methods such as feed builders and risk-factor builders need precise array contracts to satisfy level 8.

## Conclusion

The investment module static-analysis baseline was improved from `23` errors to `0` errors at level 5 for the requested scope. The stricter level 8 run is now enabled in `phpstan.neon`, and its `124` remaining findings were captured as documented technical debt for a deeper type-hardening pass.