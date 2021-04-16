<?php
$nzshpcrt_gateways[$num]['name'] = 'Paymentnepal';
$nzshpcrt_gateways[$num]['internalname'] = 'paymentnepal';
$nzshpcrt_gateways[$num]['function'] = 'gateway_paymentnepal';
$nzshpcrt_gateways[$num]['form'] = "form_paymentnepal";
$nzshpcrt_gateways[$num]['submit_function'] = "submit_paymentnepal";
$nzshpcrt_gateways[$num]['display_name'] = 'Payment gate paymentnepal.com';
//$nzshpcrt_gateways[$num]['image'] = WPSC_URL . '/images/onpay.gif';

function to_float1($sum) {
    if (strpos($sum, ".")) {
        $sum = round($sum, 2);
    } else {
        $sum = $sum . ".0";
    }
    return $sum;
}

function gateway_paymentnepal($separator, $sessionid) {
    global $wpdb;
    $purchase_log_sql = "SELECT * FROM `" . WPSC_TABLE_PURCHASE_LOGS . "` WHERE `sessionid`= " . $sessionid . " LIMIT 1";
    $purchase_log = $wpdb->get_results($purchase_log_sql, ARRAY_A);
    $cart_sql = "SELECT * FROM `" . WPSC_TABLE_CART_CONTENTS . "` WHERE `purchaseid`='" . $purchase_log[0]['id'] . "'";
    $cart = $wpdb->get_results($cart_sql, ARRAY_A);
    $paymentnepal_url = get_option('paymentnepal_url') . get_option('paymentnepal_login');
    $data['order_id'] = $purchase_log[0]['id'];
    //$data['currency'] = get_option('paymentnepal_curcode');
    //$data['url_success'] = get_option('siteurl') . "/?paymentnepal_callback=true";
    //$data['pay_mode'] = 'fix';
    $email_data = $wpdb->get_results("SELECT `id`,`type` FROM `" . WPSC_TABLE_CHECKOUT_FORMS . "` WHERE `type` IN ('email') AND `active` = '1'", ARRAY_A);
    foreach ((array) $email_data as $email) {
        $data['default_email'] = $_POST['collected_data'][$email['id']];
    }
    if (($_POST['collected_data'][get_option('email_form_field')] != null) && ($data['email'] == null)) {
        $data['default_email'] = $_POST['collected_data'][get_option('email_form_field')];
    }
    $currency_code = $wpdb->get_results("SELECT `code` FROM `" . WPSC_TABLE_CURRENCY_LIST . "` WHERE `id`='" . get_option('currency_type') . "' LIMIT 1", ARRAY_A);
    $local_currency_code = $currency_code[0]['code'];
    $paymentnepal_currency_code = get_option('paymentnepal_curcode');

    $curr = new CURRENCYCONVERTER();
    $decimal_places = 2;
    $total_price = 0;
    $i = 1;
    $all_donations = true;
    $all_no_shipping = true;
    foreach ($cart as $item) {
        $product_data = $wpdb->get_results("SELECT * FROM `" . $wpdb->posts . "` WHERE `id`='" . $item['prodid'] . "' LIMIT 1", ARRAY_A);
        $product_data = $product_data[0];
        $variation_count = count($product_variations);
        $variation_sql = "SELECT * FROM `" . WPSC_TABLE_CART_ITEM_VARIATIONS . "` WHERE `cart_id`='" . $item['id'] . "'";
        $variation_data = $wpdb->get_results($variation_sql, ARRAY_A);
        $variation_count = count($variation_data);
        if ($variation_count >= 1) {
            $variation_list = " (";
            $j = 0;
            foreach ($variation_data as $variation) {
                if ($j > 0) {
                    $variation_list .= ", ";
                }
                $value_id = $variation['venue_id'];
                $value_data = $wpdb->get_results("SELECT * FROM `" . WPSC_TABLE_VARIATION_VALUES . "` WHERE `id`='" . $value_id . "' LIMIT 1", ARRAY_A);
                $variation_list .= $value_data[0]['name'];
                $j++;
            }
            $variation_list .= ")";
        } else {
            $variation_list = '';
        }
        $local_currency_productprice = $item['price'];
        $local_currency_shipping = $item['pnp'];
        $paymentnepal_currency_productprice = $local_currency_productprice;
        $paymentnepal_currency_shipping = $local_currency_shipping;
        $data['amount_' . $i] = number_format(sprintf("%01.2f", $paymentnepal_currency_productprice), $decimal_places, '.', '');
        $data['quantity_' . $i] = $item['quantity'];
        $total_price = $total_price + ($data['amount_' . $i] * $data['quantity_' . $i]);
        if ($all_no_shipping != false)
            $total_price = $total_price + $data['shipping_' . $i] + $data['shipping2_' . $i];
        $i++;
    }
    $base_shipping = $purchase_log[0]['base_shipping'];
    if (($base_shipping > 0) && ($all_donations == false) && ($all_no_shipping == false)) {
        $data['handling_cart'] = number_format($base_shipping, $decimal_places, '.', '');
        $total_price += number_format($base_shipping, $decimal_places, '.', '');
    }
    $data['cost'] = $total_price;
    $data['name'] = 'покупка в магазине';
    //$sum_for_md5 = to_float1($data['price']);
    $data['key'] = get_option('paymentnepal_key');
    if (WPSC_GATEWAY_DEBUG == true) {
        exit("<pre>" . print_r($data, true) . "</pre>");
    }
    $output = "
		<form id=\"paymentnepal_form\" name=\"paymentnepal_form\" method=\"post\" action=\"https://pay.paymentnepal.com/alba/input\">\n";

    foreach ($data as $n => $v) {
        $output .= "			<input type=\"hidden\" name=\"$n\" value=\"$v\" />\n";
    }

    $output .= "			<input type=\"submit\" value=\"Continue to paymentnepal\" />
		</form>
	";

    if (get_option('paymentnepal_debug') == 1) {
        echo ("DEBUG MODE ON!!<br/>");
        echo("The following form is created and would be posted to paymentnepal for processing.  Press submit to continue:<br/>");
        echo("<pre>" . htmlspecialchars($output) . "</pre>");
    }

    echo($output);

    if (get_option('paymentnepal_debug') == 0) {
       echo "<script language=\"javascript\" type=\"text/javascript\">document.getElementById('paymentnepal_form').submit();</script>";
    }

    exit();
}

function nzshpcrt_paymentnepal_callback() {
    if(isset($_GET['paymentnepal_callback'])) {
    global $wpdb;
    $crc = $_POST['check'];
    $paymentnepal_skey = get_option('paymentnepal_skey');
    $data = array(
        'tid' => $_POST['tid'],
        'name' => $_POST['name'],
        'comment' => $_POST['comment'],
        'partner_id' => $_POST['partner_id'],
        'service_id' => $_POST['service_id'],
        'order_id' => $_POST['order_id'],
        'type' => $_POST['type'],
        'partner_income' => $_POST['partner_income'],
        'system_income' => $_POST['system_income'],
        'test' => $_POST['test'],
    );

    $check = md5(join('', array_values($data)) .$paymentnepal_skey );
    if ($check == $crc) { 
    echo 'OK payment order №'.$data[order_id];
    $wpdb->update(WPSC_TABLE_PURCHASE_LOGS, array('processed' => 3, 'date' => time()), array('id' => $data[order_id]), array('%d', '%s'), array('%d'));
                    exit;}
    else echo 'Bad payment!'; exit;
    } 
}

function nzshpcrt_paymentnepal_results() {
    if (isset($_POST['cs1']) && ($_POST['cs1'] != '') && ($_GET['sessionid'] == '')) {
        $_GET['sessionid'] = $_POST['cs1'];
    }
}

function submit_paymentnepal() {
    if (isset($_POST['paymentnepal_skey'])) {
        update_option('paymentnepal_skey', $_POST['paymentnepal_skey']);
    }

    if (isset($_POST['paymentnepal_key'])) {
        update_option('paymentnepal_key', $_POST['paymentnepal_key']);
    }

    if (isset($_POST['paymentnepal_url'])) {
        update_option('paymentnepal_url', $_POST['paymentnepal_url']);
    }
    if (isset($_POST['paymentnepal_debug'])) {
        update_option('paymentnepal_debug', $_POST['paymentnepal_debug']);
    }
    if (!isset($_POST['paymentnepal_form']))
        $_POST['paymentnepal_form'] = array();
    foreach ((array) $_POST['paymentnepal_form'] as $form => $value) {
        update_option(('paymentnepal_form_' . $form), $value);
    }
    return true;
}

function form_paymentnepal() {

    $paymentnepal_url = ( get_option('paymentnepal_url') == '' ? 'http://secure.paymentnepal.com/pay/' . get_option('paymentnepal_login') : get_option('paymentnepal_url') );
    $paymentnepal_salt = ( get_option('paymentnepal_key') == '' ? 'changeme' : get_option('paymentnepal_key') );
        $paymentnepal_debug = get_option('paymentnepal_debug');
    $paymentnepal_debug1 = "";
    $paymentnepal_debug2 = "";
    switch ($paymentnepal_debug) {
        case 0:
            $paymentnepal_debug2 = "checked ='checked'";
            break;
        case 1:
            $paymentnepal_debug1 = "checked ='checked'";
            break;
    }


    $output = "
		<tr>
			<td>Secret key</td>
			<td><input type='text' size='40' value='" . get_option('paymentnepal_skey') . "' name='paymentnepal_skey' /></td>
		</tr>
		<tr>
			<td>&nbsp;</td> <td><small>Enter secret key from your service settings in paymentnepal.com merchant area</small></td>
		</tr>

		<tr>
			<td>Payment key</td>
			<td><input type='text' size='40' value='" . get_option('paymentnepal_key') . "' name='paymentnepal_key' /></td>
		</tr>
		<tr>
			<td>&nbsp;</td> <td><small>Enter payment key from your service settings in paymentnepal.com merchant area</small></td>
		</tr>

		<tr>
			<td>Notification URL</td>
			<td><input type='text' size='40' value='http://" . $_SERVER['SERVER_NAME'] . '/' . "' name='paymentnepal_return_url' /></td>
		</tr>
		<tr>
			<td>&nbsp;</td> <td><small>Copy this url and paste it into Notification URL field in service settings inside paymentnepal.com merchant area</small></td>
		</tr>
    		<tr>
			<td>Debug</td>
			<td>
				<input type='radio' value='1' name='paymentnepal_debug' id='onpay_debug1' " . $paymentnepal_debug1 . " /> <label for='paymentnepal_debug1'>" . __('Yes', 'wpsc') . "</label> &nbsp;
				<input type='radio' value='0' name='paymentnepal_debug' id='onpay_debug2' " . $paymentnepal_debug2 . " /> <label for='paymentnepal_debug2'>" . __('No', 'wpsc') . "</label>
			</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td><small>Debug mode.</small></td>
		</tr>

</tr>";

    return $output;
}

add_action('init', 'nzshpcrt_paymentnepal_callback');
add_action('init', 'nzshpcrt_paymentnepal_results');
?>
