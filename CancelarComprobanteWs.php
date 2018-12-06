<?php

require_once (dirname(__FILE__) . '/utils/AuthenticatedSoapClient.php');
require_once (dirname(__FILE__) . '/utils/EstatusCancelacionSAT.php');
require_once (dirname(__FILE__) . '/utils/WsErrorResponse.php');

class CancelarComprobanteWs
{
	private $accountManager;
	private $timbradoAccount;
	
	public function __construct(AccountManager $accountManager)
	{
		$this->accountManager = $accountManager;
		$this->timbradoAccount = $this->accountManager->getSimpleAccountManager();
	}
	
	public function call($cancelacionArray)
	{
		$client = new AuthenticatedSoapClient($this->accountManager, $this->accountManager->getLinkWsCancelado());
		
		try{
			$result = $client->call('CancelarComprobante', $this->getParams($cancelacionArray));
		} catch (ExceptionPacTimbrado $e) {
			$wsErrorResponse = new WsErrorResponse($e->getWsResult());
			if ($wsErrorResponse->isErrorCodePresent(EstatusCancelacionSAT::CODIGO_ERROR_CANCELADO_PREVIAMENTE)) {
				return EstatusCancelacionSAT::CODIGO_CANCELACION_YA_REALIZADA_ANTERIORMENTE;
			}
			throw $e;
		}
		
		return $this->procesarRespuestaWs($result);
	}
	
	private function getParams($cancelacionArray)
	{
		return array('xml' => $this->getXmlCancelacion($cancelacionArray));
	}
	
	private function getXmlCancelacion($cancelacionArray)
	{
		$archivosCSDManager = $this->accountManager->getArchivosCSDManager();
		
		$xml =
		'<?xml version="1.0" encoding="utf-8"?>' .
		'<Cancelacion' . ' ' .
		'llaveCertificado="' . $archivosCSDManager->getXmlRsaKeyLlavePrivadaBase64() . '" ' .
		'certificado="' . $archivosCSDManager->getCertificadoBase64() . '" ' .
		'rfcEmisor="' . $this->timbradoAccount->getRfcEmisor() . '">' .
		
		'<Folios>';
		
		foreach($cancelacionArray as $datosCancelacionFolio)
		{
			$xml .=
			'<Folio ' .
			'UUID="' . $datosCancelacionFolio['UUID'] . '" ' .
			'total="' . $datosCancelacionFolio['total'] . '" ' .
			'rfcReceptor="' . $datosCancelacionFolio['rfcReceptor'] . '"/>';
		}
		$xml .=
		'</Folios>' .
		'</Cancelacion>';
		
		return $xml;
	}
	
	private function procesarRespuestaWs($resultDOM)
	{
		$solicitud = $resultDOM->getElementsByTagName("solicitud")->item(0);
		if(empty($solicitud))
		{
			$solicitud = $resultDOM->getElementsByTagName("Solicitud")->item(0);
		}
		
		if(strtolower($solicitud->getAttribute("esValido")) === "true")
		{
			$folios = $resultDOM->getElementsByTagName("Folios")->item(0);
			
			//TODO: aqui solo se procesa el primer folio, se deberian procesar todos.
			$status = $folios->getElementsByTagName("Folio")->item(0)->getAttribute("Estatus");
			
			if(EstatusCancelacionSAT::isErrorStatus($status))
			{
				throw new ExceptionPacTimbrado(EstatusCancelacionSAT::getMessage($status));
			}
			else
			{
				return $status;
			}
		} else {
			throw new ExceptionPacTimbrado("Petición a PAC no válida.");
		}
	}
}

