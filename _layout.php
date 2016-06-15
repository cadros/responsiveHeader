<?php
/**
 * RESPONSIVE CUSTOM HEADER LAYOUTS
 */

 /**
 * Get custom header img data to use for custom output
 *
 * @uses wp-inlcudes/theme.php get_custom_header(), get_header_image()
 * @uses _img.php get_header_srcset()
 * @return array Header data
 *
 */
function respHeader_data() {
	if( ! get_header_image() ) {
		return; // no image set
	}
	$h = get_custom_header();
	if( ! $h ) {
		return; // just in case
	}
	$id = $h->attachment_id;
	if( ! $id ) {
		// means default headers, use the data as it is
		return apply_filters( 'respHeader', (array) $h);
	} 

	// Otherwise, get header img info
	$h = (array) $h;
	$postData = get_post( $id );
	$h['title'] = $postData->post_title;
	$h['description'] = $postData->post_excerpt;
	$h['maybebuttons'] = $postData->post_content;

	$alt = get_post_meta( $id, '_wp_attachment_image_alt' );
	! $alt && $alt = get_the_title( $id);
	! $alt && $alt = "Site header image";
	$h['alt'] = $alt;

	// add srcset
	$srcset = get_header_srcset();

	$h['srcset'] = $srcset;

	return apply_filters( 'respHeader', $h);
}


/**
 * Responsive Header Output
 * @uses respHeader_data()
 */
function respHeader() {

	// bail if no header
	$header = respHeader_data();
	if( ! $header ) {
		return;
	}
	/**
	* A comprehensive article on an img srcset attr by a google guy :
	 * @link https://jakearchibald.com/2015/anatomy-of-responsive-images/
	 * mind that srcset's priority to fetch img is 1) cached img 2) the nearest matching veiwport width image
	 */
	if( cHELP::issetField( $header, 'srcset') ) :
		$sources = array();

		// prepare img sources
		foreach( $header['srcset'] as $src ) {
			$sources[] = $src['guid'].' '.$src['width'] . 'w';
		}
		// add original
		$sources[] = $header['url'].' '.$header['width'] . 'w';

		$respPart = ' sizes="100vw" '; //@todo customizable sizes to replace this hardcoded
		$respPart .= ' srcset="' . join( ',', $sources );
		$respPart .= '" ';
	endif;


	// original img width
	$hw = null; $hh = null;
	if( $header['width']) {
		$hw = ' width="'.$header['width'].'"';
	}
	// original img height
	if( $header['height']) {
		$hh = ' height="'.$header['height'].'"';
	}

	$h  = '<img class="responsive_Header" '; 
	$h .= $respPart;

	$h .= $hw.$hh;
	$h .= ' src="'.$header['url'].'"';
	$h .= ' alt="'.$header['alt'].'"';
	$h .= ' >';
	echo $h;
}