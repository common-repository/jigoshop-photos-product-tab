<?php
/*
 * Plugin Name: Jigoshop Photos Product Tab
 * Plugin URI: https://wordpress.org/plugins/jigoshop-photos-product-tab/
 * Description: Extends Jigoshop to allow you to display all images attached to a product in a new tab on the single product page.
 * Version: 1.3
 * Author: Seb's Studio
 * Author URI: http://www.sebs-studio.com
 *
 * Text Domain: jigoshop-photos-product-tab
 * Domain Path: /lang/
 *
 * Copyright 2013 Seb's Studio
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if(!defined('ABSPATH')) exit; // Exit if accessed directly

// Required minimum version of WordPress.
if(!function_exists('jigo_photos_tab_min_required')){
	function jigo_photos_tab_min_required(){
		global $wp_version;
		$plugin = plugin_basename(__FILE__);
		$plugin_data = get_plugin_data(__FILE__, false);

		if(version_compare($wp_version, "3.3", "<")){
			if(is_plugin_active($plugin)){
				deactivate_plugins($plugin);
				wp_die("'".$plugin_data['Name']."' requires WordPress 3.3 or higher, and has been deactivated! Please upgrade WordPress and try again.<br /><br />Back to <a href='".admin_url()."'>WordPress Admin</a>.");
			}
		}
	}
	add_action('admin_init', 'jigo_photos_tab_min_required');
}

// Checks if the Jigoshop plugins is installed and active.
if(in_array('jigoshop/jigoshop.php', apply_filters('active_plugins', get_option('active_plugins')))){

	/* Localisation */
	$locale = apply_filters('plugin_locale', get_locale(), 'jigoshop-photos-product-tab');
	load_textdomain('jigoshop-photos-product-tab', WP_PLUGIN_DIR."/".plugin_basename(dirname(__FILE__)).'/lang/jigoshop-photos-product-tab-'.$locale.'.mo');
	load_plugin_textdomain('jigoshop-photos-product-tab', false, dirname(plugin_basename(__FILE__)).'/lang/');

	if(!class_exists('Jigoshop_Photo_Product_Tab')){
		class Jigoshop_Photo_Product_Tab{

			public static $plugin_prefix;
			public static $plugin_url;
			public static $plugin_path;
			public static $plugin_basefile;

			private $tab_data = false;

			/**
			 * Gets things started by adding an action to
			 * initialize this plugin once Jigoshop is
			 * known to be active and initialized.
			 */
			public function __construct(){
				Jigoshop_Photo_Product_Tab::$plugin_prefix = 'jigo_photos_tab_';
				Jigoshop_Photo_Product_Tab::$plugin_basefile = plugin_basename(__FILE__);
				Jigoshop_Photo_Product_Tab::$plugin_url = plugin_dir_url(Jigoshop_Photo_Product_Tab::$plugin_basefile);
				Jigoshop_Photo_Product_Tab::$plugin_path = trailingslashit(dirname(__FILE__));
				add_action('init', array(&$this, 'jigoshop_init'), 0);
				// Settings
				add_action('init', array(&$this, 'install_settings'));
			}

			public function install_settings(){
				Jigoshop_Base::get_options()->install_external_options_tab(__('Photos Product Tab', 'jigoshop-photos-product-tab'), $this->photo_tab_settings());
			}

			/**
			 * Adds a tab section in the settings to control the photo tab.
			 */
			public function photo_tab_settings(){
				$setting = array();

				$setting[] = array(
								'name' => __('Photos Product Tab', 'jigoshop-photos-product-tab'),
								'type' => 'title',
								'desc' => '',
								'id' => 'photos_product_tab'
							 );

				$setting[] = array(
								'name' => __('Size of Photos', 'jigoshop-photos-product-tab'),
								'desc' 		=> __('What size would you like to display ?', 'jigoshop-photos-product-tab'),
								'id' 		=> 'jigoshop_product_photo_tab_size',
								'type' 		=> 'select',
								'choices'	=> array(
													'thumbnail' => __('Thumbnail', 'jigoshop-photos-product-tab'),
													'medium'	=> __('Medium', 'jigoshop-photos-product-tab'),
													'large'	=> __('Large', 'jigoshop-photos-product-tab'),
													'full'	=> __('Full / Original', 'jigoshop-photos-product-tab'),
												),
								'std'		=> 'thumbnail',
							 );

				$setting[] = array(
								'name' => __('Enable Lightbox', 'jigoshop-photos-product-tab'),
								'tip' 		=> __('Enable the use of lightbox for the photos in the tab.', 'jigoshop-photos-product-tab'),
								'id' 		=> 'jigoshop_product_photo_tab_lightbox',
								'type' 		=> 'checkbox',
								'std'		=> '',
							  );

				return $setting;
			}

			/**
			 * Init Jigoshop Photo Product Tab extension once we know Jigoshop is active
			 */
			public function jigoshop_init(){
				// backend stuff
				add_filter('plugin_row_meta', array(&$this, 'add_support_link'), 10, 2);
				// frontend stuff
				add_action('jigoshop_product_tabs', array(&$this, 'photos_product_tabs'), 999);
				add_action('jigoshop_product_tab_panels', array(&$this, 'photos_product_tabs_panel'));
				add_action('wp_enqueue_scripts', array(&$this, 'photos_tab_front_style'));
				// Write panel
				add_action('jigoshop_product_write_panel_tabs', array(&$this, 'write_photo_tab'));
				add_action('jigoshop_product_write_panels', array(&$this, 'write_photo_tab_panel'));
				add_action('jigoshop_process_product_meta', array(&$this, 'write_photo_tab_panel_save'));
			}

			/**
			 * Add links to plugin page.
			 */
			public function add_support_link($links, $file){
				if(!current_user_can('install_plugins')){
					return $links;
				}
				if($file == Jigoshop_Photo_Product_Tab::$plugin_basefile){
					$links[] = '<a href="http://www.sebs-studio.com/forum/jigoshop-photos-product-tab/" target="_blank">'.__('Support', 'jigoshop-photos-product-tab').'</a>';
					$links[] = '<a href="http://www.sebs-studio.com/wp-plugins/jigoshop-extensions/" target="_blank">'.__('More Jigoshop Extensions', 'jigoshop-photos-product-tab').'</a>';
				}
				return $links;
			}

			/**
			 * Add stylesheet to the front for the tab content.
			 */
			function photos_tab_front_style(){
				wp_enqueue_style('jigoshop-photos-product-tab', plugins_url('/assets/css/jigoshop-photos-product-tab.css', __FILE__));
			}

			/**
			 * Write the photos tab on the product view page.
			 */
			public function photos_product_tabs($current_tab){
				global $post, $wpdb;

				/**
				 * Checks if any photos are attached to the product.
				 */
				$countPhotos = $wpdb->get_var("SELECT COUNT(*) FROM ".$wpdb->posts." WHERE `post_type`='attachment' AND `post_parent`='".$post->ID."'");

				$photo_tab = get_post_meta($post->ID, 'jigoshop_disable_product_photos', true);
				if($countPhotos > 0 && $photo_tab == ''){
				?>
					<li<?php if($current_tab == '#tab-photos'){ echo ' class="active"'; } ?>><a href="#tab-photos"><?php echo __('Photos', 'jigoshop-photos-product-tab'); ?></a></li>
				<?php
				}
			}

			/**
			 * Write the photos tab panel on the product view page.
			 * In Jigoshop these are handled by templates.
			 */
			public function photos_product_tabs_panel(){
				global $post, $wpdb;

				/**
				 * Checks if any photos are attached to the product.
				 */
				$countPhotos = $wpdb->get_var("SELECT COUNT(*) FROM ".$wpdb->posts." WHERE `post_type`='attachment' AND `post_parent`='".$post->ID."'");

				$photo_tab = get_post_meta($post->ID, 'jigoshop_disable_product_photos', true);
				if($countPhotos > 0 && $photo_tab == ''){
					echo '<div class="panel" id="tab-photos">';
					echo '<h2>'.__('Photos', 'jigoshop-photos-product-tab').'</h2>';
					$argsThumb = array(
						'order'			 => 'ASC',
						'post_type'		 => 'attachment',
						'numberposts'	 => -1,
						'post_parent'	 => $post->ID,
						'post_mime_type' => 'image',
						'post_status'	 => null
					);
					$attachments = get_posts($argsThumb);
					if($attachments){
					  echo '<ul>';
						foreach($attachments as $attachment){
							$photo_attr = array(
											'class'	=> "product-photo photo-attachment-".$attachment->ID."",
											'alt'   => trim(strip_tags(get_post_meta($attachment->ID, '_wp_attachment_image_alt', true))),
							);
							echo '<li>';
							//$jigo_light = get_option('jigoshop_enable_lightbox');
							$photo_light = get_option('jigoshop_product_photo_tab_lightbox');
							if($photo_light == 'yes'){ echo '<a href="'.wp_get_attachment_url($attachment->ID).'" rel="thumbnails" class="zoom">'; }
							echo wp_get_attachment_image($attachment->ID, get_option('jigoshop_product_photo_tab_size'), false, $photo_attr);
							if($photo_light == 'yes'){ echo '</a>'; }
							echo '</li>';
						}
					  echo '</ul>';
					}
					echo '</div>';
				}
			}

			/**
			 * Product Meta Data.
			 *
			 * Adds the option to disable the photo tab on the product page.
			 */
			function write_photo_tab_panel(){
				global $post;

				$disabled = get_post_meta($post->ID, 'jigoshop_disable_product_photos', true);
				if(!empty($disabled)){
					$check = true;
				}
				else{
					$check = false;
				}
				$chkID = 'jigoshop_disable_product_photos';
				$label = '';
				$value = $check;
				$desc = __('Disable photos tab?', 'jigoshop-photos-product-tab');
			?>
			<div id="photo-tab" class="panel jigoshop_options_panel" style="display:none;">
				<fieldset>
					<?php echo jigoshop_form::checkbox($chkID, $label, $value, $desc); ?>
				</fieldset>
			</div>
			<?php
		    }

			/**
			 * Creates a new tab in the product data for the administrator.
			 * New tab called 'Photos' is added.
			 */
			function write_photo_tab(){
			?>
			<li class="photo_tab">
				<a href="#photo-tab"><?php _e('Photos', 'jigoshop_photos_product_tab');?></a>
			</li>
			<?php
			}

			/**
			 * Saves the options set in the product tab.
			 */
		    function write_photo_tab_panel_save($post_id){
		    	$jigoshop_disable_product_photos = isset($_POST['jigoshop_disable_product_photos']);
		    	update_post_meta($post_id, 'jigoshop_disable_product_photos', $jigoshop_disable_product_photos);
		    }
		}
	}

	/*
	 * Instantiate plugin class and add it to the set of globals.
	 */
	$jigoshop_photos_tab = new Jigoshop_Photo_Product_Tab();
}
else{
	add_action('admin_notices', 'jigo_photos_tab_error_notice');
	function jigo_photos_tab_error_notice(){
		global $current_screen;
		if($current_screen->parent_base == 'plugins'){
			echo '<div class="error"><p>'.__('Jigoshop Photos Product Tab requires <a href="http://www.jigoshop.com/" target="_blank">Jigoshop</a> to be activated in order to work. Please install and activate <a href="'.admin_url('plugin-install.php?tab=search&type=term&s=Jigoshop').'" target="_blank">Jigoshop</a> first.').'</p></div>';
		}
	}
}
?>
