<?php

class WP_UnitTest_Factory_For_Blog extends WP_UnitTest_Factory_For_Thing {

	function __construct( $factory = null ) {
		global $current_site, $base;
		parent::__construct( $factory );
		$this->default_generation_definitions = array(
			'domain' => $current_site->domain,
			'path' => new WP_UnitTest_Generator_Sequence( $base . 'testpath%s' ),
			'title' => new WP_UnitTest_Generator_Sequence( 'Site %s' ),
			'site_id' => $current_site->id,
		);
	}

	function create_object( $args ) {
		$meta = isset( $args['meta'] ) ? $args['meta'] : array();
		$user_id = isset( $args['user_id'] ) ? $args['user_id'] : get_current_user_id();
		return wpmu_create_blog( $args['domain'], $args['path'], $args['title'], $user_id, $meta, $args['site_id'] );
	}

	function update_object( $blog_id, $fields ) {}

	function get_object_by_id( $blog_id ) {
		return get_blog_details( $blog_id, false );
	}
}