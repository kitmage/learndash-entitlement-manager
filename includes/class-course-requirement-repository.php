<?php
namespace Aspen\LearnDashEntitlements;

defined( 'ABSPATH' ) || exit;

/** Stores and reads the optional per-course FluentCRM authorization rule. */
class Course_Requirement_Repository {
	const ENABLED = '_aspen_lde_fluentcrm_enabled';
	const TAG_IDS = '_aspen_lde_fluentcrm_tag_ids';
	const MATCH = '_aspen_lde_fluentcrm_match';

	private $cache = array();

	public function get( $course_id ) {
		$course_id = absint( $course_id );
		if ( ! isset( $this->cache[ $course_id ] ) ) {
			$this->cache[ $course_id ] = array(
				'enabled' => 'yes' === get_post_meta( $course_id, self::ENABLED, true ),
				'tag_ids' => self::normalize_tag_ids( get_post_meta( $course_id, self::TAG_IDS, true ) ),
				'match'   => self::normalize_match( get_post_meta( $course_id, self::MATCH, true ) ),
			);
		}
		return $this->cache[ $course_id ];
	}

	public function save( $course_id, $enabled, $tag_ids, $match ) {
		update_post_meta( $course_id, self::ENABLED, $enabled ? 'yes' : 'no' );
		update_post_meta( $course_id, self::TAG_IDS, self::normalize_tag_ids( $tag_ids ) );
		update_post_meta( $course_id, self::MATCH, self::normalize_match( $match ) );
		unset( $this->cache[ (int) $course_id ] );
	}

	public static function normalize_tag_ids( $tag_ids ) {
		if ( ! is_array( $tag_ids ) ) { return array(); }
		$tag_ids = array_filter( array_map( 'absint', $tag_ids ) );
		return array_values( array_unique( $tag_ids ) );
	}

	public static function normalize_match( $match ) {
		return 'any' === $match ? 'any' : 'all';
	}
}
