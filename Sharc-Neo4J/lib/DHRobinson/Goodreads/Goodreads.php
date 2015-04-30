<?php

namespace DHRobinson\Goodreads;

/**
 * Goodreads API class
 *
 * API Documentation: http://https://www.goodreads.com/api/
 * Class Documentation: 
 *
 * @author David Robinson
 * @since 03.02.2015
 * @copyright David Robinson - The Swarm 2015
 * @version 1.0
 * @license BSD http://www.opensource.org/licenses/bsd-license.php
 */
class Goodreads	 {

  /**
   * The API base URL
   */
  const API_URL = 'https://www.goodreads.com/';

  /**
   * The Goodreads API Key
   *
   * @var string
   */
  private $_apikey;

  /**
   * The Goodreads API secret
   *
   * @var string
   */
  private $_apisecret;


  /**
   * Available endpoints
   *
   * @var array
   */
  private $_actions = array(
	'book.show_by_isbn'=>'book/isbn',
  );

  /**
   * Default constructor
   *
   * @param array|string $config          Goodreads configuration data
   * @return void
   */
  public function __construct($config) {
    if (true === is_array($config)) {
      // if you want to access user data
      $this->setApiKey($config['apiKey']);
      $this->setApiSecret($config['apiSecret']);
    } else {
      throw new \Exception("Error: __construct() - Configuration data is missing.");
    }
  }

  /**
   * Wrapper for book.show_by_isbn
   *
   * @param string $isbn				Book ISBN
   * @return void
   */
  public function bookShowByISBN($isbn){
	  $book=$this->_makeCall('book.show_by_isbn',array('isbn'=>$isbn));
	  return ($book);
  }
  /**
   * The call operator
   *
   * @param string $function			API resource path
   * @param array [optional] $params	Additional request parameters
   * @param boolean [optional] $auth	Whether the function requires an access token
   * @param string [optional] $method	Request type GET|POST
   * @return mixed
   */
  protected function _makeCall($function,$params = null, $method = 'GET') {

	// Organise variables & add key
    if (isset($params) && is_array($params)) {
		$params['key'] = $this->getApiKey();
		$params['format'] = 'xml';
		$paramString = '?' . http_build_query($params);
    } else {
		$paramString = null;
    }	

    $apiCall = self::API_URL . $this->_actions[$function] . $paramString;
	#echo self::API_URL . $this->_actions[$function] . $paramString;

    // signed header of POST/DELETE requests
    $headerData = array('Accept: application/json');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiCall);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headerData);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ('POST' === $method) {
      curl_setopt($ch, CURLOPT_POST, count($params));
      curl_setopt($ch, CURLOPT_POSTFIELDS, ltrim($paramString, '&'));
    } else if ('DELETE' === $method) {
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    $xmlData = curl_exec($ch);
    if (false === $xmlData) {
      throw new \Exception("Error: _makeCall() - cURL error: " . curl_error($ch));
    }
    curl_close($ch);


	$xml = simplexml_load_string($xmlData, null, LIBXML_NOCDATA);
	$json = json_encode(($xml));

    return $json;
  }

  /**
   * API-key Setter
   *
   * @param string $apiKey
   * @return void
   */
  public function setApiKey($apiKey) {
    $this->_apikey = $apiKey;
  }

  /**
   * API Key Getter
   *
   * @return string
   */
  public function getApiKey() {
    return $this->_apikey;
  }

  /**
   * API Secret Setter
   *
   * @param string $apiSecret
   * @return void
   */
  public function setApiSecret($apiSecret) {
    $this->_apisecret = $apiSecret;
  }

  /**
   * API Secret Getter
   *
   * @return string
   */
  public function getApiSecret() {
    return $this->_apisecret;
  }
}