<?php defined('SYSPATH') OR die('Kohana bootstrap needs to be included before tests run');

/**
 * Tests View_PDF classs
 *
 * [!!] Note that there are significant test sequence dependencies in this test
 * case because of the underlying global state of DOMPDF and therefore of the
 * View_PDF class. When adding tests, take great care that you understand the
 * required sequence and global state context at any given point.
 *
 * @group pdfview
 * @group pdfview.core
 *
 * @package    PDFView
 * @category   Tests
 * @author     Andrew Coulton
 * @copyright  (c) 2012 Andrew Coulton
 * @license    http://kohanaframework.org/license
 */
class PDFView_ViewTest extends Unittest_TestCase
{
	protected static $old_modules = array();
	protected static $expect_dompdf_presence = FALSE;

	/**
	 * Setups the filesystem for test view files
	 *
	 * @return null
	 */
	public static function setupBeforeClass()
	{
		self::$old_modules = Kohana::modules();

		$new_modules = self::$old_modules+array(
			'test_views' => realpath(dirname(__FILE__).'/../test_data/')
		);
		Kohana::modules($new_modules);
	}

	/**
	 * Restores the module list
	 *
	 * @return null
	 */
	public static function teardownAfterClass()
	{
		Kohana::modules(self::$old_modules);
	}

	/**
	 * Verify the dompdf state as a precondition on every test
	 */
	public function setUp()
	{
		parent::setUp();
		$this->verify_dompdf_state();
	}

	/**
	 * Check whether DOMPDF is or is not loaded yet
	 */
	protected function verify_dompdf_state()
	{
		$state = self::$expect_dompdf_presence;
		$this->assertEquals($state, class_exists('DOMPDF', FALSE), "Verfiying whether DOMPDF class exists");
		$this->assertEquals($state, defined('DOMPDF_DIR'), "Verifying whether DOMPDF_DIR is defined");
	}

	/**
	 * Provider for test_exception_on_missing_view
	 *
	 * @return array
	 */
	public function provider_exception_on_missing_view()
	{
		return array(
			array('exists', FALSE),
			array('exists.css', FALSE),
			array('doesnt_exist', TRUE),
		);
	}

	/**
	 * Calling with an invalid view file throws an exception
	 *
	 * @dataProvider provider_exception_on_missing_view
	 */
	public function test_exception_on_missing_view($path, $expects_exception)
	{
		try
		{
			$view = new View_PDF($path);
			$this->assertSame(FALSE, $expects_exception);
		}
		catch(View_Exception $e)
		{
			$this->assertSame(TRUE, $expects_exception);
		}
	}

	/**
	 * The first call to factory initialises the default dompdf options
	 *
	 * [!!] This test must run before any other calls to View_PDF::factory
	 *
	 */
	public function test_factory_initialises_options_if_empty()
	{
		// Define a test value for ease
		$test = 'factorytest';

		// Verify that the default is returned initially (because option is not set)
		$this->assertEquals(
				$test,
				View_PDF::get_dompdf_option(View_PDF::DOMPDF_DEFAULT_PAPER_SIZE, $test),
				'Verify options are not already loaded');

		// Factory a view
		$view = View_PDF::factory('exists');

		// Verify that the default is not returned (because the option has been loaded)
		$this->assertNotEquals($test, View_PDF::get_dompdf_option(View_PDF::DOMPDF_DEFAULT_PAPER_SIZE, $test));
	}

	/**
	 * Tests that default property values are loaded from kohana config
	 *
	 */
	public function test_loads_default_dompdf_options_from_config()
	{
		// Set the Kohana Config to something unusual for testing
		$config = Kohana::$config->load('dompdf');
		$config['options'][View_PDF::DOMPDF_DEFAULT_PAPER_SIZE] = 'a5';

		// Check that the config is loaded
		View_PDF::load_default_options();
		$this->assertEquals('a5', View_PDF::get_dompdf_option(View_PDF::DOMPDF_DEFAULT_PAPER_SIZE));
	}

	/**
	 * Tests that the factory doesn't initialise options once they have been set
	 *
	 * @depends test_factory_initialisies_options_if_empty
	 * @depends test_loads_default_dompdf_options_from_config
	 */
	public function test_factory_only_initialises_options_once()
	{
		$this->assertEquals(
				'a5',
				View_PDF::get_dompdf_option(View_PDF::DOMPDF_DEFAULT_PAPER_SIZE),
				'Verifying expected state');

		$config = Kohana::$config->load('dompdf');
		$config['options'][View_PDF::DOMPDF_DEFAULT_PAPER_SIZE] = 'a4';


		// Check that the config is not reloaded by the next factory call
		View_PDF::factory('exists');

		$this->assertEquals(
				'a5',
				View_PDF::get_dompdf_option(View_PDF::DOMPDF_DEFAULT_PAPER_SIZE));
	}

	/**
	 * Tests that the factory method creates a new instance of View_PDF each
	 * time.
	 */
	public function test_factory_creates_new_instance()
	{
		$view = View_PDF::factory('exists');
		$view2 = View_PDF::factory('exists');
		$this->assertInstanceOf('View_PDF', $view);
		$this->assertInstanceOf('View_PDF', $view2);
		$this->assertNotSame($view, $view2);
	}

	/**
	 * Prior to including the library, dompdf properties can be set - they are
	 * defined as the library is included.
	 */
	public function test_dompdf_options_can_be_set_and_read()
	{
		// Set the config value to something very unusual as evidence
		View_PDF::set_dompdf_option(View_PDF::DOMPDF_DEFAULT_MEDIA_TYPE, 'braille');
		$this->assertEquals('braille', View_PDF::get_dompdf_option(View_PDF::DOMPDF_DEFAULT_MEDIA_TYPE));
	}

	/**
	 * dompdf is not included until required - to allow configuration of
	 * dompdf properties
	 *
	 * @depends test_factory_creates_new_instance
	 */
	public function test_dompdf_not_loaded_until_required()
	{
		// The actual test for this is handled by the verify_dompdf_state in the
		// setUp hook
	}

	/*
	 * ------------------------------------------------------------------------
	 * The next test will trigger inclusion of the DOMPDF library - which defines
	 * constants for major configuration values. Any tests of DOMPDF configuration
	 * must go above this point.
	 * -------------------------------------------------------------------------
	 */

	/*
	 * View_PDF::dompdf() returns an instance of the class
	 */
	public function test_dompdf_returns_instance()
	{
		self::$expect_dompdf_presence = TRUE;
		$view = View_PDF::factory('exists');
		$this->assertInstanceOf('DOMPDF', $view->dompdf());
	}

	/**
	 * DOMPDF sets global configuration properties with constants. If you try to
	 * set properties after the library has been initialised you'll get an
	 * exception.
	 *
	 * @depends test_dompdf_returns_instance
	 * @expectedException Exception_DOMPDF_Initialised
	 */
	public function test_setting_options_throws_exception_once_initialised()
	{
		View_PDF::set_dompdf_option(View_PDF::DOMPDF_FONT_CACHE, 'foo');
	}

	/**
	 * Tests that options are defined and recognised by dompdf
	 *
	 * @depends test_dompdf_options_can_be_set_and_read
	 */
	public function test_options_are_assigned_when_initialised()
	{
		$this->assertEquals('braille', DOMPDF_DEFAULT_MEDIA_TYPE);
	}
}
