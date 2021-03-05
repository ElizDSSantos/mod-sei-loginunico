<?
/*
* TRIBUNAL REGIONAL FEDERAL DA 4� REGI�O
*
* 02/05/2012 - criado por mga
*
*
*/

try {
  require_once dirname(__FILE__).'/SEI.php';

  session_start();

  //////////////////////////////////////////////////////////////////////////////
  InfraDebug::getInstance()->setBolLigado(false);
  InfraDebug::getInstance()->setBolDebugInfra(true);
  InfraDebug::getInstance()->limpar();
  //////////////////////////////////////////////////////////////////////////////

  SessaoSEIExterna::getInstance()->validarLink();
  
  SessaoSEIExterna::getInstance()->validarPermissao($_GET['acao']);
	
  PaginaSEIExterna::getInstance()->setTipoPagina(InfraPagina::$TIPO_PAGINA_SIMPLES);
  
  $strParametros = '';

  if (isset($_GET['id_acesso_externo'])){
    $strParametros .= '&id_acesso_externo='.$_GET['id_acesso_externo'];
  }

  if (isset($_GET['id_documento'])){
    $strParametros .= '&id_documento='.$_GET['id_documento'];
  }

  if (isset($_GET['controle'])){
    $strParametros .= '&controle='.$_GET['controle'];
  }

  $bolAssinaturaOK = false;

  $strDisplayCargoFuncao = '';

  switch($_GET['acao']) {

    case 'usuario_externo_assinar':

      $strTitulo = 'Assinatura de Documento';

      $objAcessoExternoDTO = new AcessoExternoDTO();
      $objAcessoExternoDTO->retDblIdProtocoloAtividade();
      $objAcessoExternoDTO->retNumIdUnidadeAtividade();
      $objAcessoExternoDTO->setNumIdAcessoExterno($_GET['id_acesso_externo']);
      $objAcessoExternoDTO->setStrStaTipo(AcessoExternoRN::$TA_ASSINATURA_EXTERNA);

      $objAcessoExternoRN = new AcessoExternoRN();
      $objAcessoExternoDTO = $objAcessoExternoRN->consultar($objAcessoExternoDTO);

      if ($objAcessoExternoDTO == null) {
        throw new InfraException('Registro de Acesso Externo n�o encontrado.');
      }

      $objDocumentoDTO = new DocumentoDTO();
      $objDocumentoDTO->retDblIdDocumento();
      $objDocumentoDTO->setDblIdDocumento($_GET['id_documento']);
      $objDocumentoDTO->setDblIdProcedimento($objAcessoExternoDTO->getDblIdProtocoloAtividade());

      $objDocumentoRN = new DocumentoRN();
      $objDocumentoDTO = $objDocumentoRN->consultarRN0005($objDocumentoDTO);

      if ($objDocumentoDTO == null) {
        throw new InfraException('Documento n�o encontrado.');
      }

      $objUsuarioDTO = new UsuarioDTO();
      $objUsuarioDTO->setNumIdUsuario(SessaoSEIExterna::getInstance()->getNumIdUsuarioExterno());

      $objUsuarioRN = new UsuarioRN();
      $arrCargoFuncao = InfraArray::converterArrInfraDTO($objUsuarioRN->listarCargoFuncao($objUsuarioDTO),'CargoFuncao');

      if (count($arrCargoFuncao) == 1) {
        $strDisplayCargoFuncao = 'display:none;';
        $strCargoFuncao = $arrCargoFuncao[0];
      } else {
        $strCargoFuncao = $_POST['selCargoFuncao'];
      }

      $objAssinaturaDTO = new AssinaturaDTO();
      $objAssinaturaDTO->setStrStaFormaAutenticacao(AssinaturaRN::$TA_SENHA);
      $objAssinaturaDTO->setNumIdUsuario(SessaoSEIExterna::getInstance()->getNumIdUsuarioExterno());
      $objAssinaturaDTO->setStrSenhaUsuario($_POST['pwdSenha']);
      $objAssinaturaDTO->setStrCargoFuncao($strCargoFuncao);
      $objAssinaturaDTO->setArrObjDocumentoDTO(array($objDocumentoDTO));

      if ($_POST['hdnFlag']=='1'){

        try{
          
          $numIdUnidadeAnterior = SessaoSEI::getInstance()->getNumIdUnidadeAtual();
          $numIdUsuarioAnterior = SessaoSEI::getInstance()->getNumIdUsuario();
          
          SessaoSEI::getInstance()->setNumIdUnidadeAtual($objAcessoExternoDTO->getNumIdUnidadeAtividade());
          SessaoSEI::getInstance()->setNumIdUsuario(SessaoSEIExterna::getInstance()->getNumIdUsuarioExterno());

          $objDocumentoRN = new DocumentoRN();
          $objDocumentoRN->assinar($objAssinaturaDTO);
          
          SessaoSEI::getInstance()->setNumIdUnidadeAtual($numIdUnidadeAnterior);
          SessaoSEI::getInstance()->setNumIdUsuario($numIdUsuarioAnterior);
          
          $bolAssinaturaOK = true;

        }catch(Exception $e){

          SessaoSEI::getInstance()->setNumIdUnidadeAtual($numIdUnidadeAnterior);
          SessaoSEI::getInstance()->setNumIdUsuario($numIdUsuarioAnterior);
          
          PaginaSEIExterna::getInstance()->processarExcecao($e, true);
        }
      }
      
      break;
      
    default:
      throw new InfraException("A��o '".$_GET['acao']."' n�o reconhecida.");
  }

  $arrComandos = array();
  
  $strDisplayAutenticacao = '';
  if ($bolAssinaturaOK){
    $strDisplayAutenticacao = 'display:none;';
  }

  $strItensSelCargoFuncao = AssinanteINT::montarSelectCargoFuncaoUnidadeUsuarioRI1344('null', '&nbsp;', $objAssinaturaDTO->getStrCargoFuncao(), SessaoSEIExterna::getInstance()->getNumIdUsuarioExterno());

}catch(Exception $e){ 
  PaginaSEIExterna::getInstance()->processarExcecao($e);
}

        $bolExisteAssinaturaExterna=false;
        foreach ($SEI_MODULOS as $seiModulo) {
          
        $boolMetodos=in_array('montarBotaoAssinaturaExterna',get_class_methods($seiModulo));
        try{
          if ($boolMetodos && ($arrRetIntegracao = $seiModulo->executar('montarBotaoAssinaturaExterna')) != null) { 
            $bolExisteAssinaturaExterna=true;
            echo $arrRetIntegracao;
          }
        }catch (Exception $e){}
        }    
   
PaginaSEIExterna::getInstance()->montarDocType();
PaginaSEIExterna::getInstance()->abrirHtml();
PaginaSEIExterna::getInstance()->abrirHead();
PaginaSEIExterna::getInstance()->montarMeta();
PaginaSEIExterna::getInstance()->montarTitle(PaginaSEIExterna::getInstance()->getStrNomeSistema().' - '.$strTitulo);
PaginaSEIExterna::getInstance()->montarStyle();
PaginaSEIExterna::getInstance()->abrirStyle();
?>

#lblUsuario {position:absolute;left:0%;top:0%;}
#txtUsuario {position:absolute;left:0%;top:40%;width:90%;}

#divCargoFuncao {<?=$strDisplayCargoFuncao?>}
#lblCargoFuncao {position:absolute;left:0%;top:0%;}
#selCargoFuncao {position:absolute;left:0%;top:40%;width:91%;}

#divAutenticacao {<?=$strDisplayAutenticacao?>}
#lblSenha {position:absolute;left:0%;top:0%;}
#pwdSenha {position:absolute;left:0%;top:40%;width:45%;}

<?
PaginaSEIExterna::getInstance()->fecharStyle();
PaginaSEIExterna::getInstance()->montarJavaScript();
PaginaSEIExterna::getInstance()->abrirJavaScript();
?>

var bolAssinandoSenha = false;

function inicializar(){
  
  //se realizou assinatura
  <? if ($bolAssinaturaOK){ ?>

      window.opener.location.reload();
      window.close();

  <? }else{ ?>
      
      document.getElementById('pwdSenha').focus();
      
  <? } ?>
}

function OnSubmitForm() {

  <? if ($strDisplayCargoFuncao==''){ ?>
  if (!infraSelectSelecionado(document.getElementById('selCargoFuncao'))){
    alert('Selecione um Cargo/Fun��o.');
    document.getElementById('selCargoFuncao').focus();
    return false;
  }
  <? } ?>

  if (infraTrim(document.getElementById('pwdSenha').value)==''){
    alert('Senha n�o informada.');
    return false;
  }

  return true;
}

function tratarSenha(obj, ev){
  if (infraGetCodigoTecla(ev)==13){
    submeter();
  }
  return true;
}

function submeter(){
  if (!bolAssinandoSenha && OnSubmitForm()){
    bolAssinandoSenha = true;
    document.getElementById('hdnFlag').value = '1';
    document.getElementById('frmAssinaturaUsuarioExterno').submit();
  }
}


<?
PaginaSEIExterna::getInstance()->fecharJavaScript();
PaginaSEIExterna::getInstance()->fecharHead();
PaginaSEIExterna::getInstance()->abrirBody($strTitulo,'onload="inicializar();"');
?>
<form id="frmAssinaturaUsuarioExterno" method="post" onsubmit="return OnSubmitForm();" action="<?=SessaoSEIExterna::getInstance()->assinarLink('controlador_externo.php?acao='.$_GET['acao'].'&acao_origem='.$_GET['acao'].$strParametros)?>">
 
	<?
	//PaginaSEIExterna::getInstance()->montarBarraLocalizacao($strTitulo);
	PaginaSEIExterna::getInstance()->montarBarraComandosSuperior($arrComandos);
	//PaginaSEIExterna::getInstance()->montarAreaValidacao();
  ?>	  
  <div id="divUsuario" class="infraAreaDados" style="height:5em;">
    <label id="lblUsuario" for="txtUsuario" accesskey="" class="infraLabelObrigatorio">Usu�rio Externo:</label>
    <input type="text" id="txtUsuario" name="txtUsuario" class="infraText" disabled="disabled" value="<?=PaginaSEIExterna::tratarHTML(SessaoSEIExterna::getInstance()->getStrSiglaUsuarioExterno())?>" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>" />
  </div>

  <div id="divCargoFuncao" class="infraAreaDados" style="height:4.5em;">
    <label id="lblCargoFuncao" for="selCargoFuncao" accesskey="F" class="infraLabelObrigatorio">Cargo / <span class="infraTeclaAtalho">F</span>un��o:</label>
    <select id="selCargoFuncao" name="selCargoFuncao" class="infraSelect" tabindex="<?=PaginaSEI::getInstance()->getProxTabDados()?>">
      <?=$strItensSelCargoFuncao?>
    </select>
  </div>

  <div id="divAutenticacao" class="infraAreaDados" style="height:5em;">
    <label id="lblSenha" for="pwdSenha" accesskey="" class="infraLabelRadio infraLabelObrigatorio" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>">Senha</label>&nbsp;&nbsp;
    <input type="password" id="pwdSenha" name="pwdSenha" autocomplete="off" class="infraText" onkeypress="return tratarSenha(this,event);" value="" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>" />
  </div>

  <button type="button" id="btnAssinar" name="btnAssinar" value="Assinar" class="infraButton" onclick="submeter()">Assinar</button>

	<?
  //PaginaSEIExterna::getInstance()->fecharAreaDados();
  PaginaSEIExterna::getInstance()->montarAreaTabela($strResultado,$numRegistros,true,'style="display:none;"');
	PaginaSEIExterna::getInstance()->montarAreaDebug();
	//PaginaSEIExterna::getInstance()->montarBarraComandosInferior($arrComandos);
  ?>
  <input type="hidden" id="hdnFlag" name="hdnFlag" value="0" />
</form>
<?
PaginaSEIExterna::getInstance()->fecharBody();
PaginaSEIExterna::getInstance()->fecharHtml();
?>