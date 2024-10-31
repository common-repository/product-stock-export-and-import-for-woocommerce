<?php
/**
 * Plugin Name: Product Stock Export and Import for WooCommerce
 * Description: Import and Export stock statuses and quantities for WooCommerce products in CSV format.
 * Version: 1.0
 * Author: Shravan Sharma
 * Author URI: https://thesoftwarejungle.com
 * License: GNU General Public License version 2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html
 * Text Domain: woo-stock-manager
 * Domain Path:  /lang
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

$stock_base = plugin_basename( __FILE__ );

//add menu under woocommerce
add_action('admin_menu', 'woo_stock_manager_admin_menu');
function woo_stock_manager_admin_menu() {
  add_submenu_page('woocommerce', 'WooCommerce Stock Management', 'Stocks', 'view_woocommerce_reports', 'woo_stock_manager_stock', 'woo_stock_manager_page');
}

add_action( 'plugins_loaded', 'woo_stock_manager_textdomain' );
function woo_stock_manager_textdomain() {
	load_plugin_textdomain( ' woo-stock-manager', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
}

//add plugin settings link
add_filter( "plugin_action_links_$stock_base", 'woo_stock_manager_settings_link' );
function woo_stock_manager_settings_link( $links ) {
    $settings_link = '<a href="admin.php?page=woo_stock_manager_stock">' . __( 'Settings' ) . '</a>';
    array_push( $links, $settings_link );
  	return $links;
}

//add css to plugin page only
add_action('admin_head', 'woo_stock_manager_add_inline_css');
function woo_stock_manager_add_inline_css() {
  global $pagenow;
  if (isset($_GET['page']) && ($_GET['page'] == 'woo_stock_manager_stock')) {
    ?>
      <style type="text/css">
        . woo-stock-manager-span { margin-right: 20px; white-space: nowrap; padding-bottom: 5px; display: inline-block; }
      </style>
    <?php
  }
  
}

function woo_stock_manager_page() {
  ?>
    <div class="wrap">
      <h2><?php _e('Stock Management', ' woo-stock-manager') ?></h2>
     
      <?php  if (!class_exists('WooCommerce')) : ?>
        <div class="error">
        	<p><?php _e('This plugin requires that WooCommerce is installed and activated.', ' woo-stock-manager') ?></p>
        </div>
        <?php exit; ?>
      <?php endif; ?>

      <h3><?php _e('Export Stock', ' woo-stock-manager') ?></h3>
      <p class="description"><?php _e('Optionally limit the export to a single product category. You can also choose the order of the products and whether or not the report will include a header row. Click Export Stock to download the report in Comma-Separated Values (CSV) format.', ' woo-stock-manager') ?></p>
      <form action="" method="post">
        <input type="hidden" name="woo_stock_manager_do_export" value="1" />
        <?php wp_nonce_field('woo_stock_manager_do_export_nonce'); ?>
        <span class=" woo-stock-manager-span">
          <label>Product category:</label>
          <?php 
             wp_dropdown_categories(
                array(
                  'taxonomy' => 'product_cat',
                  'id' => 'woo_stock_manager_field_cat',
                  'name' => 'cat',
                  'orderby' => 'NAME',
                  'order' => 'ASC',
                  'show_option_all' => 'All Categories'                 
                )
            );
          ?>
        </span>
        <span class=" woo-stock-manager-span">
          <label for=" woo-stock-manager_field_orderby"><?php _e('Sort by:', ' woo-stock-manager') ?></label>
          <select name="orderby" id=" woo-stock-manager_field_orderby">
            <option value="ID"><?php _e('Product ID', ' woo-stock-manager') ?></option>
            <option value="sku" ><?php _e('Product SKU', ' woo-stock-manager') ?></option>
            <option value="title"><?php _e('Product Name', ' woo-stock-manager') ?></option>
          </select>
          <select name="orderdir">
            <option value="asc"><?php _e('ASC', ' woo-stock-manager') ?></option>
            <option value="desc"><?php _e('DESC', ' woo-stock-manager') ?></option>
          </select>
        </span>
        <span class=" woo-stock-manager-span">
          <label>
            <input type="checkbox" name="include_header" />
            <?php _e('Include header row', ' woo-stock-manager') ?>
          </label>
        </span>
        <button type="submit" class="button-primary"><?php _e('Export Stock', ' woo-stock-manager') ?></button>
      </form>


        <h3><?php _e('Import Stock', ' woo-stock-manager') ?></h3>
                 
        <p class="description">
        	<?php _e('The import file must be in Comma-Separated Values (CSV) format. The first field in each row must be the product ID, and the last two fields must be the In Stock indicator and the stock quantity, respectively ( this is the format produced by the export function of this plugin). If the value of the In Stock indicator is empty, zero, or &quot;no&quot;, the product is considered to be out of stock; all other values are taken to mean that the product is in stock. A value of &quot;--&quot; (two dashes) in the stock quantity field will disable stock management for that product (an empty or other non-numeric value is treated as zero). <strong>Always remember to back up your WooCommerce database before attempting batch updates.</strong>', ' woo-stock-manager') ?></p>
                 
        <form action="" method="post" enctype="multipart/form-data">
          <input type="hidden" name="woo_stock_manager_do_import" value="1" />
          <?php wp_nonce_field('woo_stock_manager_do_import_nonce'); ?>
          <input type="file" name="woo_stock_manager_import_file" />
          <button type="submit" class="button-primary"><?php _e('Import Stock', ' woo-stock-manager') ?></button>
        </form>
    </div>
  <?php
}


//function to import stock
add_action('admin_init', 'woo_stock_manager_import_stock_report');
function woo_stock_manager_import_stock_report() {

	//echo html_entity_decode( 'A08ER70200 - DRUM CHARGE CORONA', ENT_QUOTES, 'UTF-8' );
	global $pagenow;

  	if (!is_admin())
    	return;
        
  	if ( $pagenow == 'admin.php' 
    	&& isset($_GET['page']) 
      	&& $_GET['page'] == 'woo_stock_manager_stock' 
      	&& !empty($_POST['woo_stock_manager_do_import'])
    ) {

    	// Verify the nonce
    	check_admin_referer('woo_stock_manager_do_import_nonce');

     	if ( isset($_FILES['woo_stock_manager_import_file']) 
        	&& empty($_FILES['woo_stock_manager_import_file']['error']) 
        	&& is_uploaded_file($_FILES['woo_stock_manager_import_file']['tmp_name'])) 
     	{

       		$count = 0;
       		$fh = fopen($_FILES['woo_stock_manager_import_file']['tmp_name'], 'r');
       
       		while (($row = fgetcsv($fh)) !== false) {

          		$fieldCount = count($row);
          		if ($fieldCount < 3 || !is_numeric($row[0]))
            		continue;
              
          		if (update_post_meta($row[0], '_stock_status', (empty($row[$fieldCount - 2]) || strcasecmp($row[$fieldCount - 2], 'no') == 0 ? 'outofstock' : 'instock') ) 
            		|| update_post_meta($row[0], '_manage_stock', $row[$fieldCount - 1] == '--' ? 'no' : 'yes')
            		|| ($row[$fieldCount - 1] == '--' ? false : update_post_meta($row[0], '_stock', (empty($row[$fieldCount - 1]) 
            		||!is_numeric($row[$fieldCount - 1]) ? 0 : $row[$fieldCount - 1]))))

                    ++$count;
       		}
       		fclose($fh);
       		@unlink($_FILES['woo_stock_manager_import_file']['tmp_name']);

       		_e( sprintf( '<div class="updated"><p>Import complete. <strong>%d</strong> product(s) were updated.</p></div>', $count), ' woo-stock-manager');
     	}
     	else {
      		_e( sprintf('<div class="error"><p>The file was not uploaded successfully. Please check that the file size does not exceed the maximum upload size permitted by your server, and try again.</p></div>'), ' woo-stock-manager');
    	}
  	}
}

// Hook into WordPress admin init; this function performs report generation when
// the admin form is submitted
add_action('admin_init', 'woo_stock_manager_export_stock_report');
function woo_stock_manager_export_stock_report() {
  global $pagenow;

  if (!is_admin())
    return;
        
  if ( $pagenow == 'admin.php' 
      && isset($_GET['page']) 
      && $_GET['page'] == 'woo_stock_manager_stock' 
      && !empty($_POST['woo_stock_manager_do_export'])
    ) {

    // Verify the nonce
    check_admin_referer('woo_stock_manager_do_export_nonce');

    // Assemble the filename for the report download
    $filename =  'Product Stock - ';
    if (!empty($_POST['cat']) && is_numeric($_POST['cat'])) {
      $cat = get_term($_POST['cat'], 'product_cat');
      
      if (!empty($cat->name))
        $filename .= addslashes(html_entity_decode($cat->name)).' - ';
    }
    $filename .= date('Y-m-d', current_time('timestamp')).'.csv';

    // Send headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header("Content-Transfer-Encoding: UTF-8");
    header('Pragma: no-cache');
   
    $buffer = fopen('php://output', 'w');

    if (!empty($_POST['include_header']))
      woo_stock_manager_export_header($buffer);

    woo_stock_manager_export_body($buffer);
   
    exit;
  }
}

// This function outputs the report header row
function woo_stock_manager_export_header($buffer) {
  $header = array('Product ID', 'Product SKU', 'Product Name', 'Price', 'In Stock', 'Stock Quantity');
  fputcsv($buffer, $header);
}


// This function generates and outputs the report body rows
function woo_stock_manager_export_body($buffer) {
       
  $queryParams = array(
    'post_type' => 'product',
    'posts_per_page' => 2,
    'order' => ($_POST['orderdir'] == 'desc' ? 'DESC' : 'ASC'),
  );
       
  // Order
  if ($_POST['orderby'] == 'ID' || $_POST['orderby'] == 'title')
    $queryParams['orderby'] = $_POST['orderby'];
  else {
    $queryParams['meta_key'] = '_sku';
    $queryParams['orderby'] = 'meta_value';
  }
       
  // Category
  if (!empty($_POST['cat']) && is_numeric($_POST['cat'])) {
    $queryParams['tax_query'] = array(array(
      'taxonomy' => 'product_cat',
      'field' => 'term_id',
      'terms' => $_POST['cat']
    ));
  }
       
     
  // Output report rows
  
  $page = 0;
  while ( true ) {

    $queryParams['paged'] = ++$page;
    $q = new WP_Query( $queryParams );
    
    if ($q->have_posts()) {
        

      while( $q->have_posts() ){ 
        $q->the_post();
        $product_id = get_the_ID();

        fputcsv(
          $buffer, 
          array(
            $product_id,
            get_post_meta($product_id, '_sku', true),
            get_the_title(),
            get_post_meta($product_id, '_price', true),
            (strcasecmp(get_post_meta($product_id, '_stock_status', true), 'instock') == 0 ? 'X' : ''),
            (strcasecmp(get_post_meta($product_id, '_manage_stock', true), 'yes') == 0 ? get_post_meta($product_id, '_stock', true)*1 : '--'),
          )
        );
      }

      wp_reset_query();
    }else {
      break;
    }
  }

}