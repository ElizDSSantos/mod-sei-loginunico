<?php
try {
    require_once dirname(__FILE__).'/../../../SEI.php';

    session_start();

    $strDominio = "usuario_externo";

    InfraDebug::getInstance()->setBolLigado(false);
    InfraDebug::getInstance()->setBolDebugInfra(false);
    InfraDebug::getInstance()->limpar();

    $token = SessaoSEIExterna::getInstance()->getAtributo('MD_LOGIN_UNICO_TOKEN');
    $objLoginUnico = new LoginControladorRN();
    $user = $objLoginUnico->pesquisarUsuario($token)['user'];
    $dadosReceita =  SessaoSEIExterna::getInstance()->getAtributo('MD_LOGIN_UNICO_TOKEN_ENDERECO');
   
    $tel = $user->getStrTelefoneResidencialContato();
    $cel = $user->getStrTelefoneCelularContato();
    $readCel = '';
    $readTel = '';
    if (strlen($token['phone_number']) < 11) {
        $tel = $token['phone_number'];
        $readTel = 'readonly';
    } else {
        $cel = $token['phone_number'];
        $readCel = 'readonly';
    }

    $numTamSenhaUsuarioExterno = ConfiguracaoSEI::getInstance()->getValor('SEI', 'TamSenhaUsuarioExterno', false, TAM_SENHA_USUARIO_EXTERNO);

    PaginaSEIExterna::getInstance()->setTipoPagina(PaginaSEIExterna::$TIPO_PAGINA_SEM_MENU);
    PaginaSEIExterna::getInstance()->salvarCamposPost(array('selUf','selCidade'));

    switch ($_GET['acao']) {
      case 'usuario_loginunico_aceite':
        $strTitulo = 'Cadastro de Usuário Externo';

        $strDisplayMensagem = '';
        $strDisplayCadastro = 'display:none;';

        $objInfraParametro = new InfraParametro(BancoSEI::getInstance());
        $strTextoFormulario = $objInfraParametro->getValor('SEI_MSG_AVISO_CADASTRO_USUARIO_EXTERNO');

        if ($strTextoFormulario==''){
          header('Location: '.SessaoSEIExterna::getInstance()->assinarLink('controlador_externo.php?acao=usuario_loginunico_atualizar&id_orgao_acesso_externo='.$_GET['id_orgao_acesso_externo']));
          die;
        }

        $strTextoFormulario .= '<br /><br /><a id="lnkCadastro" href="'.SessaoSEIExterna::getInstance()->assinarLink('controlador_externo.php?acao=usuario_loginunico_atualizar&acao_origem='.$_GET['acao']).'">Clique aqui para continuar</a>';
      break;

      case 'usuario_loginunico_atualizar':
        $strTitulo = _('Atualização de Usuário Externo - Login Único');
        $strDisplayMensagem = 'display:none;';
        $strDisplayCadastro = '';

        if($dadosReceita['nomePaisExterior']){
          $idPaisExterior = $objLoginUnico->pesquisarPais($dadosReceita['nomePaisExterior']);
        }else{
          $idsCidadeUf = $objLoginUnico->convertDadoTokenSei($dadosReceita);
          $idUfToken = $idsCidadeUf['iduf'];
          $idCidadeToken = $idsCidadeUf['idcidade'];
        }
        
        $strCaptchaPesquisa = PaginaSEIExterna::getInstance()->recuperarCampo('captcha');
        $strCodigoParaGeracaoCaptcha = InfraCaptcha::obterCodigo();
        PaginaSEIExterna::getInstance()->salvarCampo('captcha', hash('SHA512', InfraCaptcha::gerar($strCodigoParaGeracaoCaptcha)));

        if (isset($_POST['sbmEnviar'])) {
            if (hash('SHA512', $_POST['txtCaptcha']) != $strCaptchaPesquisa) {
                PaginaSEIExterna::getInstance()->setStrMensagem(_('Código de confirmação inválido.'));
            } else {
                try {
                    extract($_POST);
                    if($txtEmail!=$token['email']){
                      throw new InfraException('E-mail enviado para cadastro diferente do registrado GovBR');
                    }

                    if($txtCpf!=$token['sub']){
                      throw new InfraException('CPF enviado para cadastro diferente do registrado GovBR');
                    }

                    if($txtNome!=$token['name']){
                      throw new InfraException('Nome enviado para cadastro diferente do registrado GovBR');
                    }

                    $timestamp=time();

                    if($timestamp >= $token['exp']){
                      throw new InfraException('Token de Login do GovBR Expirado, faça login novamente no GovBR');
                  }

                    $idx = preg_replace('/\W/', "", $token['email']) . " " . strtolower(InfraString::excluirAcentos(utf8_decode($token['name'])));

                    if (!BancoSEI::getInstance()->getIdConexao()) {
                        BancoSEI::getInstance()->abrirConexao();
                    }
    
                    BancoSEI::getInstance()->abrirTransacao();

                    $txtTelefoneCelular = InfraUtil::retirarFormatacao($txtTelefoneCelular);
                    $txtTelefoneFixo = InfraUtil::retirarFormatacao($txtTelefoneFixo);
                    $txtCep = InfraUtil::retirarFormatacao($txtCep);
                    $txtCpf = InfraUtil::retirarFormatacao($txtCpf);
                    $sinSelo = SessaoSEIExterna::getInstance()->getAtributo('MD_LOGIN_UNICO_SIN_NIVEL') ? 'S' : 'N';
                    $idContato = SessaoSEIExterna::getInstance()->getAtributo('ID_CONTATO_USUARIO_EXTERNO');
                    $staTipoUsuarioExterno = ($sinSelo == 'S') ? UsuarioRN::$TU_EXTERNO : UsuarioRN::$TU_EXTERNO_PENDENTE;

                    


                    $objContatoDTO=new ContatoDTO();
                    $objContatoDTO->setStrNome($txtNome);
                    $objContatoDTO->setDblCpf($txtCpf);
                    $objContatoDTO->setDblRg($txtRg);
                    $objContatoDTO->setStrOrgaoExpedidor($txtExpedidor);
                    $objContatoDTO->setStrTelefoneResidencial($txtTelefoneFixo);
                    $objContatoDTO->setStrTelefoneCelular($txtTelefoneCelular);
                    $objContatoDTO->setStrEndereco($txtEndereco);
                    $objContatoDTO->setStrComplemento($txtComplemento);
                    $objContatoDTO->setStrBairro($txtBairro);
                    $objContatoDTO->setNumIdPais($selPais);
                    $objContatoDTO->setNumIdUf($selUf);
                    $objContatoDTO->setNumIdCidade($selCidade);
                    $objContatoDTO->setStrCep($txtCep);
                    $objContatoDTO->setStrEmail($txtEmail);
                    $objContatoDTO->setStrSigla($txtEmail);
                    $objContatoDTO->setStrNomeRegistroCivil($txtNome);
                    $objContatoDTO->setStrSinAtivo($sinSelo);
                    $objContatoDTO->setNumIdContatoAssociado($idContato);
                    $objContatoDTO->setNumIdContato($idContato);

                    $objContatoBD = new ContatoBD(BancoSEI::getInstance());
                    $objContatoBD->alterar($objContatoDTO);
                    

               

                $objUsuarioDTO=new UsuarioDTO();
                $objUsuarioDTO->setStrSigla($txtEmail);
                $objUsuarioDTO->setStrNome($txtNome);
                $objUsuarioDTO->setStrIdxUsuario($idx);
                $objUsuarioDTO->setStrNomeRegistroCivil($txtNome);
                $objUsuarioDTO->setStrSinAtivo("S");
                $objUsuarioDTO->setStrStaTipo($staTipoUsuarioExterno);
                
                $bcrypt = new InfraBcrypt();
                $objUsuarioDTO->setStrSenha($bcrypt->hash(md5($_POST['txtSenha'])));

                $objUsuarioDTO->setNumIdUsuario(SessaoSEIExterna::getInstance()->getAtributo('ID_USUARIO_EXTERNO'));

                $objUsuarioBD = new UsuarioBD(BancoSEI::getInstance());
                $objUsuarioBD->alterar($objUsuarioDTO);




                    $seqUserLogin = SessaoSEIExterna::getInstance()->getAtributo('ID_USUARIO_LOGIN');
                    $seqUsuario = SessaoSEIExterna::getInstance()->getAtributo('ID_USUARIO_EXTERNO');
                    
                    $updateDate = InfraData::getStrDataHoraAtual();

             


                $objLoginUnicoDTO=new LoginUnicoDTO();
                $objLoginUnicoDTO->setNumIdLogin($seqUserLogin);
                $objLoginUnicoDTO->setNumIdUsuario($seqUsuario);
                $objLoginUnicoDTO->setDthDataAtualizacao($updateDate);
                $objLoginUnicoDTO->setDblCpfContato($txtCpf);
                $objLoginUnicoDTO->setStrEmail($txtEmail);

                $objLoginUnicoBD = new LoginUnicoBD(BancoSEI::getInstance());
                $objLoginUnicoBD->cadastrar($objLoginUnicoDTO);



    
                    BancoSEI::getInstance()->confirmarTransacao();
                    BancoSEI::getInstance()->fecharConexao();
                } catch (Exception $e) {
                    try {
                        BancoSEI::getInstance()->cancelarTransacao();
                    } catch (Exception $e2) {
                    }
                  
                    try {
                        BancoSEI::getInstance()->fecharConexao();
                    } catch (Exception $e2) {
                    }
                  
                    throw new InfraException('Erro cadastrando usuário externo (LoginUnico).', $e);
                }

                $objOrgaoDTO = new OrgaoDTO();
                $objOrgaoDTO->retTodos();
                $objOrgaoDTO->setNumIdOrgao(SessaoSEIExterna::getInstance()->getAtributo('ID_ORGAO_USUARIO_EXTERNO'));

                $objOrgaoRN = new OrgaoRN();
                $objOrgaoDTO = $objOrgaoRN->consultarRN1352($objOrgaoDTO);

                if ($objOrgaoDTO == null) {
                  throw new InfraException('Órgão não encontrado [' . $objUsuarioDTO->getNumIdOrgao() . '].');
                }

                $objInfraParametro = new InfraParametro(BancoSEI::getInstance());
                $objEmailSistemaDTO = new EmailSistemaDTO();
                $objEmailSistemaDTO->retStrDe();
                $objEmailSistemaDTO->retStrPara();
                $objEmailSistemaDTO->retStrAssunto();
                $objEmailSistemaDTO->retStrConteudo();

                $action = 'controlador_externo.php?acao=usuario_externo_sair';
                if (SessaoSEIExterna::getInstance()->getAtributo('MD_LOGIN_UNICO_SIN_NIVEL') == 'S') {         
                  $objEmailSistemaDTO->setStrIdEmailSistemaModulo('MD_LOGINUNICO_CADASTRO_USUARIO');
                  $action = 'controlador_externo.php?acao=usuario_externo_controle_acessos';
                } else {
                  $objEmailSistemaDTO->setNumIdEmailSistema(EmailSistemaRN::$ES_CADASTRO_USUARIO_EXTERNO);
                }
                
                $objEmailSistemaRN = new EmailSistemaRN();
                $objEmailSistemaDTO = $objEmailSistemaRN->consultar($objEmailSistemaDTO);

                if($objEmailSistemaDTO){
                  $strDe = $objEmailSistemaDTO->getStrDe();
                  $strDe = str_replace('@sigla_sistema@',SessaoSEI::getInstance()->getStrSiglaSistema(),$strDe);
                  $strDe = str_replace('@email_sistema@',$objInfraParametro->getValor('SEI_EMAIL_SISTEMA'),$strDe);

                  $strPara = $objEmailSistemaDTO->getStrPara();
                  $strPara = str_replace('@email_usuario_externo@',$txtEmail,$strPara);
                  
                  $strAssunto = $objEmailSistemaDTO->getStrAssunto();
                  $strAssunto = str_replace('@sigla_sistema@',SessaoSEI::getInstance()->getStrSiglaSistema(),$strAssunto);
                  $strAssunto = str_replace('@sigla_orgao@',$objOrgaoDTO->getStrSigla(),$strAssunto);

                  $strConteudo = $objEmailSistemaDTO->getStrConteudo();
                  $strConteudo = str_replace('@nome_usuario_externo@',$txtNome,$strConteudo);
                  $strConteudo = str_replace('@email_usuario_externo@',$objOrgaoDTO->getStrSigla(),$strConteudo);
                  $strConteudo = str_replace('@link_login_usuario_externo@',ConfiguracaoSEI::getInstance()->getValor('SEI','URL').'/controlador_externo.php?acao=usuario_externo_logar&id_orgao_acesso_externo='.$objOrgaoDTO->getNumIdOrgao(),$strConteudo);
                  $strConteudo = str_replace('@sigla_orgao@',$objOrgaoDTO->getStrSigla(),$strConteudo);
                  $strConteudo = str_replace('@descricao_orgao@',$objOrgaoDTO->getStrDescricao(),$strConteudo);

                  $site = $objOrgaoDTO->isSetStrSitioInternetContato() ? $objOrgaoDTO->getStrSitioInternetContato() : '';
                  $strConteudo = str_replace('@sitio_internet_orgao@',$ite,$strConteudo);

                  $objEmailDTO = new EmailDTO();
                  $objEmailDTO->setStrDe($strDe);
                  $objEmailDTO->setStrPara($strPara);
                  $objEmailDTO->setStrAssunto($strAssunto);
                  $objEmailDTO->setStrMensagem($strConteudo);

                  EmailRN::processar(array($objEmailDTO));
                  
                if ($sinSelo == 'N') {
                  PaginaSEIExterna::getInstance()->adicionarMensagem(_('IMPORTANTE: As instruções para ativar o seu cadastro foram encaminhadas para o seu e-mail.'));
                }
              }

                header('Location: ' . SessaoSEIExterna::getInstance()->assinarLink($action.'&id_orgao_acesso_externo='.$_GET['id_orgao_acesso_externo']));
                die;
            }
        }

        $strItensSelUf = UfINT::montarSelectSiglaRI0416('null', '&nbsp;', $idUfToken ? $idUfToken : $user->getNumIdUfContato());
        $strLinkAjaxCidade = SessaoSEIExterna::getInstance()->assinarLink('controlador_ajax_externo.php?acao_ajax=cidade_montar_select_id_cidade_nome');
        $strItensSelCidade = CidadeINT::montarSelectIdCidadeNome('null', '&nbsp;', ($idCidadeToken ? $idCidadeToken : $user->getNumIdCidadeContato()), ($idUfToken ? $idUfToken : $user->getNumIdUfContato()), (isset($idPaisExterior) ? $idPaisExterior : ID_BRASIL));
        $strItensSelPaisPassaporte = PaisINT::montarSelectNome('null', '&nbsp', $_POST['selPaisPassaporte']);
        $strItensSelPais = PaisINT::montarSelectNome('null', '&nbsp', (isset($idPaisExterior) ? $idPaisExterior : ID_BRASIL));
        break;
        
        case 'usuario_loginunico_associar':
          $strTitulo = 'Associar Conta';
          require_once ('form_associar_contas.php');
          die;
        break;

      default:
        throw new InfraException(_("Ação '").$_GET['acao']._("' não reconhecida."));
    }
      $strTitulo = 'Cadastro de Usuário Externo - Login Único';
} catch (Exception $e) {
    PaginaSEIExterna::getInstance()->processarExcecao($e);
}

PaginaSEIExterna::getInstance()->montarDocType();
PaginaSEIExterna::getInstance()->abrirHtml();
PaginaSEIExterna::getInstance()->abrirHead();
PaginaSEIExterna::getInstance()->montarMeta();
PaginaSEIExterna::getInstance()->montarTitle(PaginaSEIExterna::getInstance()->getStrNomeSistema().' - '.$strTitulo);
PaginaSEIExterna::getInstance()->montarStyle();
PaginaSEIExterna::getInstance()->adicionarStyle('modulos/'. NOME_MODULO_LOGIN_UNICO. '/css/style.css');
PaginaSEIExterna::getInstance()->montarJavaScript();
PaginaSEIExterna::getInstance()->adicionarJavaScript('modulos/'. NOME_MODULO_LOGIN_UNICO . '/js/jquery.mask.js');
PaginaSEIExterna::getInstance()->abrirJavaScript();
?>

<?if(0){?><script><?}?>
function inicializar(){

  <?if ($_GET['acao']=='usuario_externo_enviar_cadastro'){?>
    document.getElementById('txtNome').focus();
  <?}?>

  <? if(!$dadosReceita['municipio'] && !$user->getNumIdCidadeContato()){ ?>
    $("#selCidade") ? limpaCampo("#selCidade") : "";
    $("#txtCidade") ? limpaCampo("#txtCidade") : "";
  <? } else if (!$dadosReceita['municipio'] && $user->getNumIdCidadeContato()){?>
    $("#selCidade") ? $("#selCidade").val(<? $user->getNumIdCidadeContato() ?>) : "";
    $("#txtCidade") ? $("#txtCidade").val(<? $user->getNumIdCidadeContato() ?>) : "";
  <? } ?>

  <? if(!$dadosReceita['uf'] && !$user->getNumIdUfContato()){ ?>
    $("#selUf") ? limpaCampo("#selUf") : "";
    $("#txtUf") ? limpaCampo("#txtUf") : "";
  <? } ?>
  
function limpaCampo(idCampo){
  $(idCampo).val("");
}

<? if($idPaisExterior){ ?>
  $("#chkSinEstrangeiro").attr("checked", true);
  $("#chkSinEstrangeiro").attr("disabled", true);
  $("#txtNumeroPassaporte").show();
  $("#selPaisPassaporte").show();
<? } ?>

  objAjaxCidade = new infraAjaxMontarSelectDependente('selUf','selCidade','<?=$strLinkAjaxCidade?>');
  objAjaxCidade.prepararExecucao = function(){
    return infraAjaxMontarPostPadraoSelect('null','','null') + '&idUf='+document.getElementById('selUf').value;
  }
  objAjaxCidade.processarResultado = function(){
  }

  infraEfeitoTabelas();

  <?php
  if ($locale == 'en_US' && empty($_POST)) {
      ?>
    $("#chkSinEstrangeiro").attr('checked', true);
  <?php
  }
  ?>

  trocarEstrangeiro();

  trocarPais(true);

  habilitaDesabilitaCampos();
}

function OnSubmitForm() {
  return validarForm();
}

function validarForm() {

  if (infraTrim(document.getElementById('txtNome').value)=='') {
    alert('<?=_('Informe o Nome do Representante.')?>');
    document.getElementById('txtNome').focus();
    return false;
  }
  if(!document.getElementById("chkSinEstrangeiro").checked) {
    if (infraTrim(document.getElementById('txtCpf').value) == '') {
      alert('<?=_('Informe o CPF.')?>');
      document.getElementById('txtCpf').focus();
      return false;
    }

    document.getElementById('txtCpf').value = document.getElementById('txtCpf').value.replace(/[\.\-]/g, '');
    
    if (!infraValidarCpf(infraTrim(document.getElementById('txtCpf').value))) {
      alert('<?=_('CPF Inválido.')?>');
      document.getElementById('txtCpf').focus();
      return false;
    }

    if (infraTrim(document.getElementById('txtRg').value) == '') {
      alert('<?=_('Informe o RG.')?>');
      document.getElementById('txtRg').focus();
      return false;
    }

    if (infraTrim(document.getElementById('txtExpedidor').value) == '') {
      alert('<?=_('Informe o Órgão Expedidor.')?>');
      document.getElementById('txtExpedidor').focus();
      return false;
    }
  }else{
    if (infraTrim(document.getElementById('txtNumeroPassaporte').value) == '') {
      alert('<?=_('Informe o Número do Passaporte.')?>');
      document.getElementById('txtNumeroPassaporte').focus();
      return false;
    }
    if (!infraSelectSelecionado('selPaisPassaporte')) {
      alert('<?=_('Selecione um País de Emissão.')?>');
      document.getElementById('selPaisPassaporte').focus();
      return false;
    }
  }
	if (infraTrim(document.getElementById('txtTelefoneFixo').value)=='' && infraTrim(document.getElementById('txtTelefoneCelular').value)=='') {
    alert('<?=_('É necessário informar pelo menos um número de telefone.')?>');
    document.getElementById('txtTelefoneFixo').focus();
    return false;
  }

  if (infraTrim(document.getElementById('txtEndereco').value)=='') {
    alert('<?=_('Informe o Endereço Residencial.')?>');
    document.getElementById('txtEndereco').focus();
    return false;
  }

  if(!infraSelectSelecionado("selPais")) {
    alert('<?=_('Selecione um País.')?>');
    $("#selPais").focus();
    return false;
  }

  if($("#selPais").val() == '<?=ID_BRASIL?>') {
    if (!infraSelectSelecionado('selUf')) {
      alert('<?=_('Selecione um Estado.')?>');
      document.getElementById('selUf').focus();
      return false;
    }

    if (!infraSelectSelecionado('selCidade')) {
      alert('<?=_('Selecione uma Cidade.')?>');
      document.getElementById('selCidade').focus();
      return false;
    }
  }else{
    if (infraTrim($('#txtCidade').val()) == "") {
      alert('<?=_('Informe a Cidade.')?>');
      $('#txtCidade').focus();
      return false;
    }
  }

  if (infraTrim(document.getElementById('txtCep').value)=='') {
    alert('<?=_('Informe o CEP.')?>');
    document.getElementById('txtCep').focus();
    return false;
  }

  if (infraTrim(document.getElementById('txtCaptcha').value)=='') {
    alert('<?=_('Informe o código de confirmação.')?>');
    document.getElementById('txtCaptcha').focus();
    return false;
  }

  return true;
}

function infraMascaraTelefoneFixoNacional(event){
  infraMascaraTelefone($("#txtTelefoneFixo").get(0),event)
}
function infraMascaraTelefoneFixoInternacional(event){
  infraMascaraTelefoneInternacional($("#txtTelefoneFixo").get(0),event)
}
function infraMascaraTelefoneCelularNacional(event){
  infraMascaraTelefone($("#txtTelefoneCelular").get(0),event)
}
function infraMascaraTelefoneCelularInternacional(event){
  infraMascaraTelefoneInternacional($("#txtTelefoneCelular").get(0),event)
}

function trocarEstrangeiro() {
  if ($("#chkSinEstrangeiro").is(':checked')) {
    $("#divNacional").hide();
    $("#divPassaporte").show();

    $("#txtTelefoneFixo").on("keyup",infraMascaraTelefoneFixoInternacional);
    $("#txtTelefoneFixo").off("keyup",infraMascaraTelefoneFixoNacional);
    $("#txtTelefoneCelular").on("keyup",infraMascaraTelefoneCelularInternacional);
    $("#txtTelefoneCelular").off("keyup",infraMascaraTelefoneCelularNacional);
  } else {
    $("#divNacional").show();
    $("#divPassaporte").hide();

    $("#txtTelefoneFixo").off("keyup",infraMascaraTelefoneFixoInternacional);
    $("#txtTelefoneFixo").on("keyup",infraMascaraTelefoneFixoNacional);
    $("#txtTelefoneCelular").off("keyup",infraMascaraTelefoneCelularInternacional);
    $("#txtTelefoneCelular").on("keyup",infraMascaraTelefoneCelularNacional);
  }
  $("#txtTelefoneFixo").keyup();
  $("#txtTelefoneCelular").keyup();
}

function trocarPais(bolInicializacao){
  if ($("#selPais").val() == "<?=ID_BRASIL?>") {
    $("#txtUf").hide();
    $("#txtCidade").hide();
    $("#selUf").show();
    $("#selCidade").show();

    $("#txtCep").mask('99999-999');
    document.getElementById('txtCep').onkeypress = mascaraCepBrasil;

    $("#lblIdUf").removeClass("infraLabelOpcional");
    $("#lblIdUf").addClass("infraLabelObrigatorio");
  } else {
    $("#txtUf").show();
    $("#txtCidade").show();
    $("#selUf").hide();
    $("#selCidade").hide();
    $("#selUf").val('');
    $("#selCidade").val('');

    $("#txtCep").on('load', mascaraCepBrasil);
    document.getElementById('txtCep').onkeypress = mascaraCepGeral;

    $("#lblIdUf").addClass("infraLabelOpcional");
    $("#lblIdUf").removeClass("infraLabelObrigatorio");

    if (!bolInicializacao) {
      $("#txtUf").val('');
      $("#txtCidade").val('');
    }
  }
}

function mascaraCepBrasil(event){
  return infraMascaraCEP(document.getElementById('txtCep'), event);
}

function mascaraCepGeral(event){
  return infraMascaraTexto(document.getElementById('txtCep'),event,15)
}

$(function(){
  $('div.infraBarraLocalizacao').attr('style', "margin-bottom: 0 !important");
})

function habilitaDesabilitaCampos(){
  $("#txtEndereco").attr("data-logradouro") ? $("#txtEndereco").attr("readonly", true) : "";
  $("#txtBairro").attr("data-bairro") ? $("#txtBairro").attr("readonly", true) : "";
  $("#txtCep").attr("data-cep") ? $("#txtCep").attr("readonly", true) : "";
  $("#txtComplemento").attr("data-complemento") ? $("#txtComplemento").attr("readonly", true) : "";
  $("#selUf").attr("data-estado") ? $("#selUf").attr("disabled", true) : "";
  $("#selCidade").attr("data-cidade") ? $("#selCidade").attr("disabled", true) : "";
  $("#selPais").attr("data-pais") ? $("#selPais").attr("disabled", true) : "";
  $("#txtUf").attr("data-estado") ? $("#txtUf").attr("disabled", true) : "";
  $("#txtCidade").attr("data-cidade") ? $("#txtCidade").attr("disabled", true) : "";

  <? if(!$idCidadeToken){ ?>
    $("#selCidade").removeAttr("disabled");
    $("#selCidade").attr("enabled", true);
  <? } ?>
  
  <? if ($idPaisExterior || $idUfToken){ ?>
    $("#selPais").attr("disabled", true);
  <? } else { ?>
    $("#selPais").removeAttr("disabled");
    $("#selPais").attr("enabled", true);
  <? } ?>
  <? if ($idUfToken){ ?>
    $("#chkSinEstrangeiro").attr("disabled", true);
  <? } ?>
}

function habPais(){
  $("#selPais").removeAttr("disabled");
  $("#selPais").val("");
}

function atualizaValor(combo){
  (combo == "uf") ?
  $(".inputEstado"). val( $('.selEstado').val() ) :
  $(".inputMunicipio"). val( $('.selMunicipio').val() );
}

<?if(0){?></script><?}?>
<?php
PaginaSEIExterna::getInstance()->fecharJavaScript();
PaginaSEIExterna::getInstance()->fecharHead();
PaginaSEIExterna::getInstance()->abrirBody($strTitulo, 'onload="inicializar();"');

$strItensSelUf = UfINT::montarSelectSiglaRI0416('null', '&nbsp;', $idUfToken ? $idUfToken : $user->getNumIdUfContato());
$strLinkAjaxCidade = SessaoSEIExterna::getInstance()->assinarLink('controlador_ajax_externo.php?acao_ajax=cidade_montar_select_id_cidade_nome');
$strItensSelCidade = CidadeINT::montarSelectIdCidadeNome('null', '&nbsp;', ($idCidadeToken ? $idCidadeToken : $user->getNumIdCidadeContato()), ($idUfToken ? $idUfToken : $user->getNumIdUfContato()), (isset($idPaisExterior) ? $idPaisExterior : ID_BRASIL));
$strItensSelPaisPassaporte = PaisINT::montarSelectNome('null', '&nbsp', $_POST['selPaisPassaporte']);
$strItensSelPais = PaisINT::montarSelectNome('null', '&nbsp', isset($idPaisExterior) ? $idPaisExterior : ID_BRASIL);
echo $strDivIdioma;
?>

<form id="frmUsuarioExterno" method="post" onsubmit="return OnSubmitForm();" action="<?=SessaoSEIExterna::getInstance()->assinarLink('controlador_externo.php?acao='.$_GET['acao'].'&lang='.$locale)?>">

<div class="formularioTexto"><?=$strTextoFormulario?></div>

<div id="divDadosCadastrais" class="infraAreaDados" style="<?=$strDisplayCadastro?>">

  <div class="row-externo mb-1 mt-1">
    <label id="lblDadosUnidade"  accesskey="" class="infraLabelTitulo">&nbsp;&nbsp;<?=_("Dados Cadastrais")?></label>
  </div>

  <div class="row-externo mb-1">
    <div class="coluna-lg-externo">
      <label for="txtNome" id="lblNome" class="infraLabelObrigatorio">
      <?=_("Nome do Representante:")?>
      </label>
      <input type="text" id="txtNome" name="txtNome" onkeypress="return infraMascaraTexto(this,event,250);" maxlength="250" class="infraText" value="<?= $token['name'] ?>" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>" readonly />
    </div>

    <div class="coluna-externo">
      <input type="checkbox" id="chkSinEstrangeiro" name="chkSinEstrangeiro" onchange="trocarEstrangeiro(); habPais();" class="infraCheckbox" <?=PaginaSEI::getInstance()->setCheckbox(PaginaSEI::getInstance()->getCheckbox($_POST['chkSinEstrangeiro']))?> tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>" />
      <label id="lblSinEstrangeiro" for="chkSinEstrangeiro" class="infraLabelCheckbox"><?=_("Estrangeiro")?></label>
    </div>
  </div>

  <div class="row-externo mb-1">
    <div class="coluna-externo-md">
      <div><label id="lblCpf" for="txtCpf" accesskey="" class="infraLabelObrigatorio"><?=_("CPF:")?></label></div>
      <input type="text" id="txtCpf" name="txtCpf" onkeypress="return infraMascaraCpf(this,event);" maxlength="15" class="infraText" value="<?=InfraUtil::formatarCpf($token['sub'])?>" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>" readonly />
    </div>

    <div class="coluna-externo-md">
      <div><label id="lblRg" for="txtRg" accesskey="" class="infraLabelObrigatorio"><?=_("RG:")?></label></div>
      <input type="text" id="txtRg" name="txtRg" onkeypress="return infraMascaraNumero(this,event,15);" maxlength="15" class="infraText" value="<?= $user->getDblRgContato() ?>" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>" />
    </div>

    <div class="coluna-externo-md">
      <label id="lblExpedidor" for="txtExpedidor" accesskey="" class="infraLabelObrigatorio"><?=_("Órgão Expedidor:")?></label>
      <input type="text" id="txtExpedidor" name="txtExpedidor" onkeypress="return infraMascaraTexto(this,event,50);" maxlength="50" class="infraText" value="<?= $user->getStrOrgaoExpedidorContato() ?>" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>" />
    </div>
  </div>

  <div id="divPassaporte" class="row-externa mb-1">
    <div class="coluna-externo-md">
      <label id="lblNumeroPassaporte" for="txtNumeroPassaporte" class="infraLabelObrigatorio"><?=_("Número do Passaporte:")?></label>
      <input type="text" id="txtNumeroPassaporte" name="txtNumeroPassaporte" maxlength="15" class="infraText" onblur="return infraMascaraNumeroPassaporte(this,event);" onkeyup="return infraMascaraNumeroPassaporte(this,event);" value="<?= $user->getStrNumeroPassaporte() ?>" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>" />
    </div>

    <div class="coluna-externo-md">
      <label id="lblPaisPassaporte" for="selPaisPassaporte" class="infraLabelObrigatorio"><?=_("País de Emissão:")?></label>
      <select id="selPaisPassaporte" name="selPaisPassaporte" value="<?= $user->getNumIdPaisPassaporte() ?>" class="infraSelect" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>">
        <?=$strItensSelPaisPassaporte?>
      </select>
    </div>
  </div>

  <div class="row-externo mb-1">
    <div class="coluna-externo-md">
      <label id="lblTelefoneFixo" for="txtTelefoneFixo" accesskey="" class="infraLabelOpcional"><?=_("Telefone Fixo:")?></label>
      <input type="text" id="txtTelefoneFixo" name="txtTelefoneFixo" class="infraText" <?= $readTel ?> value="<?= $tel ?>" maxlength="25" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>" />
    </div>

    <div class="coluna-externo-md">
      <label id="lblTelefoneCelular" for="txtTelefoneCelular" accesskey="" class="infraLabelOpcional"><?=_("Telefone Celular:")?></label>
      <input type="text" id="txtTelefoneCelular" name="txtTelefoneCelular" class="infraText" <?= $readCel?> value="<?= $cel ?>"  maxlength="25" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>" />
    </div>
  </div>
  
  <div class="row-externo mb-1">
    <label id="lblEndereco" for="txtEndereco" accesskey="" class="infraLabelObrigatorio"><?=_("Endereço Residencial:")?></label>
    <input type="text" id="txtEndereco" name="txtEndereco" class="infraText" data-logradouro="<?= $dadosReceita['logradouro'] ?>" value="<?= $dadosReceita['logradouro'] ? $dadosReceita['logradouro'] : $user->getStrEnderecoContato() ?>" onkeypress="return infraMascaraTexto(this,event,130);" maxlength="130" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>" />
  </div>

  <div class="row-externo mb-1">
    <div class="coluna-lg-externo">
      <label id="lblComplemento" for="txtComplemento" accesskey="" class="infraLabelOpcional"><?=_("Complemento:")?></label>
      <input type="text" id="txtComplemento" name="txtComplemento" class="infraText" data-complemento="<?= $dadosReceita['complemento'] ?>" value="<?= $dadosReceita['complemento'] ? $dadosReceita['complemento'] : $user->getStrComplementoContato() ?>" onkeypress="return infraMascaraTexto(this,event,130);" maxlength="130" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>" />
    </div>
    <div class="coluna-externo-md">
      <label id="lblBairro" for="txtBairro" accesskey="" class="infraLabelOpcional"><?=_("Bairro:")?></label>
      <input type="text" id="txtBairro" name="txtBairro" class="infraText" data-bairro="<?= $dadosReceita['bairro'] ?>" value="<?= $dadosReceita['bairro'] ? $dadosReceita['bairro'] : $user->getStrBairroContato() ?>" onkeypress="return infraMascaraTexto(this,event,130);" maxlength="130" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>" />
    </div>
  </div>

  <div class="row-externo mb-1">
    <div class="coluna-lg-externo">
      <div class="col-half-externo">
        <div class="col-interna">
          <label id="lblPais" for="selPais" class="infraLabelObrigatorio"><?=_("País:")?></label>
          <select id="selPais" name="selPais" data-pais="<?= $dadosReceita['nomePaisExterior'] ? $dadosReceita['nomePaisExterior'] : "Brasil" ?>" value="<?= $idPaisExterior ? $idPaisExterior : ID_BRASIL ?>" class="infraSelect" onchange="trocarPais(false)" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>">
            <?=$strItensSelPais?>
          </select>
          <input type="hidden" value="<?= $idPaisExterior ? $idPaisExterior : ID_BRASIL ?>" id="selPais"  name="selPais"/>
        </div>
        <div class="col-interna">
          <label id="lblIdUf" for="selUf" accesskey="" class="infraLabelOpcional"><?=_("Estado:")?></label>
          <select id="selUf" name="selUf" data-estado="<?= $dadosReceita['uf'] ?>" value="<?= $idUfToken ? $idUfToken : $user->getNumIdUfContato() ?>" class="infraSelect selEstado" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>" onchange="atualizaValor('uf')">
            <?=$strItensSelUf?>
          </select>

          <input type="hidden" value="<?= $idUfToken ? $idUfToken : $user->getNumIdUfContato() ?>" id="selUf"  name="selUf" class="inputEstado"/>

          <input type="text" id="txtUf" data-estado="<?= $dadosReceita['uf'] ?>" name="txtUf" value="<?= $dadosReceita['uf'] ? $dadosReceita['uf'] : $user->getStrSiglaUfContato() ?>" class="infraText" onkeypress="return infraMascaraTexto(this,event,50);" maxlength="50" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>" />

        </div>
      </div>

      <div class="col-half-externo">
        <label id="lblIdCidade" for="selCidade" accesskey="" class="infraLabelObrigatorio"><?=_("Cidade:")?></label>
        <select id="selCidade"  name="selCidade" data-cidade="<?= $dadosReceita['municipio'] ?>" value="<?= $idCidadeToken ? $idCidadeToken : $user->getNumIdCidadeContato() ?>" class="infraSelect selMunicipio" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>" onchange="atualizaValor('cidade');">
          <?=$strItensSelCidade?>
        </select>
        <input type="hidden" value="<?= $idCidadeToken ? $idCidadeToken : $user->getNumIdCidadeContato() ?>" id="selCidade"  name="selCidade" class="inputMunicipio"/>

        <input type="text" id="txtCidade" name="txtCidade" data-cidade="<?= $dadosReceita['municipio'] ?>" class="infraText" value="<?= $dadosReceita['municipio'] ? $dadosReceita['municipio'] : $user->getStrNomeCidadeContato() ?>"  onkeypress="return infraMascaraTexto(this,event,50);" maxlength="50" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>" />
      </div>
    </div>

    <div class="coluna-externo-md auxiliar">
      <div><label id="lblCep" for="txtCep" accesskey="" class="infraLabelObrigatorio"><?=_("CEP:")?></label></div>
      <input type="text" id="txtCep" name="txtCep"  maxlength="15" class="infraText" data-cep="<?= $dadosReceita['cep'] ?>" value="<?= $dadosReceita['cep'] ? $dadosReceita['cep'] : $user->getStrCepContato() ?>"  tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>" />
    </div>
  </div>

    <input type="hidden" id="txtEmail" name="txtEmail" class="infraText" value="<?= $token['email'] ?>" onkeypress="return infraMascaraTexto(this,event,100);" maxlength="100" tabindex="<?=PaginaSEIExterna::getInstance()->getProxTabDados()?>" readonly />

  <div class="row-externo mt-1">
    <label id="lblDadosUnidade"  accesskey="" class="infraLabelTitulo">&nbsp;&nbsp;<?=_("Dados de Autenticação")?></label>
  </div>

  Favor criar uma nova senha interna no SEI, poderá ser usada caso o Login Único estiver fora do ar.

  <div class="row-externo mb-1">
    <div class="coluna-externo-md">
      <label id="lblSenha" for="txtSenha" accesskey="" class="infraLabelObrigatorio"><?=_("Senha:")?></label>
      <input type="password" id="txtSenha" name="txtSenha" class="infraText" />
    </div>
  </div>
  <div class="row-externo mb-1">
    <div class="coluna-externo-md">
      <label id="lblConfirmaSenha" for="txtConfirmaSenha" accesskey="" class="infraLabelObrigatorio"><?=_("Confirmar Senha:")?></label>
      <input type="password" id="txtConfirmaSenha" name="txtConfirmaSenha" class="infraText" />
    </div>
  </div>

  <div class="row-externo mb-1">
    <div class="coluna-captcha">
      <label id="lblCaptcha" accesskey="" class="infraLabelObrigatorio"><img src="/infra_js/infra_gerar_captcha.php?codetorandom=<?=$strCodigoParaGeracaoCaptcha;?>" alt="<?=_("Não foi possível carregar a imagem de confirmação")?>" /></label>
    </div>
    <div class="coluna-captcha">
      <input type="text" id="txtCaptcha" name="txtCaptcha" class="infraText" maxlength="4" value="" />
    </div>
    <div class="coluna-captcha">
      <label id="lblCodigo" for="txtCaptcha" accesskey="" class="labelOpcional"><?=_("Digite o código da imagem ao lado")?></label>
    </div>
  </div>

  <div class="row-externo mb-1 mt ml20">
    <button type="submit" accesskey="" id="sbmEnviar" class="infraButton" name="sbmEnviar" value="Enviar" title="Enviar" ><?=_("Enviar")?></button>
    <button type="button" accesskey="" id="btnVoltar" style='margin-left:10px' class="infraButton" name="btnVoltar" value="Voltar" onclick="location.href='<?=SessaoSEIExterna::getInstance()->assinarLink('controlador_externo.php?acao=usuario_externo_sair&acao_origem='.$_GET['acao'])?>';" class="infraButton bt"><?=_("Voltar")?></button>
  </div>
  
</div>
</form>
<?php
PaginaSEIExterna::getInstance()->montarAreaDebug();
PaginaSEIExterna::getInstance()->fecharBody();
PaginaSEIExterna::getInstance()->fecharHtml();
