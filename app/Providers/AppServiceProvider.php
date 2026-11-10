<?php
/**
 * Core bindings and i18n.
 *
 * @package WPIntegrations\MailerLiteConnector
 */

namespace WPIntegrations\MailerLiteConnector\Providers;

use WPIntegrations\MailerLiteConnector\Core\ServiceProvider;

defined( 'ABSPATH' ) || exit;

final class AppServiceProvider extends ServiceProvider {

	public function boot(): void {
		// No load_plugin_textdomain(): wordpress.org has served translations
		// automatically since WP 4.6.
	}
}
