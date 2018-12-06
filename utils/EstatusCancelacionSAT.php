<?php

class EstatusCancelacionSAT
{
	const CODIGO_CANCELACION_EXITOSA = 201;
	const CODIGO_CANCELACION_YA_REALIZADA_ANTERIORMENTE = 202;
	const CODIGO_RFC_NO_CORRESPONDE_AL_UUID = 203;
	const CODIGO_FACTURA_NO_REGISTRADA_EN_SAT_AUN = 205;
	const CODIGO_CFDI_NO_ES_DE_SECTOR_PRIMARIO = 206;
	
	const CODIGO_ERROR_CANCELADO_PREVIAMENTE = "FET102";
	
	public static function isErrorStatus($status)
	{
		$intStatus = (int) $status;
		if($intStatus === self::CODIGO_CANCELACION_EXITOSA || $intStatus === self::CODIGO_CANCELACION_YA_REALIZADA_ANTERIORMENTE)
		{
			return false;
		}
		return true;
	}
	
	public static function getMessage($status)
	{
		$intStatus = (int) $status;
		switch ($intStatus)
		{
			case self::CODIGO_CANCELACION_EXITOSA:
				$msg ="Petición de cancelación recibida exitosamente.";
				break;
				
			case self::CODIGO_CANCELACION_YA_REALIZADA_ANTERIORMENTE:
				$msg ="El folio ya se encontraba cancelado.";
				break;
				
			case self::CODIGO_RFC_NO_CORRESPONDE_AL_UUID:
				$msg ="El RFC especificado para la cancelacion no coincide con el RFC que emitio el folio fiscal a cancelar.";
				break;
				
			case self::CODIGO_FACTURA_NO_REGISTRADA_EN_SAT_AUN:
				$msg ="La factura no se encuentra registrada. Si la acaba de timbrar, espere unos momentos mas.";
				break;
				
			case self::CODIGO_CFDI_NO_ES_DE_SECTOR_PRIMARIO:
				$msg ="La factura no se encuentra registrada. Si la acaba de timbrar, espere unos momentos mas.";
				break;
				
			default:
				$msg = "Error al cancelar el timbre. Estatus de cancelacion desconocido.";
				break;
		}
		
		return $msg . " Codigo: {$intStatus}.";
	}
}
