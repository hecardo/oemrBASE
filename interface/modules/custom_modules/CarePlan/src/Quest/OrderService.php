<?php
/**
 * @package   	WMT
 * @subpackage	Laboratory
 * @author    	Ron Criswell <ron.criswell@medtechsvcs.com>
 * @copyright 	Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   	https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace WMT\Laboratory\Quest;

use \Exception;

require_once 'SoapAuthClient.php';

if (!class_exists("BaseHubServiceResponse")) {
/**
 * BaseHubServiceResponse
 */
class BaseHubServiceResponse {
	/**
	 * @access public
	 * @var string
	 */
	public $responseCode;
	/**
	 * @access public
	 * @var string
	 */
	public $responseMsg;
	/**
	 * @access public
	 * @var ResponseProperty[]
	 */
	public $responseProperties;
	/**
	 * @access public
	 * @var string
	 */
	public $status;
}}

if (!class_exists("ResponseProperty")) {
/**
 * ResponseProperty
 */
class ResponseProperty {
	/**
	 * @access public
	 * @var string
	 */
	public $propertyName;
	/**
	 * @access public
	 * @var string
	 */
	public $propertyValue;
}}

if (!class_exists("OrderSupportDocument")) {
/**
 * OrderSupportDocument
 */
class OrderSupportDocument {
	/**
	 * @access public
	 * @var base64Binary
	 */
	public $documentData;
	/**
	 * @access public
	 * @var string
	 */
	public $documentType;
	/**
	 * @access public
	 * @var string
	 */
	public $requestStatus;
	/**
	 * @access public
	 * @var string
	 */
	public $responseMessage;
	/**
	 * @access public
	 * @var boolean
	 */
	public $success;
}}

if (!class_exists("Order")) {
/**
 * Order
 */
class Order {
	/**
	 * @access public
	 * @var base64Binary
	 */
	public $hl7Order;
}}

if (!class_exists("OrderResponse")) {
/**
 * OrderResponse
 */
class OrderResponse extends BaseHubServiceResponse {
	/**
	 * @access public
	 * @var string
	 */
	public $messageControlId;
	/**
	 * @access public
	 * @var string
	 */
	public $orderTransactionUid;
	/**
	 * @access public
	 * @var string[]
	 */
	public $validationErrors;
}}

if (!class_exists("OrderSupportServiceRequest")) {
/**
 * OrderSupportServiceRequest
 */
class OrderSupportServiceRequest extends Order {
	/**
	 * @access public
	 * @var string[]
	 */
	public $orderSupportRequests;
}}

if (!class_exists("OrderSupportServiceResponse")) {
/**
 * OrderSupportServiceResponse
 */
class OrderSupportServiceResponse extends OrderResponse {
	/**
	 * @access public
	 * @var OrderSupportDocument[]
	 */
	public $orderSupportDocuments;
}}

if (!class_exists("ServiceException")) {
/**
 * ServiceException
 */
class ServiceException {
}}

if (!class_exists("SOAPException")) {
/**
 * SOAPException
 */
class SOAPException {
	/**
	 * @access public
	 * @var string
	 */
	public $message;
}}

if (!class_exists("OrderService")) {
/**
 * OrderService
 * @author WSDLInterpreter
 */
class OrderService extends SoapAuthClient {
	/**
	 * Default class map for wsdl=>php
	 * @access private
	 * @var array
	 */
	private static $classmap = array(
		"BaseHubServiceResponse" => "WMT\Laboratory\Quest\BaseHubServiceResponse",
		"ResponseProperty" => "WMT\Laboratory\Quest\ResponseProperty",
		"OrderSupportDocument" => "WMT\Laboratory\Quest\OrderSupportDocument",
		"Order" => "WMT\Laboratory\Quest\Order",
		"OrderResponse" => "WMT\Laboratory\Quest\OrderResponse",
		"OrderSupportServiceRequest" => "WMT\Laboratory\Quest\OrderSupportServiceRequest",
		"OrderSupportServiceResponse" => "WMT\Laboratory\Quest\OrderSupportServiceResponse",
		"ServiceException" => "WMT\Laboratory\Quest\ServiceException",
		"SOAPException" => "WMT\Laboratory\Quest\SOAPException",
	);

	/**
	 * Constructor using wsdl location and options array
	 * @param string $wsdl WSDL location for this service
	 * @param array $options Options for the SoapClient
	 */
	public function __construct($wsdl, $options=array()) {
		foreach(self::$classmap as $wsdlClassName => $phpClassName) {
		    if(!isset($options['classmap'][$wsdlClassName])) {
		        $options['classmap'][$wsdlClassName] = $phpClassName;
		    }
		}
		parent::__construct($wsdl, $options);
	}

	/**
	 * Checks if an argument list matches against a valid argument type list
	 * @param array $arguments The argument list to check
	 * @param array $validParameters A list of valid argument types
	 * @return boolean true if arguments match against validParameters
	 * @throws Exception invalid function signature message
	 */
	public function _checkArguments($arguments, $validParameters) {
		$variables = "";
		foreach ($arguments as $arg) {
		    $type = gettype($arg);
		    if ($type == "object") {
		        $type = get_class($arg);
		    }
		    $variables .= "(".$type.")";
		}
		if (!in_array($variables, $validParameters)) {
		    throw new Exception("Invalid parameter types: ".str_replace(")(", ", ", $variables));
		}
		return true;
	}

	/**
	 * Service Call: submitOrder
	 * Parameter options:
	 * (Order) order
	 * @param mixed,... See function description for parameter options
	 * @return OrderResponse
	 * @throws Exception invalid function signature message
	 */
	public function submitOrder($mixed = null) {
		$validParameters = array(
			"(WMT\Laboratory\Quest\OrderSupportServiceRequest)",
		);
		$args = func_get_args();
		$this->_checkArguments($args, $validParameters);
		return $this->__soapCall("submitOrder", $args);
	}


	/**
	 * Service Call: validateOrder
	 * Parameter options:
	 * (Order) order
	 * @param mixed,... See function description for parameter options
	 * @return OrderResponse
	 * @throws Exception invalid function signature message
	 */
	public function validateOrder($mixed = null) {
		$validParameters = array(
			"(WMT\Laboratory\Quest\OrderSupportServiceRequest)",
		);
		$args = func_get_args();
		$this->_checkArguments($args, $validParameters);
		return $this->__soapCall("validateOrder", $args);
	}


	/**
	 * Service Call: getOrderDocuments
	 * Parameter options:
	 * (OrderSupportServiceRequest) request
	 * @param mixed,... See function description for parameter options
	 * @return OrderSupportServiceResponse
	 * @throws Exception invalid function signature message
	 */
	public function getOrderDocuments($mixed = null) {
		$validParameters = array(
			"(WMT\Laboratory\Quest\OrderSupportServiceRequest)",
		);
		$args = func_get_args();
		$this->_checkArguments($args, $validParameters);
		return $this->__soapCall("getOrderDocuments", $args);
	}


}}

?>