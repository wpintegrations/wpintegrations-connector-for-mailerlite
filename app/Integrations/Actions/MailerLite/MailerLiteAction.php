<?php
/**
 * Sends form submissions to MailerLite as subscribers, optionally adding them
 * to groups.
 *
 * One site-wide API token covers delivery and the group picker. MailerLite
 * authenticates with a Bearer token; there is no OAuth.
 *
 * The email identifies the subscriber; every other mapped target is sent as
 * the MailerLite field of that key, in the type the field takes. The field
 * list is the connected account's own, custom fields included.
 *
 * @package WPIntegrations\MailerLiteConnector
 */

namespace WPIntegrations\MailerLiteConnector\Integrations\Actions\MailerLite;

use WPIntegrations\MailerLiteConnector\Delivery\Result;
use WPIntegrations\MailerLiteConnector\Integrations\Action;
use WPIntegrations\MailerLiteConnector\Services\Http;

defined( 'ABSPATH' ) || exit;

final class MailerLiteAction extends Action {

	const BASE_URL = 'https://connect.mailerlite.com/api';

	/** Page size for GET /groups, and the page cap (MailerLite allows 1,000 groups). */
	const GROUPS_PAGE_SIZE = 100;
	const GROUPS_MAX_PAGES = 10;

	/** MailerLite's limit for a text field. */
	const TEXT_MAX = 1024;

	/** Page size for GET /fields, and the page cap. */
	const FIELDS_PAGE_SIZE = 100;
	const FIELDS_MAX_PAGES = 5;

	/**
	 * Mapping target for a consent checkbox: a submission where it is empty
	 * is not sent at all.
	 */
	const CONSENT_TARGET = '_consent';

	/** The fields every MailerLite account has, used until the account's own list is read. */
	const DEFAULT_FIELDS = array(
		'name'      => 'Name',
		'last_name' => 'Last name',
		'company'   => 'Company',
		'country'   => 'Country',
		'city'      => 'City',
		'phone'     => 'Phone',
		'state'     => 'State',
		'z_i_p'     => 'Zip',
	);

	/** @var Http */
	private $http;

	public function __construct( Http $http ) {
		$this->http = $http;
	}

	public function key(): string {
		return 'mailerlite';
	}

	public function label(): string {
		return __( 'MailerLite', 'wpintegrations-connector-for-mailerlite' );
	}

	public function global_fields(): array {
		return array(
			array(
				'key'    => 'api_key',
				'label'  => __( 'MailerLite API token', 'wpintegrations-connector-for-mailerlite' ),
				'secret' => true,
			),
		);
	}

	/**
	 * The email, every field of the connected account — the built-in ones and
	 * the account's own custom fields — and the consent gate.
	 */
	public function target_fields(): array {
		$targets = array(
			array(
				'key'      => 'email',
				'label'    => __( 'Email', 'wpintegrations-connector-for-mailerlite' ),
				'required' => true,
			),
		);

		foreach ( $this->account_fields() as $field ) {
			$targets[] = array(
				'key'   => $field['key'],
				'label' => 'name' === $field['key'] ? __( 'Name (first name)', 'wpintegrations-connector-for-mailerlite' ) : $field['name'],
			);
		}

		$targets[] = array(
			'key'   => self::CONSENT_TARGET,
			'label' => __( 'Consent — only add the person when this is ticked', 'wpintegrations-connector-for-mailerlite' ),
		);

		return $targets;
	}

	/**
	 * The email identifies the subscriber, so it must be mapped.
	 */
	public function validate_flow( array $field_map, array $settings ): ?string {
		foreach ( $field_map as $pair ) {
			if ( 'email' === strtolower( (string) $pair['target'] ) ) {
				return null;
			}
		}
		return __( 'Map a form field to Email — MailerLite identifies the subscriber by it.', 'wpintegrations-connector-for-mailerlite' );
	}

	/**
	 * Per-integration settings. Nothing is declared that execute() does not send.
	 */
	public function flow_fields(): array {
		return array(
			array(
				'key'            => 'group_ids',
				'label'          => __( 'Add to groups', 'wpintegrations-connector-for-mailerlite' ),
				'help'           => __( 'Every subscriber from this form joins these groups. Groups they are already in are kept.', 'wpintegrations-connector-for-mailerlite' ),
				'type'           => 'select',
				'multiple'       => true,
				'options_source' => 'groups',
			),
			array(
				'key'     => 'status',
				'label'   => __( 'New subscribers', 'wpintegrations-connector-for-mailerlite' ),
				'help'    => __( 'For people not yet in MailerLite; existing subscribers keep their status. Unconfirmed needs “Double opt-in for API and integrations” turned on in MailerLite (Account settings → Subscribe settings), which then emails the confirmation link.', 'wpintegrations-connector-for-mailerlite' ),
				'type'    => 'select',
				'default' => '',
				'options' => array(
					array(
						'value' => '',
						'label' => __( 'Follow my MailerLite double opt-in setting', 'wpintegrations-connector-for-mailerlite' ),
					),
					array(
						'value' => 'active',
						'label' => __( 'Active — subscribed right away', 'wpintegrations-connector-for-mailerlite' ),
					),
					array(
						'value' => 'unconfirmed',
						'label' => __( 'Unconfirmed — they confirm by email first (double opt-in)', 'wpintegrations-connector-for-mailerlite' ),
					),
				),
			),
			array(
				'key'     => 'update_existing',
				'label'   => __( 'Update subscribers that already exist in MailerLite', 'wpintegrations-connector-for-mailerlite' ),
				'help'    => __( 'Off: someone already in MailerLite is left exactly as they are.', 'wpintegrations-connector-for-mailerlite' ),
				'type'    => 'boolean',
				'default' => '1',
			),
			array(
				'key'     => 'resubscribe',
				'label'   => __( 'Resubscribe people who had unsubscribed', 'wpintegrations-connector-for-mailerlite' ),
				'help'    => __( 'Only turn this on if the form clearly asks for consent to email them.', 'wpintegrations-connector-for-mailerlite' ),
				'type'    => 'boolean',
				'default' => '',
				'below'   => true,
			),
		);
	}

	/**
	 * Check the stored token by reading one group, which writes nothing.
	 */
	public function test_connection( array $credentials ): Result {
		$key = $this->credential( 'api_key' );
		if ( '' === $key ) {
			return Result::failure( __( 'Save your MailerLite API token first.', 'wpintegrations-connector-for-mailerlite' ) );
		}

		$response = $this->http->get( self::BASE_URL . '/groups?limit=1', $this->headers( $key ) );
		if ( ! $response->ok ) {
			return $this->failure( $response, null, null );
		}

		return Result::success( __( 'Connected to MailerLite.', 'wpintegrations-connector-for-mailerlite' ) );
	}

	public function discover_options( string $key, array $settings ): array {
		$options = 'groups' === $key ? $this->groups() : null;
		return null === $options ? array() : $options;
	}

	public function execute( object $flow, array $payload ): Result {
		$key = $this->credential( 'api_key' );
		if ( '' === $key ) {
			return Result::failure( __( 'MailerLite is not connected — add your API token under Settings.', 'wpintegrations-connector-for-mailerlite' ) );
		}

		// The email is a top-level property; every other mapped target is a
		// field of that key.
		$email   = '';
		$fields  = array();
		$consent = null;
		$types   = $this->field_types();
		foreach ( $this->map_fields( $flow, $payload ) as $target => $value ) {
			$target = strtolower( trim( (string) $target ) );
			$value  = trim( (string) $value );
			if ( 'email' === $target ) {
				$email = $value;
			} elseif ( self::CONSENT_TARGET === $target ) {
				$consent = $value;
			} elseif ( '' !== $target && '' !== $value ) {
				$typed = $this->typed( $value, $types[ $target ] ?? 'text' );
				if ( null !== $typed ) {
					$fields[ $target ] = $typed;
				}
			}
		}

		// A mapped consent box left empty means no sign-up. An unmapped one
		// is no gate at all, so map_fields() never offering it is fine.
		if ( $this->maps_consent( $flow ) && ( null === $consent || '' === $consent ) ) {
			return Result::success( __( 'Not sent: the visitor did not tick the consent box.', 'wpintegrations-connector-for-mailerlite' ) );
		}

		// The mapping has the email, but the visitor may have left it empty.
		if ( '' === $email ) {
			return Result::failure( __( 'This submission has no email, so MailerLite cannot identify the subscriber. Nothing was sent.', 'wpintegrations-connector-for-mailerlite' ) );
		}
		if ( ! is_email( $email ) ) {
			/* translators: %s: the submitted email address. */
			return Result::failure( sprintf( __( '“%s” is not a valid email address, so the subscriber was not sent.', 'wpintegrations-connector-for-mailerlite' ), sanitize_text_field( $email ) ) );
		}
		$email = sanitize_email( $email );

		$settings = (array) ( $flow->settings ?? array() );
		$groups   = array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', (array) ( $settings['group_ids'] ?? array() ) ) ), 'strlen' ) );

		// MailerLite always updates a known email, so leaving one untouched
		// means asking first.
		$known = null;
		if ( empty( $settings['update_existing'] ) ) {
			$known = $this->subscriber_exists( $email, $key );
			if ( $known instanceof Result ) {
				return $known;
			}
			if ( $known ) {
				return Result::success( __( 'Subscriber already in MailerLite — left unchanged, since this integration does not update existing subscribers.', 'wpintegrations-connector-for-mailerlite' ) );
			}
		}

		$body = array( 'email' => $email );
		if ( $fields ) {
			$body['fields'] = $fields;
		}
		if ( $groups ) {
			$body['groups'] = $groups;
		}
		if ( ! empty( $settings['resubscribe'] ) ) {
			$body['resubscribe'] = true;
		}
		// The status is for new subscribers only: someone already on the list
		// keeps theirs, so an active subscriber is never sent back to unconfirmed.
		$status = (string) ( $settings['status'] ?? '' );
		$status = in_array( $status, array( 'active', 'unconfirmed' ), true ) ? $status : '';
		if ( '' !== $status && null === $known ) {
			$known = $this->subscriber_exists( $email, $key );
			if ( $known instanceof Result ) {
				return $known;
			}
		}
		if ( '' !== $status && ! $known ) {
			$body['status'] = $status;
		}

		$endpoint        = self::BASE_URL . '/subscribers';
		$request_excerpt = array(
			'endpoint' => $endpoint,
			'payload'  => $body,
		);
		$response        = $this->http->post_json( $endpoint, $body, $this->headers( $key ) );
		$logged          = array(
			'code' => $response->code,
			'body' => $response->body,
		);

		if ( 201 === $response->code ) {
			return Result::success( __( 'Subscriber added to MailerLite.', 'wpintegrations-connector-for-mailerlite' ), $request_excerpt, $logged );
		}
		if ( $response->ok ) {
			return Result::success( __( 'Subscriber updated in MailerLite.', 'wpintegrations-connector-for-mailerlite' ), $request_excerpt, $logged );
		}

		return $this->failure( $response, $request_excerpt, $logged );
	}

	/**
	 * Whether the integration maps a form field to the consent gate.
	 *
	 * @param object $flow Flow row.
	 */
	private function maps_consent( object $flow ): bool {
		foreach ( (array) $flow->field_map as $pair ) {
			if ( self::CONSENT_TARGET === (string) ( $pair['target'] ?? '' ) && '' !== (string) ( $pair['source'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * A value in the shape the field takes: MailerLite's fields are text,
	 * number or date. Null when it cannot be one, so a bad value is left out
	 * instead of failing the whole sign-up.
	 *
	 * @return string|int|float|null
	 */
	private function typed( string $value, string $type ) {
		if ( 'number' === $type ) {
			$number = str_replace( array( ' ', ',' ), array( '', '.' ), $value );
			return is_numeric( $number ) ? $number + 0 : null;
		}
		if ( 'date' === $type ) {
			$time = strtotime( $value );
			return false === $time ? null : gmdate( 'Y-m-d', $time );
		}
		return mb_substr( sanitize_text_field( $value ), 0, self::TEXT_MAX );
	}

	/**
	 * The account's fields — built-in and custom — or the built-in ones when
	 * the account cannot be read (no token yet, or MailerLite unreachable).
	 *
	 * @return array<int, array{key:string, name:string, type:string}>
	 */
	private function account_fields(): array {
		$key = $this->credential( 'api_key' );
		if ( '' !== $key ) {
			$cache_key = $this->cache_key( 'fields', $key );
			$cached    = get_transient( $cache_key );
			if ( is_array( $cached ) && $cached ) {
				return $cached;
			}

			$fields = array();
			for ( $page = 1; $page <= self::FIELDS_MAX_PAGES; $page++ ) {
				$response = $this->http->get(
					add_query_arg(
						array(
							'limit' => self::FIELDS_PAGE_SIZE,
							'page'  => $page,
						),
						self::BASE_URL . '/fields'
					),
					$this->headers( $key )
				);
				if ( ! $response->ok || ! is_array( $response->body ) ) {
					$fields = array();
					break;
				}
				$batch = (array) ( $response->body['data'] ?? array() );
				foreach ( $batch as $field ) {
					$field_key = sanitize_key( (string) ( $field['key'] ?? '' ) );
					if ( '' === $field_key || 'email' === $field_key ) {
						continue;
					}
					$fields[] = array(
						'key'  => $field_key,
						'name' => sanitize_text_field( (string) ( $field['name'] ?? $field_key ) ),
						'type' => in_array( $field['type'] ?? '', array( 'number', 'date' ), true ) ? $field['type'] : 'text',
					);
				}
				$last = (int) ( $response->body['meta']['last_page'] ?? 0 );
				if ( count( $batch ) < self::FIELDS_PAGE_SIZE || ( $last && $page >= $last ) ) {
					break;
				}
			}

			if ( $fields ) {
				// Short-lived: a field added in MailerLite should show up soon.
				set_transient( $cache_key, $fields, 10 * MINUTE_IN_SECONDS );
				return $fields;
			}
		}

		$defaults = array();
		foreach ( self::DEFAULT_FIELDS as $field_key => $name ) {
			$defaults[] = array(
				'key'  => $field_key,
				'name' => $name,
				'type' => 'text',
			);
		}
		return $defaults;
	}

	/**
	 * Field key => type.
	 *
	 * @return array<string, string>
	 */
	private function field_types(): array {
		$types = array();
		foreach ( $this->account_fields() as $field ) {
			$types[ $field['key'] ] = $field['type'];
		}
		return $types;
	}

	/**
	 * Whether MailerLite already has a subscriber with this email.
	 *
	 * @return bool|Result A Result when MailerLite could not answer, so
	 *                     delivery stops rather than guessing.
	 */
	private function subscriber_exists( string $email, string $key ) {
		$response = $this->http->get( self::BASE_URL . '/subscribers/' . rawurlencode( $email ), $this->headers( $key ) );
		if ( $response->ok ) {
			return true;
		}
		if ( 404 === $response->code ) {
			return false;
		}
		return $this->failure(
			$response,
			array( 'endpoint' => self::BASE_URL . '/subscribers/{email}' ),
			array(
				'code' => $response->code,
				'body' => $response->body,
			)
		);
	}

	/**
	 * A failed call as a Result, worded from MailerLite's own reason.
	 *
	 * @param object     $response        Normalized response.
	 * @param array|null $request_excerpt Logged request.
	 * @param array|null $logged          Logged response.
	 */
	private function failure( object $response, $request_excerpt, $logged ): Result {
		if ( 0 === $response->code ) {
			/* translators: %s: transport error message. */
			$message = sprintf( __( 'Could not reach MailerLite: %s', 'wpintegrations-connector-for-mailerlite' ), $response->error );
		} elseif ( 401 === $response->code ) {
			$message = __( 'MailerLite rejected the API token. Check that you copied the whole token, and that it has not been deleted under Integrations → MailerLite API.', 'wpintegrations-connector-for-mailerlite' );
		} elseif ( 429 === $response->code ) {
			$message = __( 'MailerLite’s rate limit (120 requests a minute) was reached, so this submission was not sent.', 'wpintegrations-connector-for-mailerlite' );
		} else {
			$message = $this->error_message( $response );
		}

		return Result::failure( $message, $request_excerpt, $logged );
	}

	/**
	 * The account's groups as select options, all pages.
	 *
	 * @return array<int, array{value:string, label:string}>|null
	 */
	private function groups(): ?array {
		$key = $this->credential( 'api_key' );
		if ( '' === $key ) {
			return null;
		}

		$cache_key = $this->cache_key( 'groups', $key );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && $cached ) {
			return $cached;
		}

		$options = array();
		for ( $page = 1; $page <= self::GROUPS_MAX_PAGES; $page++ ) {
			$url = add_query_arg(
				array(
					'limit' => self::GROUPS_PAGE_SIZE,
					'page'  => $page,
					'sort'  => 'name',
				),
				self::BASE_URL . '/groups'
			);

			$response = $this->http->get( $url, $this->headers( $key ) );
			if ( ! $response->ok || ! is_array( $response->body ) ) {
				return $options ? $options : null;
			}

			$batch = (array) ( $response->body['data'] ?? array() );
			foreach ( $batch as $group ) {
				$options[] = array(
					'value' => sanitize_text_field( (string) ( $group['id'] ?? '' ) ),
					'label' => sanitize_text_field( (string) ( $group['name'] ?? '' ) ),
				);
			}

			$last = (int) ( $response->body['meta']['last_page'] ?? 0 );
			if ( count( $batch ) < self::GROUPS_PAGE_SIZE || ( $last && $page >= $last ) ) {
				break;
			}
		}

		// Short-lived: a group made in MailerLite a minute ago should show up soon.
		set_transient( $cache_key, $options, 5 * MINUTE_IN_SECONDS );
		return $options;
	}

	/**
	 * Cache keys carry a hash of the token, so switching accounts never serves
	 * the old account's groups.
	 */
	private function cache_key( string $kind, string $api_key ): string {
		return 'wpi_mlite_api_' . $kind . '_' . substr( md5( $api_key ), 0, 12 );
	}

	/**
	 * @return array<string, string>
	 */
	private function headers( string $api_key ): array {
		return array(
			'Authorization' => 'Bearer ' . $api_key,
			'Accept'        => 'application/json',
		);
	}

	/**
	 * MailerLite's own reason. A 422 lists each invalid field; the first one
	 * is what the visitor or the mapping got wrong.
	 *
	 * @param object $response Normalized response.
	 */
	private function error_message( object $response ): string {
		$detail = '';
		if ( is_array( $response->body ) ) {
			$detail = (string) ( $response->body['message'] ?? '' );
			foreach ( (array) ( $response->body['errors'] ?? array() ) as $field => $errors ) {
				$first  = is_array( $errors ) ? (string) reset( $errors ) : (string) $errors;
				$detail = '' !== $first ? $first . ' (' . $field . ')' : $detail;
				break;
			}
			if ( '' === $detail ) {
				$detail = (string) wp_json_encode( $response->body );
			}
		} else {
			$detail = (string) $response->body;
		}
		/* translators: 1: HTTP status code, 2: error detail from MailerLite. */
		return sprintf( __( 'MailerLite error (HTTP %1$d): %2$s', 'wpintegrations-connector-for-mailerlite' ), $response->code, mb_substr( sanitize_text_field( $detail ), 0, 300 ) );
	}
}
