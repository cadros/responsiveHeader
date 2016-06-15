<?php
/**
*
* Ajax functions for WP Responsive Custom Header
*
* @author Svetlana http://cadros.eu
*/

 
/**
 * Insert responsive header image source into db
 * 
 * Receives img data from media uploader, $_POST
 * Crops img if necessary
 * Updates srcset metadata for current header
 	* at header attachment_metadata ['srcset']
 	* at theme mods {header_image_data}.srcset
 * 
 * Sends img data back to wp.media
 *
 * @uses _img.php rheader_get_header_dimensions()
 * @uses _img.php _header_srcset_data()
 * @uses hook wp_ajax_custom-
 * @see js/respHeader.js cropper
 */
function ajax_header_srcset() {
	// check/bail
	check_ajax_referer( 'image_editor-' . $_POST['id'], 'nonce' );
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_send_json_error();
	}
	if ( ! current_theme_supports( 'custom-header', 'uploads' ) ) {
		wp_send_json_error();
	}

	// current header ID
	$currentID = cHELP::issetField($_POST, 'currentHeader');

	if(! $currentID ) {
		wp_send_json_error();
		return;
	}

	// final img size from media cropper
	$crop_details = cHELP::issetField($_POST, 'cropDetails');
	
	/**
	 * Check if crop flag set true & decide on the img
	 *
	 * If crop is skipped, we assume that the originally selected img was of the right size, and can be used without ado. Just get its location & ID
	 *
	 * Otherwise we have to crop the original and insert anew
	 */
	if( ! cHELP::issetField($_POST, 'crop') ) {
		// use same location & id
		$guid = $_POST['url'];
		$newID = $_POST['id'];

	} else {// crop & insert into db anew 

		/**
		 * Try to crop
		 * Get new file location str $img
		 * By default the cropped img location is the original img dir
		 * @see wp-admin/includes/image.php
		 *
		 * @internal absolute points arg only subtracts x1/y1 from width/height. destination file arg only adds 'cropped-' to the file basename. includes/class-wp-image-editor.php
 		 * @internal destination size args, if specified, will scale the cropped img to fit the theme. which is totally wrong for responsive header. we want it exactly the width & height we got from the media uploader
		 *
		 */
		$img = wp_crop_image(
		$_POST['id'],// selected img id
		(int) $crop_details['x1'],
		(int) $crop_details['y1'],
		(int) $crop_details['width'],
		(int) $crop_details['height'],
		false, // destination sizes
		false
		// absolute points
		// destination file
		);

		// return fail on fail
		if ( ! $img || is_wp_error( $img ) ) {
			wp_send_json_error( array( 'message' => __( 'Image could not be processed. Please go back and try again.' ) ) );
		}

		/**
		 * Must-data for attachment insert
		 * To make the guid 100% right we just use the original url, which is the cropped img dir by default, not to mess with upload_dir that can get complicated at months shift or multi sites 
		 */
		$guid = str_replace( basename( $_POST['url'] ), basename( $img ), $_POST['url'] );
		$data = array(
			'post_content' => '',
			'post_status'    => 'inherit',
			'post_title' => preg_replace( '/\.[^.]+$/', '', basename($img) ),
			'post_mime_type' => cHELP::issetIndex($crop_details, 'mime') ? $crop_details['mime'] : "image/jpeg",
			'guid' => $guid
		);
		// insert the cropped and its data into db
		$newID = wp_insert_attachment( $data, $img );

		/**
		 * Generate & write up formal wp attachment metadata
		 * So if removed from header sources the img acts as naturally expected
		 * @param attachment id, file location
		 */
		$srcmeta = wp_generate_attachment_metadata( $newID, $img );

		// write up metadata to a new img 
		wp_update_attachment_metadata( $newID, $srcmeta );
	}///end cropping


	/**
	 * Update header metadata
	 */
	// get data so it's not wiped with new
	$currentHeaderMeta = wp_get_attachment_metadata( $currentID );
	$currentset = cHELP::issetField($currentHeaderMeta, 'srcset' );
	! $currentset && $currentset = array();

	// make srcset
	$srcset = array();
    $srcset['width']         = $crop_details['width'];
    $srcset['height']        = $crop_details['height'];
    $srcset['guid']          = $guid;
    $srcset['img_id']        = $newID;

    $currentset[] = $srcset;
    $currentset = cHELP::multi_unique( $currentset );
	$currentHeaderMeta['srcset'] = $currentset;
	
	// add the source widths left to add to this particular header
	$currentHeaderMeta['due_widths'] = _due_widths( $currentset );

	// write up metadata into current header's
	wp_update_attachment_metadata( $currentID, $currentHeaderMeta );


	/**
	 * Update default header key at theme mods
	 * This will make srcset available via get_theme_mod( 'header_image_data' ).
	 * @see wp-admin/custom-header.php set_header_image()
	 */
	// get header data current key
	$headerData = get_theme_mod( 'header_image_data' );
	! $headerData && $headerData = new StdClass;
	if(! cHELP::issetIndex($headerData, 'srcset' )) {
		$headerData->srcset = array();
	}
	$headerData->srcset = $currentset; 
	set_theme_mod( 'header_image_data', $headerData );
	
	
	// return final obj Srcset to js
	$srcset['curr']   = $currentID;
	wp_send_json_success( $srcset );

}	add_action( 'wp_ajax_custom-header-srcset',   'ajax_header_srcset');



/**
 * Handles removing an image source from responsive custom header 
 *
 * Removes a source data array from header metadata['srcset']
 * Removes source data array from theme mods {header_image_data}.srcset 
 * 
 * Data:
 * Header id $_POST[headerID]
 * Source width $_POST[srcWidth]
 * Img ID $_POST[img_id] 
 * $_POST[action] = 'custom-header-srcset-remove'
 * @uses _img.php unset_source_data()
 * @uses wp.ajax.send()
 * @see respHeader.js removeSRCSuccess
 */

function ajax_remove_header_source() {
	// make sure header id is
	$id = cHELP::issetField($_POST, 'headerID');
	if(! $id ) {
		return;
	}

	/**
	 * Unset the source at the corresponding header ['srcset'] stored either by header metadata or header_image_data 
	 */
	$srcWidth = cHELP::issetField($_POST, 'srcWidth');
	$img_id = cHELP::issetField($_POST, 'img_id');

	unset_source_data( $srcWidth, $img_id ); // will remove from theme mods, no need for ID

	if( unset_source_data( $srcWidth, $img_id, $id ) ) {
		wp_send_json_success( $_POST ); //only confirm after the source got actually removed from header metadata
	}

} add_action( 'wp_ajax_custom-header-source-remove',   'ajax_remove_header_source');


/**
 * Reset srcset on header change
 */
function ajax_reset_header_srcset() {
	$id = (int) cHELP::issetField($_POST, 'headerID');
	if(! $id ) {
		return;
	}
	$data = array();
	
	// send back the right due widths & srcset
	$data['toadd']  = _due_widths(null, $id);
	$data['srcset'] = get_header_srcset($id);
	wp_send_json_success( $data );

} add_action( 'wp_ajax_reset_header_srcset',   'ajax_reset_header_srcset');