# Contrib module → frontend library requirements

Drupal contrib modules often declare JavaScript in `.libraries.yml` pointing at
`web/libraries/`. Installing the **module via Composer does not install the JS
asset**. Both are required.

| Drupal module | Library path (under `web/`) | Install command |
|---------------|----------------------------|-----------------|
| slick | `libraries/slick/slick/slick.min.js` | `composer require npm-asset/slick-carousel` (see below) |
| blazy | `libraries/blazy/blazy.min.js` | `composer require npm-asset/blazy` |

## Slick carousel (required for `drupal/slick`)

```bash
# One-time project composer.json setup (asset-packagist + installer extender):
composer require oomphinc/composer-installers-extender:^2.0
composer require npm-asset/slick-carousel:^1.8

# Ensure web/libraries/slick/slick/slick.min.js exists after install.
```

Add to `foundational-checklist.yml`:

```yaml
required_libraries:
  - path: libraries/slick/slick/slick.min.js
    when_modules:
      - slick
```

Verified by `verify-foundational.sh` (filesystem) and `verify-quality.sh`
**QR-LIB-001** (HTTP 200).

## Theme vs module carousel

| Approach | When to use |
|----------|-------------|
| `drupal/slick_views` + Views Slick style | Full Views integration; more config |
| Theme JS + `slick/slick` library | Simpler; attach via `*.libraries.yml` |

Document the chosen approach in plan.md **Ambiguities Resolved**.

## References

- [Slick module install requirements](https://www.drupal.org/project/slick)
- Project `postmortem.md` (003) — missing slick JS caused silent carousel failure
