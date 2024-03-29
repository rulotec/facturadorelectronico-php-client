<?php

require_once __DIR__ . '/utils/AuthenticatedSoapClient.php';
require_once 'converter/CfdiXmlUtils.php';

class ConsultaComprobanteWs
{
	private $pacAccount;
	private $cuentaTimbrado;
	
	public function __construct(CuentaTimbrado $cuentaTimbrado)
	{
		$this->pacAccount = $cuentaTimbrado->getPacAccount();
		$this->cuentaTimbrado = $cuentaTimbrado;
	}
	
	public function call($datosCancelacionFolio)
	{
		$client = new AuthenticatedSoapClient($this->pacAccount, $this->pacAccount->getLinkWsCancelado());
		$result = $client->call('ConsultaComprobante', $this->getParams($datosCancelacionFolio));
		
		return $this->procesarRespuestaWs($result);
	}
	
	private function getParams($datosCancelacionFolio)
	{
		return array('xml' => $this->getXml($datosCancelacionFolio));
	}
	
	private function getXml($datosCancelacionFolio)
	{
		$csd = $this->cuentaTimbrado->getCsd();
		
		$consultaArray = array(
			'rfcEmisor' =>  $this->cuentaTimbrado->getRfcEmisor(),
			'rfcReceptor'  =>  $datosCancelacionFolio['rfcReceptor'],
			'UUID'  =>  $datosCancelacionFolio['UUID'],
			'total'  =>  $datosCancelacionFolio['total'],
			'llaveCertificado'  =>  $csd->getXmlRsaKeyLlavePrivadaBase64(),
			'certificado' =>  $csd->getCertificadoBase64(),
		);
		
		$rootCfdiXml = '<?xml version="1.0" encoding="utf-8"?><ConsultaCfdi/>';
		$sxml = CfdiXmlUtils::array2SimpleXml($rootCfdiXml, $consultaArray, '');
		
		return $sxml->asXml();
	}
	
	private function procesarRespuestaWs($resultDOM)
	{
		$codigoEstatus = $resultDOM->getElementsByTagName("CodigoEstatus")->item(0)->nodeValue;
		
		$esCancelable = $resultDOM->getElementsByTagName("EsCancelable")->item(0)->nodeValue;
		$estado = $resultDOM->getElementsByTagName("Estado")->item(0)->nodeValue;
		$estatusCancelacion = $resultDOM->getElementsByTagName("EstatusCancelacion")->item(0)->nodeValue;
		
		if (substr($codigoEstatus, 0, 1) === 'S') {
			return array('CodigoEstatus' => $codigoEstatus, 'EsCancelable' => $esCancelable, 'Estado' => $estado, 'EstatusCancelacion' => $estatusCancelacion);
		} else {
			throw new ExceptionPacTimbrado("CodigoEstatus: {$codigoEstatus} - EsCancelable: {$esCancelable} - Estado: {$estado}- EstatusCancelacion: {$estatusCancelacion}");
		}
	}
}
