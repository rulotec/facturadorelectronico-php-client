<?php

require_once __DIR__ . '/utils/AuthenticatedSoapClient.php';
require_once __DIR__ . '/utils/EstatusCancelacionSAT.php';
require_once __DIR__ . '/utils/WsErrorResponse.php';
require_once 'converter/CfdiXmlUtils.php';

class CancelarComprobanteWs
{
	private $pacAccount;
	private $cuentaTimbrado;
	
	public function __construct(CuentaTimbrado $cuentaTimbrado)
	{
		$this->pacAccount = $cuentaTimbrado->getPacAccount();
		$this->cuentaTimbrado = $cuentaTimbrado;
	}
	
	public function call($cancelacionArray)
	{
		$client = new AuthenticatedSoapClient($this->pacAccount, $this->pacAccount->getLinkWsCancelado());
		
		try{
			$result = $client->call('CancelarComprobante', $this->getParams($cancelacionArray));
		} catch (ExceptionPacTimbrado $e) {
			if ($e->getWsResult()) {
				$wsErrorResponse = new WsErrorResponse($e->getWsResult());
				if ($wsErrorResponse->isErrorCodePresent(EstatusCancelacionSAT::CODIGO_ERROR_CANCELADO_PREVIAMENTE)) {
					return EstatusCancelacionSAT::CODIGO_CANCELACION_YA_REALIZADA_ANTERIORMENTE;
				}
			} else {
				ErrorNotifier::notify(
					'Excepcion no se pudo obtener getWsResult en CancelarComprobanteWs.php',
					"{$e->getMessage()} <pre>" . print_r($e->getTrace(), true) . "</pre>"
				);
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
		$csd = $this->cuentaTimbrado->getCsd();
		
		$xmlArray = array(
			'llaveCertificado'  =>  $csd->getXmlRsaKeyLlavePrivadaBase64(),
			'certificado' =>  $csd->getCertificadoBase64(),
			'rfcEmisor' =>  $this->cuentaTimbrado->getRfcEmisor(),
			'Folios' => []
		);
		
		foreach($cancelacionArray as $datosCancelacionFolio)
		{
			$xmlArray['Folios']['Folio'][] = array(
				'UUID'  =>  $datosCancelacionFolio['UUID'],
				'total'  =>  $datosCancelacionFolio['total'],
				'rfcReceptor'  =>  $datosCancelacionFolio['rfcReceptor'],
			    'motivo' => $datosCancelacionFolio['motivo'],
			    'folioSustitucion' => $datosCancelacionFolio['folioSustitucion'],
			);
		}
		
		$rootCfdiXml = '<?xml version="1.0" encoding="utf-8"?><Cancelacion/>';
		$sxml = CfdiXmlUtils::array2SimpleXml($rootCfdiXml, $xmlArray, '');
		
		return $sxml->asXml();
	}
	
	private function procesarRespuestaWs($resultDOM)
	{
		$solicitud = $resultDOM->getElementsByTagName("solicitud")->item(0);
		if (empty($solicitud)) {
			$solicitud = $resultDOM->getElementsByTagName("Solicitud")->item(0);
		}
		
		if (strtolower($solicitud->getAttribute("esValido")) === "true") {
			$folios = $resultDOM->getElementsByTagName("Folios")->item(0);
			
			//TODO: aqui solo se procesa el primer folio, se deberian procesar todos.
			$status = $folios->getElementsByTagName("Folio")->item(0)->getAttribute("Estatus");
			
			if (EstatusCancelacionSAT::isErrorStatus($status)) {
				throw new ExceptionPacTimbrado(EstatusCancelacionSAT::getMessage($status));
			} else {
				return $status;
			}
		} else {
			throw new ExceptionPacTimbrado("Petición a PAC no válida.");
		}
	}
}

