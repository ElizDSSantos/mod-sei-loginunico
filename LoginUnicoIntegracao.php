<?php
class LoginUnicoIntegracao extends SeiIntegracao
{

    public function getNome()
    {
        return 'Módulo Login Único';
    }

    public function getVersao() 
    {
        return 'sei-3.1.3-loginunico-1.0.0-rc.3';
    }

    public function getInstituicao()
    {
        return 'CADE - Conselho Administrativo de Defesa Econômica';
    }

    public function getAcaoModulo($strAcao)
    {
        return 'loginunico';
    }

    public function inicializar($strVersaoSEI)
    {
        $strDirModulo = basename(dirname(__FILE__));
        define('NOME_MODULO_LOGIN_UNICO', $strDirModulo);
    }

    public function montarBotaoAutenticacaoExterna()
    {
        $controlador = new LoginControladorRN();
        if (SessaoSEIExterna::getInstance()->getAtributo('MD_LOGIN_UNICO_TOKEN')) {
            session_destroy();
        }
         PaginaSEIExterna::getInstance()->adicionarStyle('modulos/'. NOME_MODULO_LOGIN_UNICO. '/css/style.css');
        $url = $controlador->gerarURL();
        //$html  = "<p class='separador'>-------------------- ou --------------------</p>";
        $html  = "<p class='separador'><strong>ou</strong></p>";
        $html .= "<a class='btGov' href='".$url."'>
                    <span id='txtComplementarBtGov'>Acessar com </span><img src='modulos/". NOME_MODULO_LOGIN_UNICO . "/img/img_acesso.png' alt='' width='64' height='28'>
                  </a>";
        return $html;
        //return null;
    }

    public function montarBotaoAssinaturaExterna()
    {
        $bolLoginGovBr = SessaoSEIExterna::getInstance()->getAtributo('LOGIN_GOV_BR');

        if ($bolLoginGovBr) {

            $dados = [
                'id_documento' => $_GET['id_documento'],
                'id_orgao_acesso_externo' => $_GET['id_orgao_acesso_externo'],
                'id_acesso_externo' => $_GET['id_acesso_externo'],
            ];
            SessaoSEIExterna::getInstance()->setAtributo('MD_LOGIN_UNICO_DADOS_DOC', $dados);
            $controlador = new LoginControladorRN();
            $url = $controlador->gerarURL(true);
            echo "<script>
            window.resizeTo(500, 800);
            window.location.href = '$url';
            </script>";
        }
    }


    public function validarSenhaExterna(LoginExternoAPI $objLoginExternoAPI)
    {
        $controlador = new LoginControladorRN();
        $validacao = $controlador->validarTokenAssinatura($_GET);
        echo "<script>
        window.opener.location.reload();
        window.close();
        </script>";
        return $validacao;
    }


    public function validarSeLoginExterno(){

        return true;
    }

    /**
     * Função responsavel por ler os dados do usuário,
     * quando ele retornar do acesso.gov, já autenticado
     */
    public function Autenticar($dados)
    {
        $controlador = new LoginControladorRN();
        $controlador->autenticar($dados);
    }


    public function excluirUsuario($arrObjUsuarioAPI)
    {
        if(!isset($arrObjUsuarioAPI)){
            throw new InfraException("Usuários para deleção não podem ser nulos");
        }

        foreach($arrObjUsuarioAPI as $objUsuarioAPI){

            $objLoginUnicoDTO=new LoginUnicoDTO();
            $objLoginUnicoDTO->setNumIdUsuario($objUsuarioAPI->getIdUsuario());
            $objLoginUnicoDTO->retNumIdUsuario();
            $objLoginUnicoDTO->retNumIdLogin();

            $objLoginUnicoBD= new LoginUnicoBD(BancoSEI::getInstance());

            $arrLoginUnicoDTO=$objLoginUnicoBD->listar($objLoginUnicoDTO);
            
            if(!empty($arrLoginUnicoDTO)){

                foreach($arrLoginUnicoDTO as $objLoginUnicoDTO){

                    $objLoginUnicoBD->excluir($objLoginUnicoDTO);
                }

            }

        }

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
