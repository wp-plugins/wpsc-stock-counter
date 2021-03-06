<?php
/*
Plugin Name: WPSC Stock Counter
Plugin URI: http://wordpress.org/extend/plugins/wpsc-stock-counter
Description: Plugin for <a href="https://wordpress.org/plugins/wp-e-commerce/">Wordpress Shopping Cart</a> to count product stock. Products can be combined to be counted together.
Version: 1.5.6
Author: Kolja Schleich

Copyright 2007-2015  Kolja Schleich  (email : kolja [dot] schleich [at] googlemail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class WPSC_StockCounter
{
	/**
	 * all products with options
	 *
	 * @var array
	 */
	private $products = array();
		
		
	/**
	 * class constructor. initialize plugin: define constants, register hooks and actions
	 *
	 * @param none
	 * @return void
	 */ 
	public function __construct()
	{
		if ( !defined( 'WP_CONTENT_URL' ) )
			define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );
		if ( !defined( 'WP_PLUGIN_URL' ) )
			define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
		
		register_activation_hook(__FILE__, array(&$this, 'activate') );
		load_plugin_textdomain( 'wpsc-stock-counter', false, basename(__FILE__, '.php').'/languages' );
		add_action( 'admin_menu', array(&$this, 'addAdminMenu') );

		// Uninstallation for WP 2.7
		if ( function_exists('register_uninstall_hook') )
			register_uninstall_hook(__FILE__, array('WPSC_StockCounter', 'uninstall'));
			
		$this->plugin_url = esc_url(WP_PLUGIN_URL.'/'.basename(__FILE__, '.php'));
		$this->getProducts();
	}
	

	/**
	 * gets products list from database 
	 *
	 * @param none
	 * @return boolean
	 */
	private function getProducts()
	{
		global $wpdb;

		$products = $wpdb->get_results( "SELECT `ID`, `post_name` FROM {$wpdb->prefix}posts WHERE post_type = 'wpsc-product' ORDER BY id ASC" );
		if ( $products ) {
			foreach ( $products AS $product ) {
				$this->products[$product->ID]['name'] = stripslashes($product->post_name);
				$this->getProductMeta( $product->ID );	
			}
			return true;
		}

		return false;
	}


	/**
	 * gets number of sold objects for given product
	 *
	 * @param int $pid ID of product
	 * @return int
	 */
	private function getSoldTickets( $pid )
	{
		global $wpdb;
		
		$sold = 0;
		$pid = intval($pid);
		if ($pid > 0) {
			$tickets = $wpdb->get_results( $wpdb->prepare("SELECT `quantity` FROM {$wpdb->prefix}wpsc_cart_contents WHERE `prodid` = '%d'", $pid) );
			if ( $tickets ) {
				foreach ( $tickets AS $ticket )
					$sold += $ticket->quantity;
			}
			if ( $this->products[$pid]['linked_products'] != '' ) {
				$linked_products = explode( ',', $this->products[$pid]['linked_products'] );
				foreach ( $linked_products AS $l_pid ) {
					$l_pid = intval($l_pid);
					if ($l_pid > 0) {
						$tickets = $wpdb->get_results( $wpdb->prepare("SELECT `quantity` FROM {$wpdb->prefix}wpsc_cart_contents WHERE `prodid` = '%d'", $l_pid) );
						if ( $tickets ) {
							foreach ( $tickets AS $ticket ) {
								$sold += $ticket->quantity;
							}
						}
					}
				}
			}
		}
		return $sold;
	}

		
	/**
	 * gets product data for given product
	 *
	 * @param int $pid ID of product
	 * @return void
	 */
	private function getProductMeta( $pid )
	{
		$pid = intval($pid);
		$options = get_option( 'wpsc-stock-counter' );
		
		$this->products[$pid]['limit'] = intval($options['products'][$pid]['limit']);
		$this->products[$pid]['count'] = intval($options['products'][$pid]['count']);
		$this->products[$pid]['linked_products'] = $options['products'][$pid]['linked_products'];

		if ( 1 == $this->products[$pid]['count'] ) {
			$sold = $this->getSoldTickets( $pid );
			$this->products[$pid]['sold'] = $sold;
			$this->products[$pid]['remaining'] = $this->products[$pid]['limit'] - $sold;
		}
	}


	/**
	 * prints admin page
	 *
	 * @param none
	 * @return void
	 */
	public function printAdminPage()
	{		
		if ( isset( $_POST['updateEventsCounter'] ) && current_user_can('edit_stock_counter_settings') ) {
			check_admin_referer( 'wpsc-stock-counter-update-settings_stock' );

			$options = get_option( 'wpsc-stock-counter' );
			foreach ( $_POST['products'] AS $pid => $data ) {
				$pid = intval($pid);
				if (!isset($data['count'])) $data['count'] = 0;
				// escape html characters for security
				foreach($data as $i => $tmp) $data2[$i] = htmlspecialchars($tmp);
				$options['products'][$pid] = $data2;
			}
			update_option( 'wpsc-stock-counter', $options );
			
			echo '<div id="message" class="updated fade"><p><strong>'.__( 'Settings saved', 'wpsc-stock-counter' ) .'</strong></p></div>';
			$this->getProducts();
		}
		$class = "";
?>
		<div class="wrap">
			<h2><?php _e( 'Stock Summary', 'wpsc-stock-counter' ) ?></h2>
			<?php if ( current_user_can('edit_stock_counter_settings') ) : ?>
				<p style="margin-bottom: 0;"><a href="<?php esc_url(menu_page_url('wpsc-stock-counter')) ?>&showsettings=1"><?php _e( 'Show Settings', 'wpsc-stock-counter' ) ?></a></p>
			<?php endif; ?>
			
			<table class="widefat" style="margin-top: 1em;">
			<thead>
				<tr>
					<th scope="col"><?php _e( 'Product', 'wpsc-stock-counter' ) ?></th>
					<th scope="col"><?php _e( 'Sold', 'wpsc-stock-counter' ) ?></th>
					<th scope="col"><?php _e( 'Available', 'wpsc-stock-counter' ) ?></th>
				</tr>
			</thead>
			<tbody id="the-list">
				<?php foreach ( $this->products AS $pid => $data ) : ?>
				<?php if ( 1 == $this->products[$pid]['count'] ) : ?>
				<?php $class = ( 'alternate' == $class ) ? '' : 'alternate'; ?>
				<tr class="<?php echo $class ?>">
					<td><?php echo $data['name'] ?></td>
					<td><?php echo $this->products[$pid]['sold'] ?></td>
					<td><?php echo $this->products[$pid]['remaining'] ?></td>
				</tr>
				<?php endif; ?>
				<?php endforeach; ?>
			</tbody>
			</table>
		</div>
		<?php if ( current_user_can('edit_stock_counter_settings') ) : ?>
		<?php if (isset($_GET['showsettings'])) : ?>
		<div class="wrap" id="wpsc-stock-counter-settings">
			<h2><?php _e( 'Settings', 'wpsc-stock-counter' ) ?></h2>
			<p><a href="<?php esc_url(menu_page_url('wpsc-stock-counter')) ?>"><?php _e( 'Hide Settings', 'wpsc-stock-counter' ) ?></a></p>
			
			<form action="<?php esc_url(menu_page_url('wpsc-stock-counter')) ?>&showsettings=1" method="post">
				<?php wp_nonce_field( 'wpsc-stock-counter-update-settings_stock' ) ?>
				
				<table class="widefat">
				<thead>
					<tr>
						<th scope="col"><?php _e( 'ID', 'wpsc-stock-counter' ) ?></th>
						<th scope="col"><?php _e( 'Event', 'wpsc-stock-counter' ) ?></th>
						<th scope="col"><?php _e( 'Count', 'wpsc-stock-counter' ) ?></th>
						<th scope="col"><?php _e( 'Stock', 'wpsc-stock-counter' ) ?></th>
						<th scope="col"><?php _e( 'Connected Products', 'wpsc-stock-counter' ) ?>*</th>
					</tr>
				</thead>
				<tbody class="form-table">
					<?php if (count($this->products)) : ?>
					<?php foreach ( $this->products AS $pid => $data ) : $selected = ( $this->products[$pid]['count'] == 1 ) ? " checked='checked'" : ''; ?>
					<?php $class = ( 'alternate' == $class ) ? '' : 'alternate'; ?>
					<tr class="<?php echo $class ?>">
						<td><?php echo $pid ?></td>
						<td><?php echo $data['name'] ?></td>
						<td><input type="checkbox" name="products[<?php echo $pid ?>][count]"<?php echo $selected ?> value="1" /></td>
						<td><input type="text" name="products[<?php echo $pid ?>][limit]" value="<?php echo $this->products[$pid]['limit'] ?>" /></td>
						<td><input type="text" name="products[<?php echo $pid ?>][linked_products]" value="<?php echo $this->products[$pid]['linked_products'] ?>" /></td>
					</tr>
					<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
				</table>
				<p style="font-size: 0.9em; font-style: italic;">*<?php _e( 'Comma separated list of IDs', 'wpsc-stock-counter' ) ?></p>	
	
				<p class="submit"><input type="submit" name="updateEventsCounter" value="<?php _e( 'Save Settings', 'wpsc-stock-counter' ) ?>" class="button button-primary" /></p>
			</form>
		</div>
		<?php endif; ?>
		<?php endif;
	}

		
	/**
	 * Activate Plugin
	 *
	 * @param none
	 * @return void
	 */
	public function activate()
	{
		$options = array();
		add_option( 'wpsc-stock-counter', $options, '', 'yes' );

		/*
		* Add Capabilities
		*/
		$role = get_role('administrator');
		$role->add_cap('view_stock_counter');
		$role->add_cap('edit_stock_counter_settings');
		
		$role = get_role('editor');
		$role->add_cap('view_stock_counter');
	}
	
	
	/**
	 * adds code to Wordpress head
	 *
	 * @param none
	 * @return void
	 */
	public function addHeaderCode()
	{
		wp_print_scripts( 'jquery' );
	}
	
	
	/**
	 * adds admin menu
	 *
	 * @param none
	 * @return void
	 */
	public function addAdminMenu()
	{
		$plugin = basename(__FILE__,'.php').'/'.basename(__FILE__);

	 	$mypage = add_options_page( __( 'Shop Stock Counter', 'wpsc-stock-counter' ), __( 'Shop Stock Counter', 'wpsc-stock-counter' ), 'view_stock_counter', 'wpsc-stock-counter', array(&$this, 'printAdminPage') );
		add_action( "admin_print_scripts-$mypage", array(&$this, 'addHeaderCode') );
		add_filter( 'plugin_action_links_' . $plugin, array( &$this, 'pluginActions' ) );
	}
	 
	 
	/**
	 * display link to settings page in plugin table
	 *
	 * @param array $links array of action links
	 * @return new array of plugin actions
	 */
	public function pluginActions( $links )
	{
		$settings_link = '<a href="'.esc_url(menu_page_url('wpsc-stock-counter', 0)).'">' . __('Settings') . '</a>';
		array_unshift( $links, $settings_link );
	
		return $links;
	}
	
	
	/**
	 * Uninstall Plugin
	 *
	 * @param none
	 * @return void
	 */
	public static function uninstall()
	{
	 	delete_option( 'wpsc-stock-counter' );
	}
}

// Run the plugin
$wpsc_stock_counter = new WPSC_StockCounter();
?>