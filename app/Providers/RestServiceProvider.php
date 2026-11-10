<?php
/**
 * Registers REST routes from routes/api.php.
 *
 * @package WPIntegrations\MailerLiteConnector
 */

namespace WPIntegrations\MailerLiteConnector\Providers;

use WPIntegrations\MailerLiteConnector\Core\ServiceProvider;
use WPIntegrations\MailerLiteConnector\Http\Router;

defined( 'ABSPATH' ) || exit;

final class RestServiceProvider extends ServiceProvider {

	public function boot(): void {
		add_action(
			'rest_api_init',
			function () {
				$routes = require WPI_MLITE_PATH . 'routes/api.php';
				$routes( new Router( $this->app ) );
			}
		);
	}
}
