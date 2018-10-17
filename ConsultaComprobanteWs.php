<?php

require_once (dirname(__FILE__) . '/utils/AuthenticatedSoapClient.php');

class ConsultaComprobanteWs
{
	private $accountManager;
	private $timbradoAccount;
	
	public function __construct(AccountManager $accountManager)
	{
		$this->accountManager = $accountManager;
		$this->timbradoAccount = $this->accountManager->getSimpleAccountManager();
	}
	
	public function call($datosCancelacionFolio)
	{
		$client = new AuthenticatedSoapClient($this->accountManager, $this->accountManager->getLinkWsCancelado());
		$result = $client->call('ConsultaComprobante', $this->getParams($datosCancelacionFolio));
		
		return $this->procesarRespuestaWs($result);
	}
	
	private function getParams($datosCancelacionFolio)
	{
		return array('xml' => $this->getXml($datosCancelacionFolio));
	}
	
	private function getXml($datosCancelacionFolio)
	{
		$archivosCSDManager = $this->accountManager->getArchivosCSDManager();
		
		$xml =
		'<?xml version="1.0" encoding="utf-8"?>' .
		'<ConsultaCfdi' . ' ' .
		'rfcEmisor="' . $this->timbradoAccount->getRfcEmisor() . '" ' .
		'rfcReceptor="' . $datosCancelacionFolio['rfcReceptor'] .'" ' .
		'uuid="' . $datosCancelacionFolio['UUID'] . '" ' .
		'total="' . $datosCancelacionFolio['total'] . '" ' .
		'llaveCertificado="' . $archivosCSDManager->getXmlRsaKeyLlavePrivadaBase64() . '" ' .
		'certificado="' . $archivosCSDManager->getCertificadoBase64() .
		'"/>';
		
		return $xml;
	}
	
	private function procesarRespuestaWs($resultDOM)
	{
		$codigoEstatus = $resultDOM->getElementsByTagName("CodigoEstatus")->item(0)->nodeValue;
		
		$esCancelable = $resultDOM->getElementsByTagName("EsCancelable")->item(0)->nodeValue;
		$estado = $resultDOM->getElementsByTagName("Estado")->item(0)->nodeValue;
		$estatusCancelacion = $resultDOM->getElementsByTagName("EstatusCancelacion")->item(0)->nodeValue;
		
		if(substr($codigoEstatus, 0, 1) === 'S')
		{
			return array('CodigoEstatus' => $codigoEstatus, 'EsCancelable' => $esCancelable, 'Estado' => $estado, 'EstatusCancelacion' => $estatusCancelacion);
		}
		else
		{
			throw new ExceptionPacTimbrado("CodigoEstatus: {$codigoEstatus} - EsCancelable: {$esCancelable} - Estado: {$estado}- EstatusCancelacion: {$estatusCancelacion}");
		}
	}
}
