# Adding and Editing Validation Rules

Validation rules are self-contained PHP classes that inspect an `Entity` model and return a structured result. The engine discovers them automatically from the filesystem — no registration step is needed beyond running `rules:sync`.

---

## File location and naming

```
app/Services/Metadata/Rules/
├── Structural/      S01_EntityIdUri.php …
├── Certificate/     C01_CertificateValid.php …
├── Refeds/          R01_DisplayName.php …
├── XSD/             X01_SchemaValid.php …
├── Oidc/            O01_RedirectUriHttps.php …
└── Contracts/       MetadataRule.php  RuleResult.php
```

Naming convention: `{ID}_{PascalCaseName}.php`, e.g. `R16_SomeNewCheck.php`.

Pick the next available ID in the appropriate group or create a new subdirectory for a new group. The ID prefix is arbitrary — the registry uses the string returned by `id()`, not the filename.

---

## The MetadataRule interface

Every rule must be a **`final` class** that implements `MetadataRule`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\Refeds;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;

final class R16_MyNewCheck implements MetadataRule
{
    public function id(): string             { return 'R16'; }
    public function name(): string           { return 'Short display name (≤ 64 chars)'; }
    public function group(): string          { return 'refeds'; }
    public function appliesTo(): array       { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'warning'; }
    public function specUrl(): string        { return 'https://refeds.org/...'; }

    public function description(): string
    {
        return 'The mdui:DisplayName in English must not exceed 64 characters.';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        $name = $entity->uiInfo->where('field', 'display_name')->where('lang', 'en')->first()?->value;

        if ($name === null) {
            return RuleResult::notApplicable($this->id());
        }

        if (strlen($name) <= 64) {
            return RuleResult::pass($this->id(), 'DisplayName is within the 64-character limit.');
        }

        return RuleResult::fail(
            $this->id(),
            'DisplayName exceeds 64 characters.',
            'Current length: ' . strlen($name),
        );
    }
}
```

### Required methods

| Method | Return type | Purpose |
|--------|------------|---------|
| `id()` | `string` | Unique rule ID, e.g. `'R16'`. Must be stable — used as DB primary key. |
| `name()` | `string` | Short human label shown in the UI rule list. |
| `group()` | `string` | Group slug (`structural`, `certificate`, `refeds`, `xsd`, `oidc`). Controls grouping in the UI. |
| `appliesTo()` | `string[]` | Entity types: any combination of `'idp'`, `'sp'`, `'oidc'`. |
| `defaultSeverity()` | `string` | `'error'` or `'warning'`. Operators can override per entity/federation. |
| `specUrl()` | `string` | Link to the relevant specification. Shown via the ⓘ icon. |
| `description()` | `string` | Full sentence describing what the rule checks. Shown in the UI. |
| `evaluate(Entity $entity)` | `RuleResult` | Core logic — returns one of the four result factories below. |

---

## RuleResult factories

| Factory | When to use |
|---------|------------|
| `RuleResult::pass($id, $message)` | The check succeeded. |
| `RuleResult::fail($id, $message, $detail = '')` | The check failed. `$detail` is optional extra context (e.g. the offending value). |
| `RuleResult::warning($id, $message, $detail = '')` | The check found an issue but it is advisory rather than blocking. |
| `RuleResult::notApplicable($id)` | The rule does not apply to this entity (e.g. a field that is only relevant when another condition is met). |

> **Tip:** Return `notApplicable` when a prerequisite is missing rather than returning `pass`. This keeps the results honest — a missing SIRTFI assurance string should not make the SIRTFI security-contact rule "pass".

The engine may promote a `fail` result to `warning` if an operator has overridden the severity at federation or entity level. Your rule should always return `fail` for a genuine failure; let the engine handle severity adjustments.

---

## Entity relationships available in evaluate()

The entity passed to `evaluate()` has these relationships already loaded:

| Relationship | Model | Contents |
|---|---|---|
| `$entity->certificates` | `Certificate` collection | All signing/encryption certificates |
| `$entity->uiInfo` | `UiInfo` collection | `field`, `lang`, `value` rows (display_name, description, …) |
| `$entity->contacts` | `Contact` collection | ContactPerson entries |
| `$entity->endpoints` | `Endpoint` collection | SSO/ACS/SLO/… endpoints |
| `$entity->attributes` | `Attribute` collection | Requested/released attributes |
| `$entity->scopes` | `Scope` (or similar) | shibmd:Scope values for IdP |
| `$entity->oidcConfig` | `OidcConfig\|null` | OIDC-specific configuration |
| `$entity->nameid_formats` | array/string | NameIDFormat values |
| `$entity->registration_authority` | string | mdrpi:RegistrationInfo authority |

Direct scalar columns (`$entity->entity_id`, `$entity->type`, `$entity->sp_want_assertions_signed`, etc.) are also available.

Avoid issuing additional DB queries inside `evaluate()`. If a relationship you need is not already loaded, open a PR to add it to the engine's `loadMissing()` call in `RuleEngine::evaluate()`.

---

## Constructor injection

The `app()` container instantiates each rule class, so constructor dependencies are resolved automatically:

```php
public function __construct(
    private readonly MyService $service,
) {}
```

Keep dependencies lightweight — rules are instantiated for every evaluation call.

---

## Registering a new rule

1. Create the class file following the conventions above.
2. Run:
   ```bash
   php artisan rules:sync
   ```
   This discovers all `final` classes that implement `MetadataRule`, upserts their metadata into `rule_definitions`, and flushes the registry cache.

3. The new rule appears immediately in the UI at `/rules` and is active by default.

> **Note:** `rules:sync` never deletes rows. Rules removed from the codebase are marked `active = false` so that historical override configuration is preserved.

If you add a rule as part of a deployment, `rules:sync` is included in `php artisan app:deploy` — no extra step is needed.

---

## Gotchas

- **`final` is required.** The registry skips any class that is not declared `final`. This is intentional — it prevents accidental inheritance of rule logic.
- **The registry caches for 1 hour.** In development, run `php artisan rules:sync` or `php artisan cache:clear` after creating a new class if it does not appear.
- **The ID is the DB primary key.** Never change `id()` after a rule has been deployed — doing so orphans any existing override configuration. Rename the class file and `name()`/`description()` freely; leave `id()` unchanged.
- **DB never deletes.** Removing a class from the codebase causes `rules:sync` to deactivate the row, not delete it. Per-entity and per-federation overrides for that rule are preserved in case the rule is re-added later.
