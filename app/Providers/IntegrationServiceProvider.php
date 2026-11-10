<?php
/**
 * Registers built-in triggers/actions and boots available triggers.
 *
 * @package WPIntegrations\MailerLiteConnector
 */

namespace WPIntegrations\MailerLiteConnector\Providers;

use WPIntegrations\MailerLiteConnector\Core\ServiceProvider;
use WPIntegrations\MailerLiteConnector\Integrations\Actions\MailerLite\MailerLiteAction;
use WPIntegrations\MailerLiteConnector\Integrations\Registry;
use WPIntegrations\MailerLiteConnector\Integrations\Triggers\ContactForm7\ContactForm7Trigger;
use WPIntegrations\MailerLiteConnector\Integrations\Triggers\Elementor\ElementorTrigger;
use WPIntegrations\MailerLiteConnector\Integrations\Triggers\FluentForms\FluentFormsTrigger;
use WPIntegrations\MailerLiteConnector\Integrations\Triggers\GravityForms\GravityFormsTrigger;
use WPIntegrations\MailerLiteConnector\Integrations\Triggers\WPForms\WPFormsTrigger;

defined( 'ABSPATH' ) || exit;

final class IntegrationServiceProvider extends ServiceProvider {

	public function boot(): void {
		$registry = $this->app->make( Registry::class );

		// Every supported form plugin is registered; only the active ones hook
		// in, and the editor offers only those.
		$registry->add_trigger( $this->app->make( ContactForm7Trigger::class ) );
		$registry->add_trigger( $this->app->make( ElementorTrigger::class ) );
		$registry->add_trigger( $this->app->make( WPFormsTrigger::class ) );
		$registry->add_trigger( $this->app->make( FluentFormsTrigger::class ) );
		$registry->add_trigger( $this->app->make( GravityFormsTrigger::class ) );
		$registry->add_action( $this->app->make( MailerLiteAction::class ) );

		/**
		 * Add-ons register additional triggers and actions here.
		 *
		 * @param Registry $registry The integration registry.
		 */
		do_action( 'wpi_mlite_register_integrations', $registry );

		add_action(
			'init',
			static function () use ( $registry ) {
				foreach ( $registry->triggers() as $trigger ) {
					if ( $trigger->available() ) {
						$trigger->register();
					}
				}
			}
		);
	}
}
