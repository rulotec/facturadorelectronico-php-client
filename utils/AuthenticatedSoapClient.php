<?php

require_once (dirname(__FILE__) . '/WsErrorResponse.php');

class AuthenticatedSoapClient extends SoapClientWrapper
{
	private $accountManager;
	
	public function __construct(AccountManager $accountManager, $wsdlUrl)
	{
		$this->accountManager = $accountManager;
		parent::__construct($wsdlUrl);
	}
	
	public function call($funcionALlamar, $params)
	{
		$soapResponse = parent::call($funcionALlamar, array_merge($this->getAuthParams(), $params));
		$resultNodeName = $funcionALlamar . "Result";
		
		$result = new DOMDocument();
		$result->loadXML($soapResponse->$resultNodeName->any);
		
		$wsErrorResponse = new WsErrorResponse($result);
		$wsErrorResponse->throwExceptionPacTimbradoIfErrors();
		
		return $result;
	}
	
	private function getAuthParams()
	{
		$params = array(
				'usuario' => $this->accountManager->getUsuarioWs(),
				'password' => $this->accountManager->getPasswordWs(),
		);
		return $params;
	}
}

class SoapClientWrapper
{
	private $soapClient;
	
	public function __construct($wsdlUrl)
	{
		$this->soapClient = new SoapClient($wsdlUrl, array("trace"=>1, "exceptions"=>1));
	}
	
	public function call($funcionALlamar, $params)
	{
		try
		{
			$result = $this->soapClient->$funcionALlamar($params);
		}
		catch(SoapFault $fault)
		{
			throw new ExceptionPacTimbrado("Error en servicio del PAC: " . $fault->faultstring);
		}
		finally
		{
			$this->logHTTPRequestAndResponse($this->soapClient, $funcionALlamar);
		}
		
		return $result;
	}
	
	private function logHTTPRequestAndResponse($client, $accion)
	{
		//error_log("Request headers: " . print_r($client->__getLastRequestHeaders(), 1) . "\n") ;
		//error_log("Request: " . $client->__getLastRequest() . "\n");
		Logger::logComunicacionPac2Xml($client->__getLastRequest(), $accion, 'request');
		//error_log("Response headers: " . print_r($client->__getLastResponseHeaders(), 1) . "\n");
		//error_log("Response: " . $client->__getLastResponse() . "\n");
		Logger::logComunicacionPac2Xml($client->__getLastResponse(), $accion, 'response');
	}
}
