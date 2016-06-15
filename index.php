<?php
/**
* Responsive Custom Header for WordPress theme development
* @version 0.0.7
* @author Svetlana http://cadros.eu
*/

/**
 * Get the path to custom module folder
 * regardless hierarchy
 * Used by script loaders & img functions
 */
if ( ! function_exists( '_module' ) ) :
	/**
	 * @return str Path to module relative to theme root
	 */
	function _module() {
		$theme = get_stylesheet_directory(); 
		$moduleNam = basename(dirname(__FILE__));
		$module = str_replace( array("\\", $moduleNam ), array("/", ''), dirname(__FILE__) );
		return str_replace( str_replace("\\", "/", $theme), '', $module  );
	}
endif;

if ( ! function_exists( '_module_dir' ) ) :
	/**
	 * @return str Dir MU-plugin | Theme root/path-to-module- folder/
	 */
	function _module_dir() {
		if( WPMU_PLUGIN_DIR !==  dirname(__FILE__) ) {
			return get_stylesheet_directory()._module();
		}
		return WPMU_PLUGIN_DIR;
	}
endif;

if ( ! function_exists( '_module_uri' ) ) :
	/**
	 * @return str URL MU-plugin | Theme root/path-to-module- folder/
	 */
	function _module_uri() {
	  if( WPMU_PLUGIN_DIR !==   dirname(__FILE__) ) {
	    return get_stylesheet_directory_uri()._module();
	  } 
	  return WPMU_PLUGIN_URL;
	}
endif;


/**
 * Add Responsive Header theme support
 */
function add_srcset_theme_support() {
	load_textdomain("respHeader", _module_dir(). "/respHeader/lang/respHeader.mo");
	define("RESPHEADER_V", '0.0.1');

	/**
	 * (optional)
	 * Set Recommended image sizes here
	 * Mainly needed for img cropper as cropping guides
	 */
	add_theme_support( 'respHeader', array(
		array('width' => 320, 'height' => 200),
        array('width' => 375, 'height' => 300),
        array('width' => 600, 'height' => 350),
        array('width' => 768, 'height' => 400)
		) );

	/**
	 * (optional)
	 * Register default responsive headers 
	 *
	 * Allows setting the default img sources
	 *
	 * @see wp-includes/theme.php
	 *
	 */
	$alt = 'Responsive Custom Header for WordPress theme development';
	$default_image = _module_uri(). 'respHeader/img/jump.jpg';

	$defaults = array(
		'default' => array(
			'url'=> $default_image,
			'thumbnail_url' => $default_image,
			"description" => "Jumping girls",
			'width'=>1224,
			'height'=>540,
			'title' => $alt,
			'alt' => $alt,
			'srcset' => array( // default img sources
				array(
				'guid'=> _module_uri(). 'respHeader/img/jump360.jpg',
				'width'=>360
				),
				array(
				'guid'=> _module_uri(). 'respHeader/img/jump600.jpg',
				'width'=>600
				)
			)
		)
	);		
	register_default_headers($defaults);
	$def_width = !cs_get_mods( 'respheader_width' ) ? 1224 : cs_get_mods( 'respheader_width' );
	$def_height = !cs_get_mods( 'respheader_height' ) ? 540 : cs_get_mods( 'respheader_height' );

	// add support for default img if none yet
	add_theme_support( 'custom-header', array('default-image' => $default_image, 'width' => $def_width, 'height' => $def_height ) );

} add_action( 'after_setup_theme', 'add_srcset_theme_support' );

/**
* (optional)
* Load Frontend scripts
*/
function rheader_styles_and_scripts() {

	if(! is_admin()) {
		// module style
		wp_enqueue_style( 
			'respHeader_front', // handle
			_module_uri()  . 'respHeader/css/respheader_front.css', // frontend Responsive header styles. currently empty
			false,
			RESPHEADER_V // Default version
			);
	}

} 
/** add_action( 'wp_enqueue_scripts', 'rheader_styles_and_scripts' ); // uncomment to load */

/** 
* Backend scripts
*/
function rheader_admin_scripts() {
	/**
	 * Load font-awesome icons styles
	 * If it's not loaded or registered yet
	 */
	if( ! wp_style_is( 'font-awesome', 'enqueued' ) && ! wp_style_is( 'font-awesome', 'registered' )) {
		$f_awesome_url = 'http://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css';
		wp_enqueue_style('font-awesome', $f_awesome_url, false, '4.5.0'  );
	}
	wp_enqueue_style( 
			'respHeader_back', // handle
			_module_uri()  . 'respHeader/css/respheader_back.css',
			false,
			RESPHEADER_V // Default version
			);

} add_action( 'admin_enqueue_scripts', 'rheader_admin_scripts' );

// includes
require_once '_img.php';
require_once '_customizer.php';
require_once '_ajax.php';
require_once '_layout.php';