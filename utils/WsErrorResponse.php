<?php

class WsErrorResponse
{
	private $result;
	private $errores;
	
	public function __construct(DOMDocument $result)
	{
		$this->result = $result;
		$this->processErrors();
	}
	
	private function processErrors()
	{
		$errores = $this->getErroresNode();
		
		if(!empty($errores))
		{
			$mensajesError = [];
			foreach ($errores->getElementsByTagName("Error") as $error)
			{
				$this->errores[] = array(
						'codigo' => $error->getAttribute("codigo"),
						'mensaje' => htmlspecialchars($error->getAttribute("mensaje"))
				);
			}
		}
	}
	
	public function isErrorCodePresent($errorCode)
	{
		foreach ($this->errores as $error) {
			if($error[''] === $errorCode) {
				return true;
			}
		}
		return false;
	}
	
	public function throwExceptionPacTimbradoIfErrors()
	{
		if(!empty($this->errores)) {
			$mensajesError = [];
			foreach ($this->errores as $error) {
				$mensajesError[] = "Error en petición al PAC: {$error['mensaje']}" .
				" Código error PAC: {$error['codigo']}.";
			}
			
			$e = new ExceptionPacTimbrado();
			$e->setMensajesErrorUsuarioArray($mensajesError);
			$e->setWsResult($this->result);
			throw $e;
		}
	}
	
	private function getErroresNode()
	{
		$errores = $this->result->getElementsByTagName("errores")->item(0);
		if(empty($errores))
		{
			//dependiendo de funcion llamada, a veces el nombde del nodo es con mayúscula.
			$errores = $this->result->getElementsByTagName("Errores")->item(0);
		}
		return $errores;
	}
}
