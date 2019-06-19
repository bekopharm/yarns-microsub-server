<?php

/**
 * Class Yarns_Microsub_Parser
 */
class Yarns_Microsub_Parser {

	/**
	 * Loads Parse-This if it hasn't already been loaded.
	 */
	public static function load_parse_this() {

		$response = [];

		$path  = plugin_dir_path( __DIR__ ) . 'vendor/parse-this/includes/';

		// Load classes if they are not already loaded.
		$files = array(
			array( 'class-mf2-post.php', 'MF2_Post' ),
			array( 'class-parse-this.php', 'Parse_This' ),
			array( 'class-parse-this-api.php', 'Parse_This_API' ),
			array( 'class-parse-this-html.php', 'Parse_This_HTML' ),
			array( 'class-parse-this-jsonfeed.php', 'Parse_This_JSONFeed' ),
			array( 'class-parse-this-mf2.php', 'Parse_This_MF2' ),
			array( 'class-parse-this-rss.php', 'Parse_This_RSS' ),
		);
		foreach ( $files as $file ) {
			if ( ! class_exists( $file[1] ) ) {
				require_once $path . $file[0];
				$response[] = $path . $file[0];
			} else {
				$response[] = 'already exists: ' . $file[1];
			}
		}

		// Load functions.php.
		if ( ! function_exists("post_type_discovery"))
		require_once $path . 'functions.php';



		return $response;


	}


	/**
	 * Final clean up on post content before saving or previewing.
	 *
	 * @param array $item The post data to be cleaned.
	 * @param array $feed The feed data.
	 *
	 * @return array
	 */
	public static function clean_post( $item, $feed ) {

		$item['author'] = static::clean_author($item, $feed);


		// dedupe name with summary.
		if ( isset( $item['name'] ) ) {
			if ( isset( $item['summary'] ) ) {
				if ( false !== stripos( $item['summary'], $item['name'] ) ) {
					unset( $item['name'] );
				}
			}
		}

		// dedupe name with content['text'].
		if ( isset( $item['name'] ) ) {
			if ( isset( $item['content']['text'] ) ) {
				if ( false !== stripos( $item['content']['text'], $item['name'] ) ) {
					unset( $item['name'] );
				}
			}
		}

		// Attempt to set a featured image.
		if ( ! isset( $item['featured'] ) ) {
			if ( isset( $item['photo'] ) && is_array( $item['photo'] ) && 1 === count( $item['photo'] ) ) {
				$item['featured'] = $item['photo'];
				unset( $item['photo'] );
			}
		}

		// Add type='entry' for jsonfeeds which might be missing it
		// see https://github.com/jackjamieson2/yarns-microsub-server/issues/80
		if ('jsonfeed' === $feed['type'] && !isset($item['type'])){
			$item['type'] = 'entry';
		}



		/*
		$ref_types = [ 'like-of', 'repost-of', 'bookmark-of', 'in-reply-to', 'listen-of' ];
		// When these types contain an array (name, url, type) it causes together to crash - see https://github.com/cleverdevil/together/issues/80
		// so reduce them to the url.

		foreach ( $ref_types as $ref_type ) {
			if ( isset( $item[ $ref_type ] ) ) {
				if ( is_array( $item[ $ref_type ] ) ) {
					if ( isset( $item[ $ref_type ]['url'] ) ) {
						$item[ $ref_type ] = $item[ $ref_type ]['url'];
					} else {
						$item [ $ref_type ] = ( $item[ $ref_type ] );
					}
				}
			}
		}
		*/

		//

		if ( is_array( $item ) ) {
			$item = array_filter( $item );
		}
		return $item;

	}

	/**
	 * @param array $item_author
	 * @param array $feed_author
	 */
	public static function clean_author( $item, $feed ) {

		// Convert jsonfeed authors to an h-card.
		// see https://github.com/jackjamieson2/yarns-microsub-server/issues/80
		if ('jsonfeed' === $feed['type'] && !isset($feed['author'])){
			if (isset($feed['name'])){
				$feed['author']['name'] = $feed['name'];
			}

			if (isset($feed['url'])){
				$feed['author']['url'] = $feed['url'];
			}

			if (isset($feed['photo'])){
				$feed['author']['photo'] = $feed['photo'];
			}

			// Finally, if we were able to add any properties, make $feed['author'] an h-card
			if (isset($feed['author'])){
				$feed['author']['type'] = 'card';
			}
		}

		// If author is just a string, replace it with an array
		// see https://github.com/jackjamieson2/yarns-microsub-server/issues/75
		if (isset($item['author'])){
			if (! is_array($item['author'])) {
				$item['author'] = array(
					'type' => 'card',
					'name' => $item['author'],
				);
			}
		}

		if (isset($feed['author'])){
			if (! is_array($feed['author'])) {
				$feed['author'] = array(
					'type' => 'card',
					'name' => $feed['author'],
				);
			}
		}

		if ( isset ( $feed['author'] ) && isset ( $item['author'] ) ) {
			$item['author'] = array_merge( $feed['author'], $item['author'] );
		} elseif ( isset( $feed['author']) && ! isset( $item['author'] ) ) {
			$item['author'] = $feed['author'];
		}

		if ( ! isset($item['author'] ) ){
			return;
		}

		// Some feeds return multiple author photos, but only one can be shown.
		if ( isset( $item['author']['photo'] ) ) {
			if ( is_array( $item['author']['photo'] ) ) {
				$item['author']['photo'] = $item['author']['photo'][0];
			}
		}

		// if author['email'] is set, but author['name'] is not, then copy email to name
		if ( isset( $item['author']['email'] ) & ! isset($item['author']['name']) ) {
			$item['author']['name'] = $item['author']['email'];
		}




		return $item['author'];


	}

	/**
	 * Searches a URL for feeds
	 *
	 * @param string $query The query string to be searched.
	 *
	 * @return array|void
	 */
	public static function search( $query ) {
		// @@todo Check if the content itself is an rss feed and if so just return that.
		// Check if $query is a valid URL, if not try to generate one
		$url = static::validate_url( $query );
		// Search using Parse-This.
		static::load_parse_this(); // Load Parse-This if it hasn't already been loaded.
		$search  = new Parse_This( $url );
		$results = $search->fetch_feeds();

		Yarns_MicroSub_Plugin::debug_log( 'Searched ' . $url . ': ' . wp_json_encode( $results ) );
		return $results;
	}



	/**
	 * Returns a preview of the feed
	 *
	 * @param string $url URL of the feed to be previewed.
	 *
	 * @return mixed
	 */
	public static function preview( $url ) {
		$preview = static::parse_feed( $url, 5, true );

		Yarns_MicroSub_Plugin::debug_log( 'Parsed ' . $url . ': ' . wp_json_encode( $preview ) );
		return $preview;
	}

	/**
	 * Parses feed at $url.  Determines whether the feed is h-feed or rss and passes to appropriate
	 * function.
	 *
	 * @param string  $url URL to be parsed.
	 * @param int     $count Number of posts to be returned.
	 * @param boolean $preview Whether or not this is just a preview.
	 *
	 * @todo: add parameter for date of last polled update.
	 *
	 * @return array|void
	 */
	public static function parse_feed( $url, $count = 20, $preview = false ) {
		$parse_start_time = time();
		if ( ! $url ) {
			return;
		}

		$args = array(
			'alternate'  => false,
			'return'     => 'feed',
			'follow'     => true,
			'limit'      => $count,
			'html'       => true, // If mf2 parsing does not work look for html parsing
			'references' => true, // Store nested citations as references per the JF2 spec
		);

		if ( true === $preview ) {
			// When follow == true, Parse-This does some extra fetching to get an author hcard.
			// Previews should be fast, so skip this step.
			$args['follow'] = false;
		}

		static::load_parse_this(); // Load Parse-This if it hasn't already been loaded.
		$parse = new Parse_This( $url );
		$parse->fetch();
		$parse->parse( $args );
		$feed = $parse->get();

		if ( isset( $feed['items'] ) ) {
			foreach ( $feed['items'] as $key => $feeditem ) {
				$feed['items'][ $key ] = static::clean_post( $feeditem, $feed );
			}
		}

		$parse_end_time      = time();
		$parse_duration      = $parse_end_time - $parse_start_time;
		$feed['_parse_time'] = $parse_duration;
		$feed['_post_limit'] = $count;

		return $feed;
	}


	/*
	* UTILITY FUNCTIONS
	*
	*/

	/**
	 * Corrects invalid URLs if possible
	 *
	 * @param string $possible_url URL to be validated.
	 *
	 * @return string
	 */
	public static function validate_url( $possible_url ) {
		// If it is already a valid URL, return as-is.
		if ( static::is_url( $possible_url ) ) {
			return $possible_url;
		}

		// If just a word was entered, append .com.
		if ( preg_match( '/^[a-z][a-z0-9]+$/', $possible_url ) ) {
			// if just a word was entered, append .com.
			$possible_url = $possible_url . '.com';
		}

		// If missing a scheme, prepend with 'http://', otherwise return as-is.
		return wp_parse_url( $possible_url, PHP_URL_SCHEME ) === null ? 'http://' . $possible_url : $possible_url;
	}

	/**
	 * Returns true if $query is a valid URL
	 *
	 * @param string $query The URL to test.
	 *
	 * @return bool
	 */
	public static function is_url( $query ) {
		if ( filter_var( $query, FILTER_VALIDATE_URL ) ) {
			return true;
		} else {
			return false;
		}
	}

}

Yarns_Microsub_Parser::load_parse_this();

class Yarns_Parse_This extends Parse_This {
	/**
	 * Fetches a list of feeds
	 *
	 * @param string $url URL to scan
	 */
	public function fetch_feeds( $url = null ) {
		if ( ! $url ) {
			$url = $this->url;
		}
		if ( empty( $url ) ) {
			return new WP_Error( 'invalid-url', __( 'A valid URL was not provided.', 'indieweb-post-kinds' ) );
		}
		$fetch = $this->fetch( $url );
		if ( is_wp_error( $fetch ) ) {
			return $fetch;
		}
		// A feed was given
		if ( $this->content instanceof SimplePie ) {
			if ( ! class_exists( 'Parse_This_RSS', false ) ) {
				require_once plugin_dir_path( __FILE__ ) . '/class-parse-this-rss.php';
			}
			return array(
				'results' => array(
					array(
						'url'        => $url,
						'type'       => 'feed',
						'_feed_type' => Parse_This_RSS::get_type( $this->content ),
						'name'       => $this->content->get_title(),
					),
				),
			);
		}
		if ( $this->doc instanceof DOMDocument ) {
			$xpath = new DOMXPath( $this->doc );
			// Fetch and gather <link> data.
			$links = array();
			foreach ( $xpath->query( '(//link|//a)[@rel and @href]' ) as $link ) {
				$rel   = $link->getAttribute( 'rel' );
				$href  = $link->getAttribute( 'href' );
				$title = $link->getAttribute( 'title' );
				$type  = self::get_feed_type( $link->getAttribute( 'type' ) );
				if ( in_array( $rel, array( 'alternate', 'feed' ), true ) && ! empty( $type ) ) {
					$links[] = array_filter(
						array(
							'url'        => WP_Http::make_absolute_url( $href, $url ),
							'type'       => 'feed',
							'_feed_type' => $type,
							'name'       => $title,
						)
					);
				}
			}
			// Check to see if the current page is an h-feed
			$this->parse( array( 'feed' => true ) );
			if ( isset( $this->jf2['type'] ) && 'feed' === $this->jf2['type'] ) {
				$links[] = array_filter(
					array(
						'url'        => $url,
						'type'       => 'feed',
						'_feed_type' => 'microformats',
						'name'       => $this->jf2['name'],
					)
				);
			} elseif ( isset( $this->jf2['items'] ) ) {
				foreach ( $this->jf2['items'] as $item ) {
					if ( 'feed' === $item['type'] && isset( $item['uid'] ) ) {
						$links[] = array_filter(
							array(
								'url'        => $item['uid'],
								'type'       => 'feed',
								'_feed_type' => 'microformats',
								'name'       => ifset( $item['name'] ),
							)
						);
					}
				}
			}
			// Sort feeds by priority
			$rank = array(
				'jf2feed'      => 0,
				'microformats' => 1,
				'jsonfeed'     => 2,
				'atom'         => 3,
				'rss'          => 4,
			);
			usort(
				$links,
				function( $a, $b ) use ( $rank ) {
					return $rank[ $a['_feed_type'] ] > $rank[ $b['_feed_type'] ];
				}
			);

			return array( 'results' => $links );
		}
		return new WP_Error( 'unknown error', null, $this->content );
	}
}