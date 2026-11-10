<?php
/**
 * Base trigger: listens to a WordPress event and hands a normalized payload to
 * the FlowRunner.
 *
 * @package WPIntegrations\MailerLiteConnector
 */

namespace WPIntegrations\MailerLiteConnector\Integrations;

defined( 'ABSPATH' ) || exit;

abstract class Trigger {

	/** @var FlowRunner */
	protected $runner;

	public function __construct( FlowRunner $runner ) {
		$this->runner = $runner;
	}

	/** Unique trigger key, e.g. 'elementor'. */
	abstract public function key(): string;

	/** Human label for the admin UI. */
	abstract public function label(): string;

	/** Attach hooks. Called on init when the source plugin is active. */
	abstract public function register(): void;

	/** Whether the source plugin is available on this site. */
	abstract public function available(): bool;

	/**
	 * Sources discoverable before any submission, with the fields each sends.
	 * An empty list makes the UI fall back to recently seen shapes.
	 *
	 * @param bool $refresh Bypass any discovery cache.
	 * @return array[] Each: id, name, fields[] of {id, label, type}, plus optional context keys.
	 */
	public function sources( bool $refresh = false ): array {
		return array();
	}

	/**
	 * Hand a normalized submission to the engine.
	 *
	 * @param string|string[] $source  Source id within this trigger, or a list of
	 *                                 aliases for it (first = canonical id).
	 * @param array           $fields  field_id => scalar value.
	 */
	protected function dispatch( $source, array $fields ): void {
		$this->runner->handle( $this->key(), $source, $fields );
	}

	/**
	 * Sanitize a submitted value, keeping lists as lists.
	 *
	 * @param mixed $value Raw value.
	 * @return string|string[]
	 */
	protected function clean( $value ) {
		if ( is_array( $value ) ) {
			return array_values( array_map( 'sanitize_text_field', array_map( 'strval', array_filter( $value, 'is_scalar' ) ) ) );
		}
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	/**
	 * Record a submission's shape, so the UI can offer source names and field
	 * ids without the user typing them.
	 *
	 * @param string   $source    Source id.
	 * @param string[] $field_ids Submitted field ids.
	 * @param string   $label     Human-readable name, if it differs from the id.
	 */
	protected function remember_shape( string $source, array $field_ids, string $label = '' ): void {
		$shapes = (array) get_option( 'wpi_mlite_recent_shapes', array() );

		$shapes[ $this->key() . '|' . $source ] = array(
			'trigger' => $this->key(),
			'source'  => $source,
			'label'   => sanitize_text_field( $label ),
			'fields'  => array_values( array_map( 'sanitize_text_field', $field_ids ) ),
			'seen_at' => time(),
		);

		// Keep only the 20 newest shapes.
		if ( count( $shapes ) > 20 ) {
			uasort( $shapes, static fn( $a, $b ) => $b['seen_at'] <=> $a['seen_at'] );
			$shapes = array_slice( $shapes, 0, 20, true );
		}

		update_option( 'wpi_mlite_recent_shapes', $shapes, false );
	}
}
