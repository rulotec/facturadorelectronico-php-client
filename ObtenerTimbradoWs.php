<?php

require_once (dirname(__FILE__) . '/utils/AuthenticatedSoapClient.php');

class ObtenerTimbradoWs
{
	private $accountManager;
	private $timbreSat;
	
	public function __construct(AccountManager $accountManager)
	{
		$this->accountManager = $accountManager;
	}
	
	public function call($xmlFactura)
	{
		$client = new AuthenticatedSoapClient($this->accountManager, $this->accountManager->getLinkWs());
		
		try
		{
			$result = $client->call('obtenerTimbrado', $this->getParams($xmlFactura));
			$this->procesarRespuestaWs($result);
		}
		catch (ExceptionPacTimbrado $e)
		{
			//Es posible que en respuesta de error se incluya timbre si es que factura se había ya timbrado previamente.
			//Se cacha el error para
			$this->procesarRespuestaWs($e->getWsResult());
			
			if(empty($this->timbreSat))
			{
				throw $e;
			}
		}
		
		return $this->timbreSat;
	}
	
	private function getParams($xmlFactura)
	{
		$params = array(
				'CFDIcliente' => trim($xmlFactura),
				//Se proporciona parametro "Usuario" porque AuthenticatedSoapClient usa el parametro usuario (con minuscula) porque otros endpoints lo usan asi.
				//FacturadorElectronico debe arreglar eso para no tener que hacer este tipo de parches.
				'Usuario' => $this->accountManager->getUsuarioWs(),
		);
		
		return $params;
	}
	
	private function procesarRespuestaWs(DOMDocument $result)
	{
		$timbrePac = $result->getElementsByTagName("timbre")->item(0);
		
		if(strtolower($timbrePac->getAttribute("esValido")) === "true")
		{
			$this->setTimbreSat($result);
		}
		else
		{
			$this->buscarTimbreEnErrores($result);
		}
	}
	
	private function setTimbreSat(DOMDocument $timbreContainerDOMDocument)
	{
		$timbreSat= $timbreContainerDOMDocument->getElementsByTagName("TimbreFiscalDigital")->item(0);
		$this->timbreSat = $timbreContainerDOMDocument->saveXML($timbreSat);
	}
	
	/**
	 * Procesa nodo de errores en respuesta de timbrado del PAC
	 * buscando timbre cuando el error es que ya se habia timbrado antes la factura (codigo 307).
	 *
	 * @param DomDocument $result - resultado de llamada a webservice con posibles errores..
	 * @return boolean - Verdadero si el timbre se encuentra en los errores, falso si no.
	 */
	private function buscarTimbreEnErrores(DomDocument $result)
	{
		$errores = $result->getElementsByTagName("Error");
		
		foreach ($errores as $error)
		{
			if($error->getAttribute("codigo") == "TIMBRE")
			{
				$stringTimbrePac = $error->getAttribute("mensaje");
				$timbrePac = new DOMDocument();
				$timbrePac->loadXML($stringTimbrePac);
				
				$this->setTimbreSat($timbrePac);
			}
		}
	}
}