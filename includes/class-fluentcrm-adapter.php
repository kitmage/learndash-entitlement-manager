<?php
namespace Aspen\LearnDashEntitlements;

defined( 'ABSPATH' ) || exit;

/** Read-only boundary around FluentCRM's public in-process PHP API. */
class FluentCRM_Adapter {
	private $contacts = array();
	private $tags;
	private $api_factory;

	public function __construct( $api_factory = null ) { $this->api_factory = $api_factory; }

	public function available() { return is_callable( $this->api_factory ) || function_exists( 'FluentCrmApi' ); }

	private function api( $resource ) {
		if ( is_callable( $this->api_factory ) ) { return call_user_func( $this->api_factory, $resource ); }
		return function_exists( 'FluentCrmApi' ) ? FluentCrmApi( $resource ) : null;
	}

	public function tags() {
		if ( null !== $this->tags ) { return $this->tags; }
		$this->tags = array();
		try { $api = $this->api( 'tags' ); } catch ( \Throwable $exception ) { return $this->tags; }
		if ( ! $api || ! is_callable( array( $api, 'all' ) ) ) { return $this->tags; }
		try { $records = $api->all(); } catch ( \Throwable $exception ) { return $this->tags; }
		if ( is_object( $records ) && is_callable( array( $records, 'all' ) ) ) { $records = $records->all(); }
		if ( ! is_iterable( $records ) ) { return $this->tags; }
		foreach ( $records as $tag ) {
			$id = absint( is_object( $tag ) ? $tag->id : ( isset( $tag['id'] ) ? $tag['id'] : 0 ) );
			$title = is_object( $tag ) ? ( isset( $tag->title ) ? $tag->title : '' ) : ( isset( $tag['title'] ) ? $tag['title'] : '' );
			if ( $id ) { $this->tags[ $id ] = sanitize_text_field( $title ); }
		}
		return $this->tags;
	}

	public function contact( $user_id ) {
		$user_id = absint( $user_id );
		if ( array_key_exists( $user_id, $this->contacts ) ) { return $this->contacts[ $user_id ]; }
		try { $api = $this->api( 'contacts' ); } catch ( \Throwable $exception ) { $api = null; }
		try { $this->contacts[ $user_id ] = $api && is_callable( array( $api, 'getContactByUserRef' ) ) ? $api->getContactByUserRef( $user_id ) : null; } catch ( \Throwable $exception ) { $this->contacts[ $user_id ] = null; }
		return $this->contacts[ $user_id ];
	}

	public function contact_matches( $contact, array $required, $match ) {
		if ( ! $contact || ! is_callable( array( $contact, 'hasAnyTagId' ) ) ) { return false; }
		if ( 'any' === $match ) { return (bool) $contact->hasAnyTagId( $required ); }
		foreach ( $required as $tag_id ) {
			if ( ! $contact->hasAnyTagId( array( $tag_id ) ) ) { return false; }
		}
		return true;
	}
}
