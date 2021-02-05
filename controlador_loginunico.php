<?
try {
    require_once dirname(__FILE__).'/../../SEI.php';
    session_start();

    switch($_GET['acao']) {
    case 'usuario_loginunico_atualizar':
    case 'usuario_externo_enviar_cadastro':
    case 'usuario_loginunico_aceite':
    case 'usuario_loginunico_associar':
        SessaoSEIExterna::getInstance()->validarLink();
        require_once dirname(__FILE__) .'/views/form_cadastro_atualizar_loginunico.php';
        break;

    default:
        $controlador = new LoginControladorRN();
        if(isset($_GET['code']) && isset($_GET['state'])){
            $codigoRevalidacao=SessaoSEIExterna::getInstance()->getAtributo('MD_LOGIN_UNICO_STATE_REVALIDACAO');
                        
            if($_GET['state']==$codigoRevalidacao){
                SessaoSEIExterna::getInstance()->setAtributo('MD_LOGIN_UNICO_STATE_REVALIDACAO',null);
                $controlador->assinarLoginUnico();
                return;
            } 

            $controlador->autenticar($_GET);
            return;
        }
        header('Location: '.$controlador->gerarURL());
    }


}catch(Exception $e){
	PaginaSEIExterna::getInstance()->processarExcecao($e);
}