<?php
/**
 * Tests for user-query module.
 *
 * @package performance-lab
 * @group perflab_user_query_cache
 *
 * Paul: add @ticket for each function.
 */

class User_Query_Cache_Tests extends WP_UnitTestCase {

	const SITE_ID = 1;

	protected $wp_user_query_cache = null;

	protected function set_up() {
		$this->wp_user_query_cache = new WP_User_Query_Cache();
	}

	public static function call_method( $obj, $name, array $args ) {
		$class  = new \ReflectionClass( $obj );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );
		return $method->invokeArgs( $obj, $args );
	}

	/**
	 * @covers site_cache_key
	 */
	/**
	 * Paul
	 * Regular expression would be better here instead of assertsEquals.
	 */
	public function test_site_cache_key() {
		$this->assertEquals(
			'site-' . self::SITE_ID . '-last_changed',
			self::call_method(
				$this->wp_user_query_cache,
				'site_cache_key',
				array( self::SITE_ID )
			)
		);
	}

	/**
	 * For invalid user_id.
	 *
	 * @covers get_user_site_ids
	 */
	public function test_get_user_site_ids_non_numeric_user() {
		// when the user_id is not a number.
		$this->assertEquals(
			array(),
			$this->wp_user_query_cache->get_user_site_ids( 'wrong_user_id_data_type' )
		);
	}

	/**
	 * For non-logged-in user.
	 *
	 * @covers get_user_site_ids
	 */
	public function test_get_user_site_ids_no_login() {
		$this->assertEquals(
			array(),
			$this->wp_user_query_cache->get_user_site_ids( 1 )
		);
	}

	/**
	 * For single site.
	 *
	 * @covers get_user_site_ids
	 * @group ms-excluded
	 */
	public function test_get_user_site_ids_single_site() {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$this->assertEquals(
			array( 1 ),
			$this->wp_user_query_cache->get_user_site_ids( $user_id )
		);
	}

	/**
	 * For multisite.
	 *
	 * @covers get_user_site_ids
	 * @group ms-required
	 */
	public function test_get_user_site_ids_multisite() {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		// Create sample subsites.
		$subsite_id_2 = $this->factory->blog->create( array( 'user_id' => $user_id ) );
		$subsite_id_3 = $this->factory->blog->create( array( 'user_id' => $user_id ) );

		$this->assertEquals(
			array( get_current_blog_id(), $subsite_id_2, $subsite_id_3 ),
			$this->wp_user_query_cache->get_user_site_ids( $user_id )
		);
	}

	/**
	 * @covers update_last_change
	 */
	public function test_update_last_change() {
		$this->assertTrue( self::call_method( $this->wp_user_query_cache, 'update_last_change', array( 'random_cache_key' ) ) );
	}

	/**
	 * @covers clear_user
	 */
	public function test_clear_user() {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$site_ids = $this->wp_user_query_cache->get_user_site_ids( $user_id );
		$this->assertTrue( self::call_method( $this->wp_user_query_cache, 'update_last_change', array( 'last_changed' ) ) );
	}

	/**
	 * @covers clear_site
	 * @group ms-required
	 */
	public function test_clear_site_multisite() {
		$wp_site   = get_blog_details();
		$cache_key = self::call_method( $this->wp_user_query_cache, 'site_cache_key', array( $wp_site->id ) );

		$this->assertEquals(
			'site-' . $wp_site->id . '-last_changed',
			$cache_key
		);

		$this->assertTrue( self::call_method( $this->wp_user_query_cache, 'update_last_change', array( $cache_key ) ) );
		$this->assertNull( $this->wp_user_query_cache->clear_site( $wp_site ) );
	}

	/**
	 * @covers clear_site
	 * @group ms-excluded
	 */
	public function test_clear_site_single_site() {

		$cache_key = self::call_method( $this->wp_user_query_cache, 'site_cache_key', array( self::SITE_ID ) );

		$this->assertEquals(
			'site-' . self::SITE_ID . '-last_changed',
			$cache_key
		);

		$this->assertTrue( self::call_method( $this->wp_user_query_cache, 'update_last_change', array( $cache_key ) ) );
		$this->assertNull( $this->wp_user_query_cache->clear_site( self::SITE_ID ) );
	}

	/**
	 * @covers clear_user
	 * @covers after_password_reset
	 * @covers retrieve_password_key
	 * @covers updated_user_meta
	 *
	 * @group ms-excluded
	 */
	public function test_clear_user_hooks_single_site() {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );
		$user = wp_get_current_user();

		$actions = array(
			'user_register'          => array(
				$user_id,
			),
			'profile_update'         => array(
				$user_id,
				$user,
			),
			'register_new_user'      => array(
				$user_id,
			),
			'edit_user_created_user' => array(
				$user_id,
			),
			'clean_user_cache'       => array(
				$user_id,
			),
			'after_password_reset'   => array(
				$user,
				'new_password',
			),
		);

		$this->execute_hooks( get_current_blog_id(), $actions );

		/*
		 *
		 * Password reset.
		 *
		 */
		$pwd_reset_key = get_password_reset_key( $user );

		$actions = array(
			'retrieve_password_key' => array(
				$user->data->user_login,
				$pwd_reset_key,
			),
		);

		$this->execute_hooks( get_current_blog_id(), $actions );

		/*
		 *
		 * Update user meta.
		 *
		 */
		$meta_id = update_user_meta( $user_id, 'test_meta_key', 'test_meta_value' );

		$actions = array(
			'add_user_meta'     => array(
				$user_id,
			),
			'updated_user_meta' => array(
				$meta_id,
				$user_id,
			),
			'deleted_user_meta' => array(
				$meta_id,
				$user_id,
			),
		);

		$this->execute_hooks( get_current_blog_id(), $actions );

		/*
		 *
		 * User deleted.
		 *
		 */
		$actions = array(
			'delete_user' => array(
				$user_id,
			),
		);

		$this->execute_hooks( get_current_blog_id(), $actions );
	}

	/**
	 * @covers clear_user
	 * @covers add_user_to_blog
	 * @covers remove_user_from_blog
	 * @group ms-required
	 */
	public function test_clear_user_hooks_multi_site() {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );
		$user = wp_get_current_user();

		$actions = array(
			'make_spam_user'   => array(
				$user_id,
			),
			'wpmu_delete_user' => array(
				$user_id,
				$user,
			),
		);

		$this->execute_hooks( get_current_blog_id(), $actions );

		// The following actions are needs to be run after adding a new sub-site.
		$subsite_id_2 = $this->factory->blog->create( array( 'user_id' => $user_id ) );

		$actions = array(
			'wp_insert_site'        => array(
				$subsite_id_2,
			),
			'add_user_to_blog'      => array(
				$user_id,
				'subscriber',
				$subsite_id_2,
			),
			'remove_user_from_blog' => array(
				$user_id,
				$subsite_id_2,
			),
			'wp_delete_site'        => array(
				$subsite_id_2,
			),
		);
		$this->execute_hooks( $subsite_id_2, $actions );
	}

	/**
	 * @coversNothing
	 *
	 * A common code for testing clear_user for single and multi-sites.
	 *
	 * @param int   $site_id A blog ID.
	 * @param array $actions An array of actions to test.
	 *
	 * @return void
	 */
	public function execute_hooks( $site_id, $actions ) {
		$cache_key = self::call_method( $this->wp_user_query_cache, 'site_cache_key', array( $site_id ) );

		foreach ( $actions as $action => $args ) {
			$prev_cache = wp_cache_get( $cache_key, 'users' );
			do_action( $action, ...$args );
			$this->assertFalse( wp_cache_get( $cache_key, 'users' ) === $prev_cache ); // we want to assert that the cache has changed.
		}
	}

	/**
	 * WIP
	 *
	 * @covers users_pre_query
	 */
	public function test_users_pre_query() {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );
		var_dump( $this->wp_user_query_cache->cache );
		get_user_by( 'id', $user_id );
		var_dump( $this->wp_user_query_cache->cache );
		get_user_by( 'id', $user_id );
		var_dump( $this->wp_user_query_cache->cache );
	}
}
