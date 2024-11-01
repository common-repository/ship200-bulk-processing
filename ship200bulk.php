<?php
/**
 * Plugin Name: Ship 200 Bulk
 * Plugin URI: http://ship200.com/
 * Description: used for bulk order processing.
 * Version: 2.8.2
 * Author: Ship 200
 * Author URI: http://ship200.com/
 * License: GPL2
 */

/** Register custom post type for the plugin use. */

register_activation_hook(__FILE__, 'ship200bulk_activate');

global $ship_200_bulk;
$ship_200_bulk = "2.8.2";

function ship200bulk_activate()
{
    global $wpdb;
    global $ship_200_bulk;

    $table_name = $wpdb->prefix . "ship200bulk";

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
  shipid int NOT NULL AUTO_INCREMENT,
  ship200key text NOT NULL,
  orderstatusimport VARCHAR(55) NOT NULL,
  orderstatustracking VARCHAR(55) NOT NULL,
  PRIMARY KEY ( shipid )
  );";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    add_option("ship_200_bulk", $ship_200_bulk);

    $args = array(
        'name' => 'ship200-bulk',
        'post_type' => 'ship200bulk'
    );
    $my_posts = get_posts($args);
    if (count($my_posts) > 0) {

    } else {
        ship200bulk_createpage();
    }

}

function ship200bulk_createpage()
{
    // Create post object
    $postInfo = array(
        'post_title' => 'Ship200 Bulk',
        'post_type' => 'ship200bulk',
        'post_name' => 'ship200-bulk',
        'post_content' => '[ship200get]',
        'post_status' => 'publish',
        'post_author' => 1,
        'post_category' => '',
        'post_template' => 'Ship200bulk',
        'show_in_menu' => false
    );

// Insert the post into the database
    $post_id = wp_insert_post($postInfo);
}

//for insert data into database

if (isset($_POST["ship200_key_bulk"]) && isset($_POST["orderstatusimport_bulk"]) && isset($_POST["orderstatustracking_bulk"])) {

    global $wpdb;
    $table_name = $wpdb->prefix . "ship200bulk";
    $ship_data = $wpdb->get_var("SELECT * FROM " . $wpdb->prefix . "ship200bulk");
    if ($ship_data == 1) {

        $wpdb->query($wpdb->prepare("UPDATE `" . $wpdb->prefix . "ship200bulk` SET ship200key = '%s', orderstatusimport = '%s',orderstatustracking = '%s' where shipid=1", sanitize_text_field($_POST["ship200_key_bulk"]), sanitize_text_field($_POST["orderstatusimport_bulk"]), sanitize_text_field($_POST["orderstatustracking_bulk"]) ));
        $_SESSION["success"] = "Your Data Saved Successfully";
    } else {
        $wpdb->query($wpdb->prepare( "INSERT INTO `" . $wpdb->prefix . "ship200bulk` SET ship200key = '%s', orderstatusimport = '%s',orderstatustracking = '%s'", sanitize_text_field($_POST["ship200_key_bulk"]), sanitize_text_field($_POST["orderstatusimport_bulk"]), sanitize_text_field($_POST["orderstatustracking_bulk"]) ));
        $_SESSION["success"] = "Your Data Saved Successfully";

    }
}

add_shortcode('ship200get', 'getshipdata');

function getshipdata()
{
    require_once('ship200bulk_getData.php');
}


add_action('init', 'ship200bulk_create_post_type');

function ship200bulk_create_post_type()
{
    register_post_type('ship200bulk',
        array(
            'labels' => array(
                'name' => __('Ship200bulks'),
                'singular_name' => __('Ship200bulk')
            ),
            'public' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'ship200bulk'),
            'show_in_menu' => false
        )
    );
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'shipbulk_add_plugin_action_links');

function shipbulk_add_plugin_action_links($links)
{
    return array_merge(
        array('settings' => '<a href="options-general.php?page=Ship200-Bulk">Settings</a>'
        ),
        $links
    );
}

/** Step 1. */
function ship200_menu()
{
    add_options_page('Ship200 Bulk', 'Ship200 Bulk', 'manage_options', 'Ship200-Bulk', 'ship200_options');
}

/** Step 2 (from text above). */
add_action('admin_menu', 'ship200_menu');

/** Step 3. */

function ship200_options()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    ?>
    <h1>Ship200 Bulk Order</h1>
    <?php

    if (is_plugin_active('woocommerce/woocommerce.php')) {
        ?>
        <?php global $wpdb;
        $ship200settings = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM ". $wpdb->prefix . "ship200bulk WHERE shipid = %d", 1) );
        ?>
        <h2 style="color:#393;"> <?php if (isset($_SESSION["success"])) {
                echo $_SESSION["success"];
                unset($_SESSION["success"]);
            }
            ?></h2>
        <form method="post" action="<?php echo esc_attr($_SERVER['PHP_SELF']) . "?page=Ship200-Bulk"; ?>">
            <fieldset style="width: 650px;" class="width2">
                <label style="text-align: left;" for="ship200_key_bulk">Ship200 Key:</label>
                <input type="text" value="<?php echo esc_attr($ship200settings->ship200key); ?>" name="ship200_key_bulk"
                       id="ship200_key_bulk">
                <br>
                <span>
                <br>1. Login to your Ship200.com Account <br>
                2. On left bottom corner click "Add/Remove Store" <br>
                3. Click WooCommerce on the left sidebar <br>
                4. Place the key from ship200 setup page to the above, click Update settings <br/>
                5. Change domain name to your domain in "Path to Ship200 Communicator Script" Field at Ship200, then hit Save Changes <br>
                6. Choose Order Statuses for Import and Update Tracking
                </span>

                <div class="clear">&nbsp;</div>

                <label style="text-align: left;" for="orderstatusimport_bulk">Order Status Import:</label>

                <select id="orderstatusimport_bulk" name="orderstatusimport_bulk" class="chosen_select">
                    <?php
                    $order_statuses = wc_get_order_statuses();
                    foreach ($order_statuses as $key => $value) {
                        if ($key == $ship200settings->orderstatusimport) echo '<option selected="selected" value="' . esc_attr($key) . '">'; else echo '<option value="' . esc_attr($key) . '">';
                        echo esc_attr($value) . '</option>';
                    } ?>
                </select>

                <div class="clear">&nbsp;</div>

                <label style="text-align: left;" for="orderstatustracking_bulk">Order Status Tracking:</label>

                <select id="orderstatustracking_bulk" name="orderstatustracking_bulk" class="chosen_select">
                    <?php
                    foreach ($order_statuses as $key => $value) {
                        if ($key == $ship200settings->orderstatustracking) echo '<option selected="selected" value="' . esc_attr($key) . '">'; else echo '<option value="' . esc_attr($key) . '">';
                        echo esc_attr($value) . '</option>';
                    } ?>
                </select>

                <div class="clear">&nbsp;</div>
                <div class="clear">&nbsp;</div>
                <br>
                <center><input type="submit" class="button" value="Update settings" name="submitModule"></center>
            </fieldset>
        </form>
        <?php
    } else {
        ?>
        <div><span>Please Activate Woocommerce Plugin First.</span></div>
    <?php }
}

add_filter('template_include', 'include_bulk_template_function', 1);

function include_bulk_template_function($template_path)
{
    if (get_post_type() == 'ship200bulk') {
        if (is_single()) {
            if ($theme_file = locate_template(array('single-ship200bulk.php'))) {
                $template_path = $theme_file;
            } else {
                $template_path = plugin_dir_path(__FILE__) . '/single-ship200bulk.php';
            }
        }
    }
    return $template_path;
}