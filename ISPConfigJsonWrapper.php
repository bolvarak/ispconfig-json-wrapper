<?php
// Load the configuration library
require_once('../../lib/config.inc.php');
// Load the application library
require_once('../../lib/app.inc.php');
// Load the required classes
$app->load('remoting,getconf');

/**
 * @name ISPConfig JSON Wrapper
 * @description This class provides a JSON/P wrapper for the remote interface
 * @copyright 2014, Travis Brown
 * @license GPLv3 All Rights Reserved
 * @author Travis Brown (bolvarak) <tmbrown6@gmail.com>
 * @version 1.0
 * @uses ISPConfig 3
 * This file is part of ISPConfigJsonWrapper.
 *
 * ISPConfigJsonWrapper is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * ISPConfgJsonWrapper is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ISPConfigJsonWrapper.  If not, see <http://www.gnu.org/licenses/>.
 */
class ISPConfigJsonWrapper {

	//////////////////////////////////////////////////////////////////////////////
	/// Constants ///////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////////
	
	/**
	 * This constant contains the RegEx pattern that matches the method that will return the API Method Map
	 * @var string
	 */
	const MapMethodPattern      = '/^(map|methods|wsdl)$/i';

	/**
	 * This constant contains the definition for a JSON request
	 * @var integer
	 */
	const RequestJSON           = 0x001;

	/**
	 * This constant contains the definition for a JSONP request
	 * @var integer
	 */
	const RequestJSONP          = 0x002;

	//////////////////////////////////////////////////////////////////////////////
	/// Properties //////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////////
	
	/**
	 * This property contains the singleton instance of this class
	 * @access protected
	 * @static
	 * @var ISPConfigJsonWrapper
	 */
	protected static $mInstance = null;

	/**
	 * This property contains the instance of the class that handles the remote interaction
	 * @access protected
	 * @var remoting
	 */
	protected $mHandlerClass    = null;
	
	/**
	 * This property contains the request parameters
	 * @access protected
	 * @var array
	 */
	protected $mRequestParams   = array();

	/**
	 * This property contains the request type
	 * @access protected
	 * @var integer
	 */
	protected $mRequestType     = 0x00;

	/**
	 * This property contains the response to the caller
	 * @access protected
	 * @var array
	 */
	protected $mResponse        = array();

	//////////////////////////////////////////////////////////////////////////////
	/// Singleton ///////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////////

	/**
	 * This method maintains access to the singleton instance of this class
	 * @access public
	 * @param boolean $blnReset [false]
	 * @return ISPConfigJsonWrapp ISPConfigJsonWrapper::$mInstance
	 * @static
	 */
	public static function getInstance($blnReset = false) {
		// Check for an existing instance or a reset flag
		if ((self::$mInstance === null) || ($blnReset === true)) {
			// Create a new instance
			self::$mInstance = new self();
		}
		// Return the instance
		return self::$mInstance;
	}

	/**
	 * This method sets an external instance into this class.  This is primarily used for testing
	 * @access public
	 * @param ISPJsonWrapper $clsJsonWrapper
	 * @return ISPJsonWrapper ISPJsonWrapper::$mInstance
	 * @static
	 */
	public static function setInstance(ISPConfigJsonWrapper $clsJsonWrapper) {
		// Set the external instance into the class
		self::$mInstance = $clsJsonWrapper;
		// Return the instance
		return self::$mInstance;
	}
	
	//////////////////////////////////////////////////////////////////////////////
	/// Constructor /////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////////

	/**
	 * This method sets up the class and optionally instantiates the handler class
	 * @access public
	 * @param remoting $strClassName [null]
	 * @return ISPConfigJsonWrapper $this
	 */
	public function __construct(remoting $strClassName = null) {
		// Check for a handler class
		if (is_null($strClassName) === false) {
			// Instantiate the handler class and set it into the instance
			$this->mHandlerClass = new $strClassName();
		}
		// We're done
		return $this;
	}

	//////////////////////////////////////////////////////////////////////////////
	/// Protected Methods ///////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////////

	/**
	 * This method build a map of methods and arguments for the handler class
	 * @access protected
	 * @return array
	 * @uses ReflectionMethod
	 * @uses ReflectionParameter
	 */
	protected function buildMethodMap() {
		// Define the placeholder
		$arrMethodMap = array(
			'methods' => array()
		);
		// Iterate over the class methods
		foreach (get_class_methods($this->mHandlerClass) as $strMethod) {
			// Load the reflection data
			$refMethod    = new ReflectionMethod($this->mHandlerClass, $strMethod);
			// Make sure this is a callable method
			if (($refMethod->isPublic() === true) && ($refMethod->isConstructor() === false)) {
				// Create the placeholder
				$arrArguments = array();
				// Iterate over the arguments
				foreach ($refMethod->getParameters() as $refParameter) {
					// Append the parameter
					array_push($arrArguments, array(
						'name'     => $refParameter->getName(),
						'required' => !$refParameter->isOptional(),
						'default'  => ($refParameter->isOptional() ? $refParameter->getDefaultValue() : null)
					));
				}
				// Add the method to the map
				$arrMethodMap['methods'][$strMethod] = array('arguments' => $arrArguments);
			}
		}
		// Return the method map
		return $arrMethodMap;
	}

	/**
	 * This method cleans the request data
	 * @access protected
	 * @param array|boolean|double|float|integer|number|string $mixValue
	 * @return array|boolean|double|float|integer|number|string
	 */
	protected function cleanRequestParameter($mixValue) {
		// Return the sanitized parameter
		return strip_tags($mixValue);
	}

	/**
	 * This method determines the request type
	 * @access protected
	 * @return ISPConfigJsonWrapper ISPConfigJsonWrapper::$mInstance
	 */
	protected function determineRequestType() {
		// Check for a callback
		if ((array_key_exists('callback', $_GET) === true) && (empty($_GET['callback']) === false)) {
			// Set the request type to JSONP
			$this->mRequestType   = ISPConfigJsonWrapper::RequestJSONP;
			// Set the request parameters
			$this->mRequestParams = $_GET;
		} else {
			// Set the request type to JSON
			$this->mRequestType   = ISPConfigJsonWrapper::RequestJSON;
			// Set the request parameters
			$this->mRequestParams = $_POST;
		}
		// We're done
		return $this;
	}

	/**
	 * This method ensures that the requested method exists in the handler class
	 * @access protected
	 * @param string $strMethod
	 * @return void
	 * @uses ISPConfigJsonWrapper::fault()
	 */
	protected function ensureMethodExists($strMethod) {
		// Check for the method
		if (in_array($strMethod, get_class_methods($this->mHandlerClass)) === false) {
			// We're done
			$this->fault('MethodNotFound('.$strMethod.')', 'The handler class does not contain the "'.$strMethod.'".');
		}
	}

	/**
	 * This method makes sure that we have all of the required parameters needed for a successful request
	 * @access protected
	 * @param string $strParam...
	 * @return void
	 * @uses ISPConfigJsonWrapper::fault()
	 */
	protected function ensureParameters() {
		// Iterate over the parameters
		foreach (func_get_args() as $strKey) {
			// Check for a method
			if (($strKey === 'method') && (array_key_exists($strKey, $_GET) === false)) {
				// We're done
				$this->fault('MethodNotFound', 'You must provide a method key in the URL parameters with the name of the method you wish to execute.');
			} else {
				// Check for the key in the JSONP request
				if (($this->mRequestType === ISPConfigJsonWrapper::RequestJSONP)  && (array_key_exists($strKey, $_GET) === false)) {
					// We're done
					$this->fault('RequiredParameterNotFound('.$strKey.')', 'Missing required parameter "'.$strKey.'" in your URL parameters.');
				}
				// Check for the key in the JSON request
				if (($this->mRequestType === ISPConfigJsonWrapper::RequestJSON) && (array_key_exists($strKey, $_POST) === false)) {
					// We're done
					$this->fault('RequiredParameterNotFount('.$strKey.')', 'Missing required parameter "'.$strKey.'" in your POST data.');
				}
			}
		}
	}

	/**
	 * This method converts the request parameters to the proper argument positions
	 * @access protected
	 * @return array
	 * @uses ISPConfigJsonWrapper::cleanRequestParameter()
	 * @uses ISPConfigJsonWrapper::fault()
	 * @uses ReflectionMethod
	 * @uses ReflectionParameter
	 */
	protected function requestToArgs() {
		// Create the arguments placeholder
		$arrArguments = array();
		// Grab the meta data
		$refMethod    = new ReflectionMethod($this->mHandlerClass, $_GET['method']);
		// Iterate over the parameters
		foreach ($refMethod->getParameters() as $refParameter) {
			// Make sure the parameter exists if it is required
			if (($refParameter->isOptional() === false) && (array_key_exists($refParameter->getName(), $this->mRequestParams) === false)) {
				// We're done
				$this->fault('MissingRequiredParameter('.$refParameter->getName().')', 'Missing required parameter "'.$refParameter->getName().'".');
			}
			// Set the parameter into the array
			$arrArguments[$refParameter->getPosition()] = $this->mRequestParams[$this->cleanRequestParameter($refParameter->getName())];
		}
		// Return the arguments
		return $arrArguments;
	}

	/**
	 * This method sends the response to the caller
	 * @access protected
	 * @return void
	 */
	protected function sendResponse() {
		// Check for a JSONP request
		if ($this->mRequestType === ISPConfigJsonWrapper::RequestJSONP) {
			// Set the header
			header('Content-Type:  text/javascript');
			// Send the response
			printf("/**/\ntypeof %s === 'function' && %s(%s);", $_GET['callback'], $_GET['callback'], json_encode($this->mResponse));
		} else {
			// Set the header
			header('Content-Type:  application/json');
			// Send the response
			print(json_encode($this->mResponse));
		}
		// We're done
		return;
	}

	//////////////////////////////////////////////////////////////////////////////
	/// Public Methods //////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////////

	/**
	 * This method is simply a copy of the SoapServer fault() method
	 * @access public
	 * @param string $strCode
	 * @param string $strMessage
	 * @return void
	 * @uses ISPConfigJsonWrapper::sendResponse()
	 */
	public function fault($strCode, $strMessage) {
		// Set the response
		$this->mResponse = array(
			'success' => false,
			'error'   => $strMessage,
			'code'    => $strCode
		);
		// We're done
		$this->sendResponse();
		// Kill the process
		exit;
	}

	/**
	 * This method handles the API request
	 * @access public
	 * @return void ISPConfigJsonWrapper::sendResponse()
	 * @uses ISPConfigJsonWrapper::buildMethodMap()
	 * @uses ISPConfigJsonWrapper::determineRequestType()
	 * @uses ISPConfigJsonWrapper::ensureMethodExists()
	 * @uses ISPConfigJsonWrapper::ensureParameters()
	 * @uses ISPConfigJsonWrapper::fault()
	 * @uses ISPConfigJsonWrapper::requestToArgs()
	 * @uses ISPConfigJsonWrapper::sendResponse()
	 */
	public function handleRequest() {
		// Localize the globals
		global $app, $conf, $server;
		// Determine the request type
		$this->determineRequestType();
		// Make sure we have the required parameters
		$this->ensureParameters('method');
		// Check for the API Method Map
		if (preg_match(ISPConfigJsonWrapper::MapMethodPattern, $_GET['method'])) {
			// Set the response
			$this->mResponse = $this->buildMethodMap();
			// We're done
			return $this->sendResponse();
		}
		// Ensure the method exists
		$this->ensureMethodExists($_GET['method']);
		// Try the execution
		try {
			// Grab the arguments
			$arrArguments    = $this->requestToArgs();
			// Execute the method
			$mixResponse     = call_user_func_array(array($this->mHandlerClass, $_GET['method']), $arrArguments);
			// Set the response
			$this->mResponse = array(
				'success'  => true,
				'response' => $mixResponse
			);
		} catch (Exception $clsException) {
			// We're done
			$this->fault($clsException->getCode(), $clsException->getMessage());
		}
		// We're done
		return $this->sendResponse();
	}


	//////////////////////////////////////////////////////////////////////////////
	/// Getters /////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////////

	/**
	 * This method returns the instance of the handler class
	 * @access public
	 * @return remoting ISPConfigJsonRouter::$mHandlerClass
	 */
	public function getHandlerClass() { return $this->mHandlerClass; }

	/**
	 * This method returns the response
	 * @access public
	 * @return array ISPConfigJsonWrapper::$mResponse
	 */
	public function getResponse()     { return $this->mResponse;     }
	
	//////////////////////////////////////////////////////////////////////////////
	/// Setters /////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////////

	/**
	 * This method sets the instance of the remoting class into the instance
	 * @access public
	 * @param string $strClass
	 * @return ISPConfigJsonWrapper ISPConfigJsonWrapper::$mInstance
	 */
	public function setHandlerClass($strClass)      { $this->mHandlerClass = new $strClass(); return $this; }

	/**
	 * This method sets the response into the class.  This is used for testing.
	 * @access public
	 * @param array $arrResponse
	 * @return ISPConfigJsonWrapper ISPConfigJsonWrapper::$mInstance
	 */
	public function setResponse(array $arrResponse) { $this->mResponse     = $arrResponse;    return $this; }
}
