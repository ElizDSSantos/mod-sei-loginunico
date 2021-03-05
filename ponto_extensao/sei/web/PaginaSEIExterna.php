<?
/*
 * TRIBUNAL REGIONAL FEDERAL DA 4ª REGIÃO
 * 
 * 12/11/2007 - criado por MGA
 *
 */
 
 require_once dirname(__FILE__).'/SEI.php';
 
 class PaginaSEIExterna extends InfraPaginaEsquema2
 {
   private static $instance = null;
   private static $strMenu = null;

   public static function getInstance()
   {
     if (self::$instance == null) {
       self::$instance = new PaginaSEIExterna();
     }
     return self::$instance;
   }

   public function __construct()
   {
     SeiINT::validarHttps();
     parent::__construct();
   }

   public function getStrNomeSistema()
   {
     return ConfiguracaoSEI::getInstance()->getValor('PaginaSEI', 'NomeSistema');
   }

   public function isBolProducao()
   {
     return ConfiguracaoSEI::getInstance()->getValor('SEI', 'Producao');
   }

   public function getNumVersao(){
     return md5(str_replace(' ','-',SEI_VERSAO . '-' . parent::getNumVersao()));
   }

   public function validarHashTabelas(){
     return true;
   }

   public function getStrLogoSistema(){
     $strRet = '<img src="imagens/sei_logo_' . $this->getStrEsquemaCores() . '.jpg" title="Sistema Eletrônico de Informações"/>';
     if (($strComplemento = ConfiguracaoSEI::getInstance()->getValor('PaginaSEI', 'NomeSistemaComplemento',false))!=null){
       $strRet .= '<span class="infraTituloLogoSistema">'.$strComplemento.'</span>';
     }
     return $strRet;
   }

   public function getStrMenuSistema(){

     global $SEI_MODULOS;

     if (SessaoSEIExterna::getInstance()->isBolAcaoSemLogin()){
       return null;
     }

     if(self::$strMenu===null) {

       if ($this->getObjInfraSessao()->getNumIdUsuarioExterno() != null) {
         $arrMenu = array();
         $arrMenu[] = '-^controlador_externo.php?acao=usuario_externo_controle_acessos^^Controle de Acessos Externos^';
         $bolLoginExterno=false;
         //adicionando itens de menu externo definidos em ponto de extensao dos modulos
         //variavel global, declarada e inicializada na classe SEI.php
         //ver exemplo na pagina procedimento_controlar.php
         foreach ($SEI_MODULOS as $seiModulo) {
           if (($arrMenuIntegracao = $seiModulo->executar('montarMenuUsuarioExterno')) != null) {
             foreach ($arrMenuIntegracao as $strMenuIntegracao) {
               $arrMenu[] = $strMenuIntegracao;
             }
           }
           if ($seiModulo->executar('validarSeLoginExterno')) {
              $bolLoginExterno=true;
           }

         }

         if(!$bolLoginExterno)$arrMenu[] = '-^controlador_externo.php?acao=usuario_externo_alterar_senha^^Alterar Senha^';

         self::$strMenu = parent::montarSmartMenuArray($arrMenu);
       }
     }

     return self::$strMenu;
   }

   public function getArrStrAcoesSistema()
   {

     $arrStrAcoes = array();

     if (!SessaoSEIExterna::getInstance()->isBolAcaoSemLogin() && $this->getObjInfraSessao()->getNumIdUsuarioExterno() != null) {

       //$arrStrAcoes[] = parent::montarIdentificacaoUsuario();

       if ($this->getTipoPagina() == InfraPagina::$TIPO_PAGINA_COMPLETA) {
         $arrStrAcoes[] = '<a id="lnkInfraMenuSistema" target="_self" onclick="infraMenuSistemaEsquema();" title="Exibir/Ocultar Menu do Sistema" tabindex="' . $this->getProxTabBarraSistema() . '" style="font-size:1.3em;padding-right:.4em;">Menu</a>';
       }

       $arrStrAcoes[] = $this->montarLinkUsuario($this->getObjInfraSessao()->getStrSiglaUsuarioExterno(), $this->getObjInfraSessao()->getStrDescricaoOrgaoUsuarioExterno(), $this->getObjInfraSessao()->getStrNomeUsuarioExterno());
       $arrStrAcoes[] = parent::montarLinkSair(SessaoSEIExterna::getInstance()->assinarLink('controlador_externo.php?acao=usuario_externo_sair'));
     }
     return $arrStrAcoes;
   }

   public function getObjInfraSessao()
   {
     return SessaoSEIExterna::getInstance();
   }

   public function getObjInfraLog()
   {
     return LogSEI::getInstance();
     //return null;
   }

   public function abrirHead($strAtributos = '')
   {
     parent::abrirHead($strAtributos);
     echo '<link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />' . "\n";
   }

   public function montarLinkMenu()
   {
     return '';
   }

   public function montarBotaoVoltarExcecao()
   {
     return '';
   }

   public function montarBotaoFecharExcecao()
   {
     return '';
   }

   public function permitirXHTML()
   {
     return false;
   }

   public function gerarLinkLogin()
   {
     return 'processo_acesso_externo_consulta.php?';
   }

   public function getStrTextoBarraSuperior(){
     return $this->getObjInfraSessao()->getStrDescricaoOrgaoUsuarioExterno();
   }

   /*
   public function getDiretorioJavaScriptGlobal(){
     return '/infra/infra_js';
   }

   public function getDiretorioEsquemas(){
     return '/infra/infra_css/esquemas';
   }

   public function getDiretorioCssGlobal(){
     return '/infra/infra_css';
   }
   */

   public function adicionarJQuery(){
     return true;
   }

   public function obterTipoMenu(){
     return self::$MENU_SMART;
   }

   public function montarPaginaErro($strErro, $strDetalhes,$strTrace){

     $this->setTipoPagina(InfraPagina::$TIPO_PAGINA_SIMPLES);

     $this->montarDocType();
     $this->abrirHtml();
     $this->abrirHead();
     $this->montarMeta();
     $this->montarTitle($this->getStrNomeSistema());
     $this->montarStyle();
     $this->montarJavaScript();
     $this->fecharHead();
     $this->abrirBody('',false);
     $this->montarBarraLocalizacao('Erro');
     $this->montarBarraComandosSuperior(array());
     echo '<div id="divInfraExcecao" class="infraExcecao"><span class="infraExcecao">'.nl2br(self::tratarHTML($strErro)).'</span></div>'."\n";

     if (!$this->isBolProducao()){

       $this->abrirAreaDados();

       //Validar Permissao para ver detalhes
       echo '<div id="divInfraDetalhesExcecao" class="infraDetalhesExcecao">'.
           '<span class="infraDetalhesExcecao">';

       if ($strDetalhes!=''){
         echo '<br /><br /><b>Detalhes:</b><br />'.nl2br(self::tratarHTML(str_replace(',',', ',$strDetalhes)));
       }

       if ($strTrace!=''){
         $strTrace = str_replace("\\n","",$strTrace);
         echo '<br /><br /><b>Trilha de Processamento:</b><br />'.nl2br(self::tratarHTML(InfraString::limparParametrosPhp($strTrace)));
       }
       echo '</span>';
       //Deixa área de debug dentro da div para só mostrar ao clicar
       $this->montarAreaDebug();
       echo '</div>';
       $this->fecharAreaDados();

     }

     $this->fecharBody();
     $this->fecharHtml();
     die;
   }
 }
?>