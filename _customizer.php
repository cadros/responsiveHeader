<?php
/**
* Responsive Custom Header customizer controls
* 
* @author Svetlana http://cadros.eu
*/


/**
 *
 * Hook in to add responsive header controls to WP Customizer
 * @param obj WP_Customize_Manager class instance.
 */
function custom_respheader( $wp_customize ) {
	$textdomain = get_stylesheet();
	/**
	 * Remove default header control 
	 */
	$wp_customize->remove_control('header_image');
	/**
	 * 
	 * Add custom control
	 * @see class resp_Header_Image_Control
	 */
	$wp_customize->add_control( new resp_Header_Image_Control( $wp_customize, 'header_image', array(
			'section' => 'header_image',
			'settings' => 'header_image'
			)
		) 
	);

	/**
	 * respHeader custom size
	 * width
	 */
	$headerSize = get_theme_support( 'custom-header' );

	$wp_customize->add_setting( 'respheader_width', array(
		'default'           => $headerSize[0]['width'],
		'section'			=> 'header_image',
		'sanitize_callback' => 'respH_custom_width_validate',
		'sanitize_js_callback' => 'respH_custom_width_js_validate'
		)
	);
	$wp_customize->add_control( 'respheader_width', array(
		'label'   => sprintf( __( 'Header image width, in px', '%s'), $textdomain ),
		'section' => 'header_image',
		'type'    => 'text'
		)
	);
	// height
	$wp_customize->add_setting( 'respheader_height', array(
		'default'           => $headerSize[0]['height'],
		'section'			=> 'header_image',
		'sanitize_callback' => 'respH_custom_height_validate',
		'sanitize_js_callback' => 'respH_custom_height_js_validate'
		)
	);
	$wp_customize->add_control( 'respheader_height', array(
		'label'   => sprintf( __( 'Header image height, in px', '%s'), $textdomain ),
		'section' => 'header_image',
		'type'    => 'text'
		)
	);
} add_action( 'customize_register', 'custom_respheader' );


// Sanitize input
function respH_custom_height_validate($value) {
	if( is_numeric($value)) {
		return sanitize_text_field($value);
	}
}
// Sanitize output
function respH_custom_height_js_validate($value) {
	if( $value ) {
		return (int) $value;
	}
}
// Sanitize input
function respH_custom_width_validate($value) {
	if( is_numeric($value)) {
		return sanitize_text_field($value);
	}
}
// Sanitize output
function respH_custom_width_js_validate($value) {
	if( $value ) {
		return (int) $value;
	}
}


/**
* Set default settings / mods ahead
*
*/
function respHeader_load_before_customizer() {
	$headerSize = get_theme_support( 'custom-header' );
	// set header img width
	if(!cs_get_mods( 'respheader_width' ) ) {
		set_theme_mod( 'respheader_width', $headerSize[0]['width'] );
	}
	// set header img height
	if(!cs_get_mods( 'respheader_height' ) ) {
		set_theme_mod( 'respheader_height', $headerSize[0]['height'] );
	}

}	add_action( 'wp_loaded', 'respHeader_load_before_customizer');


/**
 * Responsive Header Control class
 *
 * Parent class:
 * @see ABSPATH. WPINC . '/class-wp-customize-control.php'
 *
 * @todo Add sizes tag support
 */
if ( class_exists('WP_Customize_Header_Image_Control' ) ) :
class resp_Header_Image_Control extends WP_Customize_Header_Image_Control {


	public function __construct( $manager ) {
		$this->textdomain = 'respHeader';

		parent::__construct( $manager );
	}


	/**
	 * Stringify theme supported img source sizes
	 * @return array (Str WxH)
	 */
	function widths_strings() {

		$widths = respHeader_widths();
		foreach ($widths as &$value) {
			$value =  join( 'x', $value);
		}
		return $widths;
	}

	/**
	 * A reference to supported widths
	 * Shows up when every width is covered to provide help 
	 */
	function widths_message() {
		$recommend = "<span class='expand'>Your theme recommends the responsive header to have sources of the following sizes <strong>: ".join(', ', $this->widths_strings() )."px</strong></span>";
		$link = "<span class='fa fa-plus-circle'></span><input type='checkbox' name='fake'> ".$recommend;
		return sprintf( __( "<div class='sandwich'>Your responsive header now has every <button disabled>recommended %s</button> image source. Add more images to your liking.</div>","%s"), $link, 'respHeader' );
	}

	/**
	 * Check if set image is of a default header 
	 * @return bool False|True
	 */
	function is_default() {
		$header = get_theme_mod( 'header_image_data');
		if(! $header){ return; }
		if( is_object($header)) {
			return ! cHELP::issetIndex($header, 'attachment_id');
		} else {
			return ! in_array('attachment_id', $header);
		}
	}



	public function enqueue() {
		/**
		 * Call the parent for default scripts
		 * Will call prepare_control() too
		 * @see includes/class-wp-customize-control.php
		 */ 
		parent::enqueue();
		// Re-localize parent's script, since we added a custom setting for header size and need to change the default value

		wp_localize_script( 'customize-views', '_wpCustomizeHeader', array(
			'data' => array(
				'width' => absint( cs_get_mods( 'respheader_width' ) ), // changed value
				'height' => absint( cs_get_mods( 'respheader_height' )  ), // changed value
				'flex-width' => absint( get_theme_support( 'custom-header', 'flex-width' ) ),
				'flex-height' => absint( get_theme_support( 'custom-header', 'flex-height' ) ),
				'currentImgSrc' => $this->get_current_image_src(),
			),
			'nonces' => array(
				'add' => wp_create_nonce( 'header-add' ),
				'remove' => wp_create_nonce( 'header-remove' ),
			),
			'uploads' => $this->uploaded_headers,
			'defaults' => $this->default_headers
		) );


		// Load won custom script js/respHeader.js
		wp_enqueue_script( 
			'_'.$this->textdomain, // handle
			_module_uri()
			.$this->textdomain
			.'/js/'
			.$this->textdomain.'.js', // path
			array( 'wp-util' ), // depends on wp-includes/js/wp-utils.js
			RESPHEADER_V, // skip printing version
			true // load late
		);

		/**
		 * Strings to go to custom script 
		 */
		$this->strings = array(
			'ajaxUrl' => admin_url('admin-ajax.php', (is_ssl() ? 'https': 'http')),
			'supported_widths' => respHeader_widths(),//theme recommended
			'due_widths' => _due_widths(),//left to add
			'fallback_width' => array( _fallback_widths())//img cropper guidance for when all due are added to the srcset
		);
		
		// localize custom script
		wp_localize_script( 
			'_'.$this->textdomain, // handle
			$this->textdomain, // var name
			$this->strings // value
			);
	}


	public function prepare_control() {
		/**
		 * Copy parent processing default headers
		 * @uses global Custom_Image_Header
		 * @see wp-admin/custom-header.php
		 *
		 * @uses _img.php rheader_get_uploaded_header_images()
		 */
		global $custom_image_header;
		if ( empty( $custom_image_header ) ) {
			return;
		}
		$custom_image_header->process_default_headers();
		$this->default_headers = $custom_image_header->get_default_header_images();


		// Replace native get_uploaded..() with custom getter. see wp-includes/theme.php
		$this->uploaded_headers = rheader_get_uploaded_header_images();
	}

	/**
	 * Customize default header control template
	 *
	 * @see wp-includes/js/customize-views.js HeaderTool
	 *
	 * native header control class:
	 * @see wp-admin/js/customize-controls.js (min) HeaderControl, instantiates HeaderTool Views
	 *
	 * custom extension:
	 * @see respHeader.js api.HeaderTool.CurrentView
	 */
	function print_header_image_template() {
		?>
		<script type="text/template" id="tmpl-header-choice">
			<# if (data.random) { #>
					<button type="button" class="button display-options random">
						<span class="dashicons dashicons-randomize dice"></span>
						<# if ( data.type === 'uploaded' ) { #>
							<?php _e( 'Randomize uploaded headers' ); ?>
						<# } else if ( data.type === 'default' ) { #>
							<?php _e( 'Randomize suggested headers' ); ?>
						<# } #>
					</button>

			<# } else { #>

			<# if (data.type === 'uploaded') { #>
				<div class="dashicons dashicons-no close"></div>
			<# } #>
			<button type="button" class="choice thumbnail"
				data-customize-image-value="{{{data.header.url}}}"
				data-customize-header-image-data="{{JSON.stringify(data.header)}}">
				<span class="screen-reader-text"><?php _e( 'Set image' ); ?></span>
				<img src="{{{data.header.thumbnail_url}}}" alt="{{{data.header.alt_text || data.header.description}}}">
			</button>
			<# } 
			#>
		</script>

		<script type="text/template" id="tmpl-header-current">
			<# if (data.choice) { #>
				<# if (data.random) { #>
			<div class="placeholder">
				<div class="inner">
					<span><span class="dashicons dashicons-randomize dice"></span>
					<# if ( data.type === 'uploaded' ) { #>
						<?php _e( 'Randomizing uploaded headers' ); ?>
					<# } else if ( data.type === 'default' ) { #>
						<?php _e( 'Randomizing suggested headers' ); ?>
					<# } #>
					</span>
				</div>
			</div>
			<# 
			} else {


			// not random current header img, might be single or collection. Responsive header part goes with it

				#>
				<div id="responsive_header">
					<?php // Get header/collection type ?>
					<# var type = data.collection ? data.collection.type : data.type; #>

					<?php // Render due widths ?>
					<# if( data.header.toadd ) { #>
						<#
			            var duesize = [], toadd;

						// Join widths to add
						for (i = 0; i < data.header.toadd.length; i++ ) {
							w =  data.header.toadd[i];
							if( undefined == w ) {
				                continue;
				            }
							duesize[i] = "<span class='toadd' id='add_" + w.width + "'>"+w.width+"x" + w.height + "px</span>";
						} #>
						<# toadd = duesize.join(" | ");#>

						<?php // Render message ?>
						<p>
							<# 
							if(toadd && !data.header.toadd.freescale ) { 
								#>
								<?php
								_e( 'Current theme recommends adding images of the following size to your responsive header: ', 'respHeader');?>
								{{{toadd}}}

							<# } else {
								#>
								<?php echo $this->widths_message();?>
							<# } #>
						</p>
					<# } #>
					
					<?php // Loop through added sources ?>
					<# if( data.header.srcset ) {
						for (i = 0; i < data.header.srcset.length; i++ ) {

							s =  data.header.srcset[i];
							if( undefined == s ) {
								continue;
							}
							#>
							<?php // show thum & remove icon ?>
							<div class="holder">
								<img src="{{{s.guid}}}" class="src srcset_button" />
								<# if( type !== 'default') { #>
								<span class="dashicons dashicons-no close" data-src-width="{{{s.width}}}" data-img-id="{{{s.img_id}}}" data-header-id = "{{{data.header.attachment_id}}}"></span>
								<# } #>
								<div class="src_size">{{{s.width}}}px</div>
							</div>
					<# }
					}
					#>
					<?php // Disable|Enable Source button ?>
					<# if (data.choice) { #>
						<# var clas, state = "disabled"; #>
						<# var defaultOnReload = #> <?php $this->is_default ?><# ; #>
						<#
						if( defaultOnReload ) {
							state = "";
						} 

						if ( type !== 'default') { #>
							<#

							clas = "srcset_button";
						    state = ''; 

						    #>
						<# } #>
						<button type="button" class="button {{{clas}}}" id="add_source" {{{state}}}>Add source
						</button>
					<# } #>
				</div>

				<img class="current_img" src="{{{data.header.thumbnail_url}}}" alt="{{{data.header.alt_text || data.header.description}}}" tabindex="0"/>			
			<# } #>
		<# }


		else { #>
		<div class="placeholder">
			<div class="inner">
				<span>
					<?php _e( 'No image set' ); ?>
				</span>
			</div>
		</div>
		<# } #>
		</script>
		<?php
	}


	public function render_content() {
		$this->print_header_image_template();
		$visibility = $this->get_current_image_src() ? '' : ' style="display:none" '; // default

		$toggle = null;

		// hardsell class to highlight the Add New button for better interface. Should prevent confusion indicating that no source editing is possible for demo (default) header
		$style = ' hardsell';

		if( ! $this->is_default() ) {
			// hide the respHeaderNote message 
			$toggle = 'screened';

			// Un-highlight the Add New button
			$style = null;
		}
		?>
		<div class="customize-control-content">
			<p class="customizer-section-intro respHeaderNote <?php echo $toggle ?>">
			<?php
			// respHeaderNote message, shows up initially
				_e( 'Your theme supports responsive header that can pick & show different images for different screens depending on size. After a new header image is set, click <i>Add source</i> to add source images. <br />', 'respHeader' );
				?>
			</p>
			<div class="current">
				<span class="customize-control-title">
					<?php _e( 'Current header' ); ?>
				</span>
				<div class="container">
				</div>							
			</div>
			<div class="actions">
				<?php /* translators: Hide as in hide header image via the Customizer */ ?>
				<button type="button"<?php echo $visibility ?> class="button remove"><?php _ex( 'Hide image', 'custom header' ); ?></button>
				<?php /* translators: New as in add new header image via the Customizer */ ?>
				<button type="button" class="button new newHeader<?php echo $style ?>"><?php _ex( 'Add new image', 'header image' ); ?></button>
				<div style="clear:both"></div>
			</div>
			<div class="choices">
				<span class="customize-control-title header-previously-uploaded">
					<?php _ex( 'Previously uploaded', 'custom headers' ); ?>
				</span>
				
				<div class="uploaded">
					<div class="list">
					</div>
				</div>

				<span class="customize-control-title header-default">
					<?php _ex( 'Suggested', 'custom headers' ); ?>
				</span>
				<div class="default">
					<div class="list">
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
endif;