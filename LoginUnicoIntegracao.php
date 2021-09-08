<?php
class LoginUnicoIntegracao extends SeiIntegracao
{
    private $urlLogout;

    public function __construct()
    {
        $conf = new ConfiguracaoSEI();
        $this->urlLogout  ='https://sso.staging.acesso.gov.br/logout?post_logout_redirect_uri=' . $conf->getArrConfiguracoes()['LoginUnico']['url_logout'];
    }

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

    public function montarBotaoLoginExterno()
    {
        $controlador = new LoginControladorRN();
        if (SessaoSEIExterna::getInstance()->getAtributo('MD_LOGIN_UNICO_TOKEN')) {
            session_destroy();
        }
        PaginaSEIExterna::getInstance()->adicionarStyle('modulos/'. NOME_MODULO_LOGIN_UNICO. '/css/login.css');
        $url = $controlador->gerarURL();
        //$html  = "<p class='separador'>-------------------- ou --------------------</p>";
        $html  = "<p class='separador'><strong>ou</strong></p>";
        $html .= "<a class='btGov' href='".$url."'>
                    <span id='txtComplementarBtGov'>Acessar com </span><img src='modulos/". NOME_MODULO_LOGIN_UNICO . "/img/img_acesso.png' alt='' width='64' height='28'>
                  </a>";
        return $html;
        //return null;
    }

    public function verificaInstalacao(){

        $objInfraParametroDTO = new InfraParametroDTO();
        $objInfraParametroDTO->setStrNome('MD_LOGIN_UNICO');
        $objInfraParametroDTO->retTodos();
        $objInfraParametroRN=new InfraParametroRN();
        $objInfraParametroDTO=$objInfraParametroRN->consultar($objInfraParametroDTO);
        return $objInfraParametroDTO;

    }

    public function montarBotaoAssinaturaExterno(UsuarioAPI $objUsuarioAPI)
    {
        if(!$this->verificaInstalacao()) return;
        
        $controlador = new LoginControladorRN();
        $usuario=$controlador->getIdUsuarioLoginUnico($objUsuarioAPI);
        $password=$_POST['pwdSenha'];

        if ($_POST["trocarAssinatura"]==true){
            echo "  
            <script src= 'modulos/". NOME_MODULO_LOGIN_UNICO . "/js/montarBotaoExterno.js' > </script>          
            <button type='button' class='infraButton' id='retornarGovBr'
            style='margin-left:125px'
            onclick='document.getElementById(\"frmAssinaturaUsuarioExterno\").submit();
            '>
                Voltar para Assinatura GovBr
            </button>
            <script>  formatarBotaoRetorno();  </script>
            ";
            return null;
        }

        if($password!=null){
            
            return null;
        }

        if ($usuario != null) {

            PaginaSEIExterna::getInstance()->adicionarStyle('modulos/'. NOME_MODULO_LOGIN_UNICO. '/css/style.css');

            $controlador = new LoginControladorRN();
            $state=$controlador->getRandomHex(12);
            $hashSei=$controlador->getRandomHex(12);
            $strLinkAjaxState=SessaoSEIExterna::getInstance()->assinarLink('controlador_ajax_externo.php?acao_ajax=state_login_unico_ajax&hashSei=' . $hashSei . '&id_acesso_externo=' . SessaoSEIExterna::getInstance()->getNumIdAcessoExterno());
           
            LoginUnicoINT::adicionaEmArraySessao('SessaoSEIExterna','MD_LOGIN_UNICO_HASHMAP',$hashSei,$state);

            $html = "
                    <script src= 'modulos/". NOME_MODULO_LOGIN_UNICO . "/js/montarBotaoExterno.js' > </script>
                    <a class='btGov' style='width: 90%;' onclick='
                        handleClickExterno(\"". AssinaturaRN::$TA_MODULO ."\" ,\"". $hashSei ."\");
                        abrirJanelaLoginUnico(\"" . $strLinkAjaxState . "\");
                    '>
                        <span id='txtComplementarBtGov'>Assinar com </span><img src='modulos/". NOME_MODULO_LOGIN_UNICO . "/img/img_acesso.png' alt='' width='64' height='28'>
                    </a>

                    <span class='spanSeparacao' style='width: 90%;' > OU </span>
                  
                    <a class='btGov btnAlterar' id='btGovBrOldPass'  style='width: 90%;' onclick='
                        handleClickTrocarAssinatura();
                    '>
                        Utilizar Assinatura Interna SEI
                    </a>"
                  
                  ;

            return $html;
        }
    }

    //cria o botão na tela de assinar documentos internos
    public function montarBotaoAssinaturaInterno(UsuarioAPI $objUsuarioAPI){
        
            if(!$this->verificaInstalacao()) return;
            
            $controlador = new LoginControladorRN();
            $usuario=$controlador->getIdUsuarioLoginUnico($objUsuarioAPI);
            $bolBotaoAcionado=$_POST['hdnFormaAutenticacao'];
            $password=$_POST['pwdSenha'];

            if($bolBotaoAcionado=='M'){
                return true;
            }

            if($usuario == null){
                return null;
            }

            if($_POST["trocarAssinatura"] || $password!=null){
                echo "                
                <script src= 'modulos/". NOME_MODULO_LOGIN_UNICO . "/js/montarBotaoInterno.js' ></script>
                <label class='infraLabelRadio infraLabelObrigatorio' id='retornarGovBr'
                style='margin-left:10px'
                onclick='document.getElementById(\"frmAssinaturas\").submit();'>
                    Assinatura GovBr
                </label>
                <script>trocarAssinatura(); </script>
                ";
                return null;
            }

            $controlador = new LoginControladorRN();
            $state=$controlador->getRandomHex(12);
            $hashSei=$controlador->getRandomHex(12);
            LoginUnicoINT::adicionaEmArraySessao('SessaoSEI','MD_LOGIN_UNICO_HASHMAP',$hashSei,$state);           
 
            PaginaSEI::getInstance()->adicionarStyle('modulos/'. NOME_MODULO_LOGIN_UNICO. '/css/style.css');

            $strLinkAjaxState=SessaoSEI::getInstance()->assinarLink('controlador_ajax.php?acao_ajax=state_login_unico_ajax&hashSei=' . $hashSei );
         
            $html = "
            <script src= 'modulos/". NOME_MODULO_LOGIN_UNICO . "/js/montarBotaoInterno.js' >  </script>
            
                        <div id='btnLoginUnico'>
                            <a class='btGov' id='btGovBr'  onclick='
                            handleClickInterno(\"". AssinaturaRN::$TA_MODULO ."\" ,\"". $hashSei ."\");
                            abrirJanelaLoginUnico(\"" . $strLinkAjaxState . "\");
                            '>                                                                                      
                                <span id='txtComplementarBtGov'>Assinar com </span><img src='modulos/". NOME_MODULO_LOGIN_UNICO . "/img/img_acesso.png' alt='' width='64' height='28'>
                            </a>
                            <span class='spanSeparacao' > OU </span>
                            <a class='btGov btnAlterar' id='btGovBrOldPass'  onclick='
                                handleClickTrocarAssinatura();
                            '>
                                Utilizar Assinatura Interna SEI
                            </a>
                        </div>
                <script>                    
                    initBotao(\"" . $this->urlLogout . "\")   
                </script>";
            
            return $html;

    }


    
    public function prepararAssinaturaDocumento(AssinaturaAPI $objRespostaAssinatura){

       if(!isset($_POST['loginUnicoState']))return false;

        try{ 

        if($_GET['acao']=="usuario_externo_assinar"){

            $state=LoginUnicoINT::obterDadosSessao('SessaoSEIExterna','MD_LOGIN_UNICO_HASHMAP',$_POST['loginUnicoState']);

            if($state==null){
                throw new InfraException("Erro ao obter objeto de assinatura, tente novamente");
            }

            LoginUnicoINT::adicionaEmArraySessao('SessaoSEIExterna','MD_LOGIN_UNICO_HASHMAP',$_POST['loginUnicoState'],'');

            $objAssinaturaLoginUnicoDTO=new AssinaturaLoginUnicoDTO();
            $objAssinaturaLoginUnicoDTO->setNumIdAssinaturaLoginUnico(BancoSEI::getInstance()->getValorSequencia('md_login_unico_seq_assinatura'));
            $objAssinaturaLoginUnicoDTO->setNumIdUsuario($objRespostaAssinatura->getIdUsuario());
            $objAssinaturaLoginUnicoDTO->setStrAgrupador($objRespostaAssinatura->getAgrupador());
            $objAssinaturaLoginUnicoDTO->setStrStateLoginUnico($state);
            $objAssinaturaLoginUnicoDTO->setStrOperacao("revalidacao");
            $objAssinaturaLoginUnicoDTO->setStrIdDocumentos($_GET["id_documento"]);
            $objAssinaturaLoginUnicoDTO->setNumIdAcessoExterno($_GET['id_acesso_externo']);
            $objAssinaturaLoginUnicoDTO->setDthDataAtualizacao(InfraData::getStrDataHoraAtual());

            $controlador = new LoginControladorRN();
            $controlador->gravarAgrupador($objAssinaturaLoginUnicoDTO);

            
            echo "<h3 style='font-weight:600;margin:30px'>Assinatura em Andamento</h3>
            <p>Caso a janela do GovBr não abrir, favor desabilitar o bloqueio de janelas do navegador.</p>
            <script> 
                this.parent.document.querySelector('.sparkling-modal-close').addEventListener('click',()=>{parent.location.reload();})
                window.addEventListener('load',()=>{
                    let areaForm=document.querySelector('#divInfraAreaTelaD');
                    areaForm.style.visibility='hidden';
                });
            </script>
            ";

            return;

        }//caso assinatura INTERNA


        $controlador = new LoginControladorRN();
        $bolUsuarioLoginUnico=$controlador->pesquisaUsuarioLoginUnico($objRespostaAssinatura->getIdUsuario());
        if(!$bolUsuarioLoginUnico){
            throw new InfraException("Usuário não faz parte do login Unico");
        }


        if ($_GET['arvore'] == '1'){
            $strAcaoOrigem="assinaturaPadrao";
        }else{
            $strAcaoOrigem=$_GET['acao_retorno'];
        }

        $state=LoginUnicoINT::obterDadosSessao('SessaoSEI','MD_LOGIN_UNICO_HASHMAP',$_POST['loginUnicoState']);

        if($state==null){
            throw new InfraException("Erro ao obter objeto de assinatura, tente novamente");
        }
        
        LoginUnicoINT::adicionaEmArraySessao('SessaoSEI','MD_LOGIN_UNICO_HASHMAP',$_POST['loginUnicoState'],'');    

        $objAssinaturaLoginUnicoDTO=new AssinaturaLoginUnicoDTO();
        $objAssinaturaLoginUnicoDTO->setNumIdAssinaturaLoginUnico(BancoSEI::getInstance()->getValorSequencia('md_login_unico_seq_assinatura'));
        $objAssinaturaLoginUnicoDTO->setNumIdUsuario($objRespostaAssinatura->getIdUsuario());
        $objAssinaturaLoginUnicoDTO->setStrAgrupador($objRespostaAssinatura->getAgrupador());
        $objAssinaturaLoginUnicoDTO->setStrStateLoginUnico($state);
        $objAssinaturaLoginUnicoDTO->setStrOperacao("assinaturaInterna");
        $objAssinaturaLoginUnicoDTO->setStrIdDocumentos($_POST['hdnIdDocumentos']);
        $objAssinaturaLoginUnicoDTO->setStrAcaoOrigem($strAcaoOrigem);
        $objAssinaturaLoginUnicoDTO->setDthDataAtualizacao(InfraData::getStrDataHoraAtual());

        $controlador = new LoginControladorRN();
        $controlador->gravarAgrupador($objAssinaturaLoginUnicoDTO);


        echo "<h1 style='font-weight:600;margin:30px;text-align:center'>Assinatura em Andamento</h1>
            <p style='margin:10px;text-align:center'>Caso a janela do GovBr não abrir, favor desabilitar o bloqueio de janelas do navegador.</p>
            <script> 
            this.parent.document.querySelector('.sparkling-modal-close').addEventListener('click',()=>{parent.location.reload();})
            window.addEventListener('load',()=>{
                let areaForm=document.querySelector('#divInfraAreaTelaD');
                areaForm.style.visibility='hidden';
            });
            </script>
            ";

        return;


    }catch(Exception $e){
            
        throw new InfraException("Erro ao atualizar assinatura no Banco após revalidação GovBr",$e);

    }

    }

    //validador para omitir a opção de alterar senha para usuário externo
    public function verificarLoginExterno(UsuarioAPI $objUsuarioAPI){
        
        return false;
        
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

    public function processarControladorAjax($strAcao)
    {
        switch ($_GET['acao_ajax']) { 
            
            case 'usuario_login_unico_ajax':     
                $controlador = new LoginControladorRN();
                $retorno=$controlador->pesquisaUsuarioLoginUnico($_POST['idUser']);
                InfraAjax::enviarTexto($retorno);                 
                exit(0);
                
            case 'state_login_unico_ajax':  
                $controlador = new LoginControladorRN();
                $state=LoginUnicoINT::obterDadosSessao('SessaoSEI','MD_LOGIN_UNICO_HASHMAP',$_GET['hashSei']); 
                $url = $controlador->gerarURL(false,$state);
                InfraAjax::enviarTexto($url);                              
                exit(0);
        }
        return null;
    }

    public function processarControladorAjaxExterno($strAcao)
    {
        switch ($_GET['acao_ajax']) { 

            case 'state_login_unico_ajax':  
                $controlador = new LoginControladorRN();
                $state=LoginUnicoINT::obterDadosSessao('SessaoSEIExterna','MD_LOGIN_UNICO_HASHMAP',$_GET['hashSei']); 
                if(!$state) throw new InfraException("Erro ao obter documento a ser assinado, tente novamente");
                $url = $controlador->gerarURL(true,$state);
                InfraAjax::enviarTexto($url);                              
                exit(0);
                
        }
        return null;
    }

}
