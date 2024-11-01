<?php
/*
Plugin Name: Twitter Followers
Plugin URI: http://kovshenin.com/wordpress/plugins/twitter-followers/
Description: Display your Twitter followers or people you are following in your sidebar.
Author: Konstantin Kovshenin
Version: 0.3
Author URI: http://kovshenin.com/
License: GPLv3 or later
*/

class Twitter_Followers_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct( 'twitter-followers-widget', 'Twitter Followers', array(
			'description' => 'Display your latest followers on Twitter.',
		) );
	}

	/**
	 * Displays the widget contents.
	 */
	public function widget( $args, $instance ) {
		extract( $args );
		$title = apply_filters( 'widget_title', $instance['title'] );

		echo $args['before_widget'];
		if ( ! empty( $title ) )
			echo $args['before_title'] . $title . $args['after_title'];

		$followers = $this->get_followers( array(
			'username' => $instance['username'],
			'count' => $instance['count'],
			'what' => $instance['what'],
		) );

		if ( is_wp_error( $followers ) ) {
			echo $followers->get_error_message();
		} else {
			foreach ( $followers as $follower ) {
				$screen_name = $follower->screen_name;
				$link = esc_url( sprintf( 'https://twitter.com/%s', $screen_name ) );
				$src = esc_url( $follower->profile_image_url );
				$item = sprintf( '<a class="twitter-follower" href="%s"><img src="%s" alt="%s" /></a>', $link, $src, $screen_name );
				echo $item;
			}
		}

		echo $args['after_widget'];
	}

	/**
	 * Returns an array of followers or following.
	 */
	private function get_followers( $args ) {
		$transient_key = md5( 'twitter-followers-widget-' . print_r( $args, true ) );
		$cached = get_transient( $transient_key );
		if ( $cached )
			return $cached;

		$username = isset( $args['username'] ) ? $args['username'] : '';
		$count = isset( $args['count'] ) ? absint( $args['count'] ) : 10;
		$what = isset( $args['what'] ) && in_array( $args['what'], array( 'followers', 'following' ) ) ? $args['what'] : 'followers';

		$url = esc_url_raw( add_query_arg( 'screen_name', $username, sprintf( 'https://api.twitter.com/1/%s/ids.json?cursor=-1', $what ) ) );
		$followers = wp_remote_get( $url );
		if ( is_wp_error( $followers ) )
			return $followers;

		$followers = wp_remote_retrieve_body( $followers );
		$followers = json_decode( $followers );

		if ( ! $followers || count( $followers->ids ) < 1 )
			return new WP_Error( 'error', 'Could not retrieve followers.' );

		$followers_ids = array_slice( $followers->ids, 0, $count );

		$url = esc_url_raw( add_query_arg( 'user_id', implode( ',', $followers_ids ), 'https://api.twitter.com/1/users/lookup.json' ) );
		$followers = wp_remote_get( $url );
		if ( is_wp_error( $followers ) )
			return $followers;

		$followers = wp_remote_retrieve_body( $followers );
		$followers = json_decode( $followers );

		if ( ! $followers || count( $followers ) < 1 )
			return new WP_Error( 'error', 'Could not retrieve followers data.' );

		set_transient( $transient_key, $followers, apply_filters( 'twitter_followers_widget_cache_timeout', 3600 ) );
		return $followers;
	}

	/**
	 * Validate and update widget options.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['username'] = strip_tags( $new_instance['username'] );
		$instance['count'] = absint( $new_instance['count'] );

		$instance['what'] = 'followers';
		if ( isset( $new_instance['what'] ) && in_array( $new_instance['what'], array( 'following', 'followers' ) ) )
			$instance['what'] = $new_instance['what'];

		return $new_instance;
	}

	/**
	 * Render widget controls.
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : 'Followers';
		$username = isset( $instance['username'] ) ? $instance['username'] : '';
		$count = isset( $instance['count'] ) ? absint( $instance['count'] ) : 10;
		$what = isset( $instance['what'] ) && $instance['what'] == 'following' ? 'following' : 'followers';
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'username' ); ?>"><?php _e( 'Username:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'username' ); ?>" name="<?php echo $this->get_field_name( 'username' ); ?>" type="text" value="<?php echo esc_attr( $username ); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'count' ); ?>"><?php _e( 'Count:' ); ?></label><br />
			<input type="number" min="1" max="100" value="<?php echo esc_attr( $count ); ?>" id="<?php echo $this->get_field_id( 'count' ); ?>" name="<?php echo $this->get_field_name( 'count' ); ?>" />
			<select name="<?php echo $this->get_field_name( 'what' ); ?>">
				<option <?php selected( $what, 'followers' ); ?> value="followers">Followers</option>
				<option <?php selected( $what, 'following' ); ?> value="following">I'm following</option>
			</select>
		</p>
		<?php
	}
}

// Register the widget.
add_action( 'widgets_init', 'twitter_followers_widget_init' );
function twitter_followers_widget_init() {
	register_widget( 'Twitter_Followers_Widget' );
}