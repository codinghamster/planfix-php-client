<?php
/**
 * A simple library made for easy access to account data of Planfix users.
 * Best suites for intergrating in CRM or administrator software for the sake of automation.
 *
 * You can use it absolutely free in commercial or non-commercial applications.
 *
 * Software provided AS IS without any warranty.
 *
 * @author Coding Hamster <admin@codinghamster.info>
 * @version 1.0.1
 */

/**
 * Exception overriding for better catchability.
 */
class Planfix_API_Exception extends Exception {}

/**
 * Main class with all the magic.
 */
class Planfix_API {

    /**
     * Url that handles API requests
     */
    const API_URL = 'https://api.planfix.ru/xml/';

    /**
     * Version of the library
     */
    const VERSION = '1.0.1';

    /**
     * Maximum size of a page for *.getList requests
     */
    const MAX_PAGE_SIZE = 100;

    /**
     * Default Curl options
     */
    public static $CURL_OPTS = array(
        CURLOPT_CONNECTTIMEOUT    => 10,
        CURLOPT_RETURNTRANSFER    => 1,
        CURLOPT_TIMEOUT           => 60,
        CURLOPT_SSL_VERIFYPEER    => 0,
        CURLOPT_SSL_VERIFYHOST    => 0
    );

    /**
     * Maximum simultaneous Curl handles in a Multi Curl session
     */
    public static $MAX_BATCH_SIZE = 10;

    /**
     * Api key
     *
     * @var string
     */
    protected $apiKey;

    /**
     * Api secret
     *
     * @var string
     */
    protected $apiSecret;

    /**
     * Account name (*.planfix.ru)
     *
     * @var string
     */
    protected $account;

    /**
     * User login
     *
     * @var string
     */
    protected $userLogin;

    /**
     * User password
     *
     * @var string
     */
    protected $userPassword;

    /**
     * Session identifier
     *
     * @var string
     */
    protected $sid;

    /**
     * Initializes a Planfix Client
     *
     * Required parameters:
     *    - apiKey - Application Key
     *    - apiSecret - Application Secret
     *
     * @param array $config The array containing required parameters
     */
    public function __construct($config) {
        $this->setApiKey($config['apiKey']);
        $this->setApiSecret($config['apiSecret']);
    }

    /**
     * Set the Api key
     *
     * @param string $apiKey Api key
     * @return Planfix_API
     */
    public function setApiKey($apiKey) {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * Get the Api key
     *
     * @return string the Api key
     */
    public function getApiKey() {
        return $this->apiKey;
    }

    /**
     * Set the Api secret
     *
     * @param string $apiKey Api secret
     * @return Planfix_API
     */
    public function setApiSecret($apiSecret) {
        $this->apiSecret = $apiSecret;
        return $this;
    }

    /**
     * Get the Api secret
     *
     * @return string the Api secret
     */
    public function getApiSecret() {
        return $this->apiSecret;
    }

    /**
     * Set the Account
     *
     * @param string $account Account
     * @return Planfix_API
     */
    public function setAccount($account) {
        $this->account = $account;
        return $this;
    }

    /**
     * Get the Account
     *
     * @return string the Account
     */
    public function getAccount() {
        return $this->account;
    }

    /**
     * Set User Credentials
     *
     * Required parameters:
     *    - login - User login
     *    - password - User password
     *
     * @param array $user The array containing required parameters
     */
    public function setUser($user) {
        $this->setUserLogin($user['login']);
        $this->setUserPassword($user['password']);
    }

    /**
     * Set the User login
     *
     * @param string $userLogin User login
     * @return Planfix_API
     */
    public function setUserLogin($userLogin) {
        $this->userLogin = $userLogin;
        return $this;
    }

    /**
     * Get the User login
     *
     * @return string the User login
     */
    public function getUserLogin() {
        return $this->userLogin;
    }

    /**
     * Set the User password
     *
     * @param string $userPassword User password
     * @return Planfix_API
     */
    public function setUserPassword($userPassword) {
        $this->userPassword = $userPassword;
        return $this;
    }

    /**
     * Get the User password
     * Private for no external use
     *
     * @return string the User password
     */
    private function getUserPassword() {
        return $this->userPassword;
    }

    /**
     * Set the Sid
     *
     * @param string $sid Sid
     * @return Planfix_API
     */
    public function setSid($sid) {
        $this->sid = $sid;
        return $this;
    }

    /**
     * Get the Sid
     *
     * @return string the Sid
     */
    public function getSid() {
        return $this->sid;
    }

    /**
     * Authenticate with previously set credentials
     *
     * @throws Planfix_API_Exception
     * @return Planfix_API
     */
    public function authenticate() {
        $userLogin = $this->getUserLogin();
        $userPassword = $this->getUserPassword();

        if (!($userLogin && $userPassword)) {
            throw new Planfix_API_Exception('User credentials are not set');
        }

        $requestXml = $this->createXml();

        $requestXml['method'] = 'auth.login';

        $requestXml->login = $userLogin;
        $requestXml->password = $userPassword;

        $requestXml->signature = $this->signXml($requestXml);

        $response = $this->makeRequest($requestXml);

        if (!$response['success']) {
            throw new Planfix_API_Exception('Unable to authenticate: '.$response['error_str']);
        }

        $this->setSid($response['data']['sid']);

        return $this;
    }

    /**
     * Perform Api request
     *
     * @param string|array $method Api method to be called or group of methods for batch request
     * @param array $params (optional) Parameters for called Api method
     * @throws Planfix_API_Exception
     * @return array the Api response
     */
    public function api($method, $params = '') {
        if (!$method) {
            throw new Planfix_API_Exception('No method specified');
        } elseif (is_array($method)) {
            if (isset($method['method'])) {
                $params = isset($method['params']) ? $method['params'] : '';
                $method = $method['method'];
            } else {
                foreach($method as $request) {
                    if (!isset($request['method'])) {
                        throw new Planfix_API_Exception('No method specified');
                    }
                }
            }
        }

        $sid = $this->getSid();

        if (!$sid) {
            $this->authenticate();
            $sid = $this->getSid();
        }

        if (is_array($method)) {
            $batch = array();

            foreach($method as $request) {
                $requestXml = $this->createXml();

                $requestXml['method'] = $request['method'];
                $requestXml->sid = $sid;

                $params = isset($request['params']) ? $request['params'] : '';

                if (is_array($params) && $params) {
                    $this->importParams($requestXml, $params);
                }

                if (!isset($requestXml->pageSize)) {
                    $requestXml->pageSize = self::MAX_PAGE_SIZE;
                }

                $requestXml->signature = $this->signXml($requestXml);

                $batch[] = $requestXml;
            }

            return $this->makeBatchRequest($batch);
        } else {
            $requestXml = $this->createXml();

            $requestXml['method'] = $method;
            $requestXml->sid = $sid;

            if (is_array($params) && $params) {
                $this->importParams($requestXml, $params);
            }

            if (!isset($requestXml->pageSize)) {
                $requestXml->pageSize = self::MAX_PAGE_SIZE;
            }

            $requestXml->signature = $this->signXml($requestXml);

            return $this->makeRequest($requestXml);
        }
    }

    /**
     * Create XML request
     *
     * @throws Planfix_API_Exception
     * @return SimpleXMLElement the XML request
     */
    protected function createXml() {
        $requestXml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><request></request>');

        $account = $this->getAccount();

        if (!$account) {
            throw new Planfix_API_Exception('Account is not set');
        }

        $requestXml->account = $account;

        return $requestXml;
    }

    /**
     * Import parameters to XML request
     *
     * @param SimpleXMLElement The XML request
     * @param array Parameters
     * @return SimpleXMLElement the XML request
     */
    protected function importParams($requestXml, $params) {
        foreach($params as $key => $val) {
            if (is_array($val)) {
                $requestXml->$key = new SimpleXMLElement("<$key/>");
                foreach($val as $key2 => $val2) {
                    if (is_array($val2)) {
                        $this->importParams($requestXml->$key, $val2);
                    } else {
                        $requestXml->$key->addChild($key2, $val2);
                    }
                }
            } else {
                $requestXml->addChild($key, $val);
            }
        }
        return $requestXml;
    }

    /**
     * Sign XML request
     *
     * @param SimpleXMLElement The XML request
     * @throws Planfix_API_Exception
     * @return string the Signature
     */
    protected function signXml($requestXml) {
        return md5($this->normalizeXml($requestXml).$this->getApiSecret());
    }

    /**
     * Normalize the XML request
     *
     * @param SimpleXMLElement $node The XML request
     * @return string the Normalized string
     */
    protected function normalizeXml($node) {
        $node = (array) $node;
        ksort($node);

        $normStr = '';

        foreach ($node as $child) {
            if (is_array($child)) {
                $normStr .= implode('', array_map(array($this,'normalizeXml'), $child));
            } elseif (is_object($child)) {
                $normStr .= $this->normalizeXml($child);
            } else {
                $normStr .= (string) $child;
            }
        }

        return $normStr;
    }

    /**
     * Make the batch request to Api
     *
     * @param array $batch The array of XML requests
     * @return array the array of Api responses
     */
    protected function makeBatchRequest($batch) {
        $mh = curl_multi_init();

        $batchCnt = count($batch);
        $max_size = $batchCnt < self::$MAX_BATCH_SIZE ? $batchCnt : self::$MAX_BATCH_SIZE;

        $batchResult = array();

        for ($i = 0; $i < $max_size; $i++) {
            $requestXml = array_shift($batch);
            $ch = $this->prepareCurlHandle($requestXml);
            $chKey = (string) $ch;
            $batchResult[$chKey] = array();
            curl_multi_add_handle($mh, $ch);
        }

        do {
            do {
                $mrc = curl_multi_exec($mh, $running);
            } while($mrc == CURLM_CALL_MULTI_PERFORM);

            while ($request = curl_multi_info_read($mh)) {
                $ch = $request['handle'];
                $chKey = (string) $ch;
                $batchResult[$chKey] = $this->parseApiResponse(curl_multi_getcontent($ch), curl_error($ch));

                if (count($batch)) {
                    $requestXml = array_shift($batch);
                    $ch = $this->prepareCurlHandle($requestXml);
                    $chKey = (string) $ch;
                    $batchResult[$chKey] = array();
                    curl_multi_add_handle($mh, $ch);
                }

                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }

            if ($running) {
                curl_multi_select($mh);
            }

        } while($running && $mrc == CURLM_OK);

        return array_values($batchResult);
    }

    /**
     * Make the request to Api
     *
     * @param SimpleXMLElement $requestXml The XML request
     * @return array the Api response
     */
    protected function makeRequest($requestXml) {
        $ch = $this->prepareCurlHandle($requestXml);

        $response = curl_exec($ch);
        $error = curl_error($ch);

        return $this->parseApiResponse($response, $error);
    }

    /**
     * Prepare the Curl handle
     *
     * @param SimpleXMLElement $requestXml The XML request
     * @return resource the Curl handle
     */
    protected function prepareCurlHandle($requestXml) {
        $ch = curl_init(self::API_URL);

        curl_setopt_array($ch, self::$CURL_OPTS);

        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->getApiKey().':X');

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestXml->asXML());

        return $ch;
    }

    /**
     * Parse the Api response
     *
     * @link http://goo.gl/GWa1c List of Api error codes
     *
     * @param string $response The Api response
     * @param string $error The Curl error if any
     * @return array the Curl handle
     */
    protected function parseApiResponse($response, $error) {
        $result = array(
            'success'    => 1,
            'error_str'  => '',
            'meta'       => null,
            'data'       => null
        );

        if ($error) {
            $result['success'] = 0;
            $result['error_str'] = $error;
            return $result;
        }

        try {
            $responseXml = new SimpleXMLElement($response);
        } catch (Exception $e) {
            $result['success'] = 0;
            $result['error_str'] = $e->getMessage();
            return $result;
        }

        if ($responseXml['status'] == 'error') {
            $result['success'] = 0;
            $result['error_str'] = 'Code: '.$responseXml->code.' / Message: '.$responseXml->message;
            return $result;
        }

        if (isset($responseXml->sid)) {
            $result['data']['sid'] = (string) $responseXml->sid;
        } else {
            $responseXml = $responseXml->children();

            foreach($responseXml->attributes() as $key => $val) {
                $result['meta'][$key] = (int) $val;
            }

            if ($result['meta'] == null || $result['meta']['totalCount'] || $result['meta']['count']) {
                $result['data'] = $this->exportData($responseXml);
            }
        }

        return $result;
    }

    /**
     * Exports the Xml response to array
     *
     * @param SimpleXMLElement $responseXml The Api response
     * @return array the Exported data
     */
    protected function exportData($responseXml) {
        $root = $responseXml->getName();
        $data[$root] = array();

        $rootChildren = $responseXml->children();

        $names = array();
        foreach($rootChildren as $child) {
            $names[] = $child->getName();
        }

        $is_duplicate = count(array_unique($names)) != count($names) ? true : false;

        foreach($rootChildren as $child) {
            if (count($child->children()) > 1) {
                $data[$root] = array_merge($data[$root], $is_duplicate ? array($this->exportData($child)) : $this->exportData($child));
            } else {
                $data[$root][$child->getName()] = (string) $child;
            }
        }

        return $data;
    }

}

/* EOF */