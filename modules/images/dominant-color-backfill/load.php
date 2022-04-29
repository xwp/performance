<?php
/**
 * Module Name: Dominant Color backfill
 * Description: Adds support to backfill dominant-color for an images already uploaded.
 * Experimental: Yes
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Add the dominant color metadata to the attachment.
 *
 * @since n.e.x.t
 *
 * @param array $metadata The attachment metadata.
 * @param int   $attachment_id The attachment ID.
 *
 * @return array $metadata
 */
function dominant_color_backfill( $image_meta, $attachment_id ) {

	if( function_exists( 'dominant_color_get' ) && function_exists( 'dominant_color_get_has_transparency' ) && function_exists( 'dominant_color_color_is_light' ) ) {

		/**
		 * Controls if the dominant color code should update image meta if not set.
		 * When an image shown on the site and the meta is not set, the meta will be updated.
		 *
		 * @param bool $add_dominant_color_to_image true to add the dominant color to the image: default true.
		 * @param int $attachment_id
		 */
		if ( ! isset( $image_meta['dominant_color'] ) && apply_filters( 'dominant_color_enable_back_fill', true, $attachment_id ) ) {

			/**
			 * Controls if the dominant color code should update image meta if not set.
			 * When an image shown on the site and the meta is not set, the meta will be updated.
			 *
			 * @param bool $add_dominant_color_on_pageload
			 * set to true save dominant color on the fly as part of the page load.
			 * Set to false schedule the missing color to be set via cron.
			 * @param int $attachment_id
			 */
			if ( apply_filters( 'dominant_color_enable_back_fill_realtime', false, $attachment_id ) ) {
				$image_meta = dominant_color_back_fill( $attachment_id, $image_meta );
			} else {
				wp_schedule_single_event( time(), 'dominant_color_back_fill', array( $attachment_id ) );
			}
		}
	}

	return $image_meta;
}
add_filter( 'dominant_color_img_tag_add_dominant_color_meta', 'dominant_color_metadata', 10, 2 );

/**
 * Get dominant color and adds it to the image meta and saves it for next time.
 *
 * @since n.e.x.t
 *
 * @param int   $attachment_id the attachment id.
 * @param array $image_meta the current image meta.
 *
 * @return array the updated image meta.
 */
function dominant_color_back_fill( $attachment_id, $image_meta ) {

	$dominant_color = dominant_color_get( $attachment_id );
	if ( $dominant_color ) {
		$image_meta['dominant_color']   = $dominant_color;
		$image_meta['has_transparency'] = dominant_color_get_has_transparency( $attachment_id );
		$image_meta['is_light']         = dominant_color_color_is_light( $dominant_color );
		wp_update_attachment_metadata( $attachment_id, $image_meta );
	}

	return $image_meta;
}

/**
 * Get dominant color and saves it to the image meta for next time.
 * called by cron task
 *
 * @since n.e.x.t
 *
 * @param int $attachment_id the attachment id. *
 */
function dominant_color_cron_back_fill( $attachment_id ) {

	$dominant_color = dominant_color_get( $attachment_id );
	if ( $dominant_color ) {
		$image_meta                     = wp_get_attachment_metadata( $attachment_id );
		$image_meta['dominant_color']   = $dominant_color;
		$image_meta['has_transparency'] = dominant_color_get_has_transparency( $attachment_id );
		$image_meta['is_light']         = dominant_color_color_is_light( $dominant_color );
		wp_update_attachment_metadata( $attachment_id, $image_meta );
	}
}
add_action( 'dominant_color_back_fill', 'dominant_color_cron_back_fill', 10, 2 );
