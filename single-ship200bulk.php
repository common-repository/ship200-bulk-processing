<?php
error_reporting(0);
global $wpdb;
$ship200settings = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM ". $wpdb->prefix . "ship200bulk WHERE shipid = %d", 1) );

$secret_key = $ship200settings->ship200key;
$order_status_import = $ship200settings->orderstatusimport;
$order_status_tracking_bulk = $ship200settings->orderstatustracking;
if ($secret_key == "") {
    echo "The Secret Key was never setup. Please refer to read_me file";
    exit;
}
if ($order_status_import == "") {
    echo "Please Select Order Status From Admin for Order Import";
    exit;
}
if ($order_status_tracking_bulk == "") {
    echo "Please Select Order Status From Admin for Update With Tracking";
    exit;
}

#Extra security

// Check that request is coming from Ship200 Server
$allowed_servers = file_get_contents('http://www.ship200.com/instructions/allowed_servers.txt');
if(!$allowed_servers) $allowed_servers = '173.192.194.99,173.192.194.98,108.58.55.190,45.33.71.107,45.33.73.63,45.33.89.56,45.33.85.63,97.107.136.135,45.79.162.158,45.79.136.18,45.79.130.178,173.220.12.130,2600:3c03::f03c:91ff:fe98:b9a6,2600:3c03::f03c:91ff:fe18:9304,2600:3c03::f03c:91ff:fe18:a8b8,2600:3c03::f03c:91ff:fe18:a8ab,2600:3c03::f03c:91ff:fe18:a8bc,2600:3c03::f03c:91ff:fe3b:4d12,2600:3c03::f03c:91ff:fe67:8ae5';

$server = false;
if(strpos($allowed_servers, getClientIp()) !== false) $server = true;

// Check that request is coming from Ship200 Server
if (!$server) {
    echo 'Invalid Server ('. getClientIp() . ')';
    exit;
}

if(isset($_REQUEST['maintenance']) && $_REQUEST['maintenance'] == 1) {

    if(function_exists('wc_get_order_statuses')){
        $orderStatuses = wc_get_order_statuses();
        $import_status_label = $orderStatuses[$order_status_import] . '(' . $order_status_import . ')';
        $postback_status_label = $orderStatuses[$order_status_tracking_bulk] . '(' . $order_status_tracking_bulk . ')';
    }else{
        $import_status_label = $order_status_import;
        $postback_status_label = $order_status_tracking_bulk;
    }

    if(strlen($secret_key) > 10)
        $keyLabel = str_repeat('X', strlen($secret_key) - 4);
    else
        $keyLabel = str_repeat('X', strlen($secret_key));

    echo "<div align='left'><b>System Check:</b><BR><BR>";
    echo "<B>Key:</b> ". $keyLabel . substr($secret_key, 0, 4) . " (".strlen($secret_key).") <BR>";
    echo "<B>Server List:</b> ". implode(", ", $servers_array) . " <BR>";
    echo "<B>Import Status:</b> ". $import_status_label . " <BR>";
    echo "<B>Postback Status:</b> ". $postback_status_label .  " <BR>";

    echo "<BR><B>Request:</b> <BR>";
    echo "<pre><div align='left'>";
    foreach($_REQUEST as $key => $value){
        if(is_array($value)) continue;
        echo htmlspecialchars($key) . ' => ' . htmlspecialchars($value) . PHP_EOL;
    }
    echo "</pre></div>";

    echo "</div>";
    exit;
}
elseif ($_POST['id'] == $secret_key && $_POST['update_tracking'] == '' && $server == 1) {

    $fields = array(

        //          Ship200 field name              your db field name

        //          cannot be changed           can be changed (see examples below)

        "Orderid" => "id", // that will be used 'keyForUpdate' to update the tracking number back in the backend

        "Order_Date" => "order_date",

        "Order_Status" => "status",

        "Name" => array("1" => "name"),

        "Company_Name" => "shipping_company", // ps_address table name
        //
        "Address_Line1" => "shipping_address_1",    // ps_address table name
        "Address_Line2" => "shipping_address_2",    // ps_address table name
        //
        "City" => "shipping_city",// ps_address table name

        "State" => "shipping_state", // 2 Letter State, example: NY, LA etc..

        "Zip" => "shipping_postcode",  //ps_address table name

        "Country" => "shipping_country",

        "Phone" => "billing_phone",// ps_address table name

        "Email" => "billing_email", //ps_customer table name

        //          "Ship_Method"           =>  "id_carrier", //ps_orders //ps_carrier

        //          "Subtotal"          =>  "total_paid_tax_excl", //ps_orders

        //          "Shipping"          =>  "total_shipping",

        //          "Total"         =>  "total_paid",

        "items" => array("1" => "items"),
    ); ?><?php global $woocommerce;
    $args = array(
        'post_type' => 'shop_order',
        'post_status' => 'publish',
        'posts_per_page' => 100,
        'orderby' => 'ID',
        'order' => 'ASC',
        'tax_query' => array(
            array(
                'taxonomy' => 'shop_order_status',
                'field' => 'slug',
                'terms' => array(sanitize_text_field(str_replace('wc-','',$order_status_import)))
            )
        )
    );

    wp_reset_query();

    $loop = new WP_Query($args);

    while ($loop->have_posts()) : $loop->the_post();

        $order_id = $loop->post->ID;
        #$order_id = trim(str_replace('#', '', $order->get_order_number())); #real orderid
        $order = new WC_Order($order_id);

        $out .= "<order>\n";
        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                $man = "";
                foreach ($value as $key2 => $value2) { /// if field is array
                    if ($value2 == "items") {

                        unset($totals);
                        unset($item_title_array);
                        unset($items_qty_array);
                        unset($items_price_array);
                        unset($items_sku_array);
                        $total_weight = "";
                        foreach ($order->get_items() as $item) {
                            $_product = apply_filters('woocommerce_order_item_product', $order->get_product_from_item($item), $item);

                            $item_title_array[] = apply_filters('woocommerce_order_item_name', $item['name'], $item);
                            $items_qty_array[] = apply_filters('woocommerce_order_item_quantity_html', $item['qty'], $item);
                            $items_price_array[] = $order->get_line_subtotal($item);
                            $items_sku_array[] = $_product->get_sku();

                            $total_weight += $_product->get_weight()*apply_filters('woocommerce_order_item_quantity_html', $item['qty'], $item);

                        }
                        //titles
                        $man .= "\n<title><![CDATA[" . json_encode($item_title_array) . "]]></title>\n";

                        //itemid
                        $man .= "\n<itemid><![CDATA[" . json_encode($items_sku_array) . "]]></itemid>\n";
                        //qty
                        $man .= "\n<qty>" . json_encode($items_qty_array) . "</qty>\n";
                        //price
                        $man .= "\n<price>" . json_encode($items_price_array) . "</price>\n";
                        //weight
                        $out .= "\n<Weight>" . $total_weight . "</Weight>\n";
                        //weightunit
                        $out .= "\n<Weight_Units>lb</Weight_Units>";
                        //subtotal
                        $man .= "\n<subtotal>" . json_encode($items_price_array) . "</subtotal>\n";
                        //shipping
                        $man .= "\n<shipping>" . $order->order_shipping . "</shipping>";
                        $man .= "\n<total>" . $order->order_total . "</total>";
                        // Items
                    }elseif($value2 == "name"){
                        $man .= trim($order->shipping_first_name .' '. $order->shipping_last_name);
                        $ship_method = trim(preg_replace('/ +/', ' ', preg_replace('/[^A-Za-z0-9 ]/', ' ', urldecode(html_entity_decode(strip_tags($order->get_shipping_method()))))));
                        $out .= "\n<Ship_Method><![CDATA[" . $ship_method . "]]></Ship_Method>";
                    }
                }

                $out .= "<$key>" . $man . "</$key>\n";

            } elseif ($value == "") {                         // if field is empty
                $out .= "<$key></$key>\n";
            } else {                                        // normal field
                $out .= "<$key>" . $order->$value . "</$key>\n";
            }
        }
        $out .= "</order>\n";

        wp_reset_postdata();

    endwhile;

    wp_reset_query();

    header("Content-type: text/xml; charset=utf-8");
    echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . '<orders>' . PHP_EOL . $out . '</orders>';

} else if ($_REQUEST['update_tracking'] != "" && $_REQUEST['secret_key'] == $secret_key) {

    $comment = sanitize_text_field($_POST['carrier']) . " tracking# : " . sanitize_text_field($_POST['tracking']);
    $order_id = (int) sanitize_text_field($_REQUEST['keyForUpdate']);

    $order = new WC_Order($order_id);
    $order->update_status(sanitize_text_field($order_status_tracking_bulk));
    $order->add_order_note($comment);
    echo "Tracking Inserted";
    exit;

} else {
    // Not valid request //////
    echo "Error: 1094";
    exit;
}

function getClientIp() {
    $ipAddress = '';
    if (getenv('HTTP_CLIENT_IP'))
        $ipAddress = getenv('HTTP_CLIENT_IP');
    else if(getenv('HTTP_X_FORWARDED_FOR'))
        $ipAddress = getenv('HTTP_X_FORWARDED_FOR');
    else if(getenv('HTTP_X_FORWARDED'))
        $ipAddress = getenv('HTTP_X_FORWARDED');
    else if(getenv('HTTP_FORWARDED_FOR'))
        $ipAddress = getenv('HTTP_FORWARDED_FOR');
    else if(getenv('HTTP_FORWARDED'))
        $ipAddress = getenv('HTTP_FORWARDED');
    else if(getenv('REMOTE_ADDR'))
        $ipAddress = getenv('REMOTE_ADDR');
    else
        $ipAddress = 'UNKNOWN';
    return $ipAddress;
}