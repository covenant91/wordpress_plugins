<?php
/**
 * Shared utility functions.
 */
class WSP_Helpers {

	/**
	 * Truncate a caption to $max_bytes bytes, respecting word boundaries.
	 *
	 * @param string $text
	 * @param int    $max_bytes
	 * @return string
	 */
	public static function truncate_caption( $text, $max_bytes ) {
		if ( strlen( $text ) <= $max_bytes ) {
			return $text;
		}
		$truncated = substr( $text, 0, $max_bytes );
		// Step back to the last space to avoid cutting mid-word.
		$last_space = strrpos( $truncated, ' ' );
		if ( $last_space !== false ) {
			$truncated = substr( $truncated, 0, $last_space );
		}
		return rtrim( $truncated ) . '…';
	}

	/**
	 * Append a URL to a caption if it does not already contain it.
	 *
	 * @param string $caption
	 * @param string $url
	 * @return string
	 */
	public static function append_url( $caption, $url ) {
		if ( strpos( $caption, $url ) !== false ) {
			return $caption;
		}
		return rtrim( $caption ) . "\n\n" . $url;
	}

	/**
	 * Normalise a comma-separated hashtag string.
	 * Ensures each tag starts with #, strips invalid characters.
	 *
	 * @param string $raw  Comma-separated, e.g. "wordpress, coding, webdev"
	 * @return string      e.g. "#wordpress #coding #webdev"
	 */
	public static function normalise_hashtags( $raw ) {
		$tags = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
		$out  = array();
		foreach ( $tags as $tag ) {
			// Remove leading # if already present, then strip non-alphanumeric/underscore.
			$tag = ltrim( $tag, '#' );
			$tag = preg_replace( '/[^\w]/u', '', $tag );
			if ( $tag ) {
				$out[] = '#' . $tag;
			}
		}
		return implode( ' ', $out );
	}

	/**
	 * Get the full-size featured image URL for a post.
	 *
	 * @param int $post_id
	 * @return string|null  Public URL or null if no image.
	 */
	public static function get_featured_image_url( $post_id ) {
		$thumbnail_id = get_post_thumbnail_id( $post_id );
		if ( ! $thumbnail_id ) {
			return null;
		}
		$src = wp_get_attachment_image_src( $thumbnail_id, 'full' );
		return ( $src && ! empty( $src[0] ) ) ? $src[0] : null;
	}

	/**
	 * Return true when a URL looks like a local/staging URL.
	 * Instagram requires publicly accessible images.
	 *
	 * @param string $url
	 * @return bool
	 */
	public static function is_local_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return true;
		}
		$local_patterns = array( 'localhost', '127.0.0.1', '::1', '.local', '.test', '.dev', 'staging.' );
		foreach ( $local_patterns as $pattern ) {
			if ( strpos( $host, $pattern ) !== false ) {
				return true;
			}
		}
		return false;
	}
}
