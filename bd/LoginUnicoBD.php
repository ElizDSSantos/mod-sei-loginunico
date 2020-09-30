<?
/**
* TRIBUNAL REGIONAL FEDERAL DA 4ª REGIÃO
*
* 14/02/2020 - criado por diego.tesch & behatris.fiorentini
*/

require_once dirname(__FILE__).'/../../../SEI.php';

class LoginUnicoBD extends InfraBD {

  public function __construct(InfraIBanco $objInfraIBanco){
  	 parent::__construct($objInfraIBanco);
  }
}
?>