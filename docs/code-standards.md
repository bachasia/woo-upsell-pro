# Code Standards

_Last updated: 2026-03-16_

## Scope

These standards reflect the current Woo Upsell Pro codebase patterns in `admin/`, `includes/`, `public/`, and `templates/`.

## Naming Conventions

- Constants: `WUP_*` (uppercase snake case)
- Classes: `WUP_*` (PascalCase with underscores)
- Functions/options/actions: `wup_*` (snake case)
- File names: `class-wup-*.php` (kebab-case)

## Structural Standards

- Entry point defines constants only; runtime boot logic delegated to loader.
- One primary responsibility per class/trait.
- Feature classes live under `includes/features/`.
- Rendering output goes to `templates/`; classes prepare data.
- Helper services live under `includes/helpers/`.

## Security Standards

### AJAX
- Always validate nonce with `check_ajax_referer`.
- Use capability checks for privileged operations.
- Sanitize all request inputs (`sanitize_text_field`, `absint`, etc.).
- Return JSON with `wp_send_json_success`/`wp_send_json_error`.

### Settings
- Register settings with sanitize callbacks.
- Match sanitization by field type:
  - checkbox -> `yes`/`no` normalization
  - number -> `absint`/int cast
  - color -> `sanitize_hex_color`
  - textarea -> `sanitize_textarea_field`
  - select/text -> key/text sanitization

### Output Escaping
- HTML text: `esc_html`
- Attributes: `esc_attr`
- URLs: `esc_url`
- rich content: `wp_kses_post`

## Performance Standards

- Cache expensive source-resolution work via transients.
- Invalidate by prefix on relevant option updates.
- Enqueue scripts/styles conditionally where possible.
- Keep frontend fragments minimal.

## Asset + Styling Standards

- Register script/style handles centrally in `WUP_Assets`.
- Use schema `css` mappings for dynamic inline CSS.
- Avoid hardcoded duplicated styles in feature classes when schema-driven mapping exists.

## Documentation Sync Rules

When changing code in these areas, update docs immediately:
- Bootstrap/load order -> `docs/system-architecture.md`
- Feature inventory/status -> `docs/project-roadmap.md`
- Feature requirements/scope -> `docs/project-overview-pdr.md`
- Implementation map -> `docs/codebase-summary.md`
- Release changes -> `docs/project-changelog.md`

## Review Checklist

- [ ] Nonce and capability checks present for relevant AJAX endpoints
- [ ] Inputs sanitized; outputs escaped
- [ ] New options follow `wup_` prefix
- [ ] Templates only render; business logic stays in classes
- [ ] Docs updated for changed behavior/hooks/options
