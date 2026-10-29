<?php
/**
 * One-click updates from GitHub.
 *
 * Each site checks the Foxi releases repository twice a day. That repository
 * holds the finished plugin (no source); when the version in its
 * foxi-builder.php is newer, Plugins shows "Update now" as it does for
 * plugins from wordpress.org. Publish with tools/release.sh.
 *
 * Defaults to the public releases repository, so no access token is needed.
 * To update from a private repository instead, add to wp-config.php:
 *
 *     define( 'FOXI_UPDATE_REPO', 'https://github.com/owner/repo/' );
 *     define( 'FOXI_GITHUB_TOKEN', 'github_pat_…' ); // read-only token
 *
 * Uses Plugin Update Checker by Jānis Elsts (MIT), in lib/.
 *
 * @package FoxiBuilder
 */

namespace Foxi;

defined( 'ABSPATH' ) || exit;

class Updater {

	const REPO = 'https://github.com/petersonmorita/foxi-builder-releases/';

	/** @var object|null The update checker, for "check now" tools. */
	public static $checker = null;

	public function init() {
		$loader = FOXI_DIR . 'lib/plugin-update-checker/load-v5p6.php';
		if ( ! is_readable( $loader ) ) {
			return;
		}
		require_once $loader;
		if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			return;
		}

		$repo = defined( 'FOXI_UPDATE_REPO' ) ? FOXI_UPDATE_REPO : self::REPO;
		/** Filter the GitHub repository Foxi updates from. Return '' to turn updates off. */
		$repo = apply_filters( 'foxi_update_repo', $repo );
		if ( ! $repo ) {
			return;
		}

		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( $repo, FOXI_FILE, 'foxi-builder' );

		// The releases repo's main branch holds the finished plugin; its
		// "Version:" header is the version on offer.
		$checker->setBranch( defined( 'FOXI_UPDATE_BRANCH' ) ? FOXI_UPDATE_BRANCH : 'main' );
		if ( defined( 'FOXI_GITHUB_TOKEN' ) && FOXI_GITHUB_TOKEN ) {
			$checker->setAuthentication( FOXI_GITHUB_TOKEN );
		}
		self::$checker = $checker;
	}
}
