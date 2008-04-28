<?php
/*
Plugin Name: WPSC Stock Counter
Description: Plugin for Shopping Cart from Instinct Entertainment to count product stock. Products can be combined to be counted together.
Version: 1.0
Author: Kolja Schleich

Copyright 2007-2008  Kolja Schleich  (email : kolja.schleich@googlemail.com)

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
	 * This Array includes all products together with options
	 *
	 * @var array
	 */
	var $products = array();
		
		
	/**
	 * initialize class
	 *
	 * @param none
	 * @return void
	 */ 
	function __construct()
	{
		$this->plugin_url = get_bloginfo( 'wpurl' )."/".PLUGINDIR.'/'.basename(__FILE__, ".php");
		$this->getProducts();

		return;
	}
	function WPSC_EventsTicketCounter()
	{
		$this->__construct();
	}
	

	/**
	 * gets products list from database and save them in class
	 *
	 * @param none
	 * @return void
	 */
	function getProducts()
	{
		global $wpdb;

		$products = $wpdb->get_results( "SELECT `id`, `name` FROM wp_product_list ORDER BY id ASC" );
		if ( $products ) {
			foreach ( $products AS $product ) {
				$this->products[$product->id]['name'] = $product->name;
				$this->getProductMeta( $product->id );
			}
		}

		return;
	}


	/**
	 * gets number of sold objects for given product
	 *
	 * @param int $pid
	 * @return int
	 */
	function getSoldTickets( $pid )
	{
		global $wpdb;
		
		$sold = 0;
		$tickets = $wpdb->get_results( "SELECT `quantity` FROM wp_cart_contents WHERE `prodid` = '".$pid."'" );
		if ( $tickets ) {
			foreach ( $tickets AS $ticket )
				$sold += $ticket->quantity;
		}
		if ( $this->products[$pid]['linked_products'] != '' ) {
			$linked_products = explode( ',', $this->products[$pid]['linked_products'] );
			foreach ( $linked_products AS $l_pid ) {
				$tickets = $wpdb->get_results( "SELECT `quantity` FROM wp_cart_contents WHERE `prodid` = '".$l_pid."'" );
				if ( $tickets ) {
					foreach ( $tickets AS $ticket ) {
						$sold += $ticket->quantity;
					}
				}
			}
		}
		return $sold;
	}

		
	/**
	 * gets product data for given product and save them in class
	 *
	 * @param int $pid
	 * @return void
	 */
	function getProductMeta( $pid )
	{
		$options = get_option( 'wpsc-stock-counter' );
		
		$this->products[$pid]['limit'] = $options['products'][$pid]['limit'];
		$this->products[$pid]['count'] = $options['products'][$pid]['count'];
		$this->products[$pid]['linked_products'] = $options['products'][$pid]['linked_products'];

		if ( 1 == $this->products[$pid]['count'] ) {
			$sold = $this->getSoldTickets( $pid );
			$this->products[$pid]['sold'] = $sold;
			$this->products[$pid]['remaining'] = $this->products[$pid]['limit'] - $sold;
		}

		return;
	}


	/**
	 * prints admin page
	 *
	 * @param none
	 * @return none
	 */
	function printAdminPage()
	{
		global $wpdb;
		
		$options = get_option( 'wpsc-stock-counter' );
		if ( isset( $_POST['updateEventsCounter'] ) ) {
			if ( 'settings' == $_POST['updateEventsCounter'] ) {
				foreach ( $_POST['products'] AS $pid => $data ) {
					$options['products'][$pid] = $data;
				}
				update_option( 'wpsc-stock-counter', $options );
			}
		
			echo '<div id="message" class="updated fade"><p><strong>'.__( 'Settings saved', 'wpsc-stock-counter' ) .'</strong></p></div>';
			$this->getProducts();
		}
?>
		<div class="wrap">
			<h2><?php _e( 'Stock Summary', 'wpsc-stock-counter' ) ?></h2>
			<p><a href="#" onclick="Element.show('wpsc-stock-counter-settings')"><?php _e( 'Show Settings', 'wpsc-stock-counter' ) ?></a></p>

			<table class="widefat">
			<thead>
				<tr>
					<th scope="col"><?php _e( 'Event', 'wpsc-stock-counter' ) ?></th>
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
		<div class="wrap" id="wpsc-stock-counter-settings" style="display: none;">
			<h2><?php _e( 'Settings', 'wpsc-stock-counter' ) ?></h2>
			<p><a href="#" onclick="Element.hide('wpsc-stock-counter-settings')"><?php _e( 'Hide Settings', 'wpsc-stock-counter' ) ?></a></p>
			
			<form action="" method="post">
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
				<tbody id="the-list">
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
				</tbody>
				</table>
				<p style="font-size: 0.9em; font-style: italic;">*<?php _e( 'Comma separated list of IDs', 'wpsc-stock-counter' ) ?></p>	
	
				<input type="hidden" name="updateEventsCounter" value="settings" />
				<p class="submit"><input type="submit" value="<?php _e( 'Save Settings', 'wpsc-stock-counter' ) ?> &raquo;" class="button" /></p>
			</form>
		</div>
		<?php
	}

		
	/**
	 * Initialize Plugin
	 *
	 * @param none
	 * @return void
	 */
	function init()
	{
		$options = array();
		add_option( 'wpsc-stock-counter', $options, 'DTL Ticketing Options', 'yes' );

		return;
	}
	
	
	/**
	* adds code to Wordpress head
	*
	* @param none
	*/
	function addHeaderCode()
	{
		wp_print_scripts( 'prototype' );
	}
	
	
	/**
	 * adds admin menu
	 *
	 * @param none
	 * @return none
	 */
	 function addAdminMenu()
	 {
	 	$mypage = add_submenu_page( 'wp-shopping-cart/display-log.php', __( 'Stock Counter', 'wpsc-stock-counter' ), __( 'Stock Counter', 'wpsc-stock-counter' ), 7, basename(__FILE__), array(&$this, 'printAdminPage') );
		add_action( "admin_print_scripts-$mypage", array(&$this, 'addHeaderCode') );
	 }
}

$wpsc_stock_counter = new WPSC_StockCounter();

add_action( 'activate_wpsc-stock-counter/wpsc-stock-counter.php', array(&$wpsc_stock_counter, 'init') );
add_action( 'admin_menu', array(&$wpsc_stock_counter, 'addAdminMenu') );

load_plugin_textdomain( 'wpsc-stock-counter', $path = PLUGINDIR.'/'.basename(__FILE__, ".php")  );
?>