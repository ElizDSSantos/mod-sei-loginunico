<?php
class LoginUnicoIntegracao extends SeiIntegracao
{

    public function getNome()
    {
        return 'M�dulo Login �nico';
    }

    public function getVersao() 
    {
        return 'sei-3.1.3-loginunico-1.0.0-rc.3';
    }

    public function getInstituicao()
    {
        return 'CADE - Conselho Administrativo de Defesa Econ�mica';
    }

    public function getAcaoModulo($strAcao)
    {
        return 'loginunico';
    }

    public function montarBotaoAutenticacaoExterna()
    {
        $controlador = new LoginControladorRN();
        if (SessaoSEIExterna::getInstance()->getAtributo('MD_LOGIN_EXTERNO_TOKEN')) {
            session_destroy();
        }
        PaginaSEIExterna::getInstance()->adicionarStyle('modulos/loginunico/css/style.css');
        $url = $controlador->gerarURL();
        //$html  = "<p class='separador'>-------------------- ou --------------------</p>";
        $html  = "<p class='separador'><strong>ou</strong></p>";
        $html .= "<a class='btGov' href='".$url."'>
                    <span id='txtComplementarBtGov'>Acessar com </span><img src='modulos/loginunico/img/img_acesso.png' alt='' width='64' height='28'>
                  </a>";
        return $html;
        //return null;
    }

    public function validarSenhaExterna(LoginExternoAPI $objLoginExternoAPI)
    {
        return true;
    }

    /**
     * Fun��o responsavel por ler os dados do usu�rio,
     * quando ele retornar do acesso.gov, j� autenticado
     */
    public function Autenticar($dados)
    {
        $controlador = new LoginControladorRN();
        $controlador->autenticar($dados);
    }

    public function processarControladorExterno($strAcao)
    {
        switch ($strAcao) {
            
            case 'usuario_externo_enviar_cadastro':
            case 'usuario_loginunico_atualizar':
            case 'usuario_loginunico_aceite':
            case 'usuario_loginunico_associar':
                require_once dirname (__FILE__) . '/controlador_loginunico.php'; 
                return true;  
        }
        return null;
    }

}
