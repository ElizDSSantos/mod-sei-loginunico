<?
/**
* TRIBUNAL REGIONAL FEDERAL DA 4ª REGIÃO
*
* 25/04/2012 - criado por mga
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
  InfraDebug::getInstance()->setBolDebugInfra(true);
  InfraDebug::getInstance()->limpar();
  //////////////////////////////////////////////////////////////////////////////

  SessaoSEIExterna::getInstance()->validarLink();

  switch($_GET['acao']){

      case 'usuario_externo_controle_acessos':
        $strTitulo = 'Controle de Acessos Externos';
        break;

    default:
      throw new InfraException("Ação '".$_GET['acao']."' não reconhecida.");
  }


  $objAcessoExternoDTO = new AcessoExternoDTO();
  $objAcessoExternoDTO->setNumIdUsuarioExterno(SessaoSEIExterna::getInstance()->getNumIdUsuarioExterno());

  PaginaSEIExterna::getInstance()->prepararPaginacao($objAcessoExternoDTO);

  $objAcessoExternoRN = new AcessoExternoRN();
  $arrObjAcessoExternoDTO = $objAcessoExternoRN->listarDocumentosControleAcesso($objAcessoExternoDTO);

  PaginaSEIExterna::getInstance()->processarPaginacao($objAcessoExternoDTO);

  $strResultado = '';
  $numRegistros = count($arrObjAcessoExternoDTO);

  $arrComandos = array();

  foreach ($SEI_MODULOS as $seiModulo) {
    if (($arrIntegracao = $seiModulo->executar('montarBotaoControleAcessoExterno')) != null) {
      foreach ($arrIntegracao as $strIntegracao) {
        $arrComandos[] = $strIntegracao;
      }
    }
  }

  if ($numRegistros){

    $arrObjAcessoExternoAPI = array();

    foreach($arrObjAcessoExternoDTO as $objAcessoExternoDTO) {

      $objAcessoExternoAPI = new AcessoExternoAPI();
      $objAcessoExternoAPI->setIdAcessoExterno($objAcessoExternoDTO->getNumIdAcessoExterno());
      $objAcessoExternoAPI->setDataValidade($objAcessoExternoDTO->getDtaValidade());
      $objAcessoExternoAPI->setSinAcessoProcesso($objAcessoExternoDTO->getStrSinProcesso());

      $objProcedimentoDTO = $objAcessoExternoDTO->getObjProcedimentoDTO();

      $objProcedimentoAPI = new ProcedimentoAPI();
      $objProcedimentoAPI->setIdProcedimento($objProcedimentoDTO->getDblIdProcedimento());
      $objProcedimentoAPI->setNumeroProtocolo($objProcedimentoDTO->getStrProtocoloProcedimentoFormatado());
      $objProcedimentoAPI->setIdTipoProcedimento($objProcedimentoDTO->getNumIdTipoProcedimento());
      $objProcedimentoAPI->setNomeTipoProcedimento($objProcedimentoDTO->getStrNomeTipoProcedimento());
      $objProcedimentoAPI->setNivelAcesso($objProcedimentoDTO->getStrStaNivelAcessoGlobalProtocolo());
      $objAcessoExternoAPI->setProcedimento($objProcedimentoAPI);

      if ($objAcessoExternoDTO->isSetObjDocumentoDTO()){

        $objDocumentoDTO = $objAcessoExternoDTO->getObjDocumentoDTO();

        $objDocumentoAPI = new DocumentoAPI();
        $objDocumentoAPI->setIdDocumento($objDocumentoDTO->getDblIdDocumento());
        $objDocumentoAPI->setNumeroProtocolo($objDocumentoDTO->getStrProtocoloDocumentoFormatado());
        $objDocumentoAPI->setIdSerie($objDocumentoDTO->getNumIdSerie());
        $objDocumentoAPI->setIdUnidadeGeradora($objDocumentoDTO->getNumIdUnidadeGeradoraProtocolo());
        $objDocumentoAPI->setTipo($objDocumentoDTO->getStrStaProtocoloProtocolo());
        $objDocumentoAPI->setSinAssinado($objDocumentoDTO->getStrSinAssinado());
        $objDocumentoAPI->setSinPublicado($objDocumentoDTO->getStrSinPublicado());
        $objDocumentoAPI->setNivelAcesso($objDocumentoDTO->getStrStaNivelAcessoGlobalProtocolo());
        $objAcessoExternoAPI->setDocumento($objDocumentoAPI);
      }

      $arrObjAcessoExternoAPI[] = $objAcessoExternoAPI;

      /*
      if ($objAcessoExternoDTO->isSetObjDocumentoDTO()){
        $objDocumentoDTO = $objAcessoExternoDTO->getObjDocumentoDTO();
      }
      */
    }

    foreach ($SEI_MODULOS as $seiModulo) {
      if (InfraArray::contar($arrObjAcessoExternoAPI)){
        if (($arr = $seiModulo->executar('montarAcaoControleAcessoExterno', $arrObjAcessoExternoAPI))!=null){;
          foreach($arr as $key => $arr) {
            if (!isset($arrIntegracaoAcoesProcedimentos[$key])) {
              $arrIntegracaoAcoesProcedimentos[$key] = $arr;
            }else {
              $arrIntegracaoAcoesProcedimentos[$key] = array_merge($arrIntegracaoAcoesProcedimentos[$key], $arr);
            }
          }
        }
      }
    }



    $strResultado = '<table id="tblDocumentos" width="99%" class="infraTable tabelaControleExterno" summary="Lista de Acessos Externos" align="center" >
  					  									<caption class="infraCaption" >'.PaginaSEIExterna::getInstance()->gerarCaptionTabela("Acessos Externos",$numRegistros).'</caption> 
  					 										<tr>
  					 										  <th class="tituloControleExterno" width="1%" style="display:none">'.PaginaSEIExterna::getInstance()->getThCheck().'</th>
  					 										  <th class="tituloControleExterno" width="20%">Processo</th>
  					  										<th class="tituloControleExterno" width="20%">Documento</th>
  					  										<th class="tituloControleExterno">Tipo</th>
  					  										<th class="tituloControleExterno" width="15%">Liberação</th>
  					  										<th class="tituloControleExterno" width="15%">Validade</th>
  					  										<th class="tituloControleExterno" width="10%">Ações</th>
  					  									</tr>';


    $n = 0;

    foreach($arrObjAcessoExternoDTO as $objAcessoExternoDTO){

      $objProcedimentoDTO = $objAcessoExternoDTO->getObjProcedimentoDTO();

      $objDocumentoDTO = null;
      if ($objAcessoExternoDTO->isSetObjDocumentoDTO()){
        $objDocumentoDTO = $objAcessoExternoDTO->getObjDocumentoDTO();
      }

      SessaoSEIExterna::getInstance()->configurarAcessoExterno($objAcessoExternoDTO->getNumIdAcessoExterno());

      $strLinkProcedimento = SessaoSEIExterna::getInstance()->assinarLink('processo_acesso_externo_consulta.php?id_acesso_externo='.$objAcessoExternoDTO->getNumIdAcessoExterno());

      if ($objDocumentoDTO != null){
        $bolFlagAssinou = false;
      	$arrObjAssinaturaDTO = $objDocumentoDTO->getArrObjAssinaturaDTO();
      	foreach($arrObjAssinaturaDTO as $objAssinaturaDTO){
      	  if ($objAssinaturaDTO->getNumIdUsuario()==SessaoSEIExterna::getInstance()->getNumIdUsuarioExterno()){
      	    $bolFlagAssinou = true;
      	    break;
      	  }
      	}
        $strLinkDocumento = SessaoSEIExterna::getInstance()->assinarLink('controlador_externo.php?acao=usuario_externo_documento_assinar&id_acesso_externo='.$objAcessoExternoDTO->getNumIdAcessoExterno().'&id_documento='.$objDocumentoDTO->getDblIdDocumento());
      }

      $strResultado .= '<tr class="infraTrClara">';

    	$strResultado .= '<td valign="top" style="display:none">'.PaginaSEIExterna::getInstance()->getTrCheck($n++,$objAcessoExternoDTO->getNumIdAcessoExterno(),$objAcessoExternoDTO->getNumIdAcessoExterno()).'</td>';

      if (InfraData::compararDatas(date('d/m/Y'), $objAcessoExternoDTO->getDtaValidade()) < 0){
        $strResultado .= '<td align="center"><a href="javascript:void(0);" onclick="infraLimparFormatarTrAcessada(this.parentNode.parentNode);alert(\'Este acesso externo expirou em '.$objAcessoExternoDTO->getDtaValidade().'.\');" alt="' . PaginaSEIExterna::tratarHTML($objProcedimentoDTO->getStrNomeTipoProcedimento()) . '" title="' . PaginaSEIExterna::tratarHTML($objProcedimentoDTO->getStrNomeTipoProcedimento()) . '" class="ancoraPadraoPreta">' . PaginaSEIExterna::tratarHTML($objProcedimentoDTO->getStrProtocoloProcedimentoFormatado()) . '</a></td>';
      }else {
        if ($objAcessoExternoDTO->getStrSinProcesso() == 'S') {
          $strResultado .= '<td align="center"><a href="javascript:void(0);" onclick="infraLimparFormatarTrAcessada(this.parentNode.parentNode);window.open(\'' . $strLinkProcedimento . '\');" alt="' . PaginaSEIExterna::tratarHTML($objProcedimentoDTO->getStrNomeTipoProcedimento()) . '" title="' . PaginaSEIExterna::tratarHTML($objProcedimentoDTO->getStrNomeTipoProcedimento()) . '" class="ancoraPadraoAzul">' . PaginaSEIExterna::tratarHTML($objProcedimentoDTO->getStrProtocoloProcedimentoFormatado()) . '</a></td>';
        } else {
          $strResultado .= '<td align="center"><a href="javascript:void(0);" onclick="infraLimparFormatarTrAcessada(this.parentNode.parentNode);alert(\'Sem acesso à íntegra do processo.\');" alt="' . PaginaSEIExterna::tratarHTML($objProcedimentoDTO->getStrNomeTipoProcedimento()) . '" title="' . PaginaSEIExterna::tratarHTML($objProcedimentoDTO->getStrNomeTipoProcedimento()) . '" class="ancoraPadraoPreta">' . PaginaSEIExterna::tratarHTML($objProcedimentoDTO->getStrProtocoloProcedimentoFormatado()) . '</a></td>';
        }
      }

    	if ($objDocumentoDTO != null){
    	  if ($objDocumentoDTO->getStrStaEstadoProtocolo()!=ProtocoloRN::$TE_DOCUMENTO_CANCELADO){
          $strResultado .= '<td align="center"><a onclick="infraLimparFormatarTrAcessada(this.parentNode.parentNode);infraAbrirJanela(\''.$strLinkDocumento.'\',\'navegacao\',900,650,\'location=0,status=1,resizable=1,scrollbars=1\',true);" href="#" tabindex="'.PaginaSEIExterna::getInstance()->getProxTabTabela().'" class="ancoraPadraoAzul" alt="'.PaginaSEIExterna::tratarHTML($objDocumentoDTO->getStrNomeSerie()).'" title="'.PaginaSEIExterna::tratarHTML($objDocumentoDTO->getStrNomeSerie()).'">'.PaginaSEIExterna::tratarHTML($objDocumentoDTO->getStrProtocoloDocumentoFormatado()).'</a></td>';
        }else{
    	    $strResultado .= '<td align="center"><a href="javascript:void(0);" onclick="alert(\'Documento foi cancelado.\');" alt="'.PaginaSEIExterna::tratarHTML($objDocumentoDTO->getStrNomeSerie()).'" title="'.PaginaSEIExterna::tratarHTML($objDocumentoDTO->getStrNomeSerie()).'" class="ancoraPadraoPreta">'.PaginaSEIExterna::tratarHTML($objDocumentoDTO->getStrProtocoloDocumentoFormatado()).'</a></td>';
    	  }
        $strResultado .= '<td align="center">'.PaginaSEIExterna::tratarHTML($objDocumentoDTO->getStrNomeSerie()).'</td>';
    	}else{
    	  $strResultado .= '<td align="center">&nbsp;</td>';
        $strResultado .= '<td align="center">&nbsp;</td>';
    	}


      $strResultado .= '<td align="center">'.PaginaSEIExterna::tratarHTML(substr($objAcessoExternoDTO->getDthAberturaAtividade(),0,10)).'</td>';
      $strResultado .= '<td align="center">'.PaginaSEIExterna::tratarHTML(substr($objAcessoExternoDTO->getDtaValidade(),0,10)).'</td>';
      //$strResultado .= '<td align="center"><a alt="'.$objAcessoExternoDTO->getStrDescricaoUnidade().'" title="'.$objAcessoExternoDTO->getStrDescricaoUnidade().'" class="ancoraSigla">'.$objAcessoExternoDTO->getStrSiglaUnidade().'</a></td>';
      $strResultado .= '<td align="center">';


      //if ($objAcessoExternoDTO->getStrSinProcesso()=='S'){
      //  $strResultado .= '<a href="javascript:void(0);" onclick="window.open(\''.$strLinkProcedimento.'\');" tabindex="'.PaginaSEIExterna::getInstance()->getProxTabTabela().'"><img src="'.PaginaSEIExterna::getInstance()->getDiretorioImagensLocal().'/procedimento.gif" title="Consultar Processo" alt="Consultar Processo" class="infraImg" /></a>&nbsp;';
      //}

    	//$strResultado .= '<a href="javascript:void(0);" onclick="window.open(\''.$strLinkDocumento.'\');" tabindex="'.PaginaSEIExterna::getInstance()->getProxTabTabela().'"><img src="'.PaginaSEIExterna::getInstance()->getDiretorioImagensGlobal().'/lupa.gif" title="Consultar Documento" alt="Consultar Documento" class="infraImg" /></a>&nbsp;';


    	if ($objDocumentoDTO != null && !$bolFlagAssinou && $objDocumentoDTO->getStrStaEstadoProtocolo()!=ProtocoloRN::$TE_DOCUMENTO_CANCELADO){
        
        foreach ($SEI_MODULOS as $seiModulo) {
          $boolMetodos=in_array('montarBotaoAssinaturaExterna',get_class_methods($seiModulo));
          if ($boolMetodos){
            if (($btnAssinatura = $seiModulo->executar('montarBotaoAssinaturaExterna',$objDocumentoDTO->getDblIdDocumento(),$objAcessoExternoDTO->getNumIdAcessoExterno())) != null) { 
              $strResultado .= $btnAssinatura;
              $assinaturaExterna=true;
            }
          }
        }
        if(!$assinaturaExterna){
        $strResultado .= '<a href="javascript:void(0);" onclick="infraLimparFormatarTrAcessada(this.parentNode.parentNode);infraAbrirJanela(\''.SessaoSEIExterna::getInstance()->assinarLink('controlador_externo.php?acao=usuario_externo_assinar&id_acesso_externo='.$objAcessoExternoDTO->getNumIdAcessoExterno().'&id_documento='.$objDocumentoDTO->getDblIdDocumento()).'\',\'janelaAssinaturaExterna\',450,300,\'location=0,status=1,resizable=1,scrollbars=1\');" tabindex="'.PaginaSEIExterna::getInstance()->getProxTabTabela().'"><img src="imagens/sei_assinar_pequeno.gif" title="Assinar Documento" alt="Assinar Documento" class="infraImg" /></a>&nbsp;';

        }

    	}

      if (is_array($arrIntegracaoAcoesProcedimentos) && isset($arrIntegracaoAcoesProcedimentos[$objAcessoExternoDTO->getNumIdAcessoExterno()])){
        foreach($arrIntegracaoAcoesProcedimentos[$objAcessoExternoDTO->getNumIdAcessoExterno()] as $strIconeIntegracao){
          $strResultado .= '&nbsp;'.$strIconeIntegracao;
        }
      }

    	$strResultado .='</td></tr>';

    }
    $strResultado .= '</table>';
  }

  SessaoSEIExterna::getInstance()->configurarAcessoExterno(null);

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

th.tituloControleExterno{
font-size:1em;
font-weight: bold;
text-align: center;
color: #000;
background-color: #dfdfdf;
border-spacing: 0;
padding:.2em;
}

table.tabelaControleExterno {
font-size:1.2em;
background-color:white;
border:0px solid white;
border-spacing:0;
}

table.tabelaControleExterno tr{
margin:0;
border:0;
padding:0em;
}

table.tabelaControleExterno td{
padding:.2em;
}


<?
PaginaSEIExterna::getInstance()->fecharStyle();
PaginaSEIExterna::getInstance()->montarJavaScript();
PaginaSEIExterna::getInstance()->abrirJavaScript();
?>

function inicializar(){
  infraEfeitoTabelas();
}

<?
PaginaSEIExterna::getInstance()->fecharJavaScript();
PaginaSEIExterna::getInstance()->fecharHead();
PaginaSEIExterna::getInstance()->abrirBody($strTitulo,'onload="inicializar();"');
?>
<form id="frmUsuarioExternoControle" method="post" action="<?=SessaoSEIExterna::getInstance()->assinarLink('controlador_externo.php?acao='.$_GET['acao'].'&acao_origem='.$_GET['acao'].$strParametros)?>">
<?
PaginaSEIExterna::getInstance()->montarBarraComandosSuperior($arrComandos);
PaginaSEIExterna::getInstance()->montarAreaTabela($strResultado,$numRegistros,true);
?>
</form>
<?
PaginaSEIExterna::getInstance()->montarAreaDebug();
PaginaSEIExterna::getInstance()->fecharBody();
PaginaSEIExterna::getInstance()->fecharHtml();
?>