<?php 
/*
Plugin Name: WP Facebook Open Graph protocol
Plugin URI: http://wordpress.org/extend/plugins/wp-facebook-open-graph-protocol/
Description: Adds proper Facebook Open Graph Meta tags and values to your site so when links are shared it looks awesome! Works on Google + and Linkedin too!
Version: 2.1
Author: Chuck Reynolds
Author URI: http://chuckreynolds.us
License: GPL2
*/
/*
	Copyright 2011 WordPress Facebook Open Graph protocol plugin (email: chuck@rynoweb.com)
	
	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as 
	published by the Free Software Foundation.
	
	This program is distributed in the hope that it will be useful, 
	but WITHOUT ANY WARRANTY; without even the implied warranty of 
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the 
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
*/

define('WPFBOGP_VERSION', '2.1');

// Start up the engine 
class OGPManager {


    /**
     * This is our constructor, which is private to force the use of
     * getInstance() to make this a Singleton
     *
     * @return OGPManager
     */
    public function __construct() {
        add_action      ( 'admin_menu',				array( $this, 'menu_settings'	) );
        add_action      ( 'admin_init',				array( $this, 'reg_settings'	) );
        add_action		( 'wp_head',				array( $this, 'ogp_head'		) );
        add_action		( 'after_setup_theme',		array( $this, 'excerpts_fix'	) );
        add_filter		( 'language_attributes',	array( $this, 'ogp_namespace'	) );


    }

   /**
     * add OGP namespace per ogp.me schema
     *
     * @return OGPManager
     */

	public function ogp_namespace($output) {
		return $output.' xmlns:og="http://ogp.me/ns#"';
	}
    /**
     * build out settings page and meta boxes
     *
     * @return OGPManager
     */

    public function menu_settings() {
        add_submenu_page('options-general.php', 'Facebook OGP', 'Facebook OGP', 'manage_options', 'wpfbogp', array( $this, 'settings_page' ));
    }

    /**
     * Register settings
     *
     * @return OGPManager
     */


    public function reg_settings() {
        register_setting('wpfbogp', 'wpfbogp', array( $this, 'wpfbogp_validate' ));
    }

    /**
     * Search through posts and find images
     *
     * @return OGPManager
     */

	public function find_images() {
		global $post, $posts;
	
		// Grab content and match first image
		$content = $post->post_content;
		$output = preg_match_all( '/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $content, $matches );
	
		// Make sure there was an image that was found, otherwise return false
		if ( $output === FALSE ) {
			return false;
		}
	
		$wpfbogp_images = array();
		foreach ( $matches[1] as $match ) {
			// If the image path is relative, add the site url to the beginning
			if ( ! preg_match('/^https?:\/\//', $match ) ) {
				// Remove any starting slash with ltrim() and add one to the end of site_url()
				$match = site_url( '/' ) . ltrim( $match, '/' );
			}
			$wpfbogp_images[] = $match;
		}
	
		return $wpfbogp_images;
	}

    /**
     * load OGP data in the head
     *
     * @return OGPManager
     */

	public function ogp_head() {
//		
		// grab the settings array
		$options	= get_option('wpfbogp');
		// check to see if you've filled out one of the required fields and announce if not
		$admins_id	= $options['admins_ids'];
		$fb_app_id	= $options['app_id'];

		if( empty( $admins_id ) && empty( $fb_app_id ))
			return;

		// FUCK IT, LETS DO IT LIVE
		echo "\n<!-- WordPress Facebook Open Graph protocol plugin (WPFBOGP v".WPFBOGP_VERSION.") http://rynoweb.com/wordpress-plugins/ -->\n";
			
		// check for either app ID or profile ID
		if ( isset( $admins_id ) && ! empty( $admins_id ) )
			echo '<meta property="fb:admins" content="' . esc_attr( apply_filters( 'wpfbogp_app_id', $admins_id ) ) . '">' . "\n";
			
		if ( isset( $fb_app_id ) && ! empty( $fb_app_id ) )
			echo '<meta property="fb:app_id" content="' . esc_attr( apply_filters( 'wpfbogp_app_id', $fb_app_id ) ) . '">' . "\n";

		// now lets get some post specific stuff
		global $post;
			
		// build out URL variables
		$base_url	= get_bloginfo( 'url' );
		$spec_url	= 'http' . (is_ssl() ? 's' : '') . "://".$_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$ogp_url	= is_home() || is_front_page() ? $base_url : $spec_url;
	
		// build out title variables
		$base_name	= get_bloginfo( 'name' );
		$spec_name	= get_the_title();
		$ogp_title	= is_home() || is_front_page() ? $base_name : $spec_name;

		// build out description variables

		if ( is_singular() && has_excerpt( $post->ID ) ) {
			$ogp_description = strip_tags( get_the_excerpt( $post->ID ) );
		} elseif (is_singular() ) {
			$ogp_description = str_replace( "\r\n", ' ' , substr( strip_tags( strip_shortcodes( $post->post_content ) ), 0, 160 ) );
		} else {
			$ogp_description = get_bloginfo( 'description' );
		}
		
		// build out type variables
		$ogp_type	= is_singular('post') ? 'article' : 'website';
	

		// display my the OGP tags LIKE A BOSS
		echo '<meta property="og:url" content="' . esc_url( apply_filters( 'wpfbogp_url', $ogp_url ) ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( apply_filters( 'wpfbogp_title', $ogp_title ) ) . '">' . "\n";
		echo '<meta property="og:site_name" content="' . get_bloginfo( 'name' ) . '">' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( apply_filters( 'wpfbogp_description', $ogp_description ) ) . '">' . "\n";
		echo '<meta property="og:type" content="' . esc_attr( apply_filters( 'wpfbpogp_type', $ogp_type ) ) . '">' . "\n";
		echo '<meta property="og:locale" content="' . strtolower( esc_attr( get_locale() ) ) . '">' . "\n";

		// Find/output any images for use in the OGP tags
		$ogp_images = array();
			
		// Only find images if it isn't the homepage and the fallback isn't being forced
		if ( ! is_home() && $options['force_fallback'] != 1 ) {
			// Find featured thumbnail of the current post/page
			if ( function_exists( 'has_post_thumbnail' ) && has_post_thumbnail( $post->ID ) ) {
				$thumbnail_src = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'medium' );
				$ogp_images[] = $thumbnail_src[0]; // Add to images array
			}
				
			if ( $this->find_images() !== false && is_singular() ) { // Use our function to find post/page images
				$ogp_images = array_merge( $ogp_images, $this->find_images() ); // Returns an array already, so merge into existing
			}
		}
			
		// Add the fallback image to the images array (which is empty if it's being forced)
		if ( isset( $options['fallback_img'] ) && $options['fallback_img'] != '') {
			$ogp_images[] = $options['fallback_img']; // Add to images array
		}
			
		// Make sure there were images passed as an array and loop through/output each
		if ( ! empty( $ogp_images ) && is_array( $ogp_images ) ) {
			foreach ( $ogp_images as $image ) {
				echo '<meta property="og:image" content="' . esc_url( apply_filters( 'wpfbogp_image', $image ) ) . '">' . "\n";
			}
		} else {
			// No images were outputted because they have no default image (at the very least)
			echo "<!-- There is not an image here as you haven't set a default image in the plugin settings! -->\n"; 
		}


		echo "<!-- // end wpfbogp -->\n"; // time to go home, kids

	}

    /**
     * twentyten and twentyeleven add crap to the excerpt so lets check for that and remove
     *
     * @return OGPManager
     */

	public function excerpts_fix() {
		remove_filter('get_the_excerpt','twentyten_custom_excerpt_more');
		remove_filter('get_the_excerpt','twentyeleven_custom_excerpt_more');
	}

    /**
     * Display main options page structure
     *
     * @return OGPManager
     */
     
    public function settings_page() { ?>
    
        <div class="wrap">
        <div class="icon32" id="icon-ogp"><br></div>
        <h2><?php _e('Facebook OGP Settings') ?></h2>
		<div id="poststuff" class="metabox-holder has-right-sidebar">
		<div id="side-info-column" class="inner-sidebar">
			<div class="meta-box-sortables">
				<div id="about" class="postbox">
					<h3 class="hndle" id="about-sidebar"><?php _e('About the Plugin:') ?></h3>
					<div class="inside">
						<p>Talk to <a href="http://twitter.com/chuckreynolds" target="_blank">@ChuckReynolds</a> on twitter or please fill out the <a href="http://rynoweb.com/wordpress-plugins/" target="_blank">plugin support form</a> for bugs or feature requests.</p>
						<p><?php _e('<strong>Enjoy the plugin?</strong>') ?><br />
						<a href="http://twitter.com/?status=I'm using @chuckreynolds's WordPress Facebook Open Graph plugin - check it out! http://rynoweb.com/wordpress-plugins/" target="_blank"><?php _e('Tweet about it') ?></a> <?php _e('and consider donating.') ?></p>
						<p><?php _e('<strong>Donate:</strong> A lot of hard work goes into building plugins - support your open source developers. Include your twitter username and I\'ll send you a shout out for your generosity. Thank you!') ?><br />
						<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
						<input type="hidden" name="cmd" value="_s-xclick">
						<input type="hidden" name="hosted_button_id" value="GWGGBTBJTJMPW">
						<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
						<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
						</form></p>
					</div>
				</div>
			</div>
			
			<div class="meta-box-sortables">
				<div id="about" class="postbox">
					<h3 class="hndle" id="about-sidebar"><?php _e('Relevant Information:') ?></h3>
					<div class="inside">
						<p><a href="http://ogp.me" target="_blank">The Open Graph Protocol</a><br />
						<a href="https://developers.facebook.com/docs/opengraph/" target="_blank">Facebook Open Graph Docs</a><br />
						<a href="https://developers.facebook.com/docs/insights/" target="_blank">Insights: Domain vs App vs Page</a><br />
						<a href="https://developers.facebook.com/docs/reference/plugins/like/" target="_blank">How To Add a Like Button</a></p>
					</div>
				</div>
			</div>
		</div> <!-- // #side-info-column .inner-sidebar -->


		<div id="post-body" class="has-sidebar">
			<div id="post-body-content" class="has-sidebar-content">
				<div id="normal-sortables" class="meta-box-sortables">
					<div id="about" class="postbox">
						<div class="inside">

						<form method="post" action="options.php">
						<?php
						settings_fields('wpfbogp');
						$options = get_option('wpfbogp');
						// get options for display
						$admins_id	= (isset($options['admins_ids'])		? $options['admins_ids'] 	: '');
						$fb_app_id	= (isset($options['app_id'])			? $options['app_id']		: '');
						$fb_image	= (isset($options['fallback_img'])		? $options['fallback_img']	: '');
						$fb_force	= (isset($options['force_fallback']) && $options['force_fallback'] == 1 ? 'checked="checked"' : '');
						?>

						<table class="form-table">
						<tr valign="top">
							<th scope="row"><label for="wpfbogp[admins_ids]"><?php _e('Facebook User Account ID:') ?></label></th>
							<td>
							<input type="text" name="wpfbogp[admins_ids]" value="<?php echo $admins_id ?>" class="regular-text" />
							<br />
							<?php _e('For personal sites use your Facebook User ID here. <em>(You can enter multiple by separating each with a comma)</em>, if you want to receive Insights about the Like Buttons. The meta values will not display in your site until you\'ve completed this box.<br />
								<strong>Find your ID</strong> by going to the URL like this: http://graph.facebook.com/yourusername') ?>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row"><label for="wpfbogp[app_id]"><?php _e('Facebook Application ID:') ?></label></th>
							<td>
							<input type="text" name="wpfbogp[app_id]" value="<?php echo $fb_app_id; ?>" class="regular-text" />
							<br />
							<?php _e('For business and/or brand sites use Insights on an App ID as to not associate it with a particular person. You can use this with or without the User ID field. Create an app and use the "App ID": <a href="https://www.facebook.com/developers/apps.php" target="_blank">Create FB App</a>.') ?>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row"><label for="wpfbogp[fallback_img]"><?php _e('Default Image URL to use:') ?></label></th>
							<td>
							<input type="text" name="wpfbogp[fallback_img]" value="<?php echo $fb_image; ?>" class="large-text" />
							<br />
							<?php _e('Full URL including http:// to the default image to use if your posts/pages don\'t have a featured image or an image in the content. <strong>The image is recommended to be 200px by 200px</strong>.<br />
								You can use the WordPress <a href="upload.php">media uploader</a> if you wish, just copy the location of the image and put it here.') ?>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row"><label for="wpfbogp[force_fallback]"><?php _e('Force Fallback Image as Default') ?></label></th>
							<td>
								<input type="checkbox" name="wpfbogp[force_fallback]" value="1" <?php echo $fb_force; ?> />
								<label><?php _e('Use this if you want to use the Default Image for everything instead of looking for featured/content images.') ?></label>
							</td>
						</tr>
						</table>
		
						<p class="submit"><input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" /></p>
						</form>
						<br class="clear" />
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	</div>    
    
    <?php }

    /**
     * sanitize inputs. accepts an array, return a sanitized array.
     *
     * @return OGPManager
     */

	public function wpfbogp_validate($input) {
		$input['admins_ids']		= wp_filter_nohtml_kses($input['admins_ids']);
		$input['app_id']			= wp_filter_nohtml_kses($input['app_id']);
		$input['fallback_img']		= wp_filter_nohtml_kses($input['fallback_img']);
		$input['force_fallback']	= ($input['force_fallback'] == 1)  ? 1 : 0;
		return $input;
	}

/// end class
}


// Instantiate our class
$OGPManager = new OGPManager();
