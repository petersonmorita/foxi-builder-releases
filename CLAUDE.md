# Foxi Builder

WordPress page-builder plugin (block editor + Foxi Studio). The owner uses it for hotel/property websites. The Panorama Retreat template (`starters/panorama/`) is their standard starting point.

## Working on it
- `npm install`, then `npm run build` (editor scripts and styles into `build/`, which git ignores).
- `npm run starters` rebuilds `starters/panorama/site.json` from `tools/build-panorama.mjs`. Never edit `site.json` by hand.
- Blocks are rendered on the server: `blocks/<name>/block.json`, editor in `src/blocks/*.js`, front end in `includes/Render/*.php`. `save` returns nothing (or only inner blocks), so markup changes never break saved pages. Keep it that way.
- The plugin supports PHP 7.4+. Don't use PHP 8-only syntax.
- Change only what a feature needs; don't rewrite files wholesale.

## Releasing an update
Sites update themselves from the public repo `petersonmorita/foxi-builder-releases` (see `includes/Updater.php`), using the `foxi-builder.zip` attached to each GitHub release.

    tools/release.sh 1.2.0 "* What changed, one line per item"

The script bumps the version, builds, zips (without src/tools/node_modules), commits and tags this repo, pushes, and publishes the release. Add a matching `= 1.2.0 =` entry to the changelog in `readme.txt` first. Use `DRY_RUN=1` to only build the zip.

Version numbers: patch (1.1.x) for fixes, minor (1.x.0) for new features.
