<?php

try {
    
    require_once dirname(__FILE__).'/../../../SEI.php';
    session_start();
	
	SessaoSEI::getInstance(false);
	
	$objMdVersaoRN = new MdLoginUnicoRN();
	$objMdVersaoRN->atualizarVersao();
	
} catch(Exception $e) {
	echo(InfraException::inspecionar($e));
	try {
	    LogSEI::getInstance()->gravar(InfraException::inspecionar($e));
	} catch (Exception $e) {}
}
	
?>