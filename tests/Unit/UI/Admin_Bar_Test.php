<?php

namespace Yoast\WP\Duplicate_Post\Tests\Unit\UI;

use Brain\Monkey;
use Mockery;
use WP_Admin_Bar;
use WP_Post;
use WP_Query;
use WP_Term;
use Yoast\WP\Duplicate_Post\Permissions_Helper;
use Yoast\WP\Duplicate_Post\Tests\Unit\TestCase;
use Yoast\WP\Duplicate_Post\UI\Admin_Bar;
use Yoast\WP\Duplicate_Post\UI\Asset_Manager;
use Yoast\WP\Duplicate_Post\UI\Link_Builder;

/**
 * Test the Admin_Bar class.
 */
class Admin_Bar_Test extends TestCase {

	/**
	 * Holds the object to create the action link to duplicate.
	 *
	 * @var Link_Builder
	 */
	protected $link_builder;

	/**
	 * Holds the permissions helper.
	 *
	 * @var Permissions_Helper
	 */
	protected $permissions_helper;

	/**
	 * Holds the asset manager.
	 *
	 * @var Asset_Manager
	 */
	protected $asset_manager;

	/**
	 * The instance.
	 *
	 * @var Admin_Bar
	 */
	protected $instance;

	/**
	 * Sets the instance.
	 */
	protected function set_up() {
		parent::set_up();

		$this->link_builder       = Mockery::mock( Link_Builder::class );
		$this->permissions_helper = Mockery::mock( Permissions_Helper::class );
		$this->asset_manager      = Mockery::mock( Asset_Manager::class );

		$this->instance = Mockery::mock(
			Admin_Bar::class,
			[
				$this->link_builder,
				$this->permissions_helper,
				$this->asset_manager,
			]
		)->makePartial();
	}

	/**
	 * Tests if the needed attributes are set correctly.
	 *
	 * @covers \Yoast\WP\Duplicate_Post\UI\Admin_Bar::__construct
	 */
	public function test_constructor() {
		$this->assertInstanceOf(
			Link_Builder::class,
			$this->getPropertyValue( $this->instance, 'link_builder' )
		);

		$this->assertInstanceOf(
			Permissions_Helper::class,
			$this->getPropertyValue( $this->instance, 'permissions_helper' )
		);

		$this->assertInstanceOf(
			Asset_Manager::class,
			$this->getPropertyValue( $this->instance, 'asset_manager' )
		);
	}

	/**
	 * Tests the registration of the hooks.
	 *
	 * @covers \Yoast\WP\Duplicate_Post\UI\Admin_Bar::register_hooks
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_register_hooks() {
		$utils = Mockery::mock( 'alias:\Yoast\WP\Duplicate_Post\Utils' );

		$utils->expects( 'get_option' )
			->with( 'duplicate_post_show_link_in', 'adminbar' )
			->once()
			->andReturn( '1' );

		$this->instance->register_hooks();

		$this->assertNotFalse( \has_action( 'wp_before_admin_bar_render', [ $this->instance, 'admin_bar_render' ] ), 'Does not have expected wp_before_admin_bar_render action' );
		$this->assertNotFalse( \has_action( 'wp_enqueue_scripts', [ $this->instance, 'enqueue_styles' ] ), 'Does not have expected wp_enqueue_scripts action' );
		$this->assertNotFalse( \has_action( 'admin_enqueue_scripts', [ $this->instance, 'enqueue_styles' ] ), 'Does not have expected admin_enqueue_scripts action' );
	}

	/**
	 * Tests the admin_bar_render function when both links are displayed.
	 *
	 * @covers \Yoast\WP\Duplicate_Post\UI\Admin_Bar::admin_bar_render
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_admin_bar_render_successful_both() {
		$this->stubTranslationFunctions();

		global $wp_admin_bar;
		$wp_admin_bar      = Mockery::mock( WP_Admin_Bar::class );
		$post              = Mockery::mock( WP_Post::class );
		$post->post_status = 'publish';

		Monkey\Functions\expect( '\is_admin_bar_showing' )
			->andReturn( true );

		$this->instance
			->expects( 'get_current_post' )
			->andReturn( $post );

		$this->link_builder
			->expects( 'build_new_draft_link' )
			->with( $post )
			->twice();

		$this->link_builder
			->expects( 'build_rewrite_and_republish_link' )
			->with( $post );

		$utils = Mockery::mock( 'alias:\Yoast\WP\Duplicate_Post\Utils' );

		$utils->expects( 'get_option' )
			->with( 'duplicate_post_show_link', 'new_draft' )
			->once()
			->andReturn( '1' );

		$utils->expects( 'get_option' )
			->with( 'duplicate_post_show_link', 'rewrite_republish' )
			->once()
			->andReturn( '1' );

		$this->permissions_helper
			->expects( 'should_rewrite_and_republish_be_allowed' )
			->with( $post )
			->andReturnTrue();

		$wp_admin_bar
			->expects( 'add_menu' )
			->times( 3 );

		$this->instance->admin_bar_render();

		// Clean up after the test.
		unset( $GLOBALS['wp_admin_bar'] );
	}

	/**
	 * Tests the admin_bar_render function when only the "Copy to a new draft" link is displayed.
	 *
	 * @covers \Yoast\WP\Duplicate_Post\UI\Admin_Bar::admin_bar_render
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_admin_bar_render_successful_one() {
		$this->stubTranslationFunctions();

		global $wp_admin_bar;
		$wp_admin_bar      = Mockery::mock( WP_Admin_Bar::class );
		$post              = Mockery::mock( WP_Post::class );
		$post->post_status = 'pending';

		Monkey\Functions\expect( '\is_admin_bar_showing' )
			->andReturn( true );

		$this->instance
			->expects( 'get_current_post' )
			->andReturn( $post );

		$this->link_builder
			->expects( 'build_new_draft_link' )
			->with( $post );

		$this->link_builder
			->expects( 'build_rewrite_and_republish_link' )
			->with( $post )
			->never();

		$utils = Mockery::mock( 'alias:\Yoast\WP\Duplicate_Post\Utils' );

		$utils->expects( 'get_option' )
			->with( 'duplicate_post_show_link', 'new_draft' )
			->once()
			->andReturn( '1' );

		$utils->expects( 'get_option' )
			->with( 'duplicate_post_show_link', 'rewrite_republish' )
			->once()
			->andReturn( '0' );

		$wp_admin_bar
			->expects( 'add_menu' )
			->once();

		$this->instance->admin_bar_render();

		// Clean up after the test.
		unset( $GLOBALS['wp_admin_bar'] );
	}

	/**
	 * Tests the admin_bar_render function when the admin bar is not showing.
	 *
	 * @covers \Yoast\WP\Duplicate_Post\UI\Admin_Bar::admin_bar_render
	 */
	public function test_admin_bar_render_unsuccessful_no_admin_bar() {
		global $wp_admin_bar;
		$wp_admin_bar      = Mockery::mock( WP_Admin_Bar::class );
		$post              = Mockery::mock( WP_Post::class );
		$post->post_status = 'publish';

		Monkey\Functions\expect( '\is_admin_bar_showing' )
			->andReturn( false );

		$this->instance
			->expects( 'get_current_post' )
			->andReturn( $post )
			->never();

		$this->link_builder
			->expects( 'build_new_draft_link' )
			->with( $post )
			->never();

		$this->link_builder
			->expects( 'build_rewrite_and_republish_link' )
			->with( $post )
			->never();

		$wp_admin_bar
			->expects( 'add_menu' )
			->never();

		$this->instance->admin_bar_render();

		// Clean up after the test.
		unset( $GLOBALS['wp_admin_bar'] );
	}

	/**
	 * Tests the admin_bar_render function when no post is not showing.
	 *
	 * @covers \Yoast\WP\Duplicate_Post\UI\Admin_Bar::admin_bar_render
	 */
	public function test_admin_bar_render_unsuccessful_no_post() {
		global $wp_admin_bar;
		$wp_admin_bar      = Mockery::mock( WP_Admin_Bar::class );
		$post              = Mockery::mock( WP_Post::class );
		$post->post_status = 'publish';

		Monkey\Functions\expect( '\is_admin_bar_showing' )
			->andReturn( true );

		$this->instance
			->expects( 'get_current_post' )
			->andReturn( false );

		$this->link_builder
			->expects( 'build_new_draft_link' )
			->with( $post )
			->never();

		$this->link_builder
			->expects( 'build_rewrite_and_republish_link' )
			->with( $post )
			->never();

		$wp_admin_bar
			->expects( 'add_menu' )
			->never();

		$this->instance->admin_bar_render();

		// Clean up after the test.
		unset( $GLOBALS['wp_admin_bar'] );
	}

	/**
	 * Tests the enqueue_styles function when the style is enqueued.
	 *
	 * @covers \Yoast\WP\Duplicate_Post\UI\Admin_Bar::enqueue_styles
	 */
	public function test_enqueue_styles_successful() {
		$post              = Mockery::mock( WP_Post::class );
		$post->post_status = 'publish';

		Monkey\Functions\expect( '\is_admin_bar_showing' )
			->andReturn( true );

		$this->instance->expects( 'get_current_post' )
			->andReturn( $post );

		$this->asset_manager->expects( 'enqueue_styles' );

		$this->instance->enqueue_styles();
	}

	/**
	 * Tests the enqueue_styles function when the admin bar is not showing.
	 *
	 * @covers \Yoast\WP\Duplicate_Post\UI\Admin_Bar::enqueue_styles
	 */
	public function test_enqueue_styles_unsuccessful_no_admin_bar() {
		$post              = Mockery::mock( WP_Post::class );
		$post->post_status = 'publish';

		Monkey\Functions\expect( '\is_admin_bar_showing' )
			->andReturn( false );

		$this->instance->expects( 'get_current_post' )
			->andReturn( $post )
			->never();

		$this->asset_manager->expects( 'enqueue_styles' )
			->never();

		$this->instance->enqueue_styles();
	}

	/**
	 * Tests the enqueue_styles function when no post is not showing.
	 *
	 * @covers \Yoast\WP\Duplicate_Post\UI\Admin_Bar::enqueue_styles
	 */
	public function test_enqueue_styles_unsuccessful_no_post() {
		$post              = Mockery::mock( WP_Post::class );
		$post->post_status = 'publish';

		Monkey\Functions\expect( '\is_admin_bar_showing' )
			->andReturn( true );

		$this->instance->expects( 'get_current_post' )
			->andReturn( false );

		$this->asset_manager->expects( 'enqueue_styles' )
			->never();

		$this->instance->enqueue_styles();
	}

	/**
	 * Tests the get_current_post function when a post is returned in the backend.
	 *
	 * @covers \Yoast\WP\Duplicate_Post\UI\Admin_Bar::get_current_post
	 */
	public function test_get_current_post_successful_backend() {
		global $wp_the_query;
		$wp_the_query    = Mockery::mock( WP_Query::class );
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'post';

		Monkey\Functions\expect( '\is_admin' )
			->andReturn( true );

		Monkey\Functions\expect( '\get_post' )
			->andReturn( $post );

		$wp_the_query->expects( 'get_queried_object' )
			->never();

		$this->permissions_helper
			->expects( 'should_links_be_displayed' )
			->with( $post )
			->andReturnTrue();

		$this->permissions_helper
			->expects( 'is_edit_post_screen' )
			->andReturnTrue();

		$this->permissions_helper
			->expects( 'post_type_has_admin_bar' )
			->with( $post->post_type )
			->andReturnTrue();

		$this->assertSame( $post, $this->instance->get_current_post() );

		// Clean up after the test.
		unset( $GLOBALS['wp_the_query'] );
	}

	/**
	 * Tests the get_current_post function when a post is returned in the frontend.
	 *
	 * @covers \Yoast\WP\Duplicate_Post\UI\Admin_Bar::get_current_post
	 */
	public function test_get_current_post_successful_frontend() {
		global $wp_the_query;
		$wp_the_query    = Mockery::mock( WP_Query::class );
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'post';

		Monkey\Functions\expect( '\is_admin' )
			->andReturn( false );

		Monkey\Functions\expect( '\get_post' )
			->never();

		$wp_the_query
			->expects( 'get_queried_object' )
			->andReturn( $post );

		$this->permissions_helper
			->expects( 'should_links_be_displayed' )
			->with( $post )
			->andReturnTrue();

		$this->permissions_helper
			->expects( 'is_edit_post_screen' )
			->andReturnTrue();

		$this->permissions_helper
			->expects( 'post_type_has_admin_bar' )
			->with( $post->post_type )
			->andReturnTrue();

		$this->assertSame( $post, $this->instance->get_current_post() );

		// Clean up after the test.
		unset( $GLOBALS['wp_the_query'] );
	}

	/**
	 * Tests the get_current_post function when no post is returned in the backend.
	 *
	 * @covers \Yoast\WP\Duplicate_Post\UI\Admin_Bar::get_current_post
	 */
	public function test_get_current_post_unsuccessful_backend() {
		global $wp_the_query;
		$wp_the_query = Mockery::mock( WP_Query::class );
		$post         = null;

		Monkey\Functions\expect( '\is_admin' )
			->andReturn( true );

		Monkey\Functions\expect( '\get_post' )
			->andReturn( $post );

		$wp_the_query
			->expects( 'get_queried_object' )
			->never();

		$this->permissions_helper
			->expects( 'should_links_be_displayed' )
			->with( $post )
			->never();

		$this->permissions_helper
			->expects( 'is_edit_post_screen' )
			->never();

		$this->permissions_helper
			->expects( 'post_type_has_admin_bar' )
			->never();

		$this->assertFalse( $this->instance->get_current_post() );
		$this->assertTrue( Monkey\Filters\applied( 'duplicate_post_show_link' ) === 0 );

		// Clean up after the test.
		unset( $GLOBALS['wp_the_query'] );
	}

	/**
	 * Tests the get_current_post function when a non-post is returned in the frontend.
	 *
	 * @covers \Yoast\WP\Duplicate_Post\UI\Admin_Bar::get_current_post
	 */
	public function test_get_current_post_unsuccessful_frontend() {
		global $wp_the_query;
		$wp_the_query = Mockery::mock( WP_Query::class );
		$post         = Mockery::mock( WP_Term::class );

		/*
		 * Take note that the property being set is not a property declared on WP_Term,
		 * it just needs to be set to test a specific edge-case.
		 */
		$post->post_type = 'post';

		Monkey\Functions\expect( '\is_admin' )
			->andReturn( false );

		Monkey\Functions\expect( '\get_post' )
			->never();

		$wp_the_query
			->expects( 'get_queried_object' )
			->andReturn( $post );

		$this->permissions_helper
			->expects( 'should_links_be_displayed' )
			->with( $post )
			->never();

		$this->permissions_helper
			->expects( 'is_edit_post_screen' )
			->never();

		Monkey\Functions\expect( '\is_singular' )
			->never();

		$this->permissions_helper
			->expects( 'post_type_has_admin_bar' )
			->with( $post->post_type )
			->never();

		$this->assertFalse( $this->instance->get_current_post() );
		$this->assertTrue( Monkey\Filters\applied( 'duplicate_post_show_link' ) === 0 );

		// Clean up after the test.
		unset( $GLOBALS['wp_the_query'] );
	}

	/**
	 * Tests the get_current_post function when the link should not be displayed.
	 *
	 * @covers \Yoast\WP\Duplicate_Post\UI\Admin_Bar::get_current_post
	 */
	public function test_get_current_post_unsuccessful_should_not_be_displayed() {
		global $wp_the_query;
		$wp_the_query    = Mockery::mock( WP_Query::class );
		$post            = Mockery::mock( WP_Post::class );
		$post->post_type = 'post';

		Monkey\Functions\expect( '\is_admin' )
			->andReturn( true );

		Monkey\Functions\expect( '\get_post' )
			->andReturn( $post );

		$wp_the_query
			->expects( 'get_queried_object' )
			->never();

		$this->permissions_helper
			->expects( 'is_edit_post_screen' )
			->once()
			->andReturnTrue();

		Monkey\Functions\expect( '\is_singular' )
			->andReturn( false );

		$this->permissions_helper
			->expects( 'post_type_has_admin_bar' )
			->with( $post->post_type )
			->once()
			->andReturnTrue();

		$this->permissions_helper
			->expects( 'should_links_be_displayed' )
			->with( $post )
			->once()
			->andReturnFalse();

		$this->assertSame( false, $this->instance->get_current_post() );

		// Clean up after the test.
		unset( $GLOBALS['wp_the_query'] );
	}
}
