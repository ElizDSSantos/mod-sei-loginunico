<?php
try {
    require_once dirname(__FILE__).'/../../../SEI.php';
    
    session_start();
    $strDominio = "usuario_externo";

    InfraDebug::getInstance()->setBolLigado(false);
    InfraDebug::getInstance()->setBolDebugInfra(false);
    InfraDebug::getInstance()->limpar();

    PaginaSEIExterna::getInstance()->setTipoPagina(PaginaSEIExterna::$TIPO_PAGINA_SEM_MENU);

  } catch (Exception $e) {
    PaginaSEIExterna::getInstance()->processarExcecao($e);
  }

  if (isset($_POST['sbmVincular'])) {
    $objLoginUnico->validaUser(md5($_POST['txtSenha']), $user->getStrSenha());
  }

    PaginaSEIExterna::getInstance()->montarDocType();
    PaginaSEIExterna::getInstance()->abrirHtml();
    PaginaSEIExterna::getInstance()->abrirHead();
    PaginaSEIExterna::getInstance()->montarMeta();
    PaginaSEIExterna::getInstance()->montarTitle(PaginaSEIExterna::getInstance()->getStrNomeSistema().' - '.$strTitulo);
    PaginaSEIExterna::getInstance()->montarStyle();
    PaginaSEIExterna::getInstance()->adicionarStyle('modulos/'. NOME_MODULO_LOGIN_UNICO. '/css/style.css');
    PaginaSEIExterna::getInstance()->montarJavaScript();
    PaginaSEIExterna::getInstance()->adicionarJavaScript('modulos/' . NOME_MODULO_LOGIN_UNICO . '/js/jquery.mask.js');
    PaginaSEIExterna::getInstance()->abrirJavaScript();
?>
function verificaDadosUsuario(){
  if(!$('#txtSenha').val()){
    alert('Informe a senha.');
    $('#txtSenha').focus();
    return false;
  }
  return true;
}
<?php
PaginaSEIExterna::getInstance()->fecharJavaScript();
PaginaSEIExterna::getInstance()->fecharHead();
PaginaSEIExterna::getInstance()->abrirBody($strTitulo);
?>

<form id="formAssociar" method="post" onsubmit="return verificaDadosUsuario();" action="<?=SessaoSEIExterna::getInstance()->assinarLink('controlador_externo.php?acao=usuario_loginunico_associar&lang='.$locale)?>">
<div class="formularioTexto mt-3 mb-2">
Prezado <?= $user->getStrNome() ?>,
<br><br>
Foi identificada uma conta de usuário externo do SEI vinculada ao e-mail <?= $user->getStrSigla() ?>. Com isso, será necessário realizar a unificação desta conta com o Acesso Único do Governo Federal. 
<br><br>
Por favor, confirme seus dados de autenticação para concluir a operação.
</div>
<div class="row-externo mb-1">
  <div class="coluna-externo-md auxiliar">
    <div class="row-externo mb-1">
      <div class="coluna-int">
        <label id="lblSenha" for="txtSenha" accesskey="" class="infraLabelObrigatorio"><?=_("Senha atual do usuário SEI:")?></label>
      </div>
      <div class="coluna">
        <div class="colunaP" width="20px">
          <div class="fieldImg" style="width: 20px; margin-left: 0;" 
            onmouseover="return infraTooltipMostrar('Senha do usuário externo cadastrado previamente no SEI', '', 'auto');"
            onmouseout="return infraTooltipOcultar();"><img id="imgAjuda"
            src="<?= PaginaSEI::getInstance()->getDiretorioImagensGlobal(); ?>/ajuda.gif" />
          </div>
        </div>
      </div>
    </div>
    <input type="password" id="txtSenha" name="txtSenha"  maxlength="15" class="infraText"  value=""  style="width: 40%;" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>"/>
  </div>
</div>
<div class="row-externo mb-1">
  <button type="submit" accesskey="" id="sbmVincular" class="infraButton bt" name="sbmVincular" value="Vincular" title="Vincular" ><?=_("Vincular Conta")?></button>
  <button type="button" accesskey="" id="btnVoltar" name="btnVoltar" value="Cancelar" onclick="location.href='<?=SessaoSEIExterna::getInstance()->assinarLink('controlador_externo.php?acao=usuario_externo_sair&acao_origem='.$_GET['acao'])?>';" class="infraButton bt"><?=_("Cancelar")?></button>
</div>
</form>

<?php
PaginaSEIExterna::getInstance()->montarAreaDebug();
PaginaSEIExterna::getInstance()->fecharBody();
PaginaSEIExterna::getInstance()->fecharHtml();
