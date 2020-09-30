<?
/**
* TRIBUNAL REGIONAL FEDERAL DA 4ª REGIÃO
*
* 10/06/2010 - criado por fazenda_db
*
*
*
* Versão no CVS: $Id$
*/

try {
  require_once dirname(__FILE__).'/SEI.php';

  session_start();
 
  //////////////////////////////////////////////////////////////////////////////
  InfraDebug::getInstance()->setBolLigado(false);
  InfraDebug::getInstance()->setBolDebugInfra(false);
  InfraDebug::getInstance()->limpar();
  //////////////////////////////////////////////////////////////////////////////

  SessaoSEIExterna::getInstance()->validarLink();

  PaginaSEIExterna::getInstance()->setTipoPagina(PaginaSEIExterna::$TIPO_PAGINA_SEM_MENU);

  $numLoginSemCaptcha = ConfiguracaoSEI::getInstance()->getValor('SEI', 'NumLoginUsuarioExternoSemCaptcha', false, 3);

  if (!isset($_SESSION['EXTERNO_NUM_FALHA_LOGIN'])){
    $_SESSION['EXTERNO_NUM_FALHA_LOGIN'] = 0;
  }

  if (!isset($_SESSION['EXTERNO_TOKEN'])){
    $_SESSION['EXTERNO_TOKEN'] = '';
  }

  switch($_GET['acao']){
    
      case 'usuario_externo_logar':
  
        $strTitulo = 'Acesso Externo';

        $strCaptchaPesquisa = PaginaSEIExterna::getInstance()->recuperarCampo('captcha');
        $strCodigoParaGeracaoCaptcha = InfraCaptcha::obterCodigo();
        PaginaSEIExterna::getInstance()->salvarCampo('captcha', hash('SHA512',InfraCaptcha::gerar($strCodigoParaGeracaoCaptcha)));

        $strToken = $_SESSION['EXTERNO_TOKEN'];
        $_SESSION['EXTERNO_TOKEN'] = md5(uniqid(mt_rand()));

        if (isset($_POST['sbmLogin'])) {
          try {

            $objInfraException = new InfraException();

            if ($strToken != $_POST['hdnToken']) {
              $objInfraException->lancarValidacao('Erro processando dados do formulário.');
            }

            if ($_SESSION['EXTERNO_NUM_FALHA_LOGIN'] >= $numLoginSemCaptcha && hash('SHA512', $_POST['txtCaptcha']) != $strCaptchaPesquisa) {
              $objInfraException->lancarValidacao('Código de confirmação inválido.');
            } else {

              $objUsuarioDTO = new UsuarioDTO();
              $objUsuarioDTO->setStrSigla($_POST['txtEmail']);
              SessaoSEIExterna::getInstance()->logar($objUsuarioDTO);
              $_SESSION['EXTERNO_NUM_FALHA_LOGIN'] = 0;
              header('Location: ' . SessaoSEIExterna::getInstance()->assinarLink('controlador_externo.php?acao=usuario_externo_controle_acessos&acao_origem=' . $_GET['acao']));
              die;
            }
          } catch (Exception $e) {
            if (strpos($e->__toString(), InfraLDAP::$MSG_USUARIO_SENHA_INVALIDA) !== false) {
              $_SESSION['EXTERNO_NUM_FALHA_LOGIN'] = $_SESSION['EXTERNO_NUM_FALHA_LOGIN'] + 1;
            }
            PaginaSEIExterna::getInstance()->processarExcecao($e, true);
          }
        }
        break;
        
     default:
       throw new InfraException("Ação '".$_GET['acao']."' não reconhecida.");
  }

  $strLarguraAutenticacao = '45em';
  $strDisplayCaptcha = 'display:none;';
  $strLarguraDivUsuario = '60%';
  if ($_SESSION['EXTERNO_NUM_FALHA_LOGIN'] >= $numLoginSemCaptcha){
    $strLarguraAutenticacao = '60em';
    $strLarguraDivUsuario = '45%';
    $strDisplayCaptcha = '';
  }

}catch(Exception $e){
  PaginaSEIExterna::getInstance()->processarExcecao($e);
}

PaginaSEIExterna::getInstance()->montarDocType();
PaginaSEIExterna::getInstance()->abrirHtml();
PaginaSEIExterna::getInstance()->abrirHead();
PaginaSEIExterna::getInstance()->montarMeta();
PaginaSEIExterna::getInstance()->montarTitle(PaginaSEIExterna::getInstance()->getStrNomeSistema().' - '.$strTitulo);
PaginaSEIExterna::getInstance()->montarStyle();
PaginaSEIExterna::getInstance()->abrirStyle();
?>

#divInfraAreaGlobal {background-color: #f1f1f1 !important;}
div.infraBarraSistemaE {width:90%}
div.infraBarraSistemaD {width:5%}
div.infraAreaTelaD {border:0;}
div.infraAreaDados {border:0}

#divAutenticacao {
  position:absolute;
  top:5%;
  left:29%;
  height:200px;
  width:<?=$strLarguraAutenticacao?>;
  border:1px solid #666;
  overflow:hidden;
  background-color: white !important; 
}
 
#divSistema {
  border:0px solid red;
  float:left;
  width:150px;
  height:200px;
  padding:0;
  margin:0;
  overflow:hidden !important;
  background-color: white;
  border-right:1px solid #666;
}
 
#lblSiglaSistemaValor {font-size:1.6em;font-weight:bold;}

#divUsuario {border:0px solid red;float:left;height:90%;width:<?=$strLarguraDivUsuario?>;padding:.4em .4em .4em 1.5em;}
#divUsuario label {display:block;padding:.2em 0 .01em 0;}
#divUsuario input {display:block;}
#divUsuario button {margin-top:2em;}
#divUsuario a {display:block;margin-top:1em;}

#divAviso {width:100%;padding:1em 0;text-align:center;border:0px solid yellow;}
#spnAviso {font-weight:bold;font-size:1.2em;}
 
#txtEmail {width:95%}
#pwdSenha {width:95%}

#divCaptcha {<?=$strDisplayCaptcha?>}
#divCaptcha {border:0px solid blue;height:80%;float:left;width:14em;padding:.4em;border-left:1px;}

#divCaptcha label {display:block;}
#divCaptcha input {display:block}

#lblCodigo  {padding:0em 0 .01em 0;}
#lblCaptcha {padding-top:2.3em}
#txtCaptcha {font-size:2.8em;text-align:center;}

<? if (PaginaSEIExterna::getInstance()->getNumVersaoSafariIpad()==null && PaginaSEIExterna::getInstance()->getNumVersaoSafari()==null) { ?>
  #txtCaptcha {width:4.8em !important;}
<? }else{ ?>
  #txtCaptcha {width:3.8em !important;}
<? } ?>


<?
PaginaSEIExterna::getInstance()->fecharStyle();
PaginaSEIExterna::getInstance()->montarJavaScript();
PaginaSEIExterna::getInstance()->abrirJavaScript();
?>

function posicionar(){
  
  var fator = 1.7;
  
  if (INFRA_IE && INFRA_IE < 8){
    fator = 2.2;
  }
    
  var hDados = (infraClientHeight()-(document.getElementById('divInfraBarraSuperior').offsetHeight+document.getElementById('divInfraBarraSistema').offsetHeight)*fator);
  
  if (hDados > 0){
    document.getElementById('divInfraAreaDados').style.height = hDados + 'px';
  
    var f = document.getElementById('divAutenticacao');
    
    var p = (infraClientWidth()-f.offsetWidth)/2;
    f.style.left = (p>0?p:1) + 'px'; 
    
    p = (hDados-f.offsetHeight)/2.3;
    f.style.top = (p>0?p:1) + 'px'; 
  }
}

function inicializar(){
  if (infraTrim(document.getElementById('txtEmail').value)==''){
    document.getElementById('txtEmail').focus();
  }else{
    document.getElementById('pwdSenha').focus();
  }
  
  infraAdicionarEvento(window,'resize',posicionar);
	posicionar();
}

function OnSubmitForm() {
  return validarForm();
}

function validarForm() {

  if (infraTrim(document.getElementById('txtEmail').value)=='') {
    alert('Informe o E-mail.');
    document.getElementById('txtEmail').focus();
    return false;
  }
  
  if (!infraValidarEmail(infraTrim(document.getElementById('txtEmail').value))){
		alert('E-mail Inválido.');
		document.getElementById('txtEmail').focus();
		return false;
	}

  if (infraTrim(document.getElementById('pwdSenha').value)=='') {
    alert('Informe a Senha.');
    document.getElementById('pwdSenha').focus();
    return false;
  }

<? if ($_SESSION['EXTERNO_NUM_FALHA_LOGIN'] >= $numLoginSemCaptcha){ ?>
  if (infraTrim(document.getElementById('txtCaptcha').value)=='') {
  alert('Informe o código de confirmação.');
  document.getElementById('txtCaptcha').focus();
  return false;
  }
<? } ?>

  return true;
}
<?
PaginaSEIExterna::getInstance()->fecharJavaScript();
PaginaSEIExterna::getInstance()->fecharHead();
PaginaSEIExterna::getInstance()->abrirBody('','onload="inicializar();"');
PaginaSEIExterna::getInstance()->abrirAreaDados('50em');
?>
<form id="frmLogin" method="post" onsubmit="return OnSubmitForm();" action="<?=SessaoSEIExterna::getInstance()->assinarLink('controlador_externo.php?acao='.$_GET['acao'].'&acao_origem='.$_GET['acao'])?>">
  
	<div id="divAutenticacao">
	  <div id="divSistema">
	    <img id="imgLogoSei" src="imagens/sei_logo_login_externo.png" title="Sistema Eletrônico de Informações"/>
	  </div>
	  <div id="divUsuario">
	  <div id="divAviso">
  	  <span id="spnAviso">Acesso para Usuários Externos</span>
  	</div>  
    <label id="lblEmail" for="txtEmail" accesskey="" class="infraLabelObrigatorio">E-mail:</label>
    <input type="email" id="txtEmail" name="txtEmail" class="infraText" value="<?=PaginaSEIExterna::tratarHTML($_POST['txtEmail'])?>" maxlength="100" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>" />

    <label id="lblSenha" for="pwdSenha" accesskey="" class="infraLabelObrigatorio">Senha:</label>
    <input type="password" id="pwdSenha" name="pwdSenha" autocomplete="off" class="infraText" value="<?=PaginaSEIExterna::tratarHTML($_POST['pwdSenha'])?>" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>" />

    <button type="submit" name="sbmLogin" id="sbmLogin"  accesskey="C" class="infraButton" value="Confirma" title="Confirma">&nbsp;&nbsp;<span class="infraTeclaAtalho">C</span>onfirma&nbsp;&nbsp;</button>
    &nbsp;&nbsp;&nbsp;
    <button type="button" name="btnEsqueci" id="btnEsqueci" onclick="location.href='<?=SessaoSEIExterna::getInstance()->assinarLink('controlador_externo.php?acao=usuario_externo_gerar_senha')?>'"  accesskey="E" class="infraButton" value="Esqueci minha senha" title="Esqueci minha senha">&nbsp;&nbsp;<span class="infraTeclaAtalho">E</span>squeci minha senha&nbsp;&nbsp;</button>
    <a id="lnkCadastro" href="<?=SessaoSEIExterna::getInstance()->assinarLink('controlador_externo.php?acao=usuario_externo_avisar_cadastro')?>">Clique aqui se você ainda não está cadastrado</a>
        <?php
        foreach ($SEI_MODULOS as $seiModulo) {
            if (($arrRetIntegracao = $seiModulo->executar('montarBotaoAutenticacaoExterna')) != null) {
                echo $arrRetIntegracao;
            }
        }
        ?>
    </div>
    <div id="divCaptcha">
      <? if ($_SESSION['EXTERNO_NUM_FALHA_LOGIN'] >= $numLoginSemCaptcha){ ?>
        <label id="lblCaptcha" accesskey="" class="infraLabelObrigatorio"><img src="/infra_js/infra_gerar_captcha.php?codetorandom=<?=$strCodigoParaGeracaoCaptcha;?>" alt="Não foi possível carregar a imagem de confirmação" /></label>
        <label id="lblCodigo" for="txtCaptcha" accesskey="" class="infraLabelObrigatorio">Código de confirmação:</label>
        <input type="text" id="txtCaptcha" name="txtCaptcha" class="infraText" maxlength="4" value="" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>"/>
      <? } ?>
    </div>
  </div>
  <input type="hidden" id="hdnToken" name="hdnToken" value="<?=$_SESSION['EXTERNO_TOKEN']?>"/>
</form>
<?
PaginaSEIExterna::getInstance()->fecharAreaDados();
PaginaSEIExterna::getInstance()->montarAreaDebug();
PaginaSEIExterna::getInstance()->fecharBody();
PaginaSEIExterna::getInstance()->fecharHtml();
?>