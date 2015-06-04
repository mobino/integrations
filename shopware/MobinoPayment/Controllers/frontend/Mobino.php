<?php

/**
 * Copyright © 2015 Mobino SA
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 *
 * @category   Shopware
 * @package    Shopware_Plugins
 * @subpackage Mobino
 * @author     Mobino
 */
class Shopware_Controllers_Frontend_PaymentMobino extends Shopware_Controllers_Frontend_Payment
{
    const USER_AGENT = 'mobino-php/1.0.0';
    public static $lastRawResponse;
    public static $lastRawCurlOptions;

    private $config;
    private $_responseArray = null;
    private $_apiUrl = 'https://app.mobino.com/merchants';

    public function init(){
        $this->config = Shopware()->Plugins()->Frontend()->MobinoPayment()->Config();
    }

    public function indexAction()
    {
        if (Shopware()->Session()->token_requested == false) {
            return $this->redirect(array('action' => 'gateway', 'forceSecure' => true));
        }
        else {
            if (array_values($this->checkRefNum())[0]['reference_number'] == $refnum) {
                return $this->redirect(array('action' => 'gateway', 'forceSecure' => true));
            }
            return $this->redirect(array('action' => 'result', 'forceSecure' => false));
        }
    }

    /**
     * Redirects to the confirmation page and sets an error message.
     */
    public function errorAction()
    {
        $errorMessage = null;
        if (isset(Shopware()->Session()->pigmbhErrorMessage)) {
            $errorMessage = 1;
        }

        $this->redirect(array("controller"   => "checkout", "action" => "confirm", "forceSecure" => 1,
                              "errorMessage" => $errorMessage));
    }

    public function generateRandomString($length = 10, $dashed=true) {
        //$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
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

    public function gatewayAction()
    {
        $router = $this->Front()->Router();
        $params['returnUrl'] = $router->assemble(array('action' => 'return', 'forceSecure' => true));
        $params['cancelUrl'] = $router->assemble(array('action' => 'cancel', 'forceSecure' => true));
        $params['notifyUrl'] = $router->assemble(array('action' => 'notify', 'forceSecure' => true, 'appendSession' => true));
 
        $apikey = trim($this->config->get("apikey"));
        $apisecret = trim($this->config->get("apisecret"));

        $nonce = $this->generateRandomString(12,false);
        $refnum = "mww-".$this->generateRandomString(12);

        $this->View()->apikey = $apikey;
        $this->View()->nonce = $nonce;
        $this->View()->refnum = $refnum;
        $this->View()->amount = $this->getAmount();
        $this->View()->currency = $this->getCurrencyShortName();
        $this->View()->signature = sha1($nonce.$apisecret);
        Shopware()->Session()->token_requested = true;
        Shopware()->Session()->refnum = $refnum;
    }

    public function resultAction()
    {
        $refnum = Shopware()->Session()->refnum;
        if (array_values($this->checkRefNum())[0]['reference_number'] == $refnum) {
            $bezahlt = 12;
            $orderNumber = $this->saveOrder($refnum, md5($refnum), $bezahlt);
            $this->View()->refnum = $refnum;
            Shopware()->Session()->token_requested = false;
            Shopware()->Session()->refnum = null;
            return $this->redirect(
                     array(
                        'controller' => 'checkout',
                        'action' => 'finish',
                        'forceSecure' => 1,
                        'sUniqueID' => md5($refnum)
                    )
                );
        }
        else {
            return $this->redirect(array('action' => 'gateway', 'forceSecure' => true));
        }
    }

    public function checkRefNum() {
        $apikey = trim($this->config->get("apikey"));
        $apisecret = trim($this->config->get("apisecret"));
        $nonce = $this->generateRandomString(12,false);
        $params = array(
            "reference_regex" => Shopware()->Session()->refnum,
            "api_key" => $apikey,
            "nonce" => $nonce,
            "signature" => sha1($nonce.$apisecret)
        );
        return $this->post("/api/v1/transactions/filter_by_reference", $params);
    }

    public function post($url, $itemData = array())
    {
        $response = $this->make_request(
            $url,
            $itemData,
            "POST"
        );

        return $response;
    }

    public function make_request($action, $params = array(), $method = 'POST')
    {
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
    protected function _requestApi($action = '', $params = array(), $method = 'POST')
    {
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

}