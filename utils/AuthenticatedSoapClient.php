<?php

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
		
		$this->checkErrors($result);
		
		return $result;
	}
	
	private function checkErrors($result)
	{
		$errores = $this->getErroresNode($result);
		
		if(!empty($errores))
		{
			$mensajesError = [];
			foreach ($errores->getElementsByTagName("Error") as $error)
			{
				$mensajesError[] = "Error en petición al PAC: " .
						htmlspecialchars($error->getAttribute("mensaje") .
								" Código error PAC: {$error->getAttribute("codigo")}.");
			}
			
			$e = new ExceptionPacTimbrado();
			$e->setMensajesErrorUsuarioArray($mensajesError);
			throw $e;
		}
	}
	
	private function getErroresNode($result)
	{
		$errores = $result->getElementsByTagName("errores")->item(0);
		if(empty($errores))
		{
			//dependiendo de funcion llamada, a veces el nombde del nodo es con mayúscula.
			$errores = $result->getElementsByTagName("Errores")->item(0);
		}
		return $errores;
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
		error_log("Request: " . $client->__getLastRequest() . "\n");
		file_put_contents(PATH_LOGS . '/' . $accion. '_request_PAC.xml', $client->__getLastRequest());
		//error_log("Response headers: " . print_r($client->__getLastResponseHeaders(), 1) . "\n");
		error_log("Response: " . $client->__getLastResponse() . "\n");
		file_put_contents(PATH_LOGS . '/' . $accion. '_response_PAC.xml', $client->__getLastResponse());
	}
}
