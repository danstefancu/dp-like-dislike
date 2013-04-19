<?php
/*
Plugin Name: DP Like Dislike
Plugin URI: http://dreamproduction.com/
Description: Simple voting system.
Author: Dan Ștefancu
Version: 0.1
Author URI: http://stefancu.ro/

*/

// Main function
function like_dislike() {
	$counter = new DP_Like_Dislike();
	$counter->display();
}

// Ajax handling
add_action( 'wp_ajax_nopriv_dp_like_dislike', array('DP_Like_Dislike', 'handle_ajax') );
add_action( 'wp_ajax_dp_like_dislike', array('DP_Like_Dislike', 'handle_ajax') );

wp_enqueue_style( 'ld-general', DP_Like_Dislike::url('dp-like-dislike.css') );

class DP_Like_Dislike {
	public $id;
	public static $layout = 'default';
	private static $scripts_not_loaded = true;

	function __construct( $id = 0 ) {

		// Load js on first instance
		if ( self::$scripts_not_loaded ) {
			// Plugin scripts
			add_action( 'wp_footer', array(__CLASS__, 'enqueue_scripts') );

			self::$scripts_not_loaded = false;
		}

		$this->id = ((int) $id === 0) ? get_the_ID() : $id;
	}

	static function url($path) {
		return $file = plugins_url('/dp-like-dislike/') . ltrim($path, '/');
	}

	/**
	 * Enqueue plugin js and css, plus jstorage v0.3.1, and passes some js variables via wp_localize_script()
	 */
	static function enqueue_scripts() {
		wp_enqueue_script( 'jstorage', self::url('/lib/jstorage.min.js'), array('jquery', 'json2'), '0.1', true);
		wp_enqueue_script( 'ld-general', self::url('dp-like-dislike.js'), array('jquery', 'jstorage'), '0.1', true );

		wp_localize_script(
			'ld-general',
			'ld_ajax',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('ajax-nonce'),
				'loading_image' => self::url('images/loading.gif'),
				'counters' => apply_filters('ld_counters', array('like', 'dislike', 'popularity')),
			)
		);
	}

	/**
	 * Handle AJAX request. Echoes as result a js object with votes and total popularity.
	 *
	 * Saves as meta fields:
	 * - votes (total votes)
	 * - popularity-count (combined popularity)
	 * - like-count (total like actions)
	 * - dislike-count (total dislike actions)
	 */
	function handle_ajax() {

		// Check for nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'ajax-nonce' ) )
			die();

		if ( isset($_POST['user_action']) ) {

			$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
			$action = isset($_POST['user_action']) ? $_POST['user_action'] : '';
			$new_popularity = isset($_POST['popularity']) ? (int) $_POST['popularity'] : 0;
			$old_action = isset($_POST['old_action']) ? $_POST['old_action'] : '';

			$old_popularity = (get_post_meta($id, 'popularity-count', true) !== '') ? get_post_meta($id, 'popularity-count', true) : 0;
			$votes = (get_post_meta($id, 'votes', true) !== '') ? get_post_meta($id, 'votes', true) : 0;
			$total_likes = (get_post_meta($id, 'like-count', true) !== '') ? get_post_meta($id, 'like-count', true) : 0;
			$total_dislikes = (get_post_meta($id, 'dislike-count', true) !== '') ? get_post_meta($id, 'dislike-count', true) : 0;

			switch ($action) {
				case 'like':
					$total_likes++;
					$total_dislikes = ($old_action == 'dislike') ? --$total_dislikes : $total_dislikes;
					break;
				case 'unlike':
					$total_likes--;
					break;
				case 'dislike':
					$total_dislikes++;
					$total_likes = ($old_action == 'like') ? --$total_likes : $total_likes;
					break;
				case 'undislike':
					$total_dislikes--;
					break;
				default :
					break;
			}

			update_post_meta($id, 'like-count', $total_likes);
			update_post_meta($id, 'dislike-count', $total_dislikes);

			$popularity = $old_popularity + $new_popularity;
			update_post_meta($id, 'popularity-count', $popularity);
			$votes++;
			update_post_meta($id, 'votes', $votes);

			$response = array();
			$response['popularity-count'] = $popularity;
			$response['vote-count'] = $votes;
			$response['like-count'] = $total_likes;
			$response['dislike-count'] = $total_dislikes;

			echo json_encode($response);
		}
		die();
	}

	function get_count($key) {
		return (get_post_meta($this->id, $key, true) !== '') ? get_post_meta($this->id, $key, true) : 0;
	}

	function get_like_count() {
		return $this->get_count('like-count');
	}

	function get_dislike_count() {
		return $this->get_count('dislike-count');
	}

	function get_popularity_count() {
		return $this->get_count('popularity-count');
	}

	function get_counter($key) {

		$valid_keys = apply_filters('ld_counters', array('like', 'dislike', 'popularity'));

		// quick validation - defaults to 'like'
		$key = in_array($key, $valid_keys) ? $key : 'like';

		$counter = $this->get_count($key . '-count');
		$return = '';

		$return .= sprintf('<span class="%s-counter like-dislike-counter">', $key);
		$return .= sprintf('<span class="count">%s</span>', $counter);
		$return .= '</span>';

		return apply_filters('ld_get_counter', $return, $key, $this->id);
	}

	function get_like_counter() {
		return apply_filters('ld_get_like_counter', $this->get_counter('like'), $this->id);
	}

	function get_dislike_counter() {
		return apply_filters('ld_get_dislike_counter', $this->get_counter('dislike'), $this->id);
	}

	function get_popularity_counter() {
		return apply_filters('ld_get_popularity_counter', $this->get_counter('popularity'), $this->id);
	}

	function get_button($key) {

		switch ($key) {
			case 'dislike' :
				$title = __('I dislike this', '');
				$text = __('Dislike', '');
				$valid_key = $key;
				break;
			case 'like' :
			default :
				$title = __('I like this', '');
				$text = __('Like', '');
				$valid_key = $key;
				break;
		}
		$button_class = $valid_key . '-button';
		$text_class = $valid_key . '-text';

		$return = '';
		$return .= sprintf('<button class="like-dislike-button %1$s" type="button" data-content-id="%2$d" data-action="%3$s" title="%4$s">', $button_class, $this->id, $valid_key, $title);
		$return .= sprintf('<img src="%s" alt="icon" class="button-icon" />', self::url('images/pixel.gif') );
		$return .= sprintf('<span class="button-text %1$s">%2$s</span>', $text_class, $text);
		$return .= '</button>';

		return apply_filters('ld_get_button', $return, $key, $this->id);
	}

	function get_like_button() {
		return apply_filters('ld_get_like_button', $this->get_button('like'), $this->id);
	}

	function get_dislike_button() {
		return apply_filters('ld_get_dislike_button', $this->get_button('dislike'), $this->id);
	}


	/**
	 * Display vote buttons.
	 *
	 */
	function display( ) {
		?>
		<p class="like-dislike-wrapper">

			<?php echo $this->get_like_button(); ?>

			<?php echo $this->get_like_counter(); ?>

			<?php echo $this->get_dislike_button(); ?>

			<?php echo $this->get_dislike_counter(); ?>

			<?php echo $this->get_popularity_counter(); ?>

			<span class="status">
				<!-- ajax loader -->
			</span>
		</p>
		<?php
	}
}