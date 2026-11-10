<?php
/**
 * Application configuration.
 *
 * @package WPIntegrations\MailerLiteConnector
 */

defined( 'ABSPATH' ) || exit;

return array(
	'providers' => array(
		WPIntegrations\MailerLiteConnector\Providers\AppServiceProvider::class,
		WPIntegrations\MailerLiteConnector\Providers\DatabaseServiceProvider::class,
		WPIntegrations\MailerLiteConnector\Providers\IntegrationServiceProvider::class,
		WPIntegrations\MailerLiteConnector\Providers\RetentionServiceProvider::class,
		WPIntegrations\MailerLiteConnector\Providers\RestServiceProvider::class,
		WPIntegrations\MailerLiteConnector\Providers\AdminServiceProvider::class,
	),
);
