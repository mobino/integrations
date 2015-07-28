<?php
/* -----------------------------------------------------------------------------------------
   Released under the GNU General Public License (Version 2)
   [http://www.gnu.org/licenses/gpl-2.0.html]
   -----------------------------------------------------------------------------------------
*/
  class mobino_ORIGIN {
    const USER_AGENT = 'mobino-php/1.0.0';
    var $code, $title, $description, $enabled, $orderid, $productive;
    public static $lastRawResponse;
    public static $lastRawCurlOptions;

    private $_responseArray = null;
    private $_apiUrl = 'https://app.mobino.com/merchants';

    function mobino_ORIGIN() {
        global $order;

        $this->code = 'mobino';
        $this->title = 'Mobino';
        $this->description = 'mobino payment';
        $this->info = '';
        $this->sort_order = 'sort order';
        $this->enabled = ((MODULE_PAYMENT_MOBINO_STATUS == 'True') ? true : false);
        $this->orderid = '';
        $this->images = explode(',', MODULE_PAYMENT_MOBINO_IMAGES);

        if ((int)MODULE_PAYMENT_MOBINO_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_MOBINO_ORDER_STATUS_ID;
        }

        if (is_object($order)) $this->update_status();
        $this->form_action_url = 'https://app.mobino.com/merchants/pay';
        $this->tmpOrders = true;
        $this->tmpStatus = 1;
        $this->_log( "mobino_ORIGIN(): order_status: ".$this->order_status);
    }

    function update_status() {
        global $order;
    }

    function javascript_validation() {
        return false;
    }

    function selection() {
        $images = '';
        foreach ($this->images as $image) {
            $images .= '<img src="includes/modules/payment/images/mobino-badge.png" />&nbsp;';
        }  
        $name = '<img src="includes/modules/payment/images/mobino-badge.png" />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $this->title;
        return array('id' => $this->code, 'module' => $name, 'description' => $this->info);
    }

    function pre_confirmation_check() {
        return false;
    }

    function confirmation() {
        return false;
    }

    function process_button() {
        return false;
    }

    function generateRandomString($length = 10, $dashed=true) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            if (($i == 4 || $i == 8) && $dashed) {
                $randomString.="-";
            }
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    function payment_action() {
        global $order, $xtPrice, $currencies, $customer_id, $insert_id, $tmp;        
        
        $this->_log( "payment_action(): " . $customer_id . ", insert_id:" . $insert_id);
        $this->_log( "payment_action(): order_status: ".$this->order_status);

        if (MODULE_PAYMENT_MOBINO_CURRENCY == 'Selected Currency') {
            $currency = $_SESSION['currency'];
        }
        else {
            $currency = MODULE_PAYMENT_MOBINO_CURRENCY;
        }

        $language = $_SESSION['language_code'];
            
        $this->orderid = $this->getorderid();

        if ($_SESSION['customers_status']['customers_status_show_price_tax'] == 0 && $_SESSION['customers_status']['customers_status_add_tax_ot'] == 1) {
            $amount = round($order->info['total'] + $order->info['tax'], $xtPrice->get_decimal_places($currency));
        }
        else {
            $amount = round($order->info['total'], $xtPrice->get_decimal_places($currency));
        }

        if (ENABLE_SSL == true) {
            $homeurl = HTTPS_SERVER;
        }
        else {
            $homeurl = HTTP_SERVER;
        }
        
        $catalogurl = $homeurl . DIR_WS_CATALOG;
        $nonce = $this->generateRandomString(12,false);

        $arrParams = array(
            'amount'        => $amount,
            'currency'      => $currency,
            'language'      => $language,
            'api_key'       => MODULE_PAYMENT_MOBINO_API_KEY,
            'reference'     => "gambio-".$insert_id,
            'return_url'    => $catalogurl . '/checkout_process.php',
            'abort_url'     => $catalogurl . '/checkout_payment.php',
            'nonce'         => $nonce,
            'signature'     => sha1($nonce.MODULE_PAYMENT_MOBINO_API_SECRET)
        );
        
        $query = '';
        foreach($arrParams as $key => $value) {
            $query  .= $key . '=' . urlencode($value) . '&';
        }

        xtc_redirect($this->form_action_url. '?' . $query);
        exit;
    }

    function before_process() {
      return false;
    }

    function after_process() {
        global $insert_id;
        $this->_log( "after_process(): insert_id:" . $insert_id.", order_status: ".$this->order_status);
        if (array_values($this->checkRefNum("gambio-".$insert_id))[0]['reference_number'] == "gambio-".$insert_id) {
            $this->_log( "after_process(): check succeeded!");
            if ($this->order_status) {
                xtc_db_query("UPDATE ".TABLE_ORDERS." SET orders_status='".$this->order_status."' WHERE orders_id='".$insert_id."'");
            }
        }
        else {
             if (ENABLE_SSL == true) {
                $homeurl = HTTPS_SERVER;
            }
            else {
                $homeurl = HTTP_SERVER;
            }
            $catalogurl = $homeurl . DIR_WS_CATALOG;
            $this->_log( "after_process(): check failed!");
            $this->output_error();
            xtc_redirect($catalogurl . '/checkout_payment.php');
        }
    }

    function output_error() {
        $error = array('title' => MODULE_PAYMENT_MOBINO_TEXT_ERROR,
                 'error' => MODULE_PAYMENT_MOBINO_ERROR);
    }

    function check() {
        if (!isset($this->_check)) {
            $check_query = xtc_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_MOBINO_STATUS'");
            $this->_check = xtc_db_num_rows($check_query);
        }
        return $this->_check;
    }

    function install() {
        xtc_db_query("insert into " . TABLE_CONFIGURATION . " ( configuration_key, configuration_value,  configuration_group_id, sort_order, set_function, date_added) values
                ('MODULE_PAYMENT_MOBINO_STATUS', 'True', '6', '3', 'xtc_cfg_select_option(array(\'True\', \'False\'), ', now())");
        xtc_db_query("insert into " . TABLE_CONFIGURATION . " ( configuration_key, configuration_value,  configuration_group_id, sort_order, date_added) values 
                ('MODULE_PAYMENT_MOBINO_API_KEY', '',  '6', '4', now())");
        xtc_db_query("insert into " . TABLE_CONFIGURATION . " ( configuration_key, configuration_value,  configuration_group_id, sort_order, date_added) values 
                ('MODULE_PAYMENT_MOBINO_API_SECRET', '',  '6', '4', now())");
        xtc_db_query("insert into " . TABLE_CONFIGURATION . " ( configuration_key, configuration_value,  configuration_group_id, sort_order, date_added) values 
                ('MODULE_PAYMENT_MOBINO_ALLOWED', '', '6', '0', now())");
        xtc_db_query("insert into " . TABLE_CONFIGURATION . " ( configuration_key, configuration_value,  configuration_group_id, sort_order, set_function, date_added) values
                ('MODULE_PAYMENT_MOBINO_CURRENCY', 'Selected Currency',  '6', '6', 'xtc_cfg_select_option(array(\'Selected Currency\',\'CHF\',\'EUR\',\'USD\'), ', now())");
        xtc_db_query("insert into " . TABLE_CONFIGURATION . " ( configuration_key, configuration_value,  configuration_group_id, sort_order, date_added) values
                ('MODULE_PAYMENT_MOBINO_SORT_ORDER', '0', '6', '0', now())");
        xtc_db_query("insert into " . TABLE_CONFIGURATION . " ( configuration_key, configuration_value,  configuration_group_id, sort_order, set_function, use_function, date_added) values
                ('MODULE_PAYMENT_MOBINO_ORDER_STATUS_ID', '0',  '6', '0', 'xtc_cfg_pull_down_order_statuses(', 'xtc_get_order_status_name', now())");
    }

    function remove() {
        xtc_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
        return array('MODULE_PAYMENT_MOBINO_API_KEY', 'MODULE_PAYMENT_MOBINO_API_SECRET', 'MODULE_PAYMENT_MOBINO_STATUS','MODULE_PAYMENT_MOBINO_PROD', 'MODULE_PAYMENT_MOBINO_IMAGES','MODULE_PAYMENT_MOBINO_LANGUAGE', 'MODULE_PAYMENT_MOBINO_ALLOWED', 'MODULE_PAYMENT_MOBINO_CURRENCY', 'MODULE_PAYMENT_MOBINO_ORDER_STATUS_ID', 'MODULE_PAYMENT_MOBINO_SORT_ORDER', 'MODULE_PAYMENT_MOBINO_ZONE', 'MODULE_PAYMENT_MOBINO_SHA1_SIGNATURE');
    }

    function getorderid() {
        return xtc_create_random_value(20, "digits");
    }

    public function checkRefNum($refnum) {
        $this->_log( "checkRefNum(): ".$refnum);
        $apikey = trim(MODULE_PAYMENT_MOBINO_API_KEY);
        $apisecret = trim(MODULE_PAYMENT_MOBINO_API_SECRET);
        $nonce = $this->generateRandomString(12, false);
        $params = array(
            "reference_regex" => $refnum,
            "api_key" => $apikey,
            "nonce" => $nonce,
            "signature" => sha1($nonce.$apisecret)
        );
        $this->_log( "checkRefNum(): calling mobino API...");
        return $this->post("/api/v1/transactions/filter_by_reference", $params);
    }

    public function post($url, $itemData = array()) {
        $response = $this->make_request(
            $url,
            $itemData,
            "POST"
        );
        return $response;
    }

    public function make_request($action, $params = array(), $method = 'POST') {
        if (!is_array($params)) {
            $params = array();
        }

        try {
            $this->_responseArray = $this->_requestApi($action, $params, $method);
            $httpStatusCode = $this->_responseArray['header']['status'];
            if ($httpStatusCode != 200) {
                $errorMessage = 'Client returned HTTP status code ' . $httpStatusCode;
                if (isset($this->_responseArray['body']['error'])) {
                    $errorMessage = $this->_responseArray['body']['error'];
                }
                $responseCode = '';
                if (isset($this->_responseArray['body']['response_code'])) {
                    $responseCode = $this->_responseArray['body']['response_code'];
                }
                if ($responseCode === '' && isset($this->_responseArray['body']['data']['response_code'])) {
                    $responseCode = $this->_responseArray['body']['data']['response_code'];
                }

                return array("data" => array(
                        "error" => $errorMessage,
                        "response_code" => $responseCode,
                        "http_status_code" => $httpStatusCode
                        ));
            }

            return $this->_responseArray['body'];
        }
        catch (Exception $e) {
            $this->_log( "make_request(): ".$e->getMessage());
            return array("data" => array("error" => $e->getMessage()));
        }
    }

    /**
     * Perform HTTP request to REST endpoint
     *
     * @param string $action
     * @param array $params
     * @param string $method
     * @return array
     */
    protected function _requestApi($action = '', $params = array(), $method = 'POST') {
        $curlOpts = array(
            CURLOPT_URL => $this->_apiUrl . $action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_USERAGENT => self::USER_AGENT,
            //CURLOPT_SSL_VERIFYPEER => true,
            //CURLOPT_CAINFO => realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'mobino.crt',
        );

        if ("GET" === $method) {
            if (0 !== count($params)) {
                $curlOpts[CURLOPT_URL] .= false === strpos($curlOpts[CURLOPT_URL], '?') ? '?' : '&';
                $curlOpts[CURLOPT_URL] .= http_build_query($params, null, '&');
            }
        }
        else {
            $curlOpts[CURLOPT_POSTFIELDS] = http_build_query($params, null, '&');
        }

        $curl = curl_init();
        curl_setopt_array($curl, $curlOpts);
        $responseBody = curl_exec($curl);
        self::$lastRawCurlOptions = $curlOpts;
        self::$lastRawResponse = $responseBody;
        $responseInfo = curl_getinfo($curl);
        if ($responseBody === false) {
            $responseBody = array('error' => curl_error($curl));
        }
        curl_close($curl);

        $this->_log( "_requestApi(): raw: ".$responseBody);
        if ('application/json' === $responseInfo['content_type']) {
            $responseBody = json_decode($responseBody, true);
        }

        return array(
            'header' => array(
                'status' => $responseInfo['http_code'],
                'reason' => null,
            ),
            'body' => $responseBody
        );
    }

    function _log($text) {
        /*
        $log_fp = fopen( "/tmp/mobino.log" ,"a");
        fwrite($log_fp, gmdate("M d Y H:i:s", time()). "> " . $text . "\n");
        fclose($log_fp);
        */
    }
  }

MainFactory::load_origin_class('mobino');
?>