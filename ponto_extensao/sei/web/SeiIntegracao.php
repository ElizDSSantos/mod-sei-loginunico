<?
abstract class SeiIntegracao {

  //TAM = Tipo Acesso Modulo
  public static $TAM_PERMITIDO = 'P';
  public static $TAM_NEGADO = 'N';

  public abstract function getNome();
  public abstract function getVersao();
  public abstract function getInstituicao();
  public function inicializar($strVersaoSEI) {return null;}
  public function montarBotaoControleProcessos(){return null;}
  public function montarIconeControleProcessos($arrObjProcedimentoAPI){return null;}
  public function montarIconeAcompanhamentoEspecial($arrObjProcedimentoAPI){return null;}
  public function montarBotaoProcesso(ProcedimentoAPI $objProcedimentoAPI){return null;}
  public function montarIconeProcesso(ProcedimentoAPI $objProcedimentoAPI){return null;}
  public function montarBotaoDocumento(ProcedimentoAPI $objProcedimentoAPI, $arrObjDocumentoAPI){return null;}
  public function montarMensagemProcesso(ProcedimentoAPI $objProcedimentoAPI){return null;}
  public function alterarIconeArvoreDocumento(ProcedimentoAPI $objProcedimentoAPI, $arrObjDocumentoAPI){return null;}
  public function montarIconeDocumento(ProcedimentoAPI $objProcedimentoAPI, $arrObjDocumentoAPI){return null;}
  public function atualizarConteudoDocumento(DocumentoAPI $objDocumentoAPI){return null;}
  public function gerarProcesso(ProcedimentoAPI $objProcedimentoAPI){return null;}
  public function concluirProcesso($arrObjProcedimentoAPI){return null;}
  public function reabrirProcesso(ProcedimentoAPI $objProcedimentoAPI){return null;}
  public function sobrestarProcesso(ProcedimentoAPI $objProcedimentoAPI, $objProcedimentoAPIVinculado){return null;}
  public function removerSobrestamentoProcesso(ProcedimentoAPI $objProcedimentoAPI, $objProcedimentoAPIVinculado){return null;}
  public function anexarProcesso(ProcedimentoAPI $objProcedimentoAPIPrincipal, ProcedimentoAPI $objProcedimentoAPIAnexado){return null;}
  public function desanexarProcesso(ProcedimentoAPI $objProcedimentoAPIPrincipal, ProcedimentoAPI $objProcedimentoAPIAnexado){return null;}
  public function relacionarProcesso(ProcedimentoAPI $objProcedimentoAPI1, ProcedimentoAPI $objProcedimentoAPI2){return null;}
  public function removerRelacionamentoProcesso(ProcedimentoAPI $objProcedimentoAPI1, ProcedimentoAPI $objProcedimentoAPI2){return null;}
  public function bloquearProcesso($arrObjProcedimentoAPI){return null;}
  public function desbloquearProcesso($arrObjProcedimentoAPI){return null;}
  public function excluirProcesso(ProcedimentoAPI $objProcedimentoAPI){return null;}
  public function enviarProcesso($arrObjProcedimentoAPI, $arrObjUnidadeAPI){return null;}
  public function gerarDocumento(DocumentoAPI $objDocumentoAPI){return null;}
  public function excluirDocumento(DocumentoAPI $objDocumentoAPI){return null;}
  public function moverDocumento(DocumentoAPI $objDocumentoAPI, ProcedimentoAPI $objProcedimentoAPIOrigem, ProcedimentoAPI $objProcedimentoAPIDestino){return null;}
  public function cancelarDocumento(DocumentoAPI $objDocumentoAPI){return null;}
  public function assinarDocumento($arrObjDocumentoAPI){return null;}
  public function verificarAcessoProtocolo($arrObjProcedimentoAPI, $arrObjDocumentoAPI) {return null;}
  public function verificarAcessoProtocoloExterno($arrObjProcedimentoAPI, $arrObjDocumentoAPI) {return null;}
  public function permitirAndamentoConcluido(AndamentoAPI $objAndamentoAPI){return null;}
  public function adicionarElementoMenu(){return null;}
  public function montarMenuPublicacoes(){return null;}
  public function montarMenuUsuarioExterno(){return null;}
  public function montarBotaoControleAcessoExterno(){return null;}
  public function montarAcaoControleAcessoExterno($arrObjAcessoExternoAPI){return null;}
  public function montarBotaoAcessoExternoAutorizado(ProcedimentoAPI $objProcedimentoAPI){return null;}
  public function montarAcaoDocumentoAcessoExternoAutorizado($arrObjDocumentoAPI){return null;}
  public function montarAcaoProcessoAnexadoAcessoExternoAutorizado($arrObjProcedimentAPI){return null;}
  public function montarAcaoDocumentoAcessoExternoNegado($arrObjDocumentoAPI){return null;}
  public function montarAcaoProcessoAnexadoAcessoExternoNegado($arrObjProcedimentAPI){return null;}
  public function cancelarDisponibilizacaoAcessoExterno($arrObjAcessoExternoAPI){return null;}
  public function cancelarLiberacaoAssinaturaExterna($arrObjAcessoExternoAPI){return null;}
  public function excluirUsuario($arrObjUsuarioAPI){return null;}
  public function excluirUnidade($arrObjUnidadeAPI){return null;}
  public function desativarUnidade($arrObjUnidadeAPI){return null;}
  public function reativarUnidade($arrObjUnidadeAPI){return null;}
  public function montarTipoTarjaAssinaturaCustomizada(){return null;}
  public function excluirTipoDocumento($arrObjSerieDTO){return null;}
  public function desativarTipoDocumento($arrObjSerieDTO){return null;}
  public function reativarTipoDocumento($arrObjSerieDTO){return null;}
  public function excluirTipoProcesso($arrObjTipoProcedimentoDTO){return null;}
  public function desativarTipoProcesso($arrObjTipoProcedimentoDTO){return null;}
  public function reativarTipoProcesso($arrObjTipoProcedimentoDTO){return null;}
  public function darCienciaProcesso(ProcedimentoAPI $objProcedimentoAPI){return null;}
  public function darCienciaDocumento(DocumentoAPI $objDocumentoAPI){return null;}
  public function processarControlador($strAcao){return null;}
  public function processarControladorAjax($strAcaoAjax){return null;}
  public function processarControladorExterno($strAcao){return null;}
  public function processarControladorPublicacoes($strAcao){return null;}
  public function processarControladorAjaxExterno($strAcaoAjax){return null;}
  public function processarControladorWebServices($strServico){return null;}
  public function obterAcoesExternasSemLogin(){return null;}
  public function obterAcoesAjaxExternasSemLogin(){return null;}
  public function processarVariaveisEditor(DocumentoAPI $objDocumentoAPI){return null;}
  public function obterRelacaoVariaveisEditor(){return null;}
  public function cadastrarContato(ContatoAPI $objContatoAPI){return null;}
  public function alterarContato(ContatoAPI $objContatoAPI){return null;}
  public function excluirContato($arrObjContatoAPI){return null;}
  public function desativarContato($arrObjContatoAPI){return null;}
  public function reativarContato($arrObjContatoAPI){return null;}

  public function montarAcaoVeiculoPublicacao($arrObjVeiculoPublicacaoAPI){return null;}
  public function montarAcaoPublicacao($arrObjPublicacaoAPI){return null;}
  public function ocultarAcaoAlterarPublicacao($arrObjPublicacaoAPI){return null;}
  public function ocultarAcaoCancelarAgendamentoPublicacao($arrObjPublicacaoAPI){return null;}
  public function ocultarBotaoSalvarPublicacao($arrObjVeiculoPublicacaoAPI){return null;}
  public function ocultarDadosImprensaNacionalPublicacao($arrObjVeiculoPublicacaoAPI){return null;}
  public function montarBotaoVeiculoPublicacao($arrObjVeiculoPublicacaoAPI){return null;}
  public function montarDadosImprensaNacional(PublicacaoAPI $objPublicacaoAPI){return null;}
  public function montarTextoInformativoPublicacao(PublicacaoAPI $objPublicacaoAPI){return null;}
  public function obterProximaDataPublicacao(PublicacaoAPI $objPublicacaoAPI){return null;}
  public function cadastrarVeiculoPublicacao(VeiculoPublicacaoAPI $objVeiculoPublicacaoAPI){return null;}
  public function alterarVeiculoPublicacao(VeiculoPublicacaoAPI $objVeiculoPublicacaoAPI){return null;}
  public function excluirVeiculoPublicacao($arrObjVeiculoPublicacaoAPI){return null;}
  public function desativarVeiculoPublicacao($arrObjVeiculoPublicacaoAPI){return null;}
  public function reativarVeiculoPublicacao($arrObjVeiculoPublicacaoAPI){return null;}
  public function agendarPublicacao(PublicacaoAPI $objPublicacaoAPI){return null;}
  public function alterarPublicacao(PublicacaoAPI $objPublicacaoAPI){return null;}
  public function cancelarAgendamentoPublicacao(PublicacaoAPI $objPublicacaoAPI){return null;}
  public function montarBotaoAutenticacaoExterna(){return null;}
  public function validarSenhaUsuarioExterno(UsuarioExternoAPI $objUsuarioExternoAPI){return null;}
  public function validarSeLoginExterno(){return null;}
  public function montarBotaoAssinaturaExterna($idDocumento,$IdAcessoExterno){return null;}


  public function executar($func, ...$params) {
    try {
      $ret = call_user_func_array(array($this, $func), $params);
    }catch(Exception $e){
      throw new InfraException('Erro processando opera��o "'.$func.'" no m�dulo "'.$this->getNome().'".', $e);
    }
    return $ret;
  }
}
?>