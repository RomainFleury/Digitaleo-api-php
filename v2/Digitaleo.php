<?php

/**
 * Wrapper pour les APIs REST de Digitaleo
 *
 * @author   Digitaleo 2015
 * @license  http://www.digitaleo.net/licence.txt Digitaleo Licence
 * @link     http://www.digitaleo.net
 */
class Digitaleo

{
    protected static $_outputFormatAllowed = array('application/json', 'application/xml', 'text/csv', 'application/js', 'application/bin');

    CONST INPUT_JSON = 'application/json';
    CONST INPUT_FORM_DATA = 'multipart/form-data';
    CONST INPUT_URLENCODED = 'application/x-www-form-urlencoded';

    CONST GRANT_REFRESH = 'refresh_token';
    CONST GRANT_PASSWORD = 'password';
    CONST GRANT_CLIENT = 'client_credentials';
    CONST GRANT_FINAL_USER_DIGITALEO = 'digitaleo_finaluser';
    CONST GRANT_FINAL_USER_FACEBOOK = 'facebook_finaluser';

    CONST VERB_GET = 'GET';
    CONST VERB_POST = 'POST';
    CONST VERB_PUT = 'PUT';
    CONST VERB_DELETE = 'DELETE';

    /**
     * Informations de connexion de cURL
     *
     * @var array
     */
    public $curlInfos;

    /**
     * Format of the response
     *
     * @var string
     */
    protected $_format;

    /**
     * Sortie directe dans l'outputStream de php
     *
     * @var boolean
     */
    protected $_immediateOutput;

    /**
     * Base URL to access the API
     *
     * @var string
     */
    private $_baseUrl;

    /**
     * Current Credential
     *
     * @var Credential
     */
    private $_credential = null;

    /**
     * Timeout culr option
     *
     * @var string
     */
    private $_timeout = null;

    /**
     * Content Type
     *
     * @var string
     */
    private $_contentType;

    /**
     * Code HTTP
     *
     * @var integer
     */
    private $_responseCode;

    /**
     * Response API
     *
     * @var string
     */
    private $_response = '';

    /**
     * REQUEST TYPE (GET, POST, PUT, DELETE)
     *
     * @var string
     */
    private $_verb;

    /**
     * URI called
     *
     * @var string
     */
    private $_callUri;

    /**
     * Version wrapper
     *
     * @var string
     */
    private $_version = '2.0';

    /**
     * Headers HTTP utilisé pour la requete
     *
     * @var array
     */
    private $_additionnalHeaders;

    /**
     * Constructor
     *
     * @param string $baseUrl           Base URL to access the API
     * @param string $outputFormat      [Optional] Format of the response
     * @param string $immediateOutput   [Optional] Output should be direct or not
     * @param string $additionalHeaders [Optional] Headers to add by default to requests
     *                                  key / value array
     *                                  ex : ['Accept'=> 'application/json']
     *
     * @throws \Exception
     */
    public function __construct($baseUrl = null, $outputFormat = 'application/json', $immediateOutput = false, $additionalHeaders = array())
    {
        // Check extension cURL
        if (!extension_loaded('curl')) {
            throw new \Exception('Extension "curl" is not loaded.');
        }

        if (!empty($baseUrl)) {
            $this->setBaseUrl($baseUrl);
        }

        $this->setFormat($outputFormat);
        $this->setImmediateOutput($immediateOutput);
        $this->setAdditionnalHeaders($additionalHeaders);
        $this->_contentType = static::INPUT_URLENCODED;
    }

    /**
     * Define base URL to access the API
     *
     * @param string $baseUrl Base URL
     *
     * @return void
     */
    public function setBaseUrl($baseUrl)
    {
        if (substr($baseUrl, -1) !== '/') {
            $baseUrl .= '/';
        }
        $this->_baseUrl = $baseUrl;
    }

    /**
     * Define the curl timeout
     *
     * @param string $timeout Curl Timeout
     *
     * @return Eo_Rest_WrapperOauth
     */
    public function setTimeout($timeout)
    {
        $this->_timeout = $timeout;
        return $this;
    }

    /**
     * Define format of the response
     *
     * @param string $format Format response
     *
     * @return void
     *
     * @throws \Exception
     */
    public function setFormat($format)
    {
        $format = strtolower($format);
        if (!in_array($format, self::$_outputFormatAllowed)) {
            $formats = implode(', ', self::$_outputFormatAllowed);
            throw new \Exception('Only ' . $formats . ' are supported.');
        }
        $this->_format = $format;
    }

    /**
     * @param $contentType
     *
     * @throws Exception
     */
    public function setContentType($contentType)
    {
        if (!in_array($contentType, [static::INPUT_URLENCODED, static::INPUT_JSON, static::INPUT_FORM_DATA])) {
            throw new \Exception('content type not supported');
        }
        $this->_contentType = $contentType;
    }

    /**
     * Define immediate ouput status
     *
     * @param boolean $active Active direct ouput or not
     */
    public function setImmediateOutput($active)
    {
        $this->_immediateOutput = $active;
    }

    /**
     * Set Additionnal Headers
     *
     * @param array $additionnalHeaders Headers to add by default to requests
     *                                  key / value array
     *                                  ex : ['Accept'=> 'application/json']
     */
    public function setAdditionnalHeaders($additionnalHeaders)
    {
        $this->_additionnalHeaders = $additionnalHeaders;
    }

    // <editor-fold desc="credentials" defaultstate="collapsed">

    /**
     * Récupération d'un token pour le grant type "client_credentials"
     *
     * @param string $url          URL du serveur d'autorisation
     * @param string $clientId     Client ID
     * @param string $clientSecret Client Secret
     *
     * @return Credential credential
     */
    public function setOauthClientCredentials($url, $clientId, $clientSecret)
    {
        $credential = $this->getCredential();

        if (($credential instanceof Credential)
            && isset($credential->token)
            && ($credential->clientId == $clientId)
            && ($credential->clientSecret == $clientSecret)
            && $credential->grantType == static::GRANT_CLIENT
        ) {
            return $credential;
        }

        $credential = new Credential();
        $credential->grantType = static::GRANT_CLIENT;
        $credential->clientId = $clientId;
        $credential->clientSecret = $clientSecret;
        $credential->url = $url;
        $this->setCredential($credential);

        return $this->callGetToken(true);
    }

    /**
     * Récupération d'un token pour le grant type "password"
     *
     * @param string $url          URL du serveur d'autorisation
     * @param string $clientId     Client ID
     * @param string $clientSecret Client Secret
     * @param string $username     user name
     * @param string $password     user password
     *
     * @return Credential credential
     */
    public function setOauthPasswordCredentials($url, $clientId, $clientSecret, $username, $password)
    {
        $credential = $this->getCredential();

        if (($credential instanceof Credential)
            && isset($credential->token)
            && $credential->grantType == static::GRANT_PASSWORD
            && ($credential->clientId == $clientId)
            && ($credential->clientSecret == $clientSecret)
            && ($credential->username == $username)
            && ($credential->password == $password)
        ) {
            return $credential;
        }

        $credential = new Credential();
        $credential->grantType = static::GRANT_PASSWORD;
        $credential->clientId = $clientId;
        $credential->clientSecret = $clientSecret;
        $credential->username = $username;
        $credential->password = $password;
        $credential->url = $url;
        $this->setCredential($credential);

        return $this->callGetToken(true);
    }

    /**
     * @param string $url          URL du serveur d'autorisation
     * @param string $clientId     Client ID
     * @param string $clientSecret Client Secret
     * @param string $username     user name
     * @param string $password     user password
     *
     * @return Credential
     * @throws Exception
     */
    public function setOauthFinalUserDigitaleoCredential($url, $clientId, $clientSecret, $username, $password)
    {
        $credential = $this->getCredential();

        if (($credential instanceof Credential)
            && isset($credential->token)
            && $credential->grantType == static::GRANT_FINAL_USER_DIGITALEO
            && ($credential->clientId == $clientId)
            && ($credential->clientSecret == $clientSecret)
            && ($credential->username == $username)
            && ($credential->password == $password)
        ) {
            return $credential;
        }

        $credential = new Credential();
        $credential->grantType = static::GRANT_FINAL_USER_DIGITALEO;
        $credential->clientId = $clientId;
        $credential->clientSecret = $clientSecret;
        $credential->username = $username;
        $credential->password = $password;
        $credential->url = $url;
        $this->setCredential($credential);

        return $this->callGetToken(true);
    }

    /**
     * @param string $url           URL du serveur d'autorisation
     * @param string $clientId      Client ID
     * @param string $clientSecret  Client Secret
     * @param string $facebookToken facebook token
     *
     * @return Credential
     * @throws Exception
     */
    public function setOauthFinalUserFacebookCredential($url, $clientId, $clientSecret, $facebookToken)
    {
        $credential = $this->getCredential();

        if (($credential instanceof Credential)
            && isset($credential->token)
            && $credential->grantType == static::GRANT_FINAL_USER_FACEBOOK
            && ($credential->clientId == $clientId)
            && ($credential->clientSecret == $clientSecret)
            && ($credential->facebookToken == $facebookToken)
        ) {
            return $credential;
        }

        $credential = new Credential();
        $credential->grantType = static::GRANT_FINAL_USER_FACEBOOK;
        $credential->clientId = $clientId;
        $credential->clientSecret = $clientSecret;
        $credential->facebookToken = $facebookToken;
        $credential->url = $url;
        $this->setCredential($credential);

        return $this->callGetToken(true);
    }

    /**
     * Récupération d'un token pour le grant type "refresh_token"
     *
     * @param string $url          URL du serveur d'autorisation
     * @param string $clientId     Client ID
     * @param string $clientSecret Client Secret
     * @param string $refreshToken refresh token
     *
     * @return Credential credential
     */
    public function setRefreshToken($url, $clientId, $clientSecret, $refreshToken)
    {
        $credential = $this->getCredential();

        if (($credential instanceof Credential)
            && isset($credential->token)
            && $credential->grantType == static::GRANT_REFRESH
            && ($credential->clientId == $clientId)
            && ($credential->clientSecret == $clientSecret)
            && ($credential->refreshToken == $refreshToken)
        ) {
            return $credential;
        }

        $credential = new Credential();
        $credential->grantType = static::GRANT_REFRESH;
        $credential->clientId = $clientId;
        $credential->clientSecret = $clientSecret;
        $credential->refreshToken = $refreshToken;
        $credential->url = $url;
        $this->setCredential($credential);


        return $this->callGetToken(true);
    }

    /**
     * Ajout du token oauth
     *
     * @param string $token token oauth
     */
    public function setOauthToken($token)
    {
        $credential = new Credential();
        $credential->token = $token;
        $this->setCredential($credential);
    }

    /**
     * Retrieves an authentication token
     *
     * @param bool $force
     *
     * @return Credential|void
     * @throws Exception
     */
    public function callGetToken($force = false)
    {
        $credential = $this->getCredential();

        if (!isset($credential)) {
            throw new \Exception('Credential is required');
        }

        if (!isset($credential->grantType)) {
            return $credential;
        }

        if (isset($credential->token) && !$force) {
            return $credential;
        }

        $postFields = [];
        $postFields['client_id'] = $credential->clientId;
        $postFields['client_secret'] = $credential->clientSecret;
        $postFields['grant_type'] = $credential->grantType;
        if ($credential->grantType == static::GRANT_REFRESH) {
            $postFields['refresh_token'] = $credential->refreshToken;
        } else if ($credential->grantType == static::GRANT_PASSWORD) {
            $postFields['username'] = $credential->username;
            $postFields['password'] = $credential->password;
        } else if ($credential->grantType == static::GRANT_FINAL_USER_DIGITALEO) {
            $postFields['username'] = $credential->username;
            $postFields['password'] = $credential->password;
        } else if ($credential->grantType == static::GRANT_FINAL_USER_FACEBOOK) {
            $postFields['token'] = $credential->facebookToken;
        }

        // Création d'une nouvelle ressource cURL
        $chOauth = curl_init();
        // Configuration de l'URL et d'autres options
        curl_setopt($chOauth, CURLOPT_URL, $credential->url);
        curl_setopt($chOauth, CURLOPT_HEADER, 0);
        curl_setopt($chOauth, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chOauth, CURLOPT_POSTFIELDS, $postFields);

        // Requête HTTP
        $oAuthResult = json_decode(curl_exec($chOauth));

        // Fermeture de la session cURL
        curl_close($chOauth);

        if (isset($oAuthResult)) {
            // Récupération de l'access_token
            if (isset($oAuthResult->access_token)) {
                $credential->token = $oAuthResult->access_token;
            }
            if (isset($oAuthResult->refresh_token)) {
                $credential->refreshToken = $oAuthResult->refresh_token;
            }
        }

        return $credential;
    }

    /**
     * @param string $resource            API resource to call
     * @param array  $params              parameters to add to request
     * @param array  $additionnalsHeaders headers to add to request
     *                                    key / value array
     *                                    ex : ['Accept'=> 'application/json']
     *
     * @return mixed formatted http response
     * @throws Exception
     */
    public function callGet($resource, $params = array(), $additionnalsHeaders = array())
    {
        return $this->_call($resource, static::VERB_GET, $params, null, $additionnalsHeaders);
    }

    /**
     * @param string $resource            API resource to call
     * @param array  $body                data to post
     * @param array  $params              parameters to add to request
     * @param array  $additionnalsHeaders headers to add to request
     *                                    key / value array
     *                                    ex : ['Accept'=> 'application/json']
     *
     * @return mixed formatted http response
     * @throws Exception
     */
    public function callPost($resource, $body, $params = array(), $additionnalsHeaders = array())
    {
        return $this->_call($resource, static::VERB_POST, $params, $body, $additionnalsHeaders);
    }

    /**
     * @param string $resource            API resource to call
     * @param array  $files               files to post
     * @param array  $body                data to post
     * @param array  $params              parameters to add to request
     * @param array  $additionnalsHeaders headers to add to request
     *                                    key / value array
     *                                    ex : ['Accept'=> 'application/json']
     *
     * @return mixed formatted http response
     * @throws Exception
     */
    public function callPostFile($resource, $files, $body = array(), $params = array(), $additionnalsHeaders = array())
    {
        $body = $this->_formatRequestForFiles($files, $body, $additionnalsHeaders);
        $result = $this->_call($resource, static::VERB_POST, $params, $body, $additionnalsHeaders);
        return $result;
    }

    /**
     * @param string $resource            API resource to call
     * @param array  $body                data to put
     * @param array  $params              parameters to add to request
     * @param array  $additionnalsHeaders headers to add to request
     *                                    key / value array
     *                                    ex : ['Accept'=> 'application/json']
     *
     * @return mixed formatted http response
     * @throws Exception
     */
    public function callPut($resource, $body, $params = array(), $additionnalsHeaders = array())
    {
        return $this->_call($resource, static::VERB_PUT, $params, $body, $additionnalsHeaders);
    }

    /**
     * @param string $resource            API resource to call
     * @param array  $files               files to post
     * @param array  $body                data to post
     * @param array  $params              parameters to add to request
     * @param array  $additionnalsHeaders headers to add to request
     *                                    key / value array
     *                                    ex : ['Accept'=> 'application/json']
     *
     * @return mixed formatted http response
     * @throws Exception
     */
    public function callPutFile($resource, $files, $body = array(), $params = array(), $additionnalsHeaders = array())
    {
        $body = $this->_formatRequestForFiles($files, $body, $additionnalsHeaders);
        $result = $this->_call($resource, static::VERB_PUT, $params, $body, $additionnalsHeaders);
        return $result;
    }

    /**
     * @param string $resource            API resource to call
     * @param array  $params              parameters to add to request
     * @param array  $additionnalsHeaders headers to add to request
     *                                    key / value array
     *                                    ex : ['Accept'=> 'application/json']
     *
     * @return mixed formatted http response
     * @throws Exception
     */
    public function callDelete($resource, $params = array(), $additionnalsHeaders = array())
    {
        return $this->_call($resource, static::VERB_DELETE, $params, null, $additionnalsHeaders);
    }


    /**
     * Retourne la dernière requête effectuée
     *
     * @return string
     */
    public function getLastRequest()
    {
        return $this->_callUri;
    }

    /**
     *  HTTP status code
     *
     * @return int HTTP status code
     */
    public function getResponseCode()
    {
        return $this->_responseCode;
    }

    /**
     * @return string called uri
     */
    public function getUri()
    {
        return $this->_callUri;
    }

    /**
     * @return string HTTP verb used
     */
    public function getVerb()
    {
        return $this->_verb;
    }

    /**
     * Method of debug
     *
     * @return array
     */
    public function getDetails()
    {
        $datas = array();
        $datas['response_code'] = $this->_responseCode;
        $datas['url'] = $this->_callUri;
        $callUri = parse_url($this->_callUri);
        $datas['scheme'] = $callUri['scheme'];
        $datas['host'] = $callUri['host'];
        $datas['verb'] = $this->_verb;
        $args = explode("&", $callUri['query']);
        foreach ($args as $arg) {
            $arg = explode("=", $arg);
            $datas['params_query'][$arg[0]] = $arg[1];
        }
        return $datas;
    }

    /**
     *
     * @param mixed $handle
     *
     * @return mixed response
     */
    protected function _callExec($handle)
    {
        $buffer = curl_exec($handle);
        $this->curlInfos = curl_getinfo($handle);
        return $this->_createResponse($buffer);
    }

    /**
     * Set Credential
     *
     * @param Credential $credential
     *
     * @return void
     */
    public function setCredential(Credential $credential)
    {
        $this->_credential = $credential;
    }

    /**
     * Get Credential
     *
     * @return Credential
     *
     */
    public function getCredential()
    {
        return $this->_credential;
    }
    // </editor-fold>

    /**
     * Call the API
     *
     * @param string  $resource Resource REST
     * @param string  $httpVerb HTTP Verb
     * @param array   $params   Params
     * @param boolean $force    Force get token
     *
     * @return boolean
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    private function _call($resource, $httpVerb, $params = array(), $body = null, $additionnalsHeaders = array(), $force = false)
    {
        // Check base URL to access the API is set
        if (empty($this->_baseUrl)) {
            throw new \InvalidArgumentException('Please set the base url to access the API.');
        }

        $this->callGetToken($force);

        try {

            $handle = $this->_initCurl();
            $this->_setCurlOptions($handle, $httpVerb, $body, $additionnalsHeaders);

            $uri = $this->_createUri($resource, $params);
            curl_setopt($handle, CURLOPT_URL, $uri);

            $response = $this->_callExec($handle);

            # Close curl process
            curl_close($handle);

            return $response;

        } catch (\Exception $ex) {

            if ($ex->getCode() == 401 && $force == false) {
                $this->_call($resource, $httpVerb, $params, $body, $additionnalsHeaders, true);
            }

            # Close curl process
            curl_close($handle);

            throw $ex;
        }
    }

    private function _createResponse($buffer)
    {
        # Response code
        $this->_responseCode = $this->curlInfos['http_code'];

        # RESPONSE
        $this->_response = $buffer;

        return $this->_response;
    }

    /**
     * Init curl
     *
     * @return resource
     */
    private function _initCurl()
    {
        $handle = curl_init();
        $configCurl = array(
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => !$this->_immediateOutput,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        );
        $componentsUrl = parse_url($this->_baseUrl);
        // Check secure URL
        if ($componentsUrl['scheme'] == 'https' || (array_key_exists('port', $componentsUrl) && $componentsUrl['port'] != 80)) {
            $configCurl[CURLOPT_SSL_VERIFYPEER] = false;
            $configCurl[CURLOPT_SSL_VERIFYHOST] = 2;
            $configCurl[CURLOPT_SSLVERSION] = 1;
        }
        curl_setopt_array($handle, $configCurl);

        return $handle;
    }

    private function _setCurlOptions($handle, $httpVerb, $body, $additionnalsHeaders = array())
    {
        /**
         * HTTP Header management
         */
        $headers = ['Authorization' => 'Bearer ' . $this->getCredential()->token];
        if (isset($this->_contentType)) {
            $headers['Content-Type'] = $this->_contentType;
        }
        if (isset($this->_format)) {
            $headers['Accept'] = $this->_format;
        }

        $headers = array_merge($headers, (array)$this->_additionnalHeaders, $additionnalsHeaders);
        $contentType = $headers['Content-Type'];

        $headers = array_map(function ($key, $value) {
            return "$key: $value";
        }, array_keys($headers), array_values($headers));

        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);

        if ($httpVerb == static::VERB_POST) {
            curl_setopt($handle, CURLOPT_POST, true);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $this->_formatInputData($contentType, $body));
        } elseif ($httpVerb == static::VERB_GET) {
            curl_setopt($handle, CURLOPT_POST, false);
        } elseif ($httpVerb == static::VERB_PUT) {
            curl_setopt($handle, CURLOPT_POST, false);
            curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $httpVerb);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $this->_formatInputData($contentType, $body));
        } elseif ($httpVerb == static::VERB_DELETE) {
            curl_setopt($handle, CURLOPT_POST, false);
            curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $httpVerb);
        } else {
            throw new \Exception('invalid verb');
        }

        $this->_verb = $httpVerb;

        /**
         * Http request timeout
         */
        if (!empty($this->_timeout)) {
            curl_setopt($handle, CURLOPT_TIMEOUT, $this->_timeout);
        }

        if ($this->_immediateOutput) {
            // this will handle very large files too, whereas echo'ing one big string will not
            curl_setopt($handle, CURLOPT_BUFFERSIZE, 8192); // 8192 8k
            curl_setopt($handle, CURLOPT_WRITEFUNCTION, function ($handle, $str) {
                // called every CURLOPT_BUFFERSIZE
                echo $str;
                return strlen($str);
            });
            curl_setopt($handle, CURLOPT_HEADERFUNCTION, function ($handle, $str) {
                if (preg_match('/^(?:HTTP\/1.+|(?:Content-Encoding|Content-Language|Content-Length|Content-Disposition|Content-Type):)/i', $str)) {
                    header($str);
                }
                return strlen($str);
            });
        }
    }

    /**
     * Build the URI
     *
     * @param string $resource resource to append to URI
     * @param array  $params   paramters to append to URI
     *
     * @return string
     */
    private function _createUri($resource, $params = array())
    {
        $uri = $this->_baseUrl . $resource;
        if (!empty($params) && is_array($params)) {
            $uri .= '?' . http_build_query($params);
        }
        $this->_callUri = $uri;
        return $uri;
    }

    /**
     * Formats input data
     *
     * @param string $contentType content type for the request
     * @param array  $params      input data
     *
     * @return mixed formated input data according to content type
     */
    private function _formatInputData($contentType, $params)
    {
        $inputData = $params;
        if ($contentType == static::INPUT_JSON) {
            if (!is_string($inputData)) {
                $inputData = json_encode($params);
            }
        }
        if ($contentType == static::INPUT_URLENCODED) {
            if (!is_string($inputData)) {
                $inputData = http_build_query($params);
            }
        }
        return $inputData;
    }

    /**
     * Formats request for sending files
     * <ul>
     *      <li>forces content-type to multipart/form-data</li>
     *      <li>adds @ in front of each file path (needed by cUrl)</li>
     * </ul>
     *
     * @param array $files              list of file paths
     * @param array $body               list of request body parameters
     * @param array $additionnalHeaders list of headers for request
     *
     * @return array list of request body parameters completed by formated files paths
     */
    private function _formatRequestForFiles($files, $body, &$additionnalHeaders)
    {
        $additionnalHeaders = array_merge($additionnalHeaders, ['Content-Type' => static::INPUT_FORM_DATA]);
        foreach ($files as $key => $value) {
            $body[$key] = '@' . $value;
        }
        return $body;
    }
}

class Credential
{
    /**
     * URL du serveur d'autorisation
     *
     * @var string
     */
    public $url;

    /**
     * Grant Type
     *
     * @var string
     */
    public $grantType;

    /**
     * Client Id
     *
     * @var string
     */
    public $clientId;

    /**
     * Client Secret
     *
     * @var string
     */
    public $clientSecret;

    /**
     * Login
     *
     * @var string
     */
    public $username;

    /**
     * Password
     *
     * @var string
     */
    public $password;

    /**
     * Token
     *
     * @var string
     */
    public $token;

    /**
     * Refresh token
     *
     * @var string
     */
    public $refreshToken;

    /**
     * Facebook token
     *
     * @var string
     */
    public $facebookToken;

}