<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Render a view as a PDF.
 *
 * @author     Woody Gilk <woody.gilk@kohanaphp.com>
 * @copyright  (c) 2009 Woody Gilk
 * @license    MIT
 */
class View_PDF extends View {

	/**
	 * Constants for the names of the valid DOMPDF options
	 */
	const DOMPDF_FONT_DIR              ='DOMPDF_FONT_DIR';
	const DOMPDF_FONT_CACHE            ='DOMPDF_FONT_CACHE';
	const DOMPDF_TEMP_DIR              ='DOMPDF_TEMP_DIR';
	const DOMPDF_UNICODE_ENABLED       ='DOMPDF_UNICODE_ENABLED';
	const DOMPDF_ENABLE_FONTSUBSETTING ='DOMPDF_ENABLE_FONTSUBSETTING';
	const DOMPDF_PDF_BACKEND           ='DOMPDF_PDF_BACKEND';
	const DOMPDF_PDFLIB_LICENSE        ='DOMPDF_PDFLIB_LICENSE';
	const DOMPDF_DEFAULT_MEDIA_TYPE    ='DOMPDF_DEFAULT_MEDIA_TYPE';
	const DOMPDF_DEFAULT_PAPER_SIZE    ='DOMPDF_DEFAULT_PAPER_SIZE';
	const DOMPDF_DEFAULT_FONT          ='DOMPDF_DEFAULT_FONT';
	const DOMPDF_DPI                   ='DOMPDF_DPI';
	const DOMPDF_ENABLE_PHP            ='DOMPDF_ENABLE_PHP';
	const DOMPDF_ENABLE_JAVASCRIPT     ='DOMPDF_ENABLE_JAVASCRIPT';
	const DOMPDF_ENABLE_REMOTE         ='DOMPDF_ENABLE_REMOTE';
	const DOMPDF_LOG_OUTPUT_FILE       ='DOMPDF_LOG_OUTPUT_FILE';
	const DOMPDF_FONT_HEIGHT_RATIO     ='DOMPDF_FONT_HEIGHT_RATIO';
	const DOMPDF_ENABLE_CSS_FLOAT      ='DOMPDF_ENABLE_CSS_FLOAT';
	const DOMPDF_AUTOLOAD_PREPEND      ='DOMPDF_AUTOLOAD_PREPEND';
	const DOMPDF_ENABLE_HTML5PARSER    ='DOMPDF_ENABLE_HTML5PARSER';

	/**
	 * @var boolean	Whether dompdf has been initialised yet
	 */
	protected static $_dompdf_initialised = FALSE;

	/**
	 * @var array An array of dompdf config options
	 */
	protected static $_options = NULL;

	/**
	 * @var DOMPDF Internal reference to this instance's DOMPDF instance
	 */
	protected $_dompdf = NULL;

	/**
	 * Initialises dompdf - setting any config options as required.
	 *
	 * [!!] Note that options become readonly at this point, as dompdf requires
	 * them as constants.
	 */
	public static function init_dompdf()
	{
		// Only include once
		if (self::$_dompdf_initialised)
		{
			throw new Exception_DOMPDF_Initialised("DOMPDF is already initialised");
		}

		// Define any custom config values
		foreach (self::get_dompdf_option() as $option => $value)
		{
			define($option, $value);
		}

		// Load DOMPDF configuration, this will prepare DOMPDF
		require_once Kohana::find_file('vendor', 'dompdf/dompdf/dompdf_config.inc');
		self::$_dompdf_initialised = TRUE;
	}

	/**
	 * Loads the dompdf options from the Kohana config system
	 *
	 * @return void
	 */
	public static function load_default_options()
	{
		// Only if not initialised
		if (self::$_dompdf_initialised)
		{
			throw new Exception_DOMPDF_Initialised("Could not load DOMPDF options as DOMPDF has already been initialised");
		}

		if (method_exists('Kohana', 'config'))
		{
			// Handle KO 3.0 - 3.1
			$options = Kohana::config('dompdf.options');
		}
		else
		{
			// Handle KO 3.2 style
			$options = Kohana::$config->load('dompdf.options');
		}
		self::$_options = $options;
	}

	/**
	 * Get a dompdf option setting
	 *
	 * @param string $key      Name of the option, or NULL to retrieve all options
	 * @param mixed  $default  The default value if the option is not found
	 * @return mixed The option value, or an array of option values
	 */
	public static function get_dompdf_option($key = NULL, $default = NULL)
	{
		if ($key === NULL)
		{
			return self::$_options;
		}
		return Arr::get(self::$_options, $key, $default);
	}

	/**
	 * Sets a dompdf option setting - if the library is not already initialised
	 *
	 * @param string $key
	 * @param mixed $value
	 *
	 * @throws Exception_DOMPDF_Initialised If the library has been initialised already
	 */
	public static function set_dompdf_option($key, $value)
	{
		// Only if not initialised
		if (self::$_dompdf_initialised)
		{
			throw new Exception_DOMPDF_Initialised("Could not assign :key to :value as DOMPDF has already been initialised",
				array(':key' => $key, ':value' => (string) $value));
		}

		// Set the option
		self::$_options[$key] = $value;
	}

	/**
	 * Returns an instance of View_PDF, assigning a view and data if required
	 * @return View_PDF
	 */
	public static function factory($file = NULL, array $data = NULL)
	{
		// Initialise the options from config
		if (self::$_options === NULL)
		{
			self::load_default_options();
		}

		return new View_PDF($file, $data);
	}

	/**
	 * Gets the View's DOMPDF instance - initialises the library if required.
	 * @return DOMPDF
	 */
	public function dompdf()
	{
		if ( ! $this->_dompdf)
		{
			if ( ! self::$_dompdf_initialised)
			{
				self::init_dompdf();
			}

			$this->_dompdf = new DOMPDF;
		}

		return $this->_dompdf;
	}

	/**
	 * Renders the view and returns the PDF content
	 * @return string
	 */
	public function render($file = NULL)
	{
		// Render the HTML normally
		$html = parent::render($file);

		// Turn off strict errors, DOMPDF is stupid like that
		$ER = error_reporting(~E_STRICT);

		// Render the HTML to a PDF
		$pdf = $this->dompdf();
		$pdf->load_html($html);
		$pdf->render();

		// Restore error reporting settings
		error_reporting($ER);

		return $pdf->output();
	}

} // End View_PDF
