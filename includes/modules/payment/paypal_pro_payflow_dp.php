<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2020 osCommerce

  Released under the GNU General Public License
*/

  class paypal_pro_payflow_dp extends abstract_payment_module {

    const REQUIRES = [
      'firstname',
      'lastname',
      'street_address',
      'city',
      'postcode',
      'country',
      'telephone',
      'email_address',
    ];

    const CONFIG_KEY_BASE = 'MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_';

    public $signature = 'paypal|paypal_pro_payflow_dp|3.0|2.3';

    public function __construct() {
      parent::__construct();

      if ( empty(self::get_constant(MODULE_PAYMENT_INSTALLED)) || !in_array('paypal_pro_payflow_ec.php', explode(';', MODULE_PAYMENT_INSTALLED)) || !defined('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_STATUS') || (MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_STATUS != 'True') ) {
        $this->description = '<div class="secWarning">' . MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_ERROR_EXPRESS_MODULE . '</div>' . $this->description;

        $this->enabled = false;
      }

      if ( defined('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_STATUS') ) {
        if ( MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_TRANSACTION_SERVER == 'Sandbox' ) {
          $this->title .= ' [Sandbox]';
          $this->public_title .= ' (' . $this->code . '; Sandbox)';
        }

        $this->description .= $this->getTestLinkInfo();
      }

      if ( !function_exists('curl_init') ) {
        $this->description = '<div class="secWarning">' . MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_ERROR_ADMIN_CURL . '</div>' . $this->description;

        $this->enabled = false;
      }

      if ( $this->enabled === true ) {
        if ( !tep_not_null(MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_VENDOR) || !tep_not_null(MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_PASSWORD) ) {
          $this->description = '<div class="secWarning">' . MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_ERROR_ADMIN_CONFIGURATION . '</div>' . $this->description;

          $this->enabled = false;
        }
      }

      if ( ('modules.php' === $GLOBALS['PHP_SELF']) && ('install' === ($_GET['action'] ?? null)) && ('conntest' === ($_GET['subaction'] ?? null)) ) {
        echo $this->getTestConnectionResult();
        exit;
      }
    }

    function pre_confirmation_check() {
        $GLOBALS['oscTemplate']->addBlock('<style>.date-fields .form-control {width:auto;display:inline-block}</style>', 'header_tags');
        $GLOBALS['oscTemplate']->addBlock($this->getSubmitCardDetailsJavascript(), 'footer_scripts');
    }

    public function confirmation() {
      global $order;

      $today = getdate();

      $months_array = [];
      for ($i = 1; $i <= 12; $i++) {
        $months_array[] = ['id' => sprintf('%02d', $i), 'text' => sprintf('%02d', $i)];
      }

      $year_expires_array = [];
      for ($i = $today['year']; $i < $today['year'] + 10; $i++) {
        $year_expires_array[] = ['id' => strftime('%y',mktime(0, 0, 0, 1, 1, $i)), 'text' => strftime('%Y',mktime(0, 0, 0, 1, 1, $i))];
      }

      $content = '<table id="paypal_table_new_card" border="0" width="100%" cellspacing="0" cellpadding="2">'
               . '<tr>'
               . '  <td width="30%">' . MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_CARD_OWNER_FIRSTNAME . '</td>'
               . '  <td>' . tep_draw_input_field('cc_owner_firstname', $order->billing['firstname']) . '</td>'
               . '</tr>'
               . '<tr>'
               . '  <td width="30%">' . MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_CARD_OWNER_LASTNAME . '</td>'
               . '  <td>' . tep_draw_input_field('cc_owner_lastname', $order->billing['lastname']) . '</td>'
               . '</tr>'
               . '<tr>'
               . '  <td width="30%">' . MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_CARD_NUMBER . '</td>'
               . '  <td>' . tep_draw_input_field('cc_number_nh-dns', '', 'id="paypal_card_num"') . '</td>'
               . '</tr>'
               . '<tr>'
               . '  <td width="30%">' . MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_CARD_EXPIRES . '</td>'
               . '  <td class="date-fields">' . tep_draw_pull_down_menu('cc_expires_month', $months_array) . '&nbsp;' . tep_draw_pull_down_menu('cc_expires_year', $year_expires_array) . '</td>'
               . '</tr>'
               . '<tr>'
               . '  <td width="30%">' . MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_CARD_CVC . '</td>'
               . '  <td>' . tep_draw_input_field('cc_cvc_nh-dns', '', 'size="5" maxlength="4"') . '</td>'
               . '</tr>'
               . '</table>';

      $content .= !$this->templateClassExists() ? $this->getSubmitCardDetailsJavascript() : '';

      $confirmation = ['title' => $content];

      return $confirmation;
    }

    public function before_process() {
      global $order, $order_totals, $sendto, $response_array;

      if (isset($_POST['cc_owner_firstname']) && !empty($_POST['cc_owner_firstname']) && isset($_POST['cc_owner_lastname']) && !empty($_POST['cc_owner_lastname']) && isset($_POST['cc_number_nh-dns']) && !empty($_POST['cc_number_nh-dns'])) {
        if (MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_TRANSACTION_SERVER == 'Live') {
          $api_url = 'https://payflowpro.paypal.com';
        } else {
          $api_url = 'https://pilot-payflowpro.paypal.com';
        }

        $params = [
          'USER' => (tep_not_null(MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_USERNAME) ? MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_USERNAME : MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_VENDOR),
          'VENDOR' => MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_VENDOR,
          'PARTNER' => MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_PARTNER,
          'PWD' => MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_PASSWORD,
          'TENDER' => 'C',
          'TRXTYPE' => ((MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_TRANSACTION_METHOD == 'Sale') ? 'S' : 'A'),
          'AMT' => $this->format_raw($order->info['total']),
          'CURRENCY' => $order->info['currency'],
          'BILLTOFIRSTNAME' => $_POST['cc_owner_firstname'],
          'BILLTOLASTNAME' => $_POST['cc_owner_lastname'],
          'BILLTOSTREET' => $order->billing['street_address'],
          'BILLTOCITY' => $order->billing['city'],
          'BILLTOSTATE' => tep_get_zone_code($order->billing['country']['id'], $order->billing['zone_id'], $order->billing['state']),
          'BILLTOCOUNTRY' => $order->billing['country']['iso_code_2'],
          'BILLTOZIP' => $order->billing['postcode'],
          'CUSTIP' => tep_get_ip_address(),
          'EMAIL' => $order->customer['email_address'],
          'ACCT' => $_POST['cc_number_nh-dns'],
          'EXPDATE' => $_POST['cc_expires_month'] . $_POST['cc_expires_year'],
          'CVV2' => $_POST['cc_cvc_nh-dns'],
          'BUTTONSOURCE' => 'OSCOM23_DPPF',
        ];

        if (is_numeric($sendto) && ($sendto > 0)) {
          $params['SHIPTOFIRSTNAME'] = $order->delivery['firstname'];
          $params['SHIPTOLASTNAME'] = $order->delivery['lastname'];
          $params['SHIPTOSTREET'] = $order->delivery['street_address'];
          $params['SHIPTOCITY'] = $order->delivery['city'];
          $params['SHIPTOSTATE'] = tep_get_zone_code($order->delivery['country']['id'], $order->delivery['zone_id'], $order->delivery['state']);
          $params['SHIPTOCOUNTRY'] = $order->delivery['country']['iso_code_2'];
          $params['SHIPTOZIP'] = $order->delivery['postcode'];
        }

        $item_params = [];

        $line_item_no = 0;

        foreach ($order->products as $product) {
          $item_params['L_NAME' . $line_item_no] = $product['name'];
          $item_params['L_COST' . $line_item_no] = $this->format_raw($product['final_price']);
          $item_params['L_QTY' . $line_item_no] = $product['qty'];

          $line_item_no++;
        }

        $items_total = $this->format_raw($order->info['subtotal']);

        foreach ($order_totals as $ot) {
          if ( !in_array($ot['code'], ['ot_subtotal', 'ot_shipping', 'ot_tax', 'ot_total']) ) {
            $item_params['L_NAME' . $line_item_no] = $ot['title'];
            $item_params['L_COST' . $line_item_no] = $this->format_raw($ot['value']);
            $item_params['L_QTY' . $line_item_no] = 1;

            $items_total += $this->format_raw($ot['value']);

            $line_item_no++;
          }
        }

        $item_params['ITEMAMT'] = $items_total;
        $item_params['TAXAMT'] = $this->format_raw($order->info['tax']);
        $item_params['FREIGHTAMT'] = $this->format_raw($order->info['shipping_cost']);

        if ( $this->format_raw($item_params['ITEMAMT'] + $item_params['TAXAMT'] + $item_params['FREIGHTAMT']) == $params['AMT'] ) {
          $params = array_merge($params, $item_params);
        }

        $post_string = '';

        foreach ($params as $key => $value) {
          $post_string .= $key . '[' . strlen(trim($value)) . ']=' . trim($value) . '&';
        }

        $post_string = substr($post_string, 0, -1);

        $response = $this->sendTransactionToGateway($api_url, $post_string);

        $response_array = [];
        parse_str($response, $response_array);

        if ($response_array['RESULT'] != '0') {
          $this->sendDebugEmail($response_array);

          switch ($response_array['RESULT']) {
            case '1':
            case '26':
              $error_message = MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_ERROR_CFG_ERROR;
              break;

            case '7':
              $error_message = MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_ERROR_ADDRESS;
              break;

            case '12':
              $error_message = MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_ERROR_DECLINED;
              break;

            case '23':
            case '24':
              $error_message = MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_ERROR_INVALID_CREDIT_CARD;
              break;

            default:
              $error_message = MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_ERROR_GENERAL;
              break;
          }

          tep_redirect(tep_href_link('checkout_confirmation.php', 'error_message=' . urlencode($error_message), 'SSL'));
        }
      } else {
        tep_redirect(tep_href_link('checkout_confirmation.php', 'error_message=' . MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_ERROR_ALL_FIELDS_REQUIRED, 'SSL'));
      }
    }

    public function after_process() {
      global $order_id, $response_array;

      $pp_result = 'Payflow ID: ' . tep_output_string_protected($response_array['PNREF']) . "\n"
                 . 'PayPal ID: ' . tep_output_string_protected($response_array['PPREF']) . "\n"
                 . 'Response: ' . tep_output_string_protected($response_array['RESPMSG']) . "\n";

      switch ($response_array['AVSADDR']) {
        case 'Y':
          $pp_result .= 'AVS Address: Match' . "\n";
          break;

        case 'N':
          $pp_result .= 'AVS Address: No Match' . "\n";
          break;
      }

      switch ($response_array['AVSZIP']) {
        case 'Y':
          $pp_result .= 'AVS ZIP: Match' . "\n";
          break;

        case 'N':
          $pp_result .= 'AVS ZIP: No Match' . "\n";
          break;
      }

      switch ($response_array['IAVS']) {
        case 'Y':
          $pp_result .= 'IAVS: International' . "\n";
          break;

        case 'N':
          $pp_result .= 'IAVS: USA' . "\n";
          break;
      }

      switch ($response_array['CVV2MATCH']) {
        case 'Y':
          $pp_result .= 'CVV2: Match' . "\n";
          break;

        case 'N':
          $pp_result .= 'CVV2: No Match' . "\n";
          break;
      }

      $sql_data_array = [
        'orders_id' => $order_id,
        'orders_status_id' => MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_TRANSACTIONS_ORDER_STATUS_ID,
        'date_added' => 'NOW()',
        'customer_notified' => '0',
        'comments' => trim($pp_result),
      ];

      tep_db_perform('orders_status_history', $sql_data_array);
    }

    protected function get_parameters() {
      return [
        'MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_STATUS' => [
          'title' => 'Enable PayPal Payments Pro (Payflow Edition)',
          'desc' => 'Do you want to accept PayPal Payments Pro (Payflow Edition) payments?',
          'value' => 'True',
          'set_func' => "tep_cfg_select_option(['True', 'False'], ",
        ],
        'MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_VENDOR' => [
          'title' => 'Vendor',
          'desc' => 'Your merchant login ID that you created when you registered for the PayPal Payments Pro account.',
        ],
        'MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_USERNAME' => [
          'title' => 'User',
          'desc' => 'If you set up one or more additional users on the account, this value is the ID of the user authorised to process transactions. If, however, you have not set up additional users on the account, USER has the same value as VENDOR.',
        ],
        'MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_PASSWORD' => [
          'title' => 'Password',
          'desc' => 'The 6- to 32-character password that you defined while registering for the account.',
        ],
        'MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_PARTNER' => [
          'title' => 'Partner',
          'desc' => 'The ID provided to you by the authorised PayPal Reseller who registered you for the Payflow SDK. If you purchased your account directly from PayPal, use PayPalUK.',
          'value' => 'PayPalUK',
        ],
        'MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_TRANSACTION_METHOD' => [
          'title' => 'Transaction Method',
          'desc' => 'The processing method to use for each transaction.',
          'value' => 'Sale',
          'set_func' => "tep_cfg_select_option(['Authorization', 'Sale'], ",
        ],
        'MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_ORDER_STATUS_ID' => [
          'title' => 'Set Order Status',
          'desc' => 'Set the status of orders made with this payment module to this value.',
          'value' => '0',
          'set_func' => 'tep_cfg_pull_down_order_statuses(',
          'use_func' => 'tep_get_order_status_name',
        ],
        'MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_TRANSACTIONS_ORDER_STATUS_ID' => [
          'title' => 'PayPal Transactions Order Status Level',
          'desc' => 'Include PayPal transaction information in this order status level.',
          'value' => self::ensure_order_status('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_TRANSACTIONS_ORDER_STATUS_ID', 'PayPal [Transactions]'),
          'use_func' => 'tep_get_order_status_name',
          'set_func' => 'tep_cfg_pull_down_order_statuses(',
        ],
        'MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_ZONE' => [
          'title' => 'Payment Zone',
          'desc' => 'If a zone is selected, only enable this payment method for that zone.',
          'value' => '0',
          'set_func' => 'tep_cfg_pull_down_zone_classes(',
          'use_func' => 'tep_get_zone_class_title',
        ],
        'MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_TRANSACTION_SERVER' => [
          'title' => 'Transaction Server',
          'desc' => 'Use the live or testing (sandbox) gateway server to process transactions?',
          'value' => 'Live',
          'set_func' => "tep_cfg_select_option(['Live', 'Sandbox'], ",
        ],
        'MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_VERIFY_SSL' => [
          'title' => 'Verify SSL Certificate',
          'desc' => 'Verify gateway server SSL certificate on connection?',
          'value' => 'True',
          'set_func' => "tep_cfg_select_option(['True', 'False'], ",
        ],
        'MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_PROXY' => [
          'title' => 'Proxy Server',
          'desc' => 'Send API requests through this proxy server. (host:port, eg: 123.45.67.89:8080 or proxy.example.com:8080)',
        ],
        'MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_DEBUG_EMAIL' => [
          'title' => 'Debug E-Mail Address',
          'desc' => 'All parameters of an invalid transaction will be sent to this email address.',
        ],
        'MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_SORT_ORDER' => [
          'title' => 'Sort order of display.',
          'desc' => 'Sort order of display. Lowest is displayed first.',
          'value' => '0',
        ],
      ];
    }

    function sendTransactionToGateway($url, $parameters) {
      global $cartID, $order;

      $server = parse_url($url);

      if ( !isset($server['port']) ) {
        $server['port'] = ($server['scheme'] == 'https') ? 443 : 80;
      }

      if ( !isset($server['path']) ) {
        $server['path'] = '/';
      }

      $request_id = (isset($order) && is_object($order)) ? md5($cartID . tep_session_id() . $this->format_raw($order->info['total'])) : 'oscom_conn_test';

      $headers = [
        'X-VPS-REQUEST-ID: ' . $request_id,
        'X-VPS-CLIENT-TIMEOUT: 45',
        'X-VPS-VIT-INTEGRATION-PRODUCT: OSCOM',
        'X-VPS-VIT-INTEGRATION-VERSION: 2.3',
      ];

      $curl = curl_init($server['scheme'] . '://' . $server['host'] . $server['path'] . (isset($server['query']) ? '?' . $server['query'] : ''));
      curl_setopt($curl, CURLOPT_PORT, $server['port']);
      curl_setopt($curl, CURLOPT_HEADER, false);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($curl, CURLOPT_FORBID_REUSE, true);
      curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
      curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($curl, CURLOPT_POST, true);
      curl_setopt($curl, CURLOPT_POSTFIELDS, $parameters);

      if ( MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_VERIFY_SSL == 'True' ) {
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);

        if ( file_exists(DIR_FS_CATALOG . 'ext/modules/payment/paypal/paypal.com.crt') ) {
          curl_setopt($curl, CURLOPT_CAINFO, DIR_FS_CATALOG . 'ext/modules/payment/paypal/paypal.com.crt');
        } elseif ( file_exists(DIR_FS_CATALOG . 'includes/cacert.pem') ) {
          curl_setopt($curl, CURLOPT_CAINFO, DIR_FS_CATALOG . 'includes/cacert.pem');
        }
      } else {
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
      }

      if ( tep_not_null(MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_PROXY) ) {
        curl_setopt($curl, CURLOPT_HTTPPROXYTUNNEL, true);
        curl_setopt($curl, CURLOPT_PROXY, MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_PROXY);
      }

      $result = curl_exec($curl);

      curl_close($curl);

      return $result;
    }

// format prices without currency formatting
    function format_raw($number, $currency_code = '', $currency_value = '') {
      global $currencies, $currency;

      if (empty($currency_code) || !$this->is_set($currency_code)) {
        $currency_code = $currency;
      }

      if (empty($currency_value) || !is_numeric($currency_value)) {
        $currency_value = $currencies->currencies[$currency_code]['value'];
      }

      return number_format(tep_round($number * $currency_value, $currencies->currencies[$currency_code]['decimal_places']), $currencies->currencies[$currency_code]['decimal_places'], '.', '');
    }

    function getTestLinkInfo() {
      $dialog_title = MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_DIALOG_CONNECTION_TITLE;
      $dialog_button_close = MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_DIALOG_CONNECTION_BUTTON_CLOSE;
      $dialog_success = MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_DIALOG_CONNECTION_SUCCESS;
      $dialog_failed = MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_DIALOG_CONNECTION_FAILED;
      $dialog_error = MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_DIALOG_CONNECTION_ERROR;
      $dialog_connection_time = MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_DIALOG_CONNECTION_TIME;

      $test_url = tep_href_link('modules.php', 'set=payment&module=' . $this->code . '&action=install&subaction=conntest');

      $js = <<<EOD
<script>
$(function() {
  $('#tcdprogressbar').progressbar({
    value: false
  });
});

function openTestConnectionDialog() {
  var d = $('<div>').html($('#testConnectionDialog').html()).dialog({
    modal: true,
    title: '{$dialog_title}',
    buttons: {
      '{$dialog_button_close}': function () {
        $(this).dialog('destroy');
      }
    }
  });

  var timeStart = new Date().getTime();

  $.ajax({
    url: '{$test_url}'
  }).done(function(data) {
    if ( data == '1' ) {
      d.find('#testConnectionDialogProgress').html('<p style="font-weight: bold; color: green;">{$dialog_success}</p>');
    } else {
      d.find('#testConnectionDialogProgress').html('<p style="font-weight: bold; color: red;">{$dialog_failed}</p>');
    }
  }).fail(function() {
    d.find('#testConnectionDialogProgress').html('<p style="font-weight: bold; color: red;">{$dialog_error}</p>');
  }).always(function() {
    var timeEnd = new Date().getTime();
    var timeTook = new Date(0, 0, 0, 0, 0, 0, timeEnd-timeStart);

    d.find('#testConnectionDialogProgress').append('<p>{$dialog_connection_time} ' + timeTook.getSeconds() + '.' + timeTook.getMilliseconds() + 's</p>');
  });
}
</script>
EOD;

      $info = '<p><i class="fas fa-lock"></i>&nbsp;<a href="javascript:openTestConnectionDialog();" style="text-decoration: underline; font-weight: bold;">' . MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_DIALOG_CONNECTION_LINK_TITLE . '</a></p>'
            . '<div id="testConnectionDialog" style="display: none;"><p>';

      if ( MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_TRANSACTION_SERVER == 'Live' ) {
        $info .= 'Live Server:<br>https://payflowpro.paypal.com';
      } else {
        $info .= 'Sandbox Server:<br>https://pilot-payflowpro.paypal.com';
      }

      $info .= '</p><div id="testConnectionDialogProgress"><p>' . MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_DIALOG_CONNECTION_GENERAL_TEXT . '</p><div id="tcdprogressbar"></div></div></div>'
             . $js;

      return $info;
    }

    function getTestConnectionResult() {
      if (MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_TRANSACTION_SERVER == 'Live') {
        $api_url = 'https://payflowpro.paypal.com';
      } else {
        $api_url = 'https://pilot-payflowpro.paypal.com';
      }

      $params = [
        'USER' => (tep_not_null(MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_USERNAME) ? MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_USERNAME : MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_VENDOR),
        'VENDOR' => MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_VENDOR,
        'PARTNER' => MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_PARTNER,
        'PWD' => MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_PASSWORD,
        'TENDER' => 'C',
        'TRXTYPE' => ((MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_TRANSACTION_METHOD == 'Sale') ? 'S' : 'A'),
      ];

      $post_string = '';

      foreach ($params as $key => $value) {
        $post_string .= $key . '[' . strlen(trim($value)) . ']=' . trim($value) . '&';
      }

      $post_string = substr($post_string, 0, -1);

      $response = $this->sendTransactionToGateway($api_url, $post_string);

      $response_array = [];
      parse_str($response, $response_array);

      if ( is_array($response_array) && isset($response_array['RESULT']) ) {
        return 1;
      }

      return -1;
    }

    function templateClassExists() {
      return $GLOBALS['oscTemplate'] instanceof oscTemplate;
    }

    function getSubmitCardDetailsJavascript() {
      $test_visa = '';

      if ( MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_TRANSACTION_SERVER == 'Sandbox' ) {
        $test_visa = <<<EOD
    if ( $('#paypal_card_num').val().length < 1 ) {
      $('#paypal_card_num').val('4641631486853053');
    }
EOD;
      }

      $js = <<<EOD
<script>
$(function() {
  if ( typeof($('#paypal_table_new_card').parent().closest('table').attr('width')) == 'undefined' ) {
    $('#paypal_table_new_card').parent().closest('table').attr('width', '100%');
  }

  {$test_visa}
});
</script>
EOD;

      return $js;
    }

    function sendDebugEmail($response = []) {
      if (tep_not_null(MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_DEBUG_EMAIL)) {
        $email_body = '';

        if (!empty($response)) {
          $email_body .= 'RESPONSE:' . "\n\n" . print_r($response, true) . "\n\n";
        }

        if (!empty($_POST)) {
          if (isset($_POST['cc_number_nh-dns'])) {
            $_POST['cc_number_nh-dns'] = 'XXXX' . substr($_POST['cc_number_nh-dns'], -4);
          }

          if (isset($_POST['cc_cvc_nh-dns'])) {
            $_POST['cc_cvc_nh-dns'] = 'XXX';
          }

          if (isset($_POST['cc_expires_month'])) {
            $_POST['cc_expires_month'] = 'XX';
          }

          if (isset($_POST['cc_expires_year'])) {
            $_POST['cc_expires_year'] = 'XX';
          }

          $email_body .= '$_POST:' . "\n\n" . print_r($_POST, true) . "\n\n";
        }

        if (!empty($_GET)) {
          $email_body .= '$_GET:' . "\n\n" . print_r($_GET, true) . "\n\n";
        }

        if (!empty($email_body)) {
          tep_mail('', MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_DP_DEBUG_EMAIL, 'PayPal Payments Pro (Payflow Edition) Debug E-Mail', trim($email_body), STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
        }
      }
    }
  }
