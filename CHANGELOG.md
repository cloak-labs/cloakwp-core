# Changelog

## 2.0.0

### Breaking
- Removed `CloakWP\Core\CMS` god object and `snicco/better-wp-api` dependency.
- Replaced with focused APIs: `Enqueue\Assets`, `Gutenberg\AllowedBlocks`, `Gutenberg\BlockEditor`.
- Admin/Yoast/DX toggles no longer live in Core — compose them in your theme/agency stack.

### Added
- `CloakWP\Core\Features\Feature` contract for opt-in modules.
- Characterization tests for AllowedBlocks, Assets, and BlockEditor.
