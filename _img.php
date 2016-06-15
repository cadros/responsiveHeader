<?php
/**
 * Image functions for Responsive Custom Header
 * @author Svetlana http://cadros.eu
 */




/**
 * Help class (part)
 *
 * @version     2.1.4
 * @package     c_classes
 * @category    Class
 * @author      Cadro'S Apps
 */

if(! class_exists("cHELP")) :
	class cHELP {

		static function multi_unique($array){
		  $result = array_map("unserialize", array_unique(array_map("serialize", $array)));

		  foreach($result as $key => $value) {
		    if ( is_array($value) )
		    {
		      $result[$key] = self::multi_unique($value);
		    }
		  }

		  return $result;
		}

		/**
		* Works around 'undefined index' PHP warning
		* @param string|int $index to check
		* @param array|obj $array
		* @return mixed string $index Value|null
		*/
		static function issetField( $array, $index ) {
		    return isset($array[$index]) ? $array[$index] : null;
		}

		static function issetIndex( $object, $index ) {
		    return isset($object->{$index}) ? $object->{$index} : null;
		}

	}
endif;

/**
 * Get srcset for current | given ID header
 * 
 * Gets the url stored at header metadata by header ID
 *
 * @param int header attachment id
 * @return array srcset data
 */
function get_header_srcset( $headerID = null ) {
	! $headerID && $headerID = get_custom_header()->attachment_id;
	if(! $headerID ) {
		return;
	}
	$meta = wp_get_attachment_metadata( $headerID );

	return cHELP::issetField( $meta, 'srcset' );
}


/**
 * Get recommended img sources widths
 *
 * @return array Widths | Fallback width
 */
function respHeader_widths() {
	$sizes = get_theme_support( 'respHeader' );
	if(! $sizes) {
		return;
	}
	if( ! cHELP::issetField($sizes[0], 'fallback') ) {
		return $sizes[0];
	} else {
		// cut off fallback if it's there
		unset( $sizes[0]['fallback']);
		return $sizes[0];
	}
}

/**
 * Get custom fallback source width to use for img cropping when theme recommended sizes are over
 * @return array Fallback|Default
 */
function _fallback_widths() {
	$fallback = cHELP::issetField( get_theme_support( 'respHeader' )[0], 'fallback');
	return $fallback ? $fallback : array('width'=>320, 'height'=>320 );
}

/**
 * Filter supported widths to get what's left to add
 * 
 * The data is used by cropper & customizer
 *
 * 'respHeader' theme support must be set
 * @see index.php
 *
 * @param $added array | null Header srcset to check against
 * @param int|null Header id
 * @uses respHeader_widths()
 * @return array Sizes left to add
 */
function _due_widths( $added = null, $id = null ) {
	// get widths
	$sizes = respHeader_widths();
	if(! $sizes ) {
		return;
	}

	// If no param $added to check against, get img sources added to the given header 
	!$added && $added = get_header_srcset( $id );
	if(! $added ) {
		// return full set of widths unfiltered if no srcset yet
		return $sizes;
	}

	// map $sizes with $added
	foreach ($sizes as $key => $value) {
		foreach ( $added as $width ) {
			// if a source is as close as +-20px, consider the size covered & cut it off
			if( absint( $value['width'] - $width['width'] ) < 20 ) {
				unset($sizes[$key]);
			}
		}
	}

	// restore the order & return widths so they can be added
	if(! empty($sizes) ) {
		sort($sizes);
		return $sizes;
	}
	// if no more due widths, fallback in case they still want more images. Let the cropper know it's free to scale 
	return array( _fallback_widths(), 'freescale'=> true );
}



/**
 * 
 * @return array uploaded header data + its srcset data
 *
 * Used by customizer
 	* curr_header template data.header obj
 *
 * @see _customizer.php resp_Header_Image_Control->prepare_control()
 * @see _customizer.php resp_Header_Image_Control->print_header_image_template()
 *
 * Native function:
 * @see wp-includes/theme.php get_uploaded_header_images()
 *
 * @uses _due_widths()
 */
function rheader_get_uploaded_header_images() {
	$header_images = get_uploaded_header_images();// gets uploaded headers | empty array if '_wp_attachment_is_custom_header' postmeta is not found for the current theme

	$timestamp_key = '_wp_attachment_custom_header_last_used_' . get_stylesheet();
	$alt_text_key = '_wp_attachment_image_alt';

	foreach( $header_images as &$header_image ) {
		$id = $header_image['attachment_id'];
		$header_meta = get_post_meta( $id );
		$header_image['timestamp'] = isset( $header_meta[ $timestamp_key ] ) ? $header_meta[ $timestamp_key ] : '';
		$header_image['alt_text'] = isset( $header_meta[ $alt_text_key ] ) ? $header_meta[ $alt_text_key ] : '';

		// get each header meta
		$meta = wp_get_attachment_metadata($id);

		// Check if due_widths is in meta
		$toadd = cHELP::issetField( $meta, 'due_widths');
		// Figure them out if none
		!$toadd && $toadd = _due_widths(null, $id);
		$header_image['toadd'] = $toadd;

		// get srcset
		$meta = cHELP::issetField( $meta, 'srcset');

		// bail if no srcset
		if( ! $meta ) {
			continue;
		}

		foreach( $meta as $src ) {
			// Add srcset so customizer & data.header get them
			$header_image['srcset'][] = array(
			'guid' =>$src['guid'],
			'width' => (int) $src['width'],
			'img_id' => (int) $src['img_id'] 
			);
		}

	}///end looping through headers
	return $header_images;
}


/**
 * Remove a header source
 *
 * Works for both theme mods & header attachment_metadata (where the header img data is natively stored in db)
 *
 * @param int Header id | false to work with theme mods
 * @param array $data img source width and id 
 */

function unset_source_data( $srcWidth, $img_id, $id = false ) {
	// make sure we got the param
	if(! $width && ! $img_id ) {
		return;
	}

	// pull srcset from mods or meta, depending on $id param
	if(!$id ) {
		$storage = get_theme_mod( 'header_image_data' );
		$sources = $storage->srcset;

	} else {
		$storage = wp_get_attachment_metadata( $id );
		$sources = $storage['srcset'];
	}


	// remove the source matching width & id
	foreach ( (array) $sources as $key => $set) {
		if( $set['width'] != $width && $set['img_id'] != $img_id ) {
			continue;
		}
		unset($sources[$key]);
	}

	// sort srcset if not empty & get proper due widths
	if( $sources && ! empty($sources)) {
		sort($sources);
		$id && $storage['due_widths'] = _due_widths( $sources );
	} else {
		// Update metadata with full set of widths & bail
		$id && $storage['due_widths'] = respHeader_widths();
	}


	// update the data
	if(! $id ) {
		// update theme mods
		$storage->srcset = $sources;
		set_theme_mod( 'header_image_data', $storage );

	} else {
		// update header metadata
		$storage['srcset'] = $sources;
		return wp_update_attachment_metadata( $id, $storage );// so we know success
	}
}


/**
 * Remove custom image sizes on Custom Header upload
 *
 * The extra sizes might get added by add_image_size() resulting in a bunch of cropped copies, 
 * while we only need the original image for header srcset
 *
 * Hooks into image upload via 'intermediate_image_sizes_advanced' filter
 * @see wp-admin/includes/image.php wp_generate_attachment_metadata()
 *
 * @return null on header image upload 
 */

function remove_thumbs_onHeader($sizes) {
	if ( ! isset($_REQUEST['post_id']) ) {
		return null;
	}
	return $sizes;
} 
add_filter( 'intermediate_image_sizes_advanced', 'remove_thumbs_onHeader', 10, 1);