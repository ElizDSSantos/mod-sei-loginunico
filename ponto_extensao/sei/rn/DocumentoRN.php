<?php
/**
 * TRIBUNAL REGIONAL FEDERAL DA 4� REGI�O
 *
 */

require_once dirname(__FILE__).'/../SEI.php';

class DocumentoRN extends InfraRN {

  public static $TD_EXTERNO = 'X';
  public static $TD_EDITOR_EDOC = 'E';
  public static $TD_EDITOR_INTERNO = 'I';
  public static $TD_FORMULARIO_AUTOMATICO = 'A';
  public static $TD_FORMULARIO_GERADO = 'F';

  public function __construct(){
    parent::__construct();
  }

  protected function inicializarObjInfraIBanco(){
    return BancoSEI::getInstance();
  }

  public function cadastrarRN0003(DocumentoDTO $objDocumentoDTO){

    $bolAcumulacaoPrevia = FeedSEIProtocolos::getInstance()->isBolAcumularFeeds();

    FeedSEIProtocolos::getInstance()->setBolAcumularFeeds(true);

    $objDocumentoDTO = $this->cadastrarRN0003Interno($objDocumentoDTO);

    $objIndexacaoDTO = new IndexacaoDTO();
    $objIndexacaoDTO->setArrIdProtocolos(array($objDocumentoDTO->getDblIdDocumento()));
    $objIndexacaoDTO->setStrStaOperacao(IndexacaoRN::$TO_PROTOCOLO_METADADOS_E_CONTEUDO);

    $objIndexacaoRN = new IndexacaoRN();
    $objIndexacaoRN->indexarProtocolo($objIndexacaoDTO);

    if (!$bolAcumulacaoPrevia){
      FeedSEIProtocolos::getInstance()->setBolAcumularFeeds(false);
      FeedSEIProtocolos::getInstance()->indexarFeeds();
    }

    return $objDocumentoDTO;
  }

  protected function cadastrarRN0003InternoConectado(DocumentoDTO $objDocumentoDTO) {
    try {

      //Regras de Negocio
      $objInfraException = new InfraException();

      $objDocumentoDTO->setDblIdDocumento(null);
      $this->validarStrStaDocumento($objDocumentoDTO, $objInfraException);

      switch($objDocumentoDTO->getStrStaDocumento()){

        case DocumentoRN::$TD_EDITOR_INTERNO:
          SessaoSEI::getInstance()->validarAuditarPermissao('documento_gerar', __METHOD__, $objDocumentoDTO);
          break;

        case DocumentoRN::$TD_EXTERNO:
          SessaoSEI::getInstance()->validarAuditarPermissao('documento_receber', __METHOD__, $objDocumentoDTO);
          break;

        case DocumentoRN::$TD_FORMULARIO_AUTOMATICO:
        case DocumentoRN::$TD_FORMULARIO_GERADO:
          SessaoSEI::getInstance()->validarAuditarPermissao('formulario_gerar', __METHOD__, $objDocumentoDTO);
          break;
      }

      $objDocumentoDTO->setNumIdConjuntoEstilos(null);
      $objDocumentoDTO->setDblIdDocumentoEdoc(null);
      $objDocumentoDTO->setStrSinBloqueado('N');

      $this->validarDblIdProcedimento($objDocumentoDTO, $objInfraException);

      $objProtocoloDTOProcedimento = new ProtocoloDTO();
      $objProtocoloDTOProcedimento->retStrProtocoloFormatado();
      $objProtocoloDTOProcedimento->retStrStaEstado();
      $objProtocoloDTOProcedimento->retNumIdTipoProcedimentoProcedimento();
      $objProtocoloDTOProcedimento->retStrStaNivelAcessoGlobal();
      $objProtocoloDTOProcedimento->retStrProtocoloFormatado();
      $objProtocoloDTOProcedimento->setDblIdProtocolo($objDocumentoDTO->getDblIdProcedimento());

      $objProtocoloRN = new ProtocoloRN();
      $objProtocoloDTOProcedimento = $objProtocoloRN->consultarRN0186($objProtocoloDTOProcedimento);

      $objProcedimentoRN = new ProcedimentoRN();
      $objProcedimentoRN->verificarEstadoProcedimento($objProtocoloDTOProcedimento);

      $this->validarNumIdUnidadeResponsavelRN0915($objDocumentoDTO, $objInfraException);
      $objSerieDTO = $this->validarNumIdSerieRN0009($objDocumentoDTO, $objInfraException);

      if ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_FORMULARIO_GERADO){
        $objDocumentoDTO->setNumIdTipoFormulario($objSerieDTO->getNumIdTipoFormulario());;
      }else {
        $objDocumentoDTO->setNumIdTipoFormulario(null);
      }

      if ($objDocumentoDTO->isSetNumIdTipoConferencia()) {
        $this->validarNumIdTipoConferencia($objDocumentoDTO, $objInfraException);
      }else{
        $objDocumentoDTO->setNumIdTipoConferencia(null);
      }

      //conteudo nao existe nas telas de cadastro, apenas em documentos gerados por servicos
      if ($objDocumentoDTO->isSetStrConteudo()){
        $this->validarStrConteudo($objDocumentoDTO, $objInfraException);
      }else{
        $objDocumentoDTO->setStrConteudo(null);
      }

      if ($objDocumentoDTO->getStrStaDocumento() == DocumentoRN::$TD_EDITOR_INTERNO) {
        if ($objDocumentoDTO->isSetStrProtocoloDocumentoTextoBase() && !InfraString::isBolVazia($objDocumentoDTO->getStrProtocoloDocumentoTextoBase())) {

          $objPesquisaProtocoloDTO = new PesquisaProtocoloDTO();
          $objPesquisaProtocoloDTO->setStrStaTipo(ProtocoloRN::$TPP_DOCUMENTOS_GERADOS);
          $objPesquisaProtocoloDTO->setStrStaAcesso(ProtocoloRN::$TAP_AUTORIZADO);
          $objPesquisaProtocoloDTO->setStrProtocolo($objDocumentoDTO->getStrProtocoloDocumentoTextoBase());

          $objProtocoloRN = new ProtocoloRN();
          $arrObjProtocoloDTO = $objProtocoloRN->pesquisarRN0967($objPesquisaProtocoloDTO);

          if (count($arrObjProtocoloDTO) == 0) {
            $objInfraException->lancarValidacao('Documento Base n�o encontrado.');
          }

          if ($arrObjProtocoloDTO[0]->getStrStaDocumentoDocumento() != DocumentoRN::$TD_EDITOR_INTERNO) {
            $objInfraException->lancarValidacao('Documento Base n�o foi gerado pelo editor interno.');
          }

          $objDocumentoDTO->setDblIdDocumentoTextoBase($arrObjProtocoloDTO[0]->getDblIdProtocolo());
        }
      }

      $objProtocoloDTO = $objDocumentoDTO->getObjProtocoloDTO();

      /*
      //Em vers�o futura criar par�metros para ativar estas valida��es dando tempo para uma adapta��o dos Web Services existentes.

      if ($objProtocoloDTO->isSetArrObjParticipanteDTO() && count($objProtocoloDTO->getArrObjParticipanteDTO())) {
        if ($objDocumentoDTO->getStrStaDocumento() == DocumentoRN::$TD_FORMULARIO_GERADO) {
          throw new InfraException('Formul�rio n�o pode receber remetente, destinat�rios ou interessados.');
        }

        foreach($objProtocoloDTO->getArrObjParticipanteDTO() as $objParticipanteDTO){

          if ($objParticipanteDTO->getStrStaParticipacao()==ParticipanteRN::$TP_REMETENTE && $objDocumentoDTO->getStrStaDocumento() != DocumentoRN::$TD_EXTERNO){
            throw new InfraException('Somente documentos externos podem receber remetente.');
          }

          if ($objParticipanteDTO->getStrStaParticipacao()==ParticipanteRN::$TP_DESTINATARIO){

            if ($objDocumentoDTO->getStrStaDocumento() != DocumentoRN::$TD_EDITOR_INTERNO) {
              throw new InfraException('Somente documentos internos podem receber destinat�rios.');
            }

            if ($objSerieDTO->getStrSinDestinatario()=='N'){
              throw new InfraException('Tipo do documento "'.$objSerieDTO->getStrNome().'" n�o permite destinat�rios.');
            }
          }

          if ($objParticipanteDTO->getStrStaParticipacao()==ParticipanteRN::$TP_INTERESSADO){

            if ($objDocumentoDTO->getStrStaDocumento() != DocumentoRN::$TD_EDITOR_INTERNO && $objDocumentoDTO->getStrStaDocumento() != DocumentoRN::$TD_EXTERNO) {
              throw new InfraException('Documento n�o pode receber interessados.');
            }

            if ($objSerieDTO->getStrSinInteressado()=='N'){
              throw new InfraException('Tipo do documento "'.$objSerieDTO->getStrNome().'" n�o permite interessados.');
            }
          }
        }
      }
      */

      if ($objDocumentoDTO->getStrStaDocumento() != DocumentoRN::$TD_FORMULARIO_GERADO) {
        if ($objProtocoloDTO->isSetArrObjRelProtocoloAtributoDTO() && count($objProtocoloDTO->getArrObjRelProtocoloAtributoDTO())) {
          throw new InfraException('Documento n�o pode receber atributos.');
        }
      }

      if ($objDocumentoDTO->getStrStaDocumento() == DocumentoRN::$TD_EDITOR_INTERNO || $objDocumentoDTO->getStrStaDocumento() == DocumentoRN::$TD_FORMULARIO_GERADO) {
        if ($objProtocoloDTO->isSetArrObjAnexoDTO() && count($objProtocoloDTO->getArrObjAnexoDTO())) {
          throw new InfraException('Documento n�o pode receber anexos.');
        }
      }else if ($objDocumentoDTO->getStrStaDocumento() == DocumentoRN::$TD_EXTERNO){
        if ($objProtocoloDTO->isSetArrObjAnexoDTO() && count($objProtocoloDTO->getArrObjAnexoDTO())>1){
          throw new InfraException('Mais de um anexo informado para documento recebido.');
        }

        if ($objDocumentoDTO->getNumIdTipoConferencia()!=null && count($objProtocoloDTO->getArrObjAnexoDTO())==0){
          $objInfraException->adicionarValidacao('Tipo de confer�ncia n�o pode ser informado porque o documento n�o cont�m anexo.');
        }
      }

      $objProcedimentoDTO = new ProcedimentoDTO();
      $objProcedimentoDTO->setStrProtocoloProcedimentoFormatado($objProtocoloDTOProcedimento->getStrProtocoloFormatado());
      $objProcedimentoDTO->setNumIdTipoProcedimento($objProtocoloDTOProcedimento->getNumIdTipoProcedimentoProcedimento());
      $objProcedimentoDTO->setStrStaNivelAcessoGlobalProtocolo($objProtocoloDTOProcedimento->getStrStaNivelAcessoGlobal());

      $this->validarNivelAcesso($objDocumentoDTO, $objProcedimentoDTO, $objInfraException);

      $objInfraException->lancarValidacoes();

      $objUnidadeDTO = new UnidadeDTO();
      $objUnidadeDTO->retNumIdOrgao();
      $objUnidadeDTO->retStrSigla();
      $objUnidadeDTO->setNumIdUnidade($objDocumentoDTO->getNumIdUnidadeResponsavel());

      $objUnidadeRN = new UnidadeRN();
      $objUnidadeDTO = $objUnidadeRN->consultarRN0125($objUnidadeDTO);

      $objDocumentoDTO->setNumIdOrgaoUnidadeResponsavel($objUnidadeDTO->getNumIdOrgao());
      $objDocumentoDTO->setStrSiglaUnidadeResponsavel($objUnidadeDTO->getStrSigla());

      if ($objDocumentoDTO->getStrStaDocumento() == DocumentoRN::$TD_EDITOR_INTERNO) {

        //numeracao - inicio
        if ($objSerieDTO->getStrStaNumeracao() == SerieRN::$TN_SEM_NUMERACAO) {
          // nao deve entrar nunca
          if (!InfraString::isBolVazia($objDocumentoDTO->getStrNumero())) {
            $objInfraException->lancarValidacao('Documento com n�mero preenchido mas o tipo ' . $objSerieDTO->getStrNome() . ' n�o tem numera��o.');
          }
        } else if ($objSerieDTO->getStrStaNumeracao() == SerieRN::$TN_INFORMADA) {
          if (InfraString::isBolVazia($objDocumentoDTO->getStrNumero())) {
            $objInfraException->lancarValidacao('Tipo ' . $objSerieDTO->getStrNome() . ' requer preenchimento do n�mero do documento.');
          } else {
            $this->validarTamanhoNumeroRN0993($objDocumentoDTO, $objInfraException);
          }
        } else if (InfraString::isBolVazia($objDocumentoDTO->getStrNumero())) {

          $objNumeracaoDTO = new NumeracaoDTO();
          $objNumeracaoDTO->setNumIdSerie($objSerieDTO->getNumIdSerie());
          $objNumeracaoDTO->setStrStaNumeracaoSerie($objSerieDTO->getStrStaNumeracao());
          $objNumeracaoDTO->setNumIdUnidade($objDocumentoDTO->getNumIdUnidadeResponsavel());
          $objNumeracaoDTO->setNumIdOrgao($objDocumentoDTO->getNumIdOrgaoUnidadeResponsavel());
          $objNumeracaoDTO = $this->gerarNumeracao($objNumeracaoDTO);

          $objDocumentoDTO->setStrNumero($objNumeracaoDTO->getNumSequencial());
        }
        //numeracao - fim

      }else if ($objDocumentoDTO->getStrStaDocumento() == DocumentoRN::$TD_EXTERNO){
        $this->validarTamanhoNumeroRN0993($objDocumentoDTO, $objInfraException);
      }else if ($objDocumentoDTO->isSetStrNumero() && !InfraString::isBolVazia($objDocumentoDTO->getStrNumero())){
        $objInfraException->adicionarValidacao('N�mero n�o pode ser informado para formul�rios.');
      }

      $objInfraException->lancarValidacoes();

      $objDocumentoDTO->setNumIdModeloSerie($objSerieDTO->getNumIdModelo());

      $objDocumentoDTORet = $this->gravarDocumentoInterno($objDocumentoDTO);

      return $objDocumentoDTORet;

    }catch(Exception $e){
      throw new InfraException('Erro cadastrando documento.',$e);
    }
  }

  protected function gravarDocumentoInternoControlado(DocumentoDTO $objDocumentoDTO) {
    try {

      global $SEI_MODULOS;

      $objInfraException = new InfraException();

      $objProtocoloDTO = $objDocumentoDTO->getObjProtocoloDTO();

      $this->tratarProtocoloRN1164($objDocumentoDTO);

      $objProtocoloRN = new ProtocoloRN();
      $objProtocoloDTOGerado  = $objProtocoloRN->gerarRN0154($objProtocoloDTO);

      //$objDocumentoDTO->setDblIdProcedimento($objProtocoloDTO->getDblIdProcedimento());
      $objDocumentoDTO->setDblIdDocumento($objProtocoloDTOGerado->getDblIdProtocolo());
      $objDocumentoDTO->setStrStaNivelAcessoGlobalProtocolo($objProtocoloDTOGerado->getStrStaNivelAcessoGlobal());

      $objRelProtocoloProtocoloDTO = new RelProtocoloProtocoloDTO();
      $objRelProtocoloProtocoloDTO->setDblIdRelProtocoloProtocolo(null);
      $objRelProtocoloProtocoloDTO->setDblIdProtocolo1($objProtocoloDTO->getDblIdProcedimento());
      $objRelProtocoloProtocoloDTO->setDblIdProtocolo2($objDocumentoDTO->getDblIdDocumento());
      $objRelProtocoloProtocoloDTO->setNumIdUsuario($objProtocoloDTO->getNumIdUsuarioGerador());
      $objRelProtocoloProtocoloDTO->setNumIdUnidade ($objProtocoloDTO->getNumIdUnidadeGeradora());
      $objRelProtocoloProtocoloDTO->setNumSequencia($objProtocoloRN->obterSequencia($objProtocoloDTO));
      $objRelProtocoloProtocoloDTO->setStrStaAssociacao (RelProtocoloProtocoloRN::$TA_DOCUMENTO_ASSOCIADO);
      $objRelProtocoloProtocoloDTO->setDthAssociacao(InfraData::getStrDataHoraAtual());

      $objRelProtocoloProtocoloRN = new RelProtocoloProtocoloRN();
      $objRelProtocoloProtocoloRN->cadastrarRN0839($objRelProtocoloProtocoloDTO);

      if ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_FORMULARIO_GERADO){
        if ($objProtocoloDTO->isSetArrObjRelProtocoloAtributoDTO()){
          $objDocumentoDTO->setStrConteudo(self::montarConteudoFormulario($objProtocoloDTO->getArrObjRelProtocoloAtributoDTO()));
        }
      }

      $objDocumentoBD = new DocumentoBD($this->getObjInfraIBanco());
      $objDocumentoBD->cadastrar($objDocumentoDTO);

      if ($objDocumentoDTO->getStrStaDocumento() != DocumentoRN::$TD_EXTERNO){
        $objDocumentoConteudoDTO = new DocumentoConteudoDTO();
        $objDocumentoConteudoDTO->setStrConteudo($objDocumentoDTO->getStrConteudo());
        $objDocumentoConteudoDTO->setStrConteudoAssinatura(null);
        $objDocumentoConteudoDTO->setStrCrcAssinatura(null);
        $objDocumentoConteudoDTO->setStrQrCodeAssinatura(null);
        $objDocumentoConteudoDTO->setDblIdDocumento($objProtocoloDTOGerado->getDblIdProtocolo());

        $objDocumentoConteudoBD = new DocumentoConteudoBD(BancoSEI::getInstance());
        $objDocumentoConteudoBD->cadastrar($objDocumentoConteudoDTO);
      }

      $this->verificarSobrestamento($objDocumentoDTO);

      $objControleInternoDTO = new ControleInternoDTO();
      $objControleInternoDTO->setDblIdProcedimento($objDocumentoDTO->getDblIdProcedimento());
      $objControleInternoDTO->setNumIdSerie($objDocumentoDTO->getNumIdSerie());
      $objControleInternoDTO->setNumIdOrgao($objDocumentoDTO->getNumIdOrgaoUnidadeResponsavel());
      $objControleInternoDTO->setNumIdUnidade($objDocumentoDTO->getNumIdUnidadeResponsavel());
      $objControleInternoDTO->setStrStaNivelAcessoGlobal($objDocumentoDTO->getStrStaNivelAcessoGlobalProtocolo());
      $objControleInternoDTO->setStrStaOperacao(ControleInternoRN::$TO_GERAR_DOCUMENTO);

      $objControleInternoRN = new ControleInternoRN();
      $objControleInternoRN->processar($objControleInternoDTO);

      $arrObjAtributoAndamentoDTO = array();
      $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
      $objAtributoAndamentoDTO->setStrNome('DOCUMENTO');
      $objAtributoAndamentoDTO->setStrValor($objProtocoloDTOGerado->getStrProtocoloFormatado());
      $objAtributoAndamentoDTO->setStrIdOrigem($objProtocoloDTOGerado->getDblIdProtocolo());
      $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;

      $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
      $objAtributoAndamentoDTO->setStrNome('NIVEL_ACESSO');
      $objAtributoAndamentoDTO->setStrValor(null);
      $objAtributoAndamentoDTO->setStrIdOrigem($objProtocoloDTO->getStrStaNivelAcessoLocal());
      $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;

      if (!InfraString::isBolVazia($objProtocoloDTO->getNumIdHipoteseLegal())){
        $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
        $objAtributoAndamentoDTO->setStrNome('HIPOTESE_LEGAL');
        $objAtributoAndamentoDTO->setStrValor(null);
        $objAtributoAndamentoDTO->setStrIdOrigem($objProtocoloDTO->getNumIdHipoteseLegal());
        $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;
      }

      if (!InfraString::isBolVazia($objProtocoloDTO->getStrStaGrauSigilo())){
        $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
        $objAtributoAndamentoDTO->setStrNome('GRAU_SIGILO');
        $objAtributoAndamentoDTO->setStrValor(null);
        $objAtributoAndamentoDTO->setStrIdOrigem($objProtocoloDTO->getStrStaGrauSigilo());
        $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;
      }

      if (!InfraString::isBolVazia($objDocumentoDTO->getNumIdTipoConferencia())){
        $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
        $objAtributoAndamentoDTO->setStrNome('TIPO_CONFERENCIA');
        $objAtributoAndamentoDTO->setStrValor(null);
        $objAtributoAndamentoDTO->setStrIdOrigem($objDocumentoDTO->getNumIdTipoConferencia());
        $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;
      }

      $objAtividadeDTO = new AtividadeDTO();
      $objAtividadeDTO->setDblIdProtocolo($objProtocoloDTO->getDblIdProcedimento());
      $objAtividadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());

      if ($objDocumentoDTO->getStrStaDocumento() == DocumentoRN::$TD_EXTERNO){
        $objAtividadeDTO->setNumIdTarefa(TarefaRN::$TI_RECEBIMENTO_DOCUMENTO);
      }else{
        $objAtividadeDTO->setNumIdTarefa(TarefaRN::$TI_GERACAO_DOCUMENTO);
      }

      $objAtividadeDTO->setArrObjAtributoAndamentoDTO($arrObjAtributoAndamentoDTO);

      $objAtividadeRN = new AtividadeRN();
      $objAtividadeRN->gerarInternaRN0727($objAtividadeDTO);

      if ($objProtocoloDTO->isSetArrObjAnexoDTO()) {

        $arrAnexos = $objProtocoloDTO->getArrObjAnexoDTO();

        for ($i = 0; $i < count($arrAnexos); $i++) {

          $arrObjAtributoAndamentoDTO = array();
          $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
          $objAtributoAndamentoDTO->setStrNome('ANEXO');
          $objAtributoAndamentoDTO->setStrValor($arrAnexos[$i]->getStrNome());
          $objAtributoAndamentoDTO->setStrIdOrigem($arrAnexos[$i]->getNumIdAnexo());
          $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;

          $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
          $objAtributoAndamentoDTO->setStrNome('DOCUMENTO');
          $objAtributoAndamentoDTO->setStrValor($objProtocoloDTOGerado->getStrProtocoloFormatado());
          $objAtributoAndamentoDTO->setStrIdOrigem($objProtocoloDTOGerado->getDblIdProtocolo());
          $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;

          $objAtividadeDTO = new AtividadeDTO();
          $objAtividadeDTO->setDblIdProtocolo($objProtocoloDTO->getDblIdProcedimento());
          $objAtividadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
          $objAtividadeDTO->setNumIdTarefa(TarefaRN::$TI_ARQUIVO_ANEXADO);
          $objAtividadeDTO->setArrObjAtributoAndamentoDTO($arrObjAtributoAndamentoDTO);

          $objAtividadeRN = new AtividadeRN();
          $objAtividadeRN->gerarInternaRN0727($objAtividadeDTO);
        }
      }

      if ($objDocumentoDTO->getStrStaDocumento() == DocumentoRN::$TD_EDITOR_INTERNO) {

        $objEditorDTO = new EditorDTO();
        $objEditorDTO->setDblIdDocumento($objProtocoloDTOGerado->getDblIdProtocolo());
        $objEditorDTO->setNumIdBaseConhecimento(null);
        $objEditorDTO->setNumIdModelo($objDocumentoDTO->getNumIdModeloSerie());

        if ($objDocumentoDTO->isSetDblIdDocumentoBase() && !InfraString::isBolVazia($objDocumentoDTO->getDblIdDocumentoBase())) {

          $objEditorDTO->setDblIdDocumentoBase($objDocumentoDTO->getDblIdDocumentoBase());

        } else if ($objDocumentoDTO->isSetDblIdDocumentoTextoBase() && !InfraString::isBolVazia($objDocumentoDTO->getDblIdDocumentoTextoBase())) {

          $objEditorDTO->setDblIdDocumentoTextoBase($objDocumentoDTO->getDblIdDocumentoTextoBase());

        } else if ($objDocumentoDTO->isSetDblIdDocumentoEdocBase() && !InfraString::isBolVazia($objDocumentoDTO->getDblIdDocumentoEdocBase())) {

          $objEditorDTO->setDblIdDocumentoEdocBase($objDocumentoDTO->getDblIdDocumentoEdocBase());

        } else if ($objDocumentoDTO->getStrConteudo() != null) {

          $objEditorDTO->setStrConteudoSecaoPrincipal($objDocumentoDTO->getStrConteudo());

        } else if ($objDocumentoDTO->isSetNumIdTextoPadraoInterno() && $objDocumentoDTO->getNumIdTextoPadraoInterno() != null) {
          $objEditorDTO->setNumIdTextoPadraoInterno($objDocumentoDTO->getNumIdTextoPadraoInterno());
        }

        $objEditorRN = new EditorRN();
        $objEditorRN->gerarVersaoInicial($objEditorDTO);
      }


      if ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_EXTERNO){
        //Reabertura Autom�tica
        if ($objDocumentoDTO->isSetArrObjUnidadeDTO() && count($objDocumentoDTO->getArrObjUnidadeDTO()) > 0){

          if ($objProtocoloDTOGerado->getStrStaNivelAcessoGlobal()==ProtocoloRN::$NA_SIGILOSO){
            $objInfraException->lancarValidacao('N�o � poss�vel reabrir automaticamente um processo sigiloso.');
          }

          $objUnidadeDTO = new UnidadeDTO();
          $objUnidadeDTO->setBolExclusaoLogica(false);
          $objUnidadeDTO->retStrSigla();
          $objUnidadeDTO->retStrSinProtocolo();
          $objUnidadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());

          $objUnidadeRN = new UnidadeRN();
          $objUnidadeDTO = $objUnidadeRN->consultarRN0125($objUnidadeDTO);

          if ($objUnidadeDTO->getStrSinProtocolo()=='N'){
            $objInfraException->lancarValidacao('Unidade '.$objUnidadeDTO->getStrSigla().' n�o est� sinalizada como protocolo.');
          }

          $arrIdUnidadesReabertura = InfraArray::converterArrInfraDTO($objDocumentoDTO->getArrObjUnidadeDTO(),'IdUnidade');

          $objAtividadeDTO = new AtividadeDTO();
          $objAtividadeDTO->setDistinct(true);
          $objAtividadeDTO->retNumIdUnidade();
          $objAtividadeDTO->setStrStaNivelAcessoGlobalProtocolo(ProtocoloRN::$NA_SIGILOSO, InfraDTO::$OPER_DIFERENTE);
          $objAtividadeDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdProcedimento());
          $objAtividadeDTO->setNumIdTarefa(array(TarefaRN::$TI_GERACAO_PROCEDIMENTO, TarefaRN::$TI_PROCESSO_REMETIDO_UNIDADE), InfraDTO::$OPER_IN);
          $objAtividadeDTO->setNumIdUnidade($arrIdUnidadesReabertura,InfraDTO::$OPER_IN);

          $arrIdUnidadeTramitacao = InfraArray::converterArrInfraDTO($objAtividadeRN->listarRN0036($objAtividadeDTO),'IdUnidade');

          foreach($arrIdUnidadesReabertura as $numIdUnidadeReabertura){
            if (!in_array($numIdUnidadeReabertura, $arrIdUnidadeTramitacao)){

              $objUnidadeDTO = new UnidadeDTO();
              $objUnidadeDTO->setBolExclusaoLogica(false);
              $objUnidadeDTO->retStrSigla();
              $objUnidadeDTO->setNumIdUnidade($numIdUnidadeReabertura);

              $objUnidadeDTO = $objUnidadeRN->consultarRN0125($objUnidadeDTO);

              if ($objUnidadeDTO==null){
                $objInfraException->adicionarValidacao('Unidade ['.$numIdUnidadeReabertura.'] n�o encontrada para reabertura do processo.');
              }else{
                $objInfraException->adicionarValidacao('N�o � poss�vel reabrir o processo na unidade '.$objUnidadeDTO->getStrSigla().' pois n�o ocorreu tramita��o nesta unidade.');
              }
            }
          }

          $objInfraException->lancarValidacoes();

          $objAtividadeDTO = new AtividadeDTO();
          $objAtividadeDTO->setDistinct(true);
          $objAtividadeDTO->retNumIdUnidade();
          $objAtividadeDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdProcedimento());
          $objAtividadeDTO->setNumIdUnidade($arrIdUnidadeTramitacao, InfraDTO::$OPER_IN);
          $objAtividadeDTO->setDthConclusao(null);

          $arrIdUnidadeAberto = InfraArray::converterArrInfraDTO($objAtividadeRN->listarRN0036($objAtividadeDTO),'IdUnidade');

          $objProcedimentoRN = new ProcedimentoRN();
          foreach($arrIdUnidadesReabertura as $numIdUnidadeReabertura){
            if (!in_array($numIdUnidadeReabertura, $arrIdUnidadeAberto)){
              $objReabrirProcessoDTO = new ReabrirProcessoDTO();
              $objReabrirProcessoDTO->setDblIdProcedimento($objDocumentoDTO->getDblIdProcedimento());
              $objReabrirProcessoDTO->setNumIdUnidade($numIdUnidadeReabertura);
              $objReabrirProcessoDTO->setNumIdUsuario(SessaoSEI::getInstance()->getNumIdUsuario());
              $objProcedimentoRN->reabrirRN0966($objReabrirProcessoDTO);
            }
          }
        }
      }

      if ($objDocumentoDTO->getStrStaDocumento() == DocumentoRN::$TD_EDITOR_INTERNO || $objDocumentoDTO->getStrStaDocumento() == DocumentoRN::$TD_FORMULARIO_GERADO) {
        $objSerieEscolhaDTO = new SerieEscolhaDTO();
        $objSerieEscolhaDTO->retNumIdSerie();
        $objSerieEscolhaDTO->setNumIdSerie($objDocumentoDTO->getNumIdSerie());
        $objSerieEscolhaDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
        $objSerieEscolhaDTO->setNumMaxRegistrosRetorno(1);

        $objSerieEscolhaRN = new SerieEscolhaRN();
        if ($objSerieEscolhaRN->consultar($objSerieEscolhaDTO) == null) {
          $objSerieEscolhaRN->cadastrar($objSerieEscolhaDTO);
        }
      }

      $objDocumentoDTORet = new DocumentoDTO();
      $objDocumentoDTORet->setDblIdDocumento($objProtocoloDTOGerado->getDblIdProtocolo());
      $objDocumentoDTORet->setStrProtocoloDocumentoFormatado($objProtocoloDTOGerado->getStrProtocoloFormatado());

      if (count($SEI_MODULOS)){
        $objDocumentoAPI = new DocumentoAPI();
        $objDocumentoAPI->setIdDocumento($objDocumentoDTORet->getDblIdDocumento());
        $objDocumentoAPI->setNumeroProtocolo($objDocumentoDTORet->getStrProtocoloDocumentoFormatado());
        $objDocumentoAPI->setIdProcedimento($objDocumentoDTO->getDblIdProcedimento());
        $objDocumentoAPI->setIdSerie($objDocumentoDTO->getNumIdSerie());
        $objDocumentoAPI->setNivelAcesso($objDocumentoDTO->getObjProtocoloDTO()->getStrStaNivelAcessoLocal());
        $objDocumentoAPI->setSubTipo($objDocumentoDTO->getStrStaDocumento());

        foreach($SEI_MODULOS as $seiModulo){
          $seiModulo->executar('gerarDocumento', $objDocumentoAPI);
        }
      }

      return $objDocumentoDTORet;

    }catch(Exception $e){
      throw new InfraException('Erro gravando documento.',$e);
    }
  }

  protected function gerarNumeracaoControlado(NumeracaoDTO $parObjNumeracaoDTO) {
    try {

      $objInfraException = new InfraException();

      $objNumeracaoDTO = new NumeracaoDTO();
      $objNumeracaoDTO->retNumIdNumeracao();
      $objNumeracaoDTO->retNumSequencial();
      $objNumeracaoDTO->setNumIdSerie($parObjNumeracaoDTO->getNumIdSerie());

      if ($parObjNumeracaoDTO->getStrStaNumeracaoSerie() == SerieRN::$TN_SEQUENCIAL_UNIDADE) {
        $objNumeracaoDTO->setNumIdUnidade($parObjNumeracaoDTO->getNumIdUnidade());
        $objNumeracaoDTO->setNumIdOrgao(null);
        $objNumeracaoDTO->setNumAno(null);
      } else if ($parObjNumeracaoDTO->getStrStaNumeracaoSerie() == SerieRN::$TN_SEQUENCIAL_ORGAO) {
        $objNumeracaoDTO->setNumIdUnidade(null);
        $objNumeracaoDTO->setNumIdOrgao($parObjNumeracaoDTO->getNumIdOrgao());
        $objNumeracaoDTO->setNumAno(null);
      } else if ($parObjNumeracaoDTO->getStrStaNumeracaoSerie() == SerieRN::$TN_SEQUENCIAL_ANUAL_UNIDADE) {
        $objNumeracaoDTO->setNumIdUnidade($parObjNumeracaoDTO->getNumIdUnidade());
        $objNumeracaoDTO->setNumIdOrgao(null);
        $objNumeracaoDTO->setNumAno(Date('Y'));
      } else if ($parObjNumeracaoDTO->getStrStaNumeracaoSerie() == SerieRN::$TN_SEQUENCIAL_ANUAL_ORGAO) {
        $objNumeracaoDTO->setNumIdUnidade(null);
        $objNumeracaoDTO->setNumIdOrgao($parObjNumeracaoDTO->getNumIdOrgao());
        $objNumeracaoDTO->setNumAno(Date('Y'));
      } else {
        $objInfraException->lancarValidacao('Tipo de numera��o inv�lido.');
      }

      $objNumeracaoDTO->setOrdNumSequencial(InfraDTO::$TIPO_ORDENACAO_DESC);

      $objNumeracaoRN = new NumeracaoRN();
      $arrObjNumeracaoDTORet = $objNumeracaoRN->listar($objNumeracaoDTO);

      if (count($arrObjNumeracaoDTORet)==0) {
        try {
          $objNumeracaoDTONovo = clone($objNumeracaoDTO);
          $objNumeracaoDTONovo->setNumSequencial(0);
          $objNumeracaoDTORet = $objNumeracaoRN->cadastrar($objNumeracaoDTONovo);
        }catch(Exception $e){
          $objNumeracaoDTORet = $objNumeracaoRN->consultar($objNumeracaoDTO);
          if ($objNumeracaoDTORet==null){
            throw $e;
          }
        }
      }else{
        $objNumeracaoDTORet = $arrObjNumeracaoDTORet[0];
      }

      $objNumeracaoDTORet = $objNumeracaoRN->bloquear($objNumeracaoDTORet);

      $objNumeracaoDTO = new NumeracaoDTO();
      $objNumeracaoDTO->setNumSequencial($objNumeracaoDTORet->getNumSequencial() + 1);
      $objNumeracaoDTO->setNumIdNumeracao($objNumeracaoDTORet->getNumIdNumeracao());

      $objNumeracaoRN->alterar($objNumeracaoDTO);

      if (count($arrObjNumeracaoDTORet) > 1) {
        $objNumeracaoRN->excluir(array_slice($arrObjNumeracaoDTORet,1));
      }

      return $objNumeracaoDTO;

    }catch(Exception $e){
      throw new InfraException('Erro gerando numera��o de documento.',$e);
    }
  }

  public function alterarRN0004(DocumentoDTO $parObjDocumentoDTO){

    $objDocumentoDTO = new DocumentoDTO();
    $objDocumentoDTO->retStrStaDocumento();
    $objDocumentoDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());
    $objDocumentoDTOBanco = $this->consultarRN0005($objDocumentoDTO);

    if ($objDocumentoDTOBanco==null){
      throw new InfraException('Documento n�o encontrado.');
    }

    $bolAcumulacaoPrevia = FeedSEIProtocolos::getInstance()->isBolAcumularFeeds();

    FeedSEIProtocolos::getInstance()->setBolAcumularFeeds(true);

    $objIndexacaoDTO 	= new IndexacaoDTO();
    $objIndexacaoDTO->setArrIdProtocolos(array($parObjDocumentoDTO->getDblIdDocumento()));

    $objIndexacaoRN	= new IndexacaoRN();

    if ($objDocumentoDTOBanco->getStrStaDocumento()==DocumentoRN::$TD_EXTERNO){
      $objIndexacaoRN->prepararRemocaoProtocolo($objIndexacaoDTO);
    }

    $this->alterarRN0004Interno($parObjDocumentoDTO);

    if ($objDocumentoDTOBanco->getStrStaDocumento()==DocumentoRN::$TD_EXTERNO){
      $objIndexacaoDTO->setStrStaOperacao(IndexacaoRN::$TO_PROTOCOLO_METADADOS_E_CONTEUDO);
    }else {
      $objIndexacaoDTO->setStrStaOperacao(IndexacaoRN::$TO_PROTOCOLO_METADADOS);
    }

    $objIndexacaoRN->indexarProtocolo($objIndexacaoDTO);

    if (!$bolAcumulacaoPrevia){
      FeedSEIProtocolos::getInstance()->setBolAcumularFeeds(false);
      FeedSEIProtocolos::getInstance()->indexarFeeds();
    }
  }

  protected function alterarRN0004InternoControlado(DocumentoDTO $parObjDocumentoDTO){
    try {

      global $SEI_MODULOS;

      $objInfraException = new InfraException();

      $objDocumentoDTO = new DocumentoDTO();
      $objDocumentoDTO->retDblIdDocumento();
      $objDocumentoDTO->retDblIdDocumentoEdoc();
      $objDocumentoDTO->retDblIdProcedimento();
      $objDocumentoDTO->retStrProtocoloProcedimentoFormatado();
      $objDocumentoDTO->retStrStaEstadoProcedimento();
      $objDocumentoDTO->retNumIdSerie();
      $objDocumentoDTO->retNumIdTipoConferencia();
      $objDocumentoDTO->retStrProtocoloDocumentoFormatado();
      $objDocumentoDTO->retStrNumero();
      $objDocumentoDTO->retDtaGeracaoProtocolo();
      $objDocumentoDTO->retNumIdOrgaoUnidadeGeradoraProtocolo();
      $objDocumentoDTO->retNumIdUnidadeGeradoraProtocolo();
      $objDocumentoDTO->retStrSiglaUnidadeGeradoraProtocolo();
      $objDocumentoDTO->retStrStaDocumento();
      $objDocumentoDTO->retStrSinBloqueado();
      $objDocumentoDTO->retNumIdTipoFormulario();
      $objDocumentoDTO->retStrStaNivelAcessoGlobalProtocolo();
      $objDocumentoDTO->retStrStaNivelAcessoLocalProtocolo();
      $objDocumentoDTO->retNumIdTipoProcedimentoProcedimento();
      $objDocumentoDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());
      $objDocumentoDTOBanco = $this->consultarRN0005($objDocumentoDTO);

      switch($objDocumentoDTOBanco->getStrStaDocumento()){

        case DocumentoRN::$TD_EDITOR_INTERNO:
          SessaoSEI::getInstance()->validarAuditarPermissao('documento_alterar', __METHOD__, $parObjDocumentoDTO);
          break;

        case DocumentoRN::$TD_EXTERNO:
          SessaoSEI::getInstance()->validarAuditarPermissao('documento_alterar_recebido', __METHOD__, $parObjDocumentoDTO);
          break;

        case DocumentoRN::$TD_FORMULARIO_AUTOMATICO:
        case DocumentoRN::$TD_FORMULARIO_GERADO:
          SessaoSEI::getInstance()->validarAuditarPermissao('formulario_alterar', __METHOD__, $parObjDocumentoDTO);
          break;
      }

      if ($parObjDocumentoDTO->isSetStrStaDocumento() && $parObjDocumentoDTO->getStrStaDocumento() != $objDocumentoDTOBanco->getStrStaDocumento()){
        $objInfraException->adicionarValidacao('N�o � poss�vel alterar o sinalizador interno do documento.');
      }else{
        $parObjDocumentoDTO->setStrStaDocumento($objDocumentoDTOBanco->getStrStaDocumento());
      }

      if ($parObjDocumentoDTO->isSetDblIdProcedimento() && $parObjDocumentoDTO->getDblIdProcedimento() != $objDocumentoDTOBanco->getDblIdProcedimento()){
        $objInfraException->adicionarValidacao('N�o � poss�vel alterar o processo onde o documento foi cadastrado.');
      }else{
        $objDocumentoDTO->setDblIdProcedimento($objDocumentoDTOBanco->getDblIdProcedimento());
      }

      $objProcedimentoRN = new ProcedimentoRN();
      if ($objDocumentoDTOBanco->getStrStaEstadoProcedimento()==ProtocoloRN::$TE_PROCEDIMENTO_ANEXADO) {
        $objProcedimentoRN->verificarProcessoAnexadorAberto($objDocumentoDTOBanco);
      }else{
        $objProcedimentoRN->verificarEstadoProcedimento($objDocumentoDTOBanco);
      }

      if ($parObjDocumentoDTO->isSetStrConteudoAssinatura()){
        $parObjDocumentoDTO->unSetStrConteudoAssinatura();
      }

      if ($parObjDocumentoDTO->isSetStrCrcAssinatura()){
        $parObjDocumentoDTO->unSetStrCrcAssinatura();
      }

      if ($parObjDocumentoDTO->isSetStrQrCodeAssinatura()){
        $parObjDocumentoDTO->unSetStrQrCodeAssinatura();
      }

      if ($parObjDocumentoDTO->isSetNumIdConjuntoEstilos()){
        $parObjDocumentoDTO->unSetNumIdConjuntoEstilos();
      }

      if ($parObjDocumentoDTO->isSetDblIdDocumentoEdoc()){
        $parObjDocumentoDTO->unSetDblIdDocumentoEdoc();
      }

      if ($parObjDocumentoDTO->isSetStrSinBloqueado() && $parObjDocumentoDTO->getStrSinBloqueado() != $objDocumentoDTOBanco->getStrSinBloqueado()){
        $objInfraException->adicionarValidacao('N�o � poss�vel alterar o sinalizador de bloqueio do documento.');
      }else{
        $parObjDocumentoDTO->setStrSinBloqueado($objDocumentoDTOBanco->getStrSinBloqueado());
      }

      if ($parObjDocumentoDTO->isSetNumIdUnidadeResponsavel()){
        $parObjDocumentoDTO->unSetNumIdUnidadeResponsavel();
      }

      $bolAlterouSerie = false;
      if ($parObjDocumentoDTO->isSetNumIdSerie() && $parObjDocumentoDTO->getNumIdSerie()!=$objDocumentoDTOBanco->getNumIdSerie()) {

        if ($objDocumentoDTOBanco->getStrStaDocumento() == DocumentoRN::$TD_EXTERNO) {
          $this->validarNumIdSerieRN0009($parObjDocumentoDTO, $objInfraException);
          $bolAlterouSerie = true;
        } else {
          $objInfraException->adicionarValidacao('N�o � poss�vel alterar o tipo do documento.');
        }
      }

      if ($parObjDocumentoDTO->isSetNumIdTipoFormulario() && $parObjDocumentoDTO->getNumIdTipoFormulario() != $objDocumentoDTOBanco->getNumIdTipoFormulario()){
        $objInfraException->adicionarValidacao('N�o � poss�vel alterar o tipo do formul�rio do documento.');
      }

      if ($parObjDocumentoDTO->isSetStrNumero() && $parObjDocumentoDTO->getStrNumero()!=$objDocumentoDTOBanco->getStrNumero()) {
        if ($objDocumentoDTOBanco->getStrStaDocumento() == DocumentoRN::$TD_EXTERNO) {
          $this->validarTamanhoNumeroRN0993($parObjDocumentoDTO, $objInfraException);
        } else {

          $objSerieDTO = new SerieDTO();
          $objSerieDTO->setBolExclusaoLogica(false);
          $objSerieDTO->retStrStaNumeracao();
          $objSerieDTO->setNumIdSerie($objDocumentoDTOBanco->getNumIdSerie());

          $objSerieRN = new SerieRN();
          $objSerieDTO = $objSerieRN->consultarRN0644($objSerieDTO);

          if ($objSerieDTO->getStrStaNumeracao()!=SerieRN::$TN_INFORMADA) {
            $objInfraException->adicionarValidacao('N�o � poss�vel alterar o n�mero do documento.');
          }
        }
      }

      //o conteudo � alterado apenas por uma chamada separada
      if ($parObjDocumentoDTO->isSetStrConteudo()){
        $parObjDocumentoDTO->unSetStrConteudo();
      }

      if ($parObjDocumentoDTO->isSetObjProtocoloDTO()){

        $objProtocoloDTO = $parObjDocumentoDTO->getObjProtocoloDTO();

        if ($objProtocoloDTO->isSetDtaGeracao() && $objProtocoloDTO->getDtaGeracao() != $objDocumentoDTOBanco->getDtaGeracaoProtocolo()){

          if ($objDocumentoDTOBanco->getStrStaDocumento() != DocumentoRN::$TD_EXTERNO) {
            $objInfraException->adicionarValidacao('N�o � poss�vel alterar a data do documento.');
          }
        }

        //if ($objDocumentoDTOBanco->getStrStaDocumento() != DocumentoRN::$TD_FORMULARIO_GERADO) {
          if ($objProtocoloDTO->isSetArrObjRelProtocoloAtributoDTO() && count($objProtocoloDTO->getArrObjRelProtocoloAtributoDTO())) {
            throw new InfraException('N�o � poss�vel alterar os atributos do documento.');
          }
        //}

        if ($objProtocoloDTO->isSetArrObjAnexoDTO()){

          if ($objDocumentoDTOBanco->getStrStaDocumento()!=DocumentoRN::$TD_EXTERNO){

            $objProtocoloDTO->unSetArrObjAnexoDTO();

          }else {

            if (count($objProtocoloDTO->getArrObjAnexoDTO()) > 1) {
              throw new InfraException('Mais de um anexo informado para documento recebido.');
            }

            //busca conjunto de anexos antes da altera��o
            $objAnexoDTO = new AnexoDTO();
            $objAnexoDTO->retNumIdAnexo();
            $objAnexoDTO->retStrNome();
            $objAnexoDTO->setDblIdProtocolo($parObjDocumentoDTO->getDblIdDocumento());

            $objAnexoRN = new AnexoRN();
            $arrObjAnexoDTOOriginal = $objAnexoRN->listarRN0218($objAnexoDTO);

          }
        }

        if ($objProtocoloDTO->isSetStrStaNivelAcessoLocal()){

          if ($objDocumentoDTOBanco->getNumIdUnidadeGeradoraProtocolo()!=SessaoSEI::getInstance()->getNumIdUnidadeAtual() && $objProtocoloDTO->getStrStaNivelAcessoLocal()!=$objDocumentoDTOBanco->getStrStaNivelAcessoLocalProtocolo()) {

            $objInfraParametro = new InfraParametro(BancoSEI::getInstance());
            $bolPermitirAlteracaoNivelAcesso = $objInfraParametro->getValor('SEI_ALTERACAO_NIVEL_ACESSO_DOCUMENTO',false);

            if ($bolPermitirAlteracaoNivelAcesso != '1') {
              $objInfraException->adicionarValidacao('N�vel de acesso do documento somente pode ser alterado pela unidade '.$objDocumentoDTOBanco->getStrSiglaUnidadeGeradoraProtocolo().'.');
            }
          }

          $objProcedimentoDTO = new ProcedimentoDTO();
          $objProcedimentoDTO->setStrProtocoloProcedimentoFormatado($objDocumentoDTOBanco->getStrProtocoloProcedimentoFormatado());
          $objProcedimentoDTO->setNumIdTipoProcedimento($objDocumentoDTOBanco->getNumIdTipoProcedimentoProcedimento());
          $objProcedimentoDTO->setStrStaNivelAcessoGlobalProtocolo($objDocumentoDTOBanco->getStrStaNivelAcessoGlobalProtocolo());

          $this->validarNivelAcesso($parObjDocumentoDTO, $objProcedimentoDTO, $objInfraException);
        }


        $objProtocoloRN = new ProtocoloRN();
        $objProtocoloRN->alterarRN0203($objProtocoloDTO);

        /*
        if ($objDocumentoDTOBanco->getStrStaDocumento()==DocumentoRN::$TD_FORMULARIO_GERADO){
          if ($objProtocoloDTO->isSetArrObjRelProtocoloAtributoDTO()){
            $parObjDocumentoDTO->setStrConteudo(self::montarConteudoFormulario($objProtocoloDTO->getArrObjRelProtocoloAtributoDTO()));
          }
        }
        */
      }

      $objInfraException->lancarValidacoes();

      $objDocumentoBD = new DocumentoBD($this->getObjInfraIBanco());
      $objDocumentoBD->alterar($parObjDocumentoDTO);

      if ($objDocumentoDTOBanco->getStrStaDocumento()==DocumentoRN::$TD_EXTERNO) {

        if ($parObjDocumentoDTO->isSetObjProtocoloDTO() && $parObjDocumentoDTO->getObjProtocoloDTO()->isSetArrObjAnexoDTO()) {

          //busca conjunto de anexos ap�s a altera��o
          $objAnexoDTO = new AnexoDTO();
          $objAnexoDTO->retNumIdAnexo();
          $objAnexoDTO->retStrNome();
          $objAnexoDTO->setDblIdProtocolo($parObjDocumentoDTO->getDblIdDocumento());

          $objAnexoRN = new AnexoRN();
          $arrObjAnexoDTONovo = $objAnexoRN->listarRN0218($objAnexoDTO);

          $arrIdAnexoOriginal = InfraArray::converterArrInfraDTO($arrObjAnexoDTOOriginal, 'IdAnexo');
          $arrIdAnexoNovo = InfraArray::converterArrInfraDTO($arrObjAnexoDTONovo, 'IdAnexo');

          sort($arrIdAnexoOriginal);
          sort($arrIdAnexoNovo);

          //verifica se houve altera��o no conte�do (adicionou, removeu ou modificou)
          if ($arrIdAnexoOriginal != $arrIdAnexoNovo) {

            if ($objDocumentoDTOBanco->getStrStaEstadoProcedimento()==ProtocoloRN::$TE_PROCEDIMENTO_ANEXADO) {
              $objInfraException->lancarValidacao('Conte�do do documento n�o pode ser alterado porque o processo est� anexado.');
            }

            $objRelProtocoloProtocoloDTO = new RelProtocoloProtocoloDTO();
            $objRelProtocoloProtocoloDTO->retStrSinCiencia();
            $objRelProtocoloProtocoloDTO->setDblIdProtocolo1($objDocumentoDTOBanco->getDblIdProcedimento());
            $objRelProtocoloProtocoloDTO->setDblIdProtocolo2($objDocumentoDTOBanco->getDblIdDocumento());
            $objRelProtocoloProtocoloDTO->setStrStaAssociacao(RelProtocoloProtocoloRN::$TA_DOCUMENTO_ASSOCIADO);

            $objRelProtocoloProtocoloRN = new RelProtocoloProtocoloRN();
            $objRelProtocoloProtocoloDTO = $objRelProtocoloProtocoloRN->consultarRN0841($objRelProtocoloProtocoloDTO);

            if ($objRelProtocoloProtocoloDTO->getStrSinCiencia() == 'S') {
              $objInfraException->lancarValidacao('Conte�do do documento n�o pode ser alterado porque recebeu ci�ncia.');
            }

            $objArquivamentoDTO = new ArquivamentoDTO();
            $objArquivamentoDTO->retStrStaArquivamento();
            $objArquivamentoDTO->setDblIdProtocolo($parObjDocumentoDTO->getDblIdDocumento());

            $objArquivamentoRN = new ArquivamentoRN();
            $objArquivamentoDTO = $objArquivamentoRN->consultar($objArquivamentoDTO);

            if ($objArquivamentoDTO!=null) {
              if ($objArquivamentoDTO->getStrStaArquivamento() == ArquivamentoRN::$TA_ARQUIVADO || $objArquivamentoDTO->getStrStaArquivamento() == ArquivamentoRN::$TA_SOLICITADO_DESARQUIVAMENTO) {
                $objInfraException->lancarValidacao('Conte�do do documento n�o pode ser alterado porque est� arquivado.');
              }
            }

            if ($objDocumentoDTOBanco->getStrSinBloqueado() == 'S') {
              $objInfraException->lancarValidacao('N�o � mais poss�vel alterar o conte�do do documento.');
            }
          }

          $arrObjAnexoDTOOriginal = InfraArray::indexarArrInfraDTO($arrObjAnexoDTOOriginal, 'IdAnexo');
          $arrObjAnexoDTONovo = InfraArray::indexarArrInfraDTO($arrObjAnexoDTONovo, 'IdAnexo');

          //verifica se removeu pelo menos um anexo
          foreach ($arrObjAnexoDTOOriginal as $objAnexoDTOOriginal) {
            if (!in_array($objAnexoDTOOriginal->getNumIdAnexo(), array_keys($arrObjAnexoDTONovo))) {

              $this->cancelarAssinatura($parObjDocumentoDTO);

              $objProtocoloDTO = new ProtocoloDTO();
              $objProtocoloDTO->setDblIdProtocolo($parObjDocumentoDTO->getDblIdDocumento());

              $objArquivamentoRN = new ArquivamentoRN();
              $objArquivamentoRN->validarProtocoloArquivadoRN1210($objProtocoloDTO);

              $objDocumentoAPI = new DocumentoAPI();
              $objDocumentoAPI->setIdDocumento($parObjDocumentoDTO->getDblIdDocumento());
              
              foreach ($SEI_MODULOS as $seiModulo) {
                $seiModulo->executar('atualizarConteudoDocumento', $objDocumentoAPI);
              }

              break;
            }
          }

          //lan�a um andamento para cada anexo removido
          foreach ($arrObjAnexoDTOOriginal as $objAnexoDTOOriginal) {
            if (!in_array($objAnexoDTOOriginal->getNumIdAnexo(), array_keys($arrObjAnexoDTONovo))) {

              $arrObjAtributoAndamentoDTO = array();
              $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
              $objAtributoAndamentoDTO->setStrNome('ANEXO');
              $objAtributoAndamentoDTO->setStrValor($objAnexoDTOOriginal->getStrNome());
              $objAtributoAndamentoDTO->setStrIdOrigem($objAnexoDTOOriginal->getNumIdAnexo());
              $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;

              $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
              $objAtributoAndamentoDTO->setStrNome('DOCUMENTO');
              $objAtributoAndamentoDTO->setStrValor($objDocumentoDTOBanco->getStrProtocoloDocumentoFormatado());
              $objAtributoAndamentoDTO->setStrIdOrigem($objDocumentoDTOBanco->getDblIdDocumento());
              $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;

              $objAtividadeDTO = new AtividadeDTO();
              $objAtividadeDTO->setDblIdProtocolo($objDocumentoDTOBanco->getDblIdProcedimento());
              $objAtividadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
              $objAtividadeDTO->setNumIdTarefa(TarefaRN::$TI_ARQUIVO_DESANEXADO);
              $objAtividadeDTO->setArrObjAtributoAndamentoDTO($arrObjAtributoAndamentoDTO);

              $objAtividadeRN = new AtividadeRN();
              $objAtividadeRN->gerarInternaRN0727($objAtividadeDTO);
            }
          }

          //lan�a um andamento para cada anexo incluido
          foreach ($arrObjAnexoDTONovo as $objAnexoNovo) {
            if (!in_array($objAnexoNovo->getNumIdAnexo(), array_keys($arrObjAnexoDTOOriginal))) {

              $arrObjAtributoAndamentoDTO = array();
              $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
              $objAtributoAndamentoDTO->setStrNome('ANEXO');
              $objAtributoAndamentoDTO->setStrValor($objAnexoNovo->getStrNome());
              $objAtributoAndamentoDTO->setStrIdOrigem($objAnexoNovo->getNumIdAnexo());
              $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;

              $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
              $objAtributoAndamentoDTO->setStrNome('DOCUMENTO');
              $objAtributoAndamentoDTO->setStrValor($objDocumentoDTOBanco->getStrProtocoloDocumentoFormatado());
              $objAtributoAndamentoDTO->setStrIdOrigem($objDocumentoDTOBanco->getDblIdDocumento());
              $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;

              $objAtividadeDTO = new AtividadeDTO();
              $objAtividadeDTO->setDblIdProtocolo($objDocumentoDTOBanco->getDblIdProcedimento());
              $objAtividadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
              $objAtividadeDTO->setNumIdTarefa(TarefaRN::$TI_ARQUIVO_ANEXADO);
              $objAtividadeDTO->setArrObjAtributoAndamentoDTO($arrObjAtributoAndamentoDTO);

              $objAtividadeRN = new AtividadeRN();
              $objAtividadeRN->gerarInternaRN0727($objAtividadeDTO);
            }
          }
        }
      }

      //validar tipo de confer�ncia ap�s anexos pois a troca de anexo cancela as autentica��es
      if ($parObjDocumentoDTO->isSetNumIdTipoConferencia() && $parObjDocumentoDTO->getNumIdTipoConferencia()!=$objDocumentoDTOBanco->getNumIdTipoConferencia()) {
        if ($objDocumentoDTOBanco->getNumIdUnidadeGeradoraProtocolo()!=SessaoSEI::getInstance()->getNumIdUnidadeAtual()) {
          $objInfraException->adicionarValidacao('Tipo de confer�ncia somente pode ser alterado pela unidade '.$objDocumentoDTOBanco->getStrSiglaUnidadeGeradoraProtocolo().'.');
        }else{

          $this->validarNumIdTipoConferencia($parObjDocumentoDTO, $objInfraException);

          $objAssinaturaDTO = new AssinaturaDTO();
          $objAssinaturaDTO->setDblIdDocumento($objDocumentoDTOBanco->getDblIdDocumento());

          $objAssinaturaRN = new AssinaturaRN();
          $numAssinaturas = $objAssinaturaRN->contarRN1324($objAssinaturaDTO);
          if ($numAssinaturas){
            $objInfraException->adicionarValidacao('Tipo de confer�ncia n�o pode ser alterado porque o documento cont�m '.($numAssinaturas==1?'autentica��o':'autentica��es').'.');
          }else {

            if ($parObjDocumentoDTO->getNumIdTipoConferencia()!=null){
              $objAnexoDTO = new AnexoDTO();
              $objAnexoDTO->retNumIdAnexo();
              $objAnexoDTO->setDblIdProtocolo($objDocumentoDTOBanco->getDblIdDocumento());
              $objAnexoDTO->setNumMaxRegistrosRetorno(1);

              $objAnexoRN = new AnexoRN();
              if ($objAnexoRN->consultarRN0736($objAnexoDTO) == null){
                $objInfraException->adicionarValidacao('Tipo de confer�ncia n�o pode ser informado porque o documento n�o cont�m anexo.');
              }
            }

            $arrObjAtributoAndamentoDTO = array();

            $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
            $objAtributoAndamentoDTO->setStrNome('DOCUMENTO');
            $objAtributoAndamentoDTO->setStrValor($objDocumentoDTOBanco->getStrProtocoloDocumentoFormatado());
            $objAtributoAndamentoDTO->setStrIdOrigem($objDocumentoDTOBanco->getDblIdDocumento());
            $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;

            $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
            $objAtributoAndamentoDTO->setStrNome('TIPO_CONFERENCIA');
            $objAtributoAndamentoDTO->setStrValor(null);
            $objAtributoAndamentoDTO->setStrIdOrigem($parObjDocumentoDTO->getNumIdTipoConferencia());
            $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;

            $objAtividadeDTO = new AtividadeDTO();
            $objAtividadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
            $objAtividadeDTO->setDblIdProtocolo($objDocumentoDTOBanco->getDblIdProcedimento());
            $objAtividadeDTO->setNumIdTarefa(TarefaRN::$TI_ALTERACAO_TIPO_CONFERENCIA_DOCUMENTO);
            $objAtividadeDTO->setArrObjAtributoAndamentoDTO($arrObjAtributoAndamentoDTO);

            $objAtividadeRN = new AtividadeRN();
            $objAtividadeRN->gerarInternaRN0727($objAtividadeDTO);
          }
        }
      }

      $objInfraException->lancarValidacoes();

      if ($bolAlterouSerie){
        $objControleInternoDTO = new ControleInternoDTO();
        $objControleInternoDTO->setDblIdProcedimento($objDocumentoDTOBanco->getDblIdProcedimento());
        $objControleInternoDTO->setNumIdSerie($parObjDocumentoDTO->getNumIdSerie());
        $objControleInternoDTO->setNumIdSerieAnterior($objDocumentoDTOBanco->getNumIdSerie());
        $objControleInternoDTO->setNumIdOrgao($objDocumentoDTOBanco->getNumIdOrgaoUnidadeGeradoraProtocolo());
        $objControleInternoDTO->setStrStaOperacao(ControleInternoRN::$TO_ALTERAR_DOCUMENTO);

        $objControleInternoRN = new ControleInternoRN();
        $objControleInternoRN->processar($objControleInternoDTO);
      }

      //Auditoria

      return $parObjDocumentoDTO;

    }catch(Exception $e){
      throw new InfraException('Erro alterando documento.',$e);
    }
  }

  public function excluirRN0006(DocumentoDTO $parObjDocumentoDTO){

    $bolAcumulacaoPrevia = FeedSEIProtocolos::getInstance()->isBolAcumularFeeds();

    FeedSEIProtocolos::getInstance()->setBolAcumularFeeds(true);

    $objIndexacaoDTO = new IndexacaoDTO();
    $objIndexacaoDTO->setArrIdProtocolos(array($parObjDocumentoDTO->getDblIdDocumento()));

    $objIndexacaoRN	= new IndexacaoRN();
    $objIndexacaoRN->prepararRemocaoProtocolo($objIndexacaoDTO);

    $this->excluirRN0006Interno($parObjDocumentoDTO);

    if (!$bolAcumulacaoPrevia){
      FeedSEIProtocolos::getInstance()->setBolAcumularFeeds(false);
      FeedSEIProtocolos::getInstance()->indexarFeeds();
    }
  }

  protected function excluirRN0006InternoControlado(DocumentoDTO $parObjDocumentoDTO){
    try {

      global $SEI_MODULOS;
      
      //Regras de Negocio
      $objInfraException = new InfraException();

      $objDocumentoDTO = new DocumentoDTO();
      $objDocumentoDTO->retDblIdDocumento();
      $objDocumentoDTO->retNumIdSerie();
      $objDocumentoDTO->retDblIdProcedimento();
      $objDocumentoDTO->retStrProtocoloProcedimentoFormatado();
      $objDocumentoDTO->retStrStaEstadoProcedimento();
      $objDocumentoDTO->retStrStaProtocoloProtocolo();
      $objDocumentoDTO->retStrProtocoloDocumentoFormatado();
      $objDocumentoDTO->retNumIdOrgaoUnidadeGeradoraProtocolo();
      $objDocumentoDTO->retNumIdUnidadeGeradoraProtocolo();
      $objDocumentoDTO->retStrStaNivelAcessoGlobalProtocolo();
      $objDocumentoDTO->retStrStaDocumento();
      $objDocumentoDTO->retStrConteudo();
      $objDocumentoDTO->retStrSinBloqueado();
      $objDocumentoDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());
      $objDocumentoDTO = $this->consultarRN0005($objDocumentoDTO);

      if ($objDocumentoDTO==null){
        //throw new InfraException('Registro n�o encontrado.');
        $objInfraException->lancarValidacao('Documento n�o encontrado.');
      }

      $parObjDocumentoDTO->setStrConteudo($this->obterConteudoAuditoriaExclusaoCancelamento($objDocumentoDTO));

      SessaoSEI::getInstance()->validarAuditarPermissao('documento_excluir',__METHOD__,$parObjDocumentoDTO);

      if($objDocumentoDTO->getNumIdUnidadeGeradoraProtocolo()!= SessaoSEI::getInstance()->getNumIdUnidadeAtual()){
        $objInfraException->lancarValidacao('Documento '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado().' somente pode ser exclu�do pela unidade geradora.');
      }

      if ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_FORMULARIO_AUTOMATICO){
        $objInfraException->lancarValidacao('Formul�rio '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado().' n�o pode ser exclu�do.');
      }

      $objProcedimentoRN = new ProcedimentoRN();
      $objProcedimentoRN->verificarEstadoProcedimento($objDocumentoDTO);

      $objRelProtocoloProtocoloDTO = new RelProtocoloProtocoloDTO();
      $objRelProtocoloProtocoloDTO->retDblIdProtocolo1();
      $objRelProtocoloProtocoloDTO->setDblIdProtocolo2($parObjDocumentoDTO->getDblIdDocumento());
      $objRelProtocoloProtocoloDTO->setStrStaAssociacao(RelProtocoloProtocoloRN::$TA_DOCUMENTO_MOVIDO);
      $objRelProtocoloProtocoloDTO->setNumMaxRegistrosRetorno(1);
      
      $objRelProtocoloProtocoloRN = new RelProtocoloProtocoloRN();
      if ($objRelProtocoloProtocoloRN->consultarRN0841($objRelProtocoloProtocoloDTO) != null){
        $objInfraException->lancarValidacao('N�o foi poss�vel excluir o documento '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado().' porque ele foi movimentado entre processos.');
      }

      $objRelProtocoloProtocoloDTO = new RelProtocoloProtocoloDTO();
      $objRelProtocoloProtocoloDTO->setDblIdProtocolo1($parObjDocumentoDTO->getDblIdDocumento());
      $objRelProtocoloProtocoloDTO->setStrStaAssociacao(RelProtocoloProtocoloRN::$TA_DOCUMENTO_CIRCULAR);

      $objRelProtocoloProtocoloRN = new RelProtocoloProtocoloRN();
      if (($numCircular = $objRelProtocoloProtocoloRN->contarRN0843($objRelProtocoloProtocoloDTO))){
        $objInfraException->lancarValidacao('N�o foi poss�vel excluir o documento '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado().' porque ele � base para '.($numCircular==1?'um documento circular':$numCircular.' documentos circulares').'.');
      }

      if ($objDocumentoDTO->getStrStaProtocoloProtocolo()==ProtocoloRN::$TP_DOCUMENTO_GERADO){
        $this->validarDocumentoPublicadoRN1211($parObjDocumentoDTO);
      }

      if ($objDocumentoDTO->getStrSinBloqueado()=='S'){
        $objInfraException->lancarValidacao('N�o � mais poss�vel excluir o documento '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado().'.');
      }

      $objAcessoExternoDTO = new AcessoExternoDTO();
      $objAcessoExternoDTO->setBolExclusaoLogica(false);
      $objAcessoExternoDTO->retNumIdAcessoExterno();
      $objAcessoExternoDTO->retNumIdTarefaAtividade();
      $objAcessoExternoDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());
      $objAcessoExternoDTO->setStrStaTipo(AcessoExternoRN::$TA_ASSINATURA_EXTERNA);

      $objAcessoExternoRN = new AcessoExternoRN();
      $arrObjAcessoExternoDTO = $objAcessoExternoRN->listar($objAcessoExternoDTO);

      foreach($arrObjAcessoExternoDTO as $objAcessoExternoDTO) {
        if ($objAcessoExternoDTO->getNumIdTarefaAtividade()==TarefaRN::$TI_LIBERACAO_ASSINATURA_EXTERNA){
          $objInfraException->lancarValidacao('N�o foi poss�vel excluir o documento ' . $objDocumentoDTO->getStrProtocoloDocumentoFormatado() . ' porque foi dada libera��o para assinatura externa.');
        }
      }

      if ($objDocumentoDTO->getStrStaNivelAcessoGlobalProtocolo() == ProtocoloRN::$NA_SIGILOSO) {
        $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
        $objAtributoAndamentoDTO->retNumIdAtributoAndamento();
        $objAtributoAndamentoDTO->setStrIdOrigem($objDocumentoDTO->getDblIdDocumento());
        $objAtributoAndamentoDTO->setStrNome('DOCUMENTO');
        $objAtributoAndamentoDTO->setDblIdProtocoloAtividade($objDocumentoDTO->getDblIdProcedimento());
        $objAtributoAndamentoDTO->setNumIdTarefaAtividade(TarefaRN::$TI_CONCESSAO_CREDENCIAL_ASSINATURA);
        $objAtributoAndamentoDTO->setNumMaxRegistrosRetorno(1);

        $objAtributoAndamentoRN = new AtributoAndamentoRN();
        if ($objAtributoAndamentoRN->consultarRN1366($objAtributoAndamentoDTO) != null){
          $objInfraException->lancarValidacao('N�o foi poss�vel excluir o documento '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado().' porque existe Credencial para Assinatura ativa.');
        }
      }

      $objInfraException->lancarValidacoes();

      $objDocumentoAPI = new DocumentoAPI();
      $objDocumentoAPI->setIdDocumento($parObjDocumentoDTO->getDblIdDocumento());

      foreach($SEI_MODULOS as $seiModulo){
        $seiModulo->executar('excluirDocumento', $objDocumentoAPI);
      }

      $objAcessoExternoRN->excluir($arrObjAcessoExternoDTO);

      $objRelProtocoloProtocoloDTO = new RelProtocoloProtocoloDTO();
      $objRelProtocoloProtocoloDTO->retDblIdProtocolo1();
      $objRelProtocoloProtocoloDTO->setDblIdProtocolo2($objDocumentoDTO->getDblIdDocumento());
      $objRelProtocoloProtocoloDTO->setStrStaAssociacao(RelProtocoloProtocoloRN::$TA_DOCUMENTO_ASSOCIADO);

      $objRelProtocoloProtocoloRN = new RelProtocoloProtocoloRN();
      $objRelProtocoloProtocoloDTO = $objRelProtocoloProtocoloRN->consultarRN0841($objRelProtocoloProtocoloDTO);


      $objAssinaturaDTO = new AssinaturaDTO();
      $objAssinaturaDTO->retNumIdAssinatura();
      $objAssinaturaDTO->setBolExclusaoLogica(false); //pode ter assinatura digital pendente de confirma��o
      $objAssinaturaDTO->setDblIdDocumento($objDocumentoDTO->getDblIdDocumento());

      $objAssinaturaRN = new AssinaturaRN();
      $objAssinaturaRN->excluirRN1321($objAssinaturaRN->listarRN1323($objAssinaturaDTO));
       
      if ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_EDITOR_INTERNO){
        $objSecaoDocumentoDTO = new SecaoDocumentoDTO();
        $objSecaoDocumentoDTO->retNumIdSecaoDocumento();
        $objSecaoDocumentoDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());
         
        $objSecaoDocumentoRN = new SecaoDocumentoRN();
        $objSecaoDocumentoRN->excluir($objSecaoDocumentoRN->listar($objSecaoDocumentoDTO));
      }

      $objDocumentoConteudoDTO = new DocumentoConteudoDTO();
      $objDocumentoConteudoDTO->setDblIdDocumento($objDocumentoDTO->getDblIdDocumento());
      $objDocumentoConteudoBD = new DocumentoConteudoBD($this->getObjInfraIBanco());
      if ($objDocumentoConteudoBD->contar($objDocumentoConteudoDTO)){
        $objDocumentoConteudoBD->excluir($objDocumentoConteudoDTO);
      }

      $objDocumentoBD = new DocumentoBD($this->getObjInfraIBanco());
      $objDocumentoBD->excluir($parObjDocumentoDTO);

      $objProtocoloDTO = new ProtocoloDTO();
      $objProtocoloDTO->setDblIdProtocolo($parObjDocumentoDTO->getDblIdDocumento());

      $objProtocoloRN = new ProtocoloRN();
      $objProtocoloRN->excluirRN0748($objProtocoloDTO);
       
      $arrObjAtributoAndamentoDTO = array();
      $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
      $objAtributoAndamentoDTO->setStrNome('DOCUMENTO');
      $objAtributoAndamentoDTO->setStrValor($objDocumentoDTO->getStrProtocoloDocumentoFormatado());
      $objAtributoAndamentoDTO->setStrIdOrigem($objDocumentoDTO->getDblIdDocumento());
      $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;

      $objAtividadeDTO = new AtividadeDTO();
      $objAtividadeDTO->setDblIdProtocolo($objRelProtocoloProtocoloDTO->getDblIdProtocolo1());
      $objAtividadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
      $objAtividadeDTO->setNumIdTarefa(TarefaRN::$TI_EXCLUSAO_DOCUMENTO);
      $objAtividadeDTO->setArrObjAtributoAndamentoDTO($arrObjAtributoAndamentoDTO);

      $objAtividadeRN = new AtividadeRN();
      $objAtividadeRN->gerarInternaRN0727($objAtividadeDTO);


      $objDocumentoDTOEscolha = new DocumentoDTO();
      $objDocumentoDTOEscolha->retDblIdDocumento();
      $objDocumentoDTOEscolha->setNumIdUnidadeGeradoraProtocolo(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
      $objDocumentoDTOEscolha->setNumIdSerie($objDocumentoDTO->getNumIdSerie());
      $objDocumentoDTOEscolha->setNumMaxRegistrosRetorno(1);

      if ($this->consultarRN0005($objDocumentoDTOEscolha) == null){
        $objSerieEscolhaDTO = new SerieEscolhaDTO();
        $objSerieEscolhaDTO->setNumIdSerie($objDocumentoDTO->getNumIdSerie());
        $objSerieEscolhaDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
         
        $objSerieEscolhaRN = new SerieEscolhaRN();
        if ($objSerieEscolhaRN->contar($objSerieEscolhaDTO)==1){
          $objSerieEscolhaRN->excluir(array($objSerieEscolhaDTO));
        }
      }

      $objControleInternoDTO = new ControleInternoDTO();
      $objControleInternoDTO->setDblIdProcedimento($objDocumentoDTO->getDblIdProcedimento());
      $objControleInternoDTO->setNumIdSerie($objDocumentoDTO->getNumIdSerie());
      $objControleInternoDTO->setNumIdOrgao($objDocumentoDTO->getNumIdOrgaoUnidadeGeradoraProtocolo());
      $objControleInternoDTO->setStrStaOperacao(ControleInternoRN::$TO_EXCLUIR_DOCUMENTO);

      $objControleInternoRN = new ControleInternoRN();
      $objControleInternoRN->processar($objControleInternoDTO);

      //Auditoria

    }catch(Exception $e){
      throw new InfraException('Erro excluindo Documento.',$e);
    }
  }

  protected function darCienciaControlado(DocumentoDTO $parObjDocumentoDTO){
    try {

      global $SEI_MODULOS;

      //Valida Permissao
      SessaoSEI::getInstance()->validarAuditarPermissao('documento_ciencia',__METHOD__,$parObjDocumentoDTO);


      //Regras de Negocio
      $objInfraException = new InfraException();

      $objDocumentoDTO = new DocumentoDTO();
      $objDocumentoDTO->retDblIdDocumento();
      $objDocumentoDTO->retDblIdProcedimento();
      $objDocumentoDTO->retStrStaProtocoloProtocolo();
      $objDocumentoDTO->retStrStaDocumento();
      $objDocumentoDTO->retStrProtocoloDocumentoFormatado();
      $objDocumentoDTO->retStrSinBloqueado();

      if (count($SEI_MODULOS)) {
        $objDocumentoDTO->retNumIdSerie();
        $objDocumentoDTO->retNumIdUnidadeGeradoraProtocolo();
        $objDocumentoDTO->retNumIdOrgaoUnidadeGeradoraProtocolo();
        $objDocumentoDTO->retNumIdUsuarioGeradorProtocolo();
        $objDocumentoDTO->retStrStaNivelAcessoGlobalProtocolo();
      }

      $objDocumentoDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());
      $objDocumentoDTO = $this->consultarRN0005($objDocumentoDTO);

      if ($objDocumentoDTO==null){
        $objInfraException->lancarValidacao('Documento n�o encontrado.');
      }

      $objInfraException->lancarValidacoes();

      
      $numVersaoCiencia = null;
      if ($objDocumentoDTO->getStrStaProtocoloProtocolo()==ProtocoloRN::$TP_DOCUMENTO_GERADO){
        if ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_EDITOR_INTERNO){
          $objEditorRN = new EditorRN();
          $numVersaoCiencia = $objEditorRN->obterNumeroUltimaVersao($objDocumentoDTO);
        }else if ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_EDITOR_EDOC){
          $numVersaoCiencia = 0;
        }else{
          $numVersaoCiencia = 0;
        }
      }else{
        $objAnexoDTO = new AnexoDTO();
        $objAnexoDTO->retNumIdAnexo();
        $objAnexoDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdDocumento());
        
        $objAnexoRN = new AnexoRN();
        $objAnexoDTO = $objAnexoRN->consultarRN0736($objAnexoDTO);
        
        if ($objAnexoDTO!=null){
          $numVersaoCiencia = $objAnexoDTO->getNumIdAnexo();
        }else{
          $objInfraException->lancarValidacao('Documento n�o possui conte�do para ci�ncia.');
        }
      }

      $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
      $objAtributoAndamentoDTO->retNumIdAtividade();
      $objAtributoAndamentoDTO->setStrNome('DOCUMENTO');
      $objAtributoAndamentoDTO->setStrIdOrigem($objDocumentoDTO->getDblIdDocumento());
      $objAtributoAndamentoDTO->setNumIdUsuarioOrigemAtividade(SessaoSEI::getInstance()->getNumIdUsuario());
      $objAtributoAndamentoDTO->setDblIdProtocoloAtividade($objDocumentoDTO->getDblIdProcedimento());
      $objAtributoAndamentoDTO->setNumIdTarefaAtividade(TarefaRN::$TI_DOCUMENTO_CIENCIA);

      $objAtributoAndamentoRN = new AtributoAndamentoRN();
      $arrObjAtributoAndamentoDTO = $objAtributoAndamentoRN->listarRN1367($objAtributoAndamentoDTO);
      
      if (count($arrObjAtributoAndamentoDTO)){
        $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
        $objAtributoAndamentoDTO->retNumIdAtributoAndamento();
        $objAtributoAndamentoDTO->setStrNome('VERSAO_CIENCIA');
        $objAtributoAndamentoDTO->setStrValor($numVersaoCiencia);
        $objAtributoAndamentoDTO->setNumIdAtividade(InfraArray::converterArrInfraDTO($arrObjAtributoAndamentoDTO,'IdAtividade'),InfraDTO::$OPER_IN);
        $objAtributoAndamentoDTO->setNumMaxRegistrosRetorno(1);
        
        if ($objAtributoAndamentoRN->consultarRN1366($objAtributoAndamentoDTO) != null){
          $objInfraException->lancarValidacao('Usu�rio j� deu ci�ncia neste documento.');
        }
      }
      
      $arrObjAtributoAndamentoDTO = array();
      $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
      $objAtributoAndamentoDTO->setStrNome('DOCUMENTO');
      $objAtributoAndamentoDTO->setStrValor($objDocumentoDTO->getStrProtocoloDocumentoFormatado());
      $objAtributoAndamentoDTO->setStrIdOrigem($objDocumentoDTO->getDblIdDocumento());
      $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;

      $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
      $objAtributoAndamentoDTO->setStrNome('VERSAO_CIENCIA');
      $objAtributoAndamentoDTO->setStrValor($numVersaoCiencia);
      $objAtributoAndamentoDTO->setStrIdOrigem(null);
      $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;
      
      $objAtividadeDTO = new AtividadeDTO();
      $objAtividadeDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdProcedimento());
      $objAtividadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
      $objAtividadeDTO->setNumIdTarefa(TarefaRN::$TI_DOCUMENTO_CIENCIA);
      $objAtividadeDTO->setArrObjAtributoAndamentoDTO($arrObjAtributoAndamentoDTO);

      $objAtividadeRN = new AtividadeRN();
      $ret = $objAtividadeRN->gerarInternaRN0727($objAtividadeDTO);


      $objRelProtocoloProtocoloDTO = new RelProtocoloProtocoloDTO();
      $objRelProtocoloProtocoloDTO->retDblIdRelProtocoloProtocolo();
      $objRelProtocoloProtocoloDTO->setDblIdProtocolo1($objDocumentoDTO->getDblIdProcedimento());
      $objRelProtocoloProtocoloDTO->setDblIdProtocolo2($objDocumentoDTO->getDblIdDocumento());
      $objRelProtocoloProtocoloDTO->setStrStaAssociacao(RelProtocoloProtocoloRN::$TA_DOCUMENTO_ASSOCIADO);

      $objRelProtocoloProtocoloRN = new RelProtocoloProtocoloRN();
      $objRelProtocoloProtocoloDTO = $objRelProtocoloProtocoloRN->consultarRN0841($objRelProtocoloProtocoloDTO);
      
      $objRelProtocoloProtocoloDTO->setStrSinCiencia('S');
      $objRelProtocoloProtocoloRN->alterar($objRelProtocoloProtocoloDTO);

      $objProcedimentoDTOBanco = new ProcedimentoDTO();
      $objProcedimentoDTOBanco->setDblIdProcedimento($objDocumentoDTO->getDblIdProcedimento());
      
      $objProcedimentoRN = new ProcedimentoRN();
      $objProcedimentoRN->marcarCiencia($objProcedimentoDTOBanco);      
      
      $this->bloquearProcessado($objDocumentoDTO);

      if (count($SEI_MODULOS)) {

        $objDocumentoAPI = new DocumentoAPI();
        $objDocumentoAPI->setIdDocumento($objDocumentoDTO->getDblIdDocumento());
        $objDocumentoAPI->setIdProcedimento($objDocumentoDTO->getDblIdProcedimento());
        $objDocumentoAPI->setNumeroProtocolo($objDocumentoDTO->getStrProtocoloDocumentoFormatado());
        $objDocumentoAPI->setIdSerie($objDocumentoDTO->getNumIdSerie());
        $objDocumentoAPI->setIdUnidadeGeradora($objDocumentoDTO->getNumIdUnidadeGeradoraProtocolo());
        $objDocumentoAPI->setIdOrgaoUnidadeGeradora($objDocumentoDTO->getNumIdOrgaoUnidadeGeradoraProtocolo());
        $objDocumentoAPI->setIdUsuarioGerador($objDocumentoDTO->getNumIdUsuarioGeradorProtocolo());
        $objDocumentoAPI->setTipo($objDocumentoDTO->getStrStaProtocoloProtocolo());
        $objDocumentoAPI->setSubTipo($objDocumentoDTO->getStrStaDocumento());
        $objDocumentoAPI->setNivelAcesso($objDocumentoDTO->getStrStaNivelAcessoGlobalProtocolo());

        foreach ($SEI_MODULOS as $seiModulo) {
          $seiModulo->executar('darCienciaDocumento', $objDocumentoAPI);
        }
      }


      return $ret;

    }catch(Exception $e){
      throw new InfraException('Erro dando ci�ncia no documento.',$e);
    }
  }

  protected function consultarRN0005Conectado(DocumentoDTO $objDocumentoDTO){
    try {

      //Valida Permissao
      SessaoSEI::getInstance()->validarAuditarPermissao('documento_consultar',__METHOD__,$objDocumentoDTO);

      //Regras de Negocio
      //$objInfraException = new InfraException();

      //$objInfraException->lancarValidacoes();

      if ($objDocumentoDTO->isRetObjPublicacaoDTO() || $objDocumentoDTO->isRetArrObjAssinaturaDTO()){
        $objDocumentoDTO->retDblIdDocumento();
        $objDocumentoDTO->retStrStaProtocoloProtocolo();
      }

      $objDocumentoBD = new DocumentoBD($this->getObjInfraIBanco());
      $ret = $objDocumentoBD->consultar($objDocumentoDTO);

      if ($ret !== null){

        if ($objDocumentoDTO->isRetObjPublicacaoDTO()){
          if ($ret->getStrStaProtocoloProtocolo()==ProtocoloRN::$TP_DOCUMENTO_GERADO){

            $objPublicacaoDTO = new PublicacaoDTO();
            $objPublicacaoDTO->retNumIdPublicacao();
            $objPublicacaoDTO->retDblIdDocumento();
            $objPublicacaoDTO->retStrStaEstado();
            $objPublicacaoDTO->retNumIdVeiculoPublicacao();
            $objPublicacaoDTO->retStrStaTipoVeiculoPublicacao();
            $objPublicacaoDTO->retStrNomeVeiculoPublicacao();
            $objPublicacaoDTO->retDtaDisponibilizacao();
            $objPublicacaoDTO->retDtaPublicacao();
            $objPublicacaoDTO->retNumNumero();
            $objPublicacaoDTO->retNumIdVeiculoIO();
            $objPublicacaoDTO->retDtaPublicacaoIO();
            $objPublicacaoDTO->retStrPaginaIO();
            $objPublicacaoDTO->retStrSiglaVeiculoImprensaNacional();
            $objPublicacaoDTO->retStrNomeSecaoImprensaNacional();

            $objPublicacaoDTO->setDblIdDocumento($ret->getDblIdDocumento());

            $objPublicacaoRN = new PublicacaoRN();
            $objPublicacaoDTO = $objPublicacaoRN->consultarRN1044($objPublicacaoDTO);

            $ret->setObjPublicacaoDTO($objPublicacaoDTO);
          }
        }

        if ($objDocumentoDTO->isRetArrObjAssinaturaDTO()){
          //if ($ret->getStrStaProtocoloProtocolo()==ProtocoloRN::$TP_DOCUMENTO_GERADO){
            $objAssinaturaDTO = new AssinaturaDTO();
            //$objAssinaturaDTO->retTodos();
            $objAssinaturaDTO->retNumIdUsuario();
            $objAssinaturaDTO->retNumIdUnidade();
            $objAssinaturaDTO->retStrNome();
            $objAssinaturaDTO->retStrTratamento();

            $objAssinaturaDTO->setDblIdDocumento($ret->getDblIdDocumento());

            $objAssinaturaRN = new AssinaturaRN();
            $ret->setArrObjAssinaturaDTO($objAssinaturaRN->listarRN1323($objAssinaturaDTO));
          //}
        }
      }

      //Auditoria

      return $ret;
    }catch(Exception $e){
      throw new InfraException('Erro consultando Documento.',$e);
    }
  }

  protected function listarRN0008Conectado(DocumentoDTO $parObjDocumentoDTO) {
    try {

      //Valida Permissao
      SessaoSEI::getInstance()->validarAuditarPermissao('documento_listar',__METHOD__,$parObjDocumentoDTO);

      //Regras de Negocio
      //$objInfraException = new InfraException();

      //$objInfraException->lancarValidacoes();

      if ($parObjDocumentoDTO->isRetObjPublicacaoDTO() || $parObjDocumentoDTO->isRetArrObjAssinaturaDTO() || $parObjDocumentoDTO->isRetObjArquivamentoDTO()){
        $parObjDocumentoDTO->retDblIdDocumento();
        $parObjDocumentoDTO->retStrStaProtocoloProtocolo();
      }

      $objDocumentoBD = new DocumentoBD($this->getObjInfraIBanco());
      $arrObjDocumentoDTO = $objDocumentoBD->listar($parObjDocumentoDTO);

      if (count($arrObjDocumentoDTO)){
        if ($parObjDocumentoDTO->isRetObjPublicacaoDTO() || $parObjDocumentoDTO->isRetArrObjAssinaturaDTO() || $parObjDocumentoDTO->isRetObjArquivamentoDTO()){
  
          $arrIdDocumentosGerados = array();
          $arrIdDocumentosRecebidos = array();
          foreach($arrObjDocumentoDTO as $objDocumentoDTO){
            if ($objDocumentoDTO->getStrStaProtocoloProtocolo()==ProtocoloRN::$TP_DOCUMENTO_GERADO){
              $arrIdDocumentosGerados[] = $objDocumentoDTO->getDblIdDocumento();
            }else{
              $arrIdDocumentosRecebidos[] = $objDocumentoDTO->getDblIdDocumento();
            }
          }

          if (count($arrIdDocumentosGerados)) {

            if ($parObjDocumentoDTO->isRetObjPublicacaoDTO()) {

              $objPublicacaoDTO = new PublicacaoDTO();
              $objPublicacaoDTO->retDblIdDocumento();
              $objPublicacaoDTO->retNumIdPublicacao();
              $objPublicacaoDTO->retStrStaEstado();
              $objPublicacaoDTO->retNumIdVeiculoPublicacao();
              $objPublicacaoDTO->retStrStaTipoVeiculoPublicacao();
              $objPublicacaoDTO->retStrNomeVeiculoPublicacao();
              $objPublicacaoDTO->retDtaDisponibilizacao();
              $objPublicacaoDTO->retDtaPublicacao();
              $objPublicacaoDTO->retNumNumero();
              $objPublicacaoDTO->retNumIdVeiculoIO();
              $objPublicacaoDTO->retDtaPublicacaoIO();
              $objPublicacaoDTO->retStrPaginaIO();
              $objPublicacaoDTO->retStrSiglaVeiculoImprensaNacional();
              $objPublicacaoDTO->retStrNomeSecaoImprensaNacional();

              $objPublicacaoDTO->setDblIdDocumento($arrIdDocumentosGerados, InfraDTO::$OPER_IN);

              $objPublicacaoRN = new PublicacaoRN();
              $arrObjPublicacaoDTO = InfraArray::indexarArrInfraDTO($objPublicacaoRN->listarRN1045($objPublicacaoDTO), 'IdDocumento');

              foreach ($arrObjDocumentoDTO as $objDocumentoDTO) {
                if (isset($arrObjPublicacaoDTO[$objDocumentoDTO->getDblIdDocumento()])) {
                  $objDocumentoDTO->setObjPublicacaoDTO($arrObjPublicacaoDTO[$objDocumentoDTO->getDblIdDocumento()]);
                } else {
                  $objDocumentoDTO->setObjPublicacaoDTO(null);
                }
              }
            }

          }

          if (count($arrIdDocumentosRecebidos)) {

            if ($parObjDocumentoDTO->isRetObjArquivamentoDTO()) {

              $objArquivamentoDTO = new ArquivamentoDTO();
              $objArquivamentoDTO->retDblIdProtocolo();
              $objArquivamentoDTO->retStrStaArquivamento();
              $objArquivamentoDTO->retNumIdLocalizador();
              $objArquivamentoDTO->retStrSiglaTipoLocalizador();
              $objArquivamentoDTO->retNumSeqLocalizadorLocalizador();
              $objArquivamentoDTO->retNumIdUnidadeLocalizador();
              $objArquivamentoDTO->setDblIdProtocolo($arrIdDocumentosRecebidos, InfraDTO::$OPER_IN);

              $objArquivamentoRN = new ArquivamentoRN();
              $arrObjArquivamentoDTO = InfraArray::indexarArrInfraDTO($objArquivamentoRN->listar($objArquivamentoDTO), 'IdProtocolo');

              foreach ($arrObjDocumentoDTO as $objDocumentoDTO) {
                if (isset($arrObjArquivamentoDTO[$objDocumentoDTO->getDblIdDocumento()])) {
                  $objDocumentoDTO->setObjArquivamentoDTO($arrObjArquivamentoDTO[$objDocumentoDTO->getDblIdDocumento()]);
                } else {
                  $objDocumentoDTO->setObjArquivamentoDTO(null);
                }
              }
            }
          }

          if ($parObjDocumentoDTO->isRetArrObjAssinaturaDTO()){
            $objAssinaturaDTO = new AssinaturaDTO();
            //$objAssinaturaDTO->retTodos();
            $objAssinaturaDTO->retDblIdDocumento();
            $objAssinaturaDTO->retNumIdUsuario();
            $objAssinaturaDTO->retNumIdUnidade();
            $objAssinaturaDTO->retStrNome();
            $objAssinaturaDTO->retStrTratamento();

            $objAssinaturaDTO->setDblIdDocumento(InfraArray::converterArrInfraDTO($arrObjDocumentoDTO,'IdDocumento'), InfraDTO::$OPER_IN);
          
            $objAssinaturaRN = new AssinaturaRN();
            $arrObjAssinaturaDTO = $objAssinaturaRN->listarRN1323($objAssinaturaDTO);
          
            foreach($arrObjDocumentoDTO as $objDocumentoDTO){
              $arrTemp = array();
              foreach($arrObjAssinaturaDTO as $objAssinaturaDTO){
                if ($objDocumentoDTO->getDblIdDocumento()==$objAssinaturaDTO->getDblIdDocumento()){
                  $arrTemp[] = $objAssinaturaDTO;
                }
              }
              $objDocumentoDTO->setArrObjAssinaturaDTO($arrTemp);
            }
          }
        }
      }
      //Auditoria

      return $arrObjDocumentoDTO;

    }catch(Exception $e){
      throw new InfraException('Erro listando Documentos.',$e);
    }
  }

  protected function contarRN0007Conectado(DocumentoDTO $objDocumentoDTO){
    try {

      //Valida Permissao
      SessaoSEI::getInstance()->validarAuditarPermissao('documento_listar',__METHOD__,$objDocumentoDTO);

      //Regras de Negocio
      //$objInfraException = new InfraException();

      //$objInfraException->lancarValidacoes();

      $objDocumentoBD = new DocumentoBD($this->getObjInfraIBanco());
      $ret = $objDocumentoBD->contar($objDocumentoDTO);

      //Auditoria

      return $ret;
    }catch(Exception $e){
      throw new InfraException('Erro contando Documentos.',$e);
    }
  }

  protected function bloquearControlado(DocumentoDTO $objDocumentoDTO){
    try {

      //Valida Permissao
      SessaoSEI::getInstance()->validarAuditarPermissao('documento_consultar',__METHOD__,$objDocumentoDTO);

      //Regras de Negocio
      //$objInfraException = new InfraException();

      //$objInfraException->lancarValidacoes();

      $objDocumentoBD = new DocumentoBD($this->getObjInfraIBanco());
      $ret = $objDocumentoBD->bloquear($objDocumentoDTO);

      //Auditoria

      return $ret;
    }catch(Exception $e){
      throw new InfraException('Erro bloqueando Documento.',$e);
    }
  }

  private function validarNivelAcesso(DocumentoDTO $objDocumentoDTO, ProcedimentoDTO $objProcedimentoDTO, InfraException $objInfraException)
  {

    $objProtocoloRN = new ProtocoloRN();
    $objProtocoloRN->validarStrStaNivelAcessoLocalRN0685($objDocumentoDTO->getObjProtocoloDTO(), $objInfraException);

    if ((int)$objDocumentoDTO->getObjProtocoloDTO()->getStrStaNivelAcessoLocal() > (int)$objProcedimentoDTO->getStrStaNivelAcessoGlobalProtocolo()) {
      $objNivelAcessoPermitidoDTO = new NivelAcessoPermitidoDTO();
      $objNivelAcessoPermitidoDTO->retNumIdNivelAcessoPermitido();
      $objNivelAcessoPermitidoDTO->setNumIdTipoProcedimento($objProcedimentoDTO->getNumIdTipoProcedimento());
      $objNivelAcessoPermitidoDTO->setStrStaNivelAcesso($objDocumentoDTO->getObjProtocoloDTO()->getStrStaNivelAcessoLocal());
      $objNivelAcessoPermitidoDTO->setNumMaxRegistrosRetorno(1);

      $objNivelAcessoPermitidoRN = new NivelAcessoPermitidoRN();
      if ($objNivelAcessoPermitidoRN->consultar($objNivelAcessoPermitidoDTO) == null) {
        $objInfraException->adicionarValidacao('N�vel de acesso n�o permitido para o tipo do processo '.$objProcedimentoDTO->getStrProtocoloProcedimentoFormatado().'.');
      }
    }
  }

  private function validarStrStaDocumento(DocumentoDTO $objDocumentoDTO, InfraException $objInfraException){
    if (InfraString::isBolVazia($objDocumentoDTO->getStrStaDocumento())){
      $objInfraException->adicionarValidacao('Tipo do documento n�o informado.');
    }
        
    if (//$objDocumentoDTO->getStrStaDocumento()!=DocumentoRN::$TD_EDITOR_EDOC &&
        $objDocumentoDTO->getStrStaDocumento()!=DocumentoRN::$TD_EDITOR_INTERNO &&
        $objDocumentoDTO->getStrStaDocumento()!=DocumentoRN::$TD_EXTERNO &&
        $objDocumentoDTO->getStrStaDocumento()!=DocumentoRN::$TD_FORMULARIO_AUTOMATICO &&
        $objDocumentoDTO->getStrStaDocumento()!=DocumentoRN::$TD_FORMULARIO_GERADO
    ){
      $objInfraException->adicionarValidacao('Tipo do documento ['.$objDocumentoDTO->getStrStaDocumento().'] inv�lido.');
    }
  }

  private function validarDblIdProcedimento(DocumentoDTO $objDocumentoDTO, InfraException $objInfraException){
    if (InfraString::isBolVazia($objDocumentoDTO->getDblIdProcedimento())){
      $objInfraException->adicionarValidacao('Processo n�o informado.');
    }
  }

  private function validarNumIdUnidadeResponsavelRN0915(DocumentoDTO $objDocumentoDTO, InfraException $objInfraException){
    if (InfraString::isBolVazia($objDocumentoDTO->getNumIdUnidadeResponsavel ())){
      $objInfraException->adicionarValidacao('Unidade Respons�vel n�o informada.');
    }
  }

  private function validarNumIdTipoFormulario(DocumentoDTO $objDocumentoDTO, InfraException $objInfraException){
    if (InfraString::isBolVazia($objDocumentoDTO->getNumIdTipoFormulario ())){
      $objInfraException->adicionarValidacao('Tipo do formul�rio n�o informado.');
    }
  }

  private function validarStrCrcAssinatura(DocumentoDTO $objDocumentoDTO, InfraException $objInfraException){
    if (InfraString::isBolVazia($objDocumentoDTO->getStrCrcAssinatura())){
      $objDocumentoDTO->setStrCrcAssinatura(null);
    }else{
      $objDocumentoDTO->setStrCrcAssinatura(strtoupper(trim($objDocumentoDTO->getStrCrcAssinatura())));
      if (strlen($objDocumentoDTO->getStrCrcAssinatura())>8){
        $objInfraException->lancarValidacao('Tamanho do c�digo CRC inv�lido.');
      }
    }
  }

  private function validarStrCodigoVerificador(DocumentoDTO $objDocumentoDTO, InfraException $objInfraException){
    if (InfraString::isBolVazia($objDocumentoDTO->getStrCodigoVerificador())){
      $objDocumentoDTO->setStrCodigoVerificador(null);
    }else{
      $objDocumentoDTO->setStrCodigoVerificador(strtoupper(trim($objDocumentoDTO->getStrCodigoVerificador())));
      if (!is_numeric($objDocumentoDTO->getStrCodigoVerificador())){
        $objInfraException->lancarValidacao('C�digo Verificador inv�lido.');
      }
    }
  }
  
  private function prepararCodigoVerificador($strCodigoVerificador){    
    if (strpos($strCodigoVerificador,'v') !== false){
      $arrCodigoVerificador = explode('v',$strCodigoVerificador);
      $strCodigoVerificador = $arrCodigoVerificador[0];
    }else if (strpos($strCodigoVerificador,'V') !== false){
      $arrCodigoVerificador = explode('V',$strCodigoVerificador);
      $strCodigoVerificador = $arrCodigoVerificador[0];
    }    
    return $strCodigoVerificador;
  }

  private function validarNumIdSerieRN0009(DocumentoDTO $objDocumentoDTO, InfraException $objInfraException){

    $objSerieDTO = null;

    if (InfraString::isBolVazia($objDocumentoDTO->getNumIdSerie())){
      $objInfraException->lancarValidacao('Tipo do documento n�o informado.');
    }else{

    	$objSerieDTO = new SerieDTO();
    	$objSerieDTO->setBolExclusaoLogica(false);
      $objSerieDTO->retNumIdSerie();
    	$objSerieDTO->retStrStaAplicabilidade();
      $objSerieDTO->retNumIdTipoFormulario();
    	$objSerieDTO->retStrNome();
      $objSerieDTO->retNumIdModelo();
      $objSerieDTO->retStrStaNumeracao();
      $objSerieDTO->retStrSinInteressado();
      $objSerieDTO->retStrSinDestinatario();
    	$objSerieDTO->setNumIdSerie($objDocumentoDTO->getNumIdSerie());

    	$objSerieRN = new SerieRN();
    	$objSerieDTO = $objSerieRN->consultarRN0644($objSerieDTO);

      if ($objSerieDTO==null){
        throw new InfraException('Tipo do documento ['.$objDocumentoDTO->getNumIdSerie().'] n�o encontrado.');
      }

      if ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_EDITOR_INTERNO) {
        $strCache = 'SEI_TDR_'.$objDocumentoDTO->getNumIdSerie();
        $arrCache = CacheSEI::getInstance()->getAtributo($strCache);
        if ($arrCache == null) {
          $objSerieRestricaoDTO = new SerieRestricaoDTO();
          $objSerieRestricaoDTO->retNumIdOrgao();
          $objSerieRestricaoDTO->retNumIdUnidade();
          $objSerieRestricaoDTO->setNumIdSerie($objDocumentoDTO->getNumIdSerie());

          $objSerieRestricaoRN = new SerieRestricaoRN();
          $arrObjSerieRestricaoDTO = $objSerieRestricaoRN->listar($objSerieRestricaoDTO);

          $arrCache = array();
          foreach ($arrObjSerieRestricaoDTO as $objSerieRestricaoDTO) {
            $arrCache[$objSerieRestricaoDTO->getNumIdOrgao()][($objSerieRestricaoDTO->getNumIdUnidade() == null ? '*' : $objSerieRestricaoDTO->getNumIdUnidade())] = 0;
          }
          CacheSEI::getInstance()->setAtributo($strCache, $arrCache, CacheSEI::getInstance()->getNumTempo());
        }

        if (count($arrCache) && !isset($arrCache[SessaoSEI::getInstance()->getNumIdOrgaoUnidadeAtual()]['*']) && !isset($arrCache[SessaoSEI::getInstance()->getNumIdOrgaoUnidadeAtual()][SessaoSEI::getInstance()->getNumIdUnidadeAtual()])){
          $objInfraException->adicionarValidacao('Tipo de documento "' . $objSerieDTO->getStrNome() . '" n�o est� liberado para a unidade ' . SessaoSEI::getInstance()->getStrSiglaUnidadeAtual() . '/' . SessaoSEI::getInstance()->getStrSiglaOrgaoUnidadeAtual() . '.');
        }
      }

      switch($objDocumentoDTO->getStrStaDocumento()){

        case DocumentoRN::$TD_EDITOR_INTERNO:
          if ($objSerieDTO->getStrStaAplicabilidade()!=SerieRN::$TA_INTERNO_EXTERNO && $objSerieDTO->getStrStaAplicabilidade()!=SerieRN::$TA_INTERNO){
            $objInfraException->adicionarValidacao('Tipo de documento "'.$objSerieDTO->getStrNome().'" n�o aplic�vel para documentos internos.');
          }

          if ($objSerieDTO->getNumIdModelo()==null){
            $objInfraException->adicionarValidacao('Tipo de documento "'.$objSerieDTO->getStrNome().'" n�o possui modelo associado.');
          }
          break;

        case DocumentoRN::$TD_EXTERNO:
          if ($objSerieDTO->getStrStaAplicabilidade()!=SerieRN::$TA_INTERNO_EXTERNO && $objSerieDTO->getStrStaAplicabilidade()!=SerieRN::$TA_EXTERNO){
            $objInfraException->adicionarValidacao('Tipo de documento "'.$objSerieDTO->getStrNome().'" n�o aplic�vel para documentos externos.');
          }
          break;

        case DocumentoRN::$TD_FORMULARIO_GERADO:
          if ($objSerieDTO->getStrStaAplicabilidade()!=SerieRN::$TA_FORMULARIO){
            $objInfraException->adicionarValidacao('Tipo de documento "'.$objSerieDTO->getStrNome().'" n�o aplic�vel para formul�rios.');
          }

          if ($objSerieDTO->getNumIdTipoFormulario()==null){
            $objInfraException->adicionarValidacao('Tipo de documento "'.$objSerieDTO->getStrNome().'" n�o possui modelo de formul�rio associado.');
          }
          break;

        case DocumentoRN::$TD_FORMULARIO_AUTOMATICO:
          //pode usar qualquer tipo de documento
          break;

        default:
          throw new InfraException('Sinalizador interno do documento inv�lido.');
      }
    }
    return $objSerieDTO;
  }

  private function validarTamanhoNumeroRN0993(DocumentoDTO $objDocumentoDTO, InfraException $objInfraException){
    $objDocumentoDTO->setStrNumero(trim($objDocumentoDTO->getStrNumero()));
    if (strlen($objDocumentoDTO->getStrNumero())>50){
      $objInfraException->adicionarValidacao('N�mero possui tamanho superior a 50 caracteres.');
    }
  }

  private function validarStrConteudo(DocumentoDTO $objDocumentoDTO, InfraException $objInfraException){
    if (InfraString::isBolVazia($objDocumentoDTO->getStrConteudo())){
      $objDocumentoDTO->setStrConteudo(null);
    }
  }

  private function validarNumIdTipoConferencia(DocumentoDTO $objDocumentoDTO, InfraException $objInfraException){

    if (InfraString::isBolVazia($objDocumentoDTO->getNumIdTipoConferencia())){

      $objDocumentoDTO->setNumIdTipoConferencia(null);

    }else {

      if ($objDocumentoDTO->getStrStaDocumento() == DocumentoRN::$TD_EXTERNO) {

        $objTipoConferenciaDTO = new TipoConferenciaDTO();
        $objTipoConferenciaDTO->setBolExclusaoLogica(false);
        $objTipoConferenciaDTO->retNumIdTipoConferencia();
        $objTipoConferenciaDTO->setNumIdTipoConferencia($objDocumentoDTO->getNumIdTipoConferencia());

        $objTipoConferenciaRN = new TipoConferenciaRN();
        if ($objTipoConferenciaRN->consultar($objTipoConferenciaDTO) == null) {
          $objInfraException->adicionarValidacao('Tipo de Confer�ncia n�o encontrada.');
        }

      } else {
        $objInfraException->adicionarValidacao('Tipo de Confer�ncia n�o aplic�vel ao documento.');
      }
    }
  }
  
  protected function prepararCloneRN1110Conectado(DocumentoDTO $objDocumentoDTO){
    try{
      
      //Recuperar os dados do documento para clonagem
      $objDocumentoCloneDTO = new DocumentoDTO();
      $objDocumentoCloneDTO->retNumIdUnidadeResponsavel();
      $objDocumentoCloneDTO->retDblIdDocumentoEdoc();
      $objDocumentoCloneDTO->retNumIdSerie();
      $objDocumentoCloneDTO->retNumIdTipoConferencia();
      $objDocumentoCloneDTO->retStrNumero();
      $objDocumentoCloneDTO->retStrDescricaoProtocolo();
      $objDocumentoCloneDTO->retStrConteudo();
      $objDocumentoCloneDTO->retStrStaProtocoloProtocolo();
      $objDocumentoCloneDTO->retDtaGeracaoProtocolo();
      $objDocumentoCloneDTO->retStrStaDocumento();
      $objDocumentoCloneDTO->retStrStaNivelAcessoLocalProtocolo();
      $objDocumentoCloneDTO->retNumIdHipoteseLegalProtocolo();


      $objDocumentoCloneDTO->setDblIdDocumento($objDocumentoDTO->getDblIdDocumento());
      $objDocumentoCloneDTO = $this->consultarRN0005($objDocumentoCloneDTO);

      $objDocumentoCloneDTO->setDblIdProcedimento($objDocumentoDTO->getDblIdProcedimento());


      $objDocumentoCloneDTO->setNumIdUnidadeResponsavel(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
      
      if ($objDocumentoCloneDTO->getStrStaDocumento() == DocumentoRN::$TD_EDITOR_INTERNO){
        
        $objSerieDTO = new SerieDTO();
        $objSerieDTO->setBolExclusaoLogica(false);
        $objSerieDTO->retStrStaNumeracao();
        $objSerieDTO->setNumIdSerie($objDocumentoCloneDTO->getNumIdSerie());
        
        $objSerieRN = new SerieRN();
        $objSerieDTO = $objSerieRN->consultarRN0644($objSerieDTO);
        
        if ($objSerieDTO->getStrStaNumeracao()==SerieRN::$TN_SEQUENCIAL_ANUAL_ORGAO ||
            $objSerieDTO->getStrStaNumeracao()==SerieRN::$TN_SEQUENCIAL_ANUAL_UNIDADE ||
            $objSerieDTO->getStrStaNumeracao()==SerieRN::$TN_SEQUENCIAL_ORGAO ||
            $objSerieDTO->getStrStaNumeracao()==SerieRN::$TN_SEQUENCIAL_UNIDADE){
          $objDocumentoCloneDTO->setStrNumero(null);
        }
      }
      
      
      $objDocumentoCloneDTO->setDblIdDocumentoEdocBase(null);
      $objDocumentoCloneDTO->setDblIdDocumentoBase(null);
      
      //usa o documento original como base
      if ($objDocumentoCloneDTO->getStrStaProtocoloProtocolo() == ProtocoloRN::$TP_DOCUMENTO_GERADO){
        if ($objDocumentoCloneDTO->getStrStaDocumento()==DocumentoRN::$TD_EDITOR_EDOC){
          $objDocumentoCloneDTO->setDblIdDocumentoEdocBase($objDocumentoCloneDTO->getDblIdDocumentoEdoc());
          $objDocumentoCloneDTO->setStrStaDocumento(DocumentoRN::$TD_EDITOR_INTERNO);
        }else if ($objDocumentoCloneDTO->getStrStaDocumento()==DocumentoRN::$TD_EDITOR_INTERNO){
          $objDocumentoCloneDTO->setDblIdDocumentoBase($objDocumentoDTO->getDblIdDocumento());
        }
      }else{
        $objDocumentoCloneDTO->setDblIdDocumentoEdoc(null);
      }

      $objProtocoloDTO = new ProtocoloDTO();
      $objProtocoloDTO->setDtaGeracao(InfraData::getStrDataAtual());
      $objProtocoloDTO->setStrDescricao($objDocumentoCloneDTO->getStrDescricaoProtocolo());
      $objProtocoloDTO->setStrStaNivelAcessoLocal($objDocumentoCloneDTO->getStrStaNivelAcessoLocalProtocolo());

      if ($objDocumentoCloneDTO->getStrStaNivelAcessoLocalProtocolo()!=ProtocoloRN::$NA_PUBLICO) {
        $objProtocoloDTO->setNumIdHipoteseLegal($objDocumentoCloneDTO->getNumIdHipoteseLegalProtocolo());
      }

      //Recuperar em ArrAssuntos os assuntos
      $objRelProtocoloAssuntoDTO = new RelProtocoloAssuntoDTO();
      $objRelProtocoloAssuntoDTO->setDistinct(true);
      $objRelProtocoloAssuntoDTO->retNumIdAssunto();
      $objRelProtocoloAssuntoDTO->retNumSequencia();
      $objRelProtocoloAssuntoDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdDocumento());

      $objRelProtocoloAssuntoRN = new RelProtocoloAssuntoRN();
      $arrAssuntos = $objRelProtocoloAssuntoRN->listarRN0188($objRelProtocoloAssuntoDTO);
      $objProtocoloDTO->setArrObjRelProtocoloAssuntoDTO($arrAssuntos);

      //Recuperar em ArrParticipantes os participantes
      $objPartipantesDTO = new ParticipanteDTO();
      $objPartipantesDTO->retNumIdContato();
      $objPartipantesDTO->retStrStaParticipacao();
      $objPartipantesDTO->retNumSequencia();
      $objPartipantesDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdDocumento());

      $objPartipantesRN = new ParticipanteRN();
      $arrParticipantes = $objPartipantesRN->listarRN0189($objPartipantesDTO);
      $objProtocoloDTO->setArrObjParticipanteDTO($arrParticipantes);

      $objRelProtocoloAtributoDTO = new RelProtocoloAtributoDTO();
      $objRelProtocoloAtributoDTO->retNumIdAtributo();
      $objRelProtocoloAtributoDTO->retStrValor();
      $objRelProtocoloAtributoDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdDocumento());

      $objRelProtocoloAtributoRN = new RelProtocoloAtributoRN();
      $arrAtributos = $objRelProtocoloAtributoRN->listar($objRelProtocoloAtributoDTO);

      $objProtocoloDTO->setArrObjRelProtocoloAtributoDTO($arrAtributos);

      //Recuperar em ArrObservacoes as observacoes desta unidade
      $objObservacaoDTO = new ObservacaoDTO();
      $objObservacaoDTO->retStrDescricao();

      $objObservacaoDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
      $objObservacaoDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdDocumento());

      $objObservacaoRN = new ObservacaoRN();
      $arrObservacoes = $objObservacaoRN->listarRN0219($objObservacaoDTO);

      $objProtocoloDTO->setArrObjObservacaoDTO($arrObservacoes);

      $objAnexoDTO = new AnexoDTO();
      $objAnexoDTO->retNumIdAnexo();
      $objAnexoDTO->retStrNome();
      $objAnexoDTO->retNumTamanho();
      $objAnexoDTO->retDthInclusao();
      $objAnexoDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdDocumento());

      $objAnexoRN = new AnexoRN();
      $arrObjAnexoDTO = $objAnexoRN->listarRN0218($objAnexoDTO);

      foreach($arrObjAnexoDTO as $objAnexoDTO){

        $strNomeUpload = $objAnexoRN->gerarNomeArquivoTemporario();

        $strNomeUploadCompleto = DIR_SEI_TEMP.'/'.$strNomeUpload;
        copy($objAnexoRN->obterLocalizacao($objAnexoDTO), $strNomeUploadCompleto);

        $objAnexoDTO->setNumIdAnexo($strNomeUpload);
        $objAnexoDTO->setDthInclusao(InfraData::getStrDataHoraAtual());
        $objAnexoDTO->setStrSinDuplicando('S');
      }

      $objProtocoloDTO->setArrObjAnexoDTO($arrObjAnexoDTO);

      $objDocumentoCloneDTO->setObjProtocoloDTO($objProtocoloDTO);

      $objDocumentoCloneDTO->setDblIdDocumento(null);

      return $objDocumentoCloneDTO;

    }catch(Exception $e){
      throw new InfraException('Erro preparando clone do documento.',$e);
    }
  }

  protected function agruparRN1116Controlado(ProtocoloDTO $objProtocoloRecebidoDTO) {
    try{

      //Regras de Negocio
      //$objInfraException = new InfraException();

      //Obter dados da publica��o atrav�s da Publicacao
      $objProtocoloDTO = new ProtocoloDTO();
      $objProtocoloDTO->setDblIdProtocolo($objProtocoloRecebidoDTO->getDblIdProtocolo());
      $objProtocoloDTO->setDblIdProtocoloAgrupador($objProtocoloRecebidoDTO->getDblIdProtocoloAgrupador());

      $objProtocoloBD = new ProtocoloBD($this->getObjInfraIBanco());
      $objProtocoloBD->alterar($objProtocoloDTO);


    }catch(Exception $e){
      throw new InfraException('Erro agrupando Protocolo.',$e);
    }
  }

  private function tratarProtocoloRN1164(DocumentoDTO $objDocumentoDTO) {
    try{

      $objProtocoloDTO = $objDocumentoDTO->getObjProtocoloDTO();

      $objProtocoloDTO->setDblIdProcedimento($objDocumentoDTO->getDblIdProcedimento());

      $objProtocoloDTO->setStrProtocoloFormatado(null);

      if ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_EXTERNO) {
        $objProtocoloDTO->setStrStaProtocolo(ProtocoloRN::$TP_DOCUMENTO_RECEBIDO);
      }else{
        $objProtocoloDTO->setStrStaProtocolo(ProtocoloRN::$TP_DOCUMENTO_GERADO);
      }

      if (!$objProtocoloDTO->isSetNumIdUnidadeGeradora()){
        $objProtocoloDTO->setNumIdUnidadeGeradora(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
      }

      if (!$objProtocoloDTO->isSetNumIdUsuarioGerador()){
        $objProtocoloDTO->setNumIdUsuarioGerador(SessaoSEI::getInstance()->getNumIdUsuario());
      }

      if (!$objProtocoloDTO->isSetDtaGeracao()){
        $objProtocoloDTO->setDtaGeracao(InfraData::getStrDataAtual());
      }

      if (!$objProtocoloDTO->isSetArrObjRelProtocoloAssuntoDTO()){
        $objProtocoloDTO->setArrObjRelProtocoloAssuntoDTO(array());
      }

      $objDocumentoDTO->setObjProtocoloDTO($objProtocoloDTO);

    }catch(Exception $e){
      throw new InfraException('Erro tratando protocolo do documento.',$e);
    }
  }

  protected function gerarPublicacaoRelacionadaRN1207Controlado(DocumentoDTO $parObjDocumentoDTO){
    try {

      //Valida Permissao
      SessaoSEI::getInstance()->validarAuditarPermissao('publicacao_gerar_relacionada',__METHOD__,$parObjDocumentoDTO);

      //Regras de Negocio
      $objInfraException = new InfraException();

      $objDocumentoDTO = new DocumentoDTO();
      $objDocumentoDTO->retDblIdDocumento();
      $objDocumentoDTO->retDblIdProcedimento();
      $objDocumentoDTO->retObjPublicacaoDTO();
      $objDocumentoDTO->retDtaGeracaoProtocolo();
      $objDocumentoDTO->retStrNumero();
      $objDocumentoDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());

      $objDocumentoDTO = $this->consultarRN0005($objDocumentoDTO);

      $objPublicacaoDTO = $objDocumentoDTO->getObjPublicacaoDTO();

      if ($objPublicacaoDTO==null){
        $objInfraException->lancarValidacao('Documento n�o foi publicado.');
      }

      if ($objPublicacaoDTO->getStrStaEstado()==PublicacaoRN::$TE_AGENDADO){
        $objInfraException->lancarValidacao('Documento ainda n�o foi publicado.');
      }

      //Clonar o documento
      $objDocumentoClonarDTO = new DocumentoDTO();
      $objDocumentoClonarDTO->setDblIdDocumento($objDocumentoDTO->getDblIdDocumento());
      $objDocumentoClonarDTO->setDblIdProcedimento($objDocumentoDTO->getDblIdProcedimento());
      $objDocumentoClonarDTO = $this->prepararCloneRN1110($objDocumentoClonarDTO);

      if ($objDocumentoDTO->getStrNumero()!=null && $objDocumentoClonarDTO->getStrNumero()==null){
        $objDocumentoClonarDTO->setStrNumero($objDocumentoDTO->getStrNumero());
      }

      $parObjDocumentoDTO->getObjProtocoloDTO()->setDtaGeracao($objDocumentoDTO->getDtaGeracaoProtocolo());
      $objDocumentoClonarDTO->setObjProtocoloDTO($parObjDocumentoDTO->getObjProtocoloDTO());

      $ret = $this->cadastrarRN0003($objDocumentoClonarDTO);

      //Recuperar o protocolo agrupador
      $objProtocoloDTO = new ProtocoloDTO();
      $objProtocoloDTO->retDblIdProtocoloAgrupador();
      $objProtocoloDTO->setDblIdProtocolo($parObjDocumentoDTO->getDblIdDocumento());

      $objProtocoloRN = new ProtocoloRN();
      $objProtocoloDTO = $objProtocoloRN->consultarRN0186($objProtocoloDTO);

      // Agrupar os protocolos
      $dto = new ProtocoloDTO();
      $dto->setDblIdProtocolo($ret->getDblIdDocumento());
      $dto->setDblIdProtocoloAgrupador($objProtocoloDTO->getDblIdProtocoloAgrupador());
      $this->agruparRN1116($dto);

      return $ret;

    }catch(Exception $e){
      throw new InfraException('Erro gerando publica��o relacionada.',$e);
    }
  }

  public function atualizarConteudoRN1205(DocumentoDTO $objDocumentoDTO){

    $bolAcumulacaoPrevia = FeedSEIProtocolos::getInstance()->isBolAcumularFeeds();

    FeedSEIProtocolos::getInstance()->setBolAcumularFeeds(true);

    $this->atualizarConteudoRN1205Interno($objDocumentoDTO);

    if ($objDocumentoDTO->isSetDblIdDocumento()){

      $objIndexacaoDTO = new IndexacaoDTO();
      $objIndexacaoDTO->setArrIdProtocolos(array($objDocumentoDTO->getDblIdDocumento()));
      $objIndexacaoDTO->setStrStaOperacao(IndexacaoRN::$TO_PROTOCOLO_METADADOS_E_CONTEUDO);

      $objIndexacaoRN = new IndexacaoRN();
      $objIndexacaoRN->indexarProtocolo($objIndexacaoDTO);
    }

    if (!$bolAcumulacaoPrevia){
      FeedSEIProtocolos::getInstance()->setBolAcumularFeeds(false);
      FeedSEIProtocolos::getInstance()->indexarFeeds();
    }
  }

  protected function configurarEstilosControlado(DocumentoDTO $parObjDocumentoDTO){
    try {

      $objDocumentoDTO = new DocumentoDTO();
      $objDocumentoDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());
      $objDocumentoDTO->setNumIdConjuntoEstilos($parObjDocumentoDTO->getNumIdConjuntoEstilos());

      $objDocumentoBD = new DocumentoBD($this->getObjInfraIBanco());
      $objDocumentoBD->alterar($objDocumentoDTO);

      //Auditoria

    }catch(Exception $e){
      throw new InfraException('Erro configurando estilos do documento.',$e);
    }
  }

  protected function bloquearConteudoControlado(DocumentoDTO $parObjDocumentoDTO){
    try {

      $objDocumentoDTO = new DocumentoDTO();
      $objDocumentoDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());
      $objDocumentoDTO->setStrSinBloqueado('S');

      $objDocumentoBD = new DocumentoBD($this->getObjInfraIBanco());
      $objDocumentoBD->alterar($objDocumentoDTO);

    }catch(Exception $e){
      throw new InfraException('Erro bloqueando documento.',$e);
    }
  }

  protected function desbloquearConteudoControlado(DocumentoDTO $parObjDocumentoDTO){
    try {

      $objDocumentoDTO = new DocumentoDTO();
      $objDocumentoDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());
      $objDocumentoDTO->setStrSinBloqueado('N');

      $objDocumentoBD = new DocumentoBD($this->getObjInfraIBanco());
      $objDocumentoBD->alterar($objDocumentoDTO);

    }catch(Exception $e){
      throw new InfraException('Erro desbloqueando documento.',$e);
    }
  }

  protected function bloquearConsultadoConectado(DocumentoDTO $parObjDocumentoDTO){
    try {

      $bolBloquear = false;

  		if ($parObjDocumentoDTO->getStrSinBloqueado()=='N' && SessaoSEI::getInstance()->getNumIdUnidadeAtual()!=$parObjDocumentoDTO->getNumIdUnidadeGeradoraProtocolo()){

        if ($parObjDocumentoDTO->getStrStaProtocoloProtocolo() == ProtocoloRN::$TP_DOCUMENTO_GERADO) {

          if ($parObjDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_FORMULARIO_AUTOMATICO){

            //formul�rios autom�ticos
            $bolBloquear = true;

          }else{
            $objAssinaturaDTO = new AssinaturaDTO();
            $objAssinaturaDTO->retNumIdAssinatura();
            $objAssinaturaDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());
            $objAssinaturaDTO->setNumMaxRegistrosRetorno(1);

            $objAssinaturaRN = new AssinaturaRN();

            if ($objAssinaturaRN->consultarRN1322($objAssinaturaDTO) != null) {

              $objRelBlocoUnidadeDTO = new RelBlocoUnidadeDTO();
              $objRelBlocoUnidadeDTO->retNumIdBloco();
              $objRelBlocoUnidadeDTO->setStrStaTipoBloco(BlocoRN::$TB_ASSINATURA);
              $objRelBlocoUnidadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
              $objRelBlocoUnidadeDTO->setStrSinRetornado('N');
              $objRelBlocoUnidadeDTO->setStrStaEstadoBloco(BlocoRN::$TE_DISPONIBILIZADO);

              $objRelBlocoUnidadeRN = new RelBlocoUnidadeRN();
              $arrObjRelBlocoUnidadeDTO = $objRelBlocoUnidadeRN->listarRN1304($objRelBlocoUnidadeDTO);

              //se tem blocos de assinatura disponibilizados
              if (count($arrObjRelBlocoUnidadeDTO)) {

                //se o documento consta como disponibilizado para assinatura
                $objRelBlocoProtocoloDTO = new RelBlocoProtocoloDTO();
                $objRelBlocoProtocoloDTO->retDblIdProtocolo();
                $objRelBlocoProtocoloDTO->setDblIdProtocolo($parObjDocumentoDTO->getDblIdDocumento());
                $objRelBlocoProtocoloDTO->setNumIdBloco(InfraArray::converterArrInfraDTO($arrObjRelBlocoUnidadeDTO, 'IdBloco'), InfraDTO::$OPER_IN);
                $objRelBlocoProtocoloDTO->setNumMaxRegistrosRetorno(1);

                $objRelBlocoProtocoloRN = new RelBlocoProtocoloRN();
                if ($objRelBlocoProtocoloRN->consultarRN1290($objRelBlocoProtocoloDTO) != null) {
                  //a unidade que possui o documento disponibilizado nao deve bloquear mesmo nao sendo a geradora
                  return;
                }
              }

              //gerado e assinado
              $bolBloquear = true;
            }
          }
        }else if ($parObjDocumentoDTO->getStrStaProtocoloProtocolo() == ProtocoloRN::$TP_DOCUMENTO_RECEBIDO) {

          $objAnexoDTO = new AnexoDTO();
          $objAnexoDTO->retNumIdAnexo();
          $objAnexoDTO->setDblIdProtocolo($parObjDocumentoDTO->getDblIdDocumento());
          $objAnexoDTO->setNumMaxRegistrosRetorno(1);

          $objAnexoRN = new AnexoRN();
          if ($objAnexoRN->consultarRN0736($objAnexoDTO) != null) {

            //externo com conte�do
            $bolBloquear = true;
          }
        }

        if ($bolBloquear) {
          $this->bloquearConteudo($parObjDocumentoDTO);
        }
 		  }
      //Auditoria

    }catch(Exception $e){
      throw new InfraException('Erro bloqueando documento por consulta.',$e);
    }
  }

  protected function bloquearProcessadoConectado(DocumentoDTO $parObjDocumentoDTO){
    try {

      $bolBloquear = false;

      if ($parObjDocumentoDTO->getStrSinBloqueado()=='N') {

        if ($parObjDocumentoDTO->getStrStaProtocoloProtocolo() == ProtocoloRN::$TP_DOCUMENTO_GERADO) {

          if ($parObjDocumentoDTO->getStrStaDocumento() == DocumentoRN::$TD_FORMULARIO_AUTOMATICO) {

            //formul�rios autom�ticos
            $bolBloquear = true;

          } else {
            $objAssinaturaDTO = new AssinaturaDTO();
            $objAssinaturaDTO->retNumIdAssinatura();
            $objAssinaturaDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());
            $objAssinaturaDTO->setNumMaxRegistrosRetorno(1);

            $objAssinaturaRN = new AssinaturaRN();
            if ($objAssinaturaRN->consultarRN1322($objAssinaturaDTO) != null) {

              //gerado com assinatura
              $bolBloquear = true;
            }
          }
        } else if ($parObjDocumentoDTO->getStrStaProtocoloProtocolo() == ProtocoloRN::$TP_DOCUMENTO_RECEBIDO) {

          $objAnexoDTO = new AnexoDTO();
          $objAnexoDTO->retNumIdAnexo();
          $objAnexoDTO->setDblIdProtocolo($parObjDocumentoDTO->getDblIdDocumento());
          $objAnexoDTO->setNumMaxRegistrosRetorno(1);

          $objAnexoRN = new AnexoRN();
          if ($objAnexoRN->consultarRN0736($objAnexoDTO) != null) {

            //externo com conte�do
            $bolBloquear = true;
          }
        }

        if ($bolBloquear) {
          $this->bloquearConteudo($parObjDocumentoDTO);
        }
      }
        //Auditoria

    }catch(Exception $e){
      throw new InfraException('Erro bloqueando documento por processamento.',$e);
    }
  }

  protected function bloquearTramitacaoConclusaoConectado(ProcedimentoDTO $objProcedimentoDTO){
    try {

      $objDocumentoDTO = new DocumentoDTO();
      $objDocumentoDTO->retDblIdDocumento();
      $objDocumentoDTO->retStrSinBloqueado();
      $objDocumentoDTO->retStrStaDocumento();
      $objDocumentoDTO->retStrStaProtocoloProtocolo();
      $objDocumentoDTO->setNumIdUnidadeGeradoraProtocolo(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
      $objDocumentoDTO->setDblIdProcedimento($objProcedimentoDTO->getDblIdProcedimento());
      $objDocumentoDTO->setStrSinBloqueado('N');

      $arrObjDocumentoDTO = $this->listarRN0008($objDocumentoDTO);

      foreach($arrObjDocumentoDTO as $objDocumentoDTO){
        $this->bloquearProcessado($objDocumentoDTO);
      }

      //Auditoria

    }catch(Exception $e){
      throw new InfraException('Erro bloqueando documento por tramita��o ou conclus�o do processo.',$e);
    }
  }

  protected function bloquearPublicadoConectado(DocumentoDTO $parObjDocumentoDTO){
    try {

      $objDocumentoDTO = new DocumentoDTO();
      $objDocumentoDTO->retStrSinBloqueado();
      $objDocumentoDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());
      $objDocumentoDTO = $this->consultarRN0005($objDocumentoDTO);

      if ($objDocumentoDTO->getStrSinBloqueado()=='N'){
        $this->bloquearConteudo($parObjDocumentoDTO);
      }

      //Auditoria

    }catch(Exception $e){
      throw new InfraException('Erro bloqueando documento por publica��o.',$e);
    }
  }

  protected function atualizarConteudoRN1205InternoControlado(DocumentoDTO $parObjDocumentoDTO){
    try {

      global $SEI_MODULOS;

      //Regras de Negocio
      $objInfraException = new InfraException();

      //Edoc
      if ($parObjDocumentoDTO->isSetDblIdDocumentoEdoc()){

        $objInfraException->lancarValidacao('A atualiza��o de conte�do para documentos do e-Doc n�o est� mais dispon�vel.');

      }else{

        $objDocumentoDTO = new DocumentoDTO();
        $objDocumentoDTO->retStrStaDocumento();
        $objDocumentoDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());

        $objDocumentoDTO = $this->consultarRN0005($objDocumentoDTO);

        if ($objDocumentoDTO == null){
          $objInfraException->lancarValidacao('Documento n�o encontrado para atualiza��o do conte�do.');
        }

        if ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_FORMULARIO_GERADO){
          //gera conteudo com base nos atributos recebidos
          $parObjDocumentoDTO->setStrConteudo(self::montarConteudoFormulario($parObjDocumentoDTO->getObjProtocoloDTO()->getArrObjRelProtocoloAtributoDTO()));
        }

      }

      $this->validarDocumentoPublicadoRN1211($parObjDocumentoDTO);

      $this->cancelarAssinatura($parObjDocumentoDTO);

      $objDocumentoAPI = new DocumentoAPI();
      $objDocumentoAPI->setIdDocumento($parObjDocumentoDTO->getDblIdDocumento());
      
      foreach($SEI_MODULOS as $seiModulo){
        $seiModulo->executar('atualizarConteudoDocumento', $objDocumentoAPI);
      }

      //criar novas instancias para evitar modificar outros dados
      if ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_FORMULARIO_GERADO){
        $objProtocoloDTO = new ProtocoloDTO();
        $objProtocoloDTO->setDblIdProtocolo($parObjDocumentoDTO->getDblIdDocumento());
        $objProtocoloDTO->setArrObjRelProtocoloAtributoDTO($parObjDocumentoDTO->getObjProtocoloDTO()->getArrObjRelProtocoloAtributoDTO());

        $objProtocoloRN = new ProtocoloRN();
        $objProtocoloRN->alterarRN0203($objProtocoloDTO);
      }

      $objDocumentoConteudoDTO = new DocumentoConteudoDTO();
      $objDocumentoConteudoDTO->setStrConteudo($parObjDocumentoDTO->getStrConteudo());
      $objDocumentoConteudoDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());

      $objDocumentoConteudoBD = new DocumentoBD($this->getObjInfraIBanco());
      $objDocumentoConteudoBD->alterar($objDocumentoConteudoDTO);

    }catch(Exception $e){
      throw new InfraException('Erro atualizando conte�do do documento.',$e);
    }
  }

  public function assinar(AssinaturaDTO $objAssinaturaDTO){

    $arrObjAssinaturaDTO = $this->assinarInterno($objAssinaturaDTO);

    if ($objAssinaturaDTO->getStrStaFormaAutenticacao()==AssinaturaRN::$TA_SENHA){

      $objIndexacaoDTO = new IndexacaoDTO();
      $objIndexacaoDTO->setArrIdProtocolos(InfraArray::converterArrInfraDTO($objAssinaturaDTO->getArrObjDocumentoDTO(),'IdDocumento'));
      $objIndexacaoDTO->setStrStaOperacao(IndexacaoRN::$TO_PROTOCOLO_METADADOS);

      $objIndexacaoRN = new IndexacaoRN();
      $objIndexacaoRN->indexarProtocolo($objIndexacaoDTO);
    }

    return $arrObjAssinaturaDTO;
  }

  protected function assinarInternoControlado(AssinaturaDTO $objAssinaturaDTO) {
    try{

      global $SEI_MODULOS;
      
      //Valida Permissao
      $objAssinaturaDTOAuditoria = clone($objAssinaturaDTO);
      $objAssinaturaDTOAuditoria->unSetStrSenhaUsuario();

      SessaoSEI::getInstance()->validarAuditarPermissao('documento_assinar',__METHOD__,$objAssinaturaDTOAuditoria);

      //Regras de Negocio
      $objInfraException = new InfraException();

      $objInfraParametro = new InfraParametro(BancoSEI::getInstance());

      $objUsuarioDTOPesquisa = new UsuarioDTO();
      $objUsuarioDTOPesquisa->setBolExclusaoLogica(false);
      $objUsuarioDTOPesquisa->retNumIdUsuario();
      $objUsuarioDTOPesquisa->retStrSigla();
      $objUsuarioDTOPesquisa->retStrNome();
      $objUsuarioDTOPesquisa->retDblCpfContato();
      $objUsuarioDTOPesquisa->retStrStaTipo();
      $objUsuarioDTOPesquisa->retStrSenha();
      $objUsuarioDTOPesquisa->retNumIdContato();
      $objUsuarioDTOPesquisa->setNumIdUsuario($objAssinaturaDTO->getNumIdUsuario());

      $objUsuarioRN = new UsuarioRN();
      $objUsuarioDTO = $objUsuarioRN->consultarRN0489($objUsuarioDTOPesquisa);

      if ($objUsuarioDTO==null){
        throw new InfraException('Assinante n�o cadastrado como usu�rio do sistema.');
      }

      if ($objUsuarioDTO->getStrStaTipo()==UsuarioRN::$TU_EXTERNO_PENDENTE){
        $objInfraException->lancarValidacao('Usu�rio externo '.$objUsuarioDTO->getStrSigla().' n�o foi liberado.');
      }

      if ($objUsuarioDTO->getStrStaTipo()!=UsuarioRN::$TU_SIP && $objUsuarioDTO->getStrStaTipo()!=UsuarioRN::$TU_EXTERNO){
        throw new InfraException('Tipo do usu�rio ['.$objUsuarioDTO->getStrStaTipo().'] inv�lido para assinatura.');
      }

      if ($objAssinaturaDTO->getStrStaFormaAutenticacao()==AssinaturaRN::$TA_CERTIFICADO_DIGITAL &&
          InfraString::isBolVazia($objUsuarioDTO->getDblCpfContato()) &&
          $objInfraParametro->getValor('SEI_HABILITAR_VALIDACAO_CPF_CERTIFICADO_DIGITAL')=='1'){
        $objInfraException->lancarValidacao('Assinante n�o possui CPF cadastrado.');
      }

      if (InfraString::isBolVazia($objAssinaturaDTO->getStrCargoFuncao())){
        $objInfraException->lancarValidacao('Cargo/Fun��o n�o informado.');
      }

      if (!in_array($objAssinaturaDTO->getStrCargoFuncao(), InfraArray::converterArrInfraDTO($objUsuarioRN->listarCargoFuncao($objUsuarioDTO),'CargoFuncao'))){
        $objInfraException->lancarValidacao('Cargo/Fun��o "'.$objAssinaturaDTO->getStrCargoFuncao().'" n�o est� associado com este usu�rio.');
      }

      if (SessaoSEI::getInstance()->getNumIdUsuario()==$objAssinaturaDTO->getNumIdUsuario()){
        $objUsuarioDTOLogado = clone($objUsuarioDTO);
      }else{
        $objUsuarioDTOPesquisa->setNumIdUsuario(SessaoSEI::getInstance()->getNumIdUsuario());
        $objUsuarioDTOLogado = $objUsuarioRN->consultarRN0489($objUsuarioDTOPesquisa);
      }

      $objAssinaturaRN = new AssinaturaRN();
      $objSecaoDocumentoRN = new SecaoDocumentoRN();

      $arrIdDocumentoAssinatura = array_unique(InfraArray::converterArrInfraDTO($objAssinaturaDTO->getArrObjDocumentoDTO(),'IdDocumento'));

      //verifica permiss�o de acesso ao documento
      $objPesquisaProtocoloDTO = new PesquisaProtocoloDTO();
      $objPesquisaProtocoloDTO->setStrStaTipo(ProtocoloRN::$TPP_DOCUMENTOS);
      $objPesquisaProtocoloDTO->setStrStaAcesso(ProtocoloRN::$TAP_TODOS);
      $objPesquisaProtocoloDTO->setDblIdProtocolo($arrIdDocumentoAssinatura);

      $objProtocoloRN = new ProtocoloRN();
      $arrObjProtocoloDTO = $objProtocoloRN->pesquisarRN0967($objPesquisaProtocoloDTO);

      $numDocOrigem = count($arrIdDocumentoAssinatura);
      $numDocEncontrado = count($arrObjProtocoloDTO);
      $n = $numDocOrigem - $numDocEncontrado;

      if ($n == 1){
        if ($numDocOrigem == 1){
          $objInfraException->lancarValidacao('Documento n�o encontrado para assinatura.');
        }else{
          $objInfraException->lancarValidacao('Um documento n�o est� mais dispon�vel para assinatura.');
        }
      }else if ($n > 1){
        $objInfraException->lancarValidacao($n.' documentos n�o est�o mais dispon�veis para assinatura.');
      }


      $objProtocoloDTOProcedimento = new ProtocoloDTO();
      $objProtocoloDTOProcedimento->retStrProtocoloFormatado();
      $objProtocoloDTOProcedimento->retStrStaEstado();
      $objProtocoloDTOProcedimento->setDblIdProtocolo(InfraArray::converterArrInfraDTO($arrObjProtocoloDTO,'IdProcedimentoDocumento'),InfraDTO::$OPER_IN);

      $objProtocoloRN = new ProtocoloRN();
      $arrObjProtocoloDTOProcedimentos = $objProtocoloRN->listarRN0668($objProtocoloDTOProcedimento);

      $objProcedimentoRN = new ProcedimentoRN();
      foreach($arrObjProtocoloDTOProcedimentos as $objProtocoloDTOProcedimento){
        $objProcedimentoRN->verificarEstadoProcedimento($objProtocoloDTOProcedimento);
      }


      $objAcessoExternoRN = new AcessoExternoRN();

      foreach($arrObjProtocoloDTO as $objProtocoloDTO){

        if ($objProtocoloDTO->getStrStaEstado()==ProtocoloRN::$TE_DOCUMENTO_CANCELADO){

          $objInfraException->adicionarValidacao('Documento '.$objProtocoloDTO->getStrProtocoloFormatado().' foi cancelado.');

        }else if ($objUsuarioDTOLogado->getStrStaTipo()==UsuarioRN::$TU_SIP && $objProtocoloDTO->getNumCodigoAcesso() < 0){

          $objInfraException->adicionarValidacao('Usu�rio '.$objUsuarioDTOLogado->getStrSigla().' n�o possui acesso ao documento '.$objProtocoloDTO->getStrProtocoloFormatado().'.');

        //s� valida se o usu�rio externo estiver logado pois ele pode estar na institui��o para assinar atrav�s do login de outro usu�rio
        }elseif ($objUsuarioDTO->getStrStaTipo()==UsuarioRN::$TU_EXTERNO && $objUsuarioDTO->getNumIdUsuario()==$objUsuarioDTOLogado->getNumIdUsuario()){

          $objAcessoExternoDTO = new AcessoExternoDTO();
          $objAcessoExternoDTO->retNumIdAcessoExterno();
          $objAcessoExternoDTO->setNumIdContatoParticipante($objUsuarioDTO->getNumIdContato());
          $objAcessoExternoDTO->setDblIdDocumento($objProtocoloDTO->getDblIdProtocolo());
          $objAcessoExternoDTO->setStrStaTipo(AcessoExternoRN::$TA_ASSINATURA_EXTERNA);
          $objAcessoExternoDTO->setNumMaxRegistrosRetorno(1);

          if ($objAcessoExternoRN->consultar($objAcessoExternoDTO) == null){
            $objInfraException->adicionarValidacao('Usu�rio externo '.$objUsuarioDTO->getStrSigla().' n�o recebeu libera��o para assinar o documento '.$objProtocoloDTO->getStrProtocoloFormatado().'.');
          }
        }

        if ($objProtocoloDTO->getStrStaProtocolo()==ProtocoloRN::$TP_DOCUMENTO_GERADO){

          if ($objProtocoloDTO->getStrSinPublicado()=='S'){
            $objInfraException->adicionarValidacao('Documento '.$objProtocoloDTO->getStrProtocoloFormatado().' j� foi publicado.');
          }

          if ($objProtocoloDTO->getStrSinDisponibilizadoParaOutraUnidade()=='S' && $objUsuarioDTO->getStrStaTipo()!=UsuarioRN::$TU_EXTERNO){
            $objInfraException->adicionarValidacao('Documento '.$objProtocoloDTO->getStrProtocoloFormatado().' foi disponibilizado para assinatura em outra unidade.');
          }

          if ($objProtocoloDTO->getStrStaDocumentoDocumento()==DocumentoRN::$TD_FORMULARIO_AUTOMATICO){
            $objInfraException->adicionarValidacao('Formul�rio '.$objProtocoloDTO->getStrProtocoloFormatado().' n�o pode receber assinatura.');
          }

          if ($objProtocoloDTO->getStrStaDocumentoDocumento()==DocumentoRN::$TD_EDITOR_EDOC){
            $objInfraException->adicionarValidacao('N�o � poss�vel assinar documentos gerados pelo e-Doc ('.$objProtocoloDTO->getStrProtocoloFormatado().').');
          }

          if ($objUsuarioDTO->getStrStaTipo()!=UsuarioRN::$TU_EXTERNO) {
            if (!($objProtocoloDTO->getNumIdUnidadeGeradora()==SessaoSEI::getInstance()->getNumIdUnidadeAtual() || $objProtocoloDTO->getStrSinAcessoAssinaturaBloco()=='S' || $objProtocoloDTO->getStrSinCredencialAssinatura()=='S')) {
              $objInfraException->adicionarValidacao('Documento '.$objProtocoloDTO->getStrProtocoloFormatado().' n�o pode ser assinado pelo usu�rio '.$objUsuarioDTO->getStrSigla().' na unidade '.SessaoSEI::getInstance()->getStrSiglaUnidadeAtual().'.');
            }
          }
        }

        $dto = new AssinaturaDTO();
        $dto->retStrNomeUsuario();
        $dto->setDblIdDocumento($objProtocoloDTO->getDblIdProtocolo());
        $dto->setNumIdUsuario($objAssinaturaDTO->getNumIdUsuario());
        $dto = $objAssinaturaRN->consultarRN1322($dto);

        if ($dto != null){
          $objInfraException->adicionarValidacao('Documento '.$objProtocoloDTO->getStrProtocoloFormatado().' j� foi assinado por "'.$dto->getStrNomeUsuario().'".');
        }

        if ($objProtocoloDTO->getStrStaDocumentoDocumento()==DocumentoRN::$TD_EDITOR_INTERNO) {
          $objSecaoDocumentoDTO = new SecaoDocumentoDTO();
          $objSecaoDocumentoDTO->retNumIdSecaoDocumento();
          $objSecaoDocumentoDTO->setDblIdDocumento($objProtocoloDTO->getDblIdProtocolo());
          $objSecaoDocumentoDTO->setStrSinAssinatura('S');
          $objSecaoDocumentoDTO->setNumMaxRegistrosRetorno(1);

          if ($objSecaoDocumentoRN->consultar($objSecaoDocumentoDTO) == null) {
            $objInfraException->adicionarValidacao('Documento ' . $objProtocoloDTO->getStrProtocoloFormatado() . ' n�o cont�m se��o de assinatura.');
          }
        }

        if ($objProtocoloDTO->getStrStaProtocolo()==ProtocoloRN::$TP_DOCUMENTO_RECEBIDO && $objProtocoloDTO->getNumIdTipoConferenciaDocumento()==null){
          $objInfraException->adicionarValidacao('Documento ' . $objProtocoloDTO->getStrProtocoloFormatado() . ' n�o possui Tipo de Confer�ncia informada.');
        }
      }

      $objInfraException->lancarValidacoes();

      /*
       foreach($arrObjProtocoloDTO as $objProtocoloDTO){
      $objDocumentoDTO = new DocumentoDTO();
      $objDocumentoDTO->setDblIdDocumento($objProtocoloDTO->getDblIdProtocolo());

      $objDocumentoBD = new DocumentoBD($this->getObjInfraIBanco());
      $objDocumentoBD->bloquear($objDocumentoDTO);
      }
      */

      $objInfraException->lancarValidacoes();

      if ($objAssinaturaDTO->getStrStaFormaAutenticacao()==AssinaturaRN::$TA_SENHA){

        if ($objUsuarioDTO->getStrStaTipo()==UsuarioRN::$TU_SIP){

          $objInfraSip = new InfraSip(SessaoSEI::getInstance());
          $objInfraSip->autenticar($objAssinaturaDTO->getNumIdOrgaoUsuario(),
              $objAssinaturaDTO->getNumIdContextoUsuario(),
              $objUsuarioDTO->getStrSigla(),
              $objAssinaturaDTO->getStrSenhaUsuario());

        }else{
          // alteracoes Login Unico
          $loginUnico = false;
          $objLoginExternoAPI = new LoginExternoAPI();
          $objLoginExternoAPI->setPassword($objAssinaturaDTO->getStrSenhaUsuario());
          $objLoginExternoAPI->setToken(SessaoSEIExterna::getInstance()->getAtributo('MD_LOGIN_EXTERNO_TOKEN'));  
     
          $conf = new ConfiguracaoSEI();

          if(!$conf->getArrConfiguracoes()['SEI']['Producao']){
            foreach ($SEI_MODULOS as $seiModulo) {
              if (($arrRetIntegracao = $seiModulo->executar('validarSenhaExterna', $objLoginExternoAPI)) != null){
                // verificar senha do usuario no gov br
                $loginUnico = true;
              }
            }
          }else{
            $objInfraException->lancarValidacao('Op��o de assinatura via GovBr indispon�vel para o ambiente de produ��o.');
          }
          if (!$loginUnico) {
            $bcrypt = new InfraBcrypt();
            if (!$bcrypt->verificar(md5($objAssinaturaDTO->getStrSenhaUsuario()), $objUsuarioDTO->getStrSenha())) {
              $objInfraException->lancarValidacao('Senha inv�lida.');
            }
          }
        }
      }

      foreach($arrObjProtocoloDTO as $objProtocoloDTO){
        if ($objProtocoloDTO->getStrStaProtocolo()==ProtocoloRN::$TP_DOCUMENTO_GERADO){
          if ($objProtocoloDTO->getStrSinAssinado()=='N'){

            if ($objProtocoloDTO->getStrStaDocumentoDocumento()==DocumentoRN::$TD_EDITOR_INTERNO) {
              /*
              //gerar nova versao igual a anterior para substitui��o de dados din�micos (ex.: datas)
              $objVersaoSecaoDocumentoDTO = new VersaoSecaoDocumentoDTO();
              $objVersaoSecaoDocumentoDTO->retNumIdSecaoModeloSecaoDocumento();
              $objVersaoSecaoDocumentoDTO->retStrConteudo();
              $objVersaoSecaoDocumentoDTO->setDblIdDocumentoSecaoDocumento($objProtocoloDTO->getDblIdProtocolo());
              $objVersaoSecaoDocumentoDTO->setNumIdBaseConhecimentoSecaoDocumento(null);
              $objVersaoSecaoDocumentoDTO->setStrSinUltima('S');
              $objVersaoSecaoDocumentoDTO->setOrdNumOrdemSecaoDocumento(InfraDTO::$TIPO_ORDENACAO_ASC);

              $objVersaoSecaoDocumentoRN = new VersaoSecaoDocumentoRN();
              $arrObjVersaoSecaoDocumentoDTO = $objVersaoSecaoDocumentoRN->listar($objVersaoSecaoDocumentoDTO);

              $arrObjSecaoDocumentoDTO = array();
              foreach($arrObjVersaoSecaoDocumentoDTO as $objVersaoSecaoDocumentoDTO){
                $objSecaoDocumentoDTO = new SecaoDocumentoDTO();
                $objSecaoDocumentoDTO->setNumIdSecaoModelo($objVersaoSecaoDocumentoDTO->getNumIdSecaoModeloSecaoDocumento());
                $objSecaoDocumentoDTO->setStrConteudo($objVersaoSecaoDocumentoDTO->getStrConteudo());
                $arrObjSecaoDocumentoDTO[] = $objSecaoDocumentoDTO;
              }
              $objEditorDTO = new EditorDTO();
              $objEditorDTO->setDblIdDocumento($objProtocoloDTO->getDblIdProtocolo());
              $objEditorDTO->setNumIdBaseConhecimento(null);
              $objEditorDTO->setArrObjSecaoDocumentoDTO($arrObjSecaoDocumentoDTO);

              $objEditorRN = new EditorRN();
              $objEditorRN->adicionarVersao($objEditorDTO);
              */

              /////////
              $objEditorDTO = new EditorDTO();
              $objEditorDTO->setDblIdDocumento($objProtocoloDTO->getDblIdProtocolo());
              $objEditorDTO->setNumIdBaseConhecimento(null);
              $objEditorDTO->setStrSinCabecalho('S');
              $objEditorDTO->setStrSinRodape('S');
              $objEditorDTO->setStrSinCarimboPublicacao('N');
              $objEditorDTO->setStrSinIdentificacaoVersao('N');

              $objEditorRN = new EditorRN();
              $strDocumentoHTML = $objEditorRN->consultarHtmlVersao($objEditorDTO);

            }else if ($objProtocoloDTO->getStrStaDocumentoDocumento()==DocumentoRN::$TD_FORMULARIO_GERADO){

              $dto = new DocumentoDTO();
              $dto->setDblIdDocumento($objProtocoloDTO->getDblIdProtocolo());
              $strDocumentoHTML = $this->consultarHtmlFormulario($dto);

            }

            $objDocumentoConteudoDTO = new DocumentoConteudoDTO();
            $objDocumentoConteudoDTO->setStrConteudoAssinatura($strDocumentoHTML);
            $objDocumentoConteudoDTO->setStrCrcAssinatura(strtoupper(hash('crc32b', $strDocumentoHTML)));
            $this->gerarQrCode($objProtocoloDTO, $objDocumentoConteudoDTO);
            $objDocumentoConteudoDTO->setDblIdDocumento($objProtocoloDTO->getDblIdProtocolo());

            $objDocumentoConteudoBD = new DocumentoConteudoBD($this->getObjInfraIBanco());
            $objDocumentoConteudoBD->alterar($objDocumentoConteudoDTO);

          }

        }else{

          $objAnexoDTO = new AnexoDTO();
          $objAnexoDTO->retNumIdAnexo();
          $objAnexoDTO->retDthInclusao();
          $objAnexoDTO->setDblIdProtocolo($objProtocoloDTO->getDblIdProtocolo());

          $objAnexoRN = new AnexoRN();
          $objAnexoDTO = $objAnexoRN->consultarRN0736($objAnexoDTO);

          if ($objAnexoDTO==null){
            $objInfraException->lancarValidacao('Documento '.$objProtocoloDTO->getStrProtocoloFormatado().' n�o possui anexo associado.');
          }

          $objDocumentoConteudoBD = new DocumentoConteudoBD($this->getObjInfraIBanco());

          $objDocumentoConteudoDTO = new DocumentoConteudoDTO();
          $objDocumentoConteudoDTO->setDblIdDocumento($objProtocoloDTO->getDblIdProtocolo());

          if ($objDocumentoConteudoBD->contar($objDocumentoConteudoDTO) == 0){
            $objDocumentoConteudoDTO->setStrConteudo(null);
            $objDocumentoConteudoDTO->setStrConteudoAssinatura(null);
            $objDocumentoConteudoDTO->setStrCrcAssinatura(strtoupper(hash_file('crc32b', $objAnexoRN->obterLocalizacao($objAnexoDTO))));
            $this->gerarQrCode($objProtocoloDTO, $objDocumentoConteudoDTO);
            $objDocumentoConteudoBD->cadastrar($objDocumentoConteudoDTO);
          }else{
            $objDocumentoConteudoDTO->setStrCrcAssinatura(strtoupper(hash_file('crc32b', $objAnexoRN->obterLocalizacao($objAnexoDTO))));
            $this->gerarQrCode($objProtocoloDTO, $objDocumentoConteudoDTO);
            $objDocumentoConteudoBD->alterar($objDocumentoConteudoDTO);
          }
        }
      }

      $objTarjaAssinaturaDTO = new TarjaAssinaturaDTO();
      $objTarjaAssinaturaDTO->retNumIdTarjaAssinatura();

      if ($objProtocoloDTO->getStrStaProtocolo()==ProtocoloRN::$TP_DOCUMENTO_GERADO) {
        if ($objAssinaturaDTO->getStrStaFormaAutenticacao() == AssinaturaRN::$TA_SENHA) {
          $objTarjaAssinaturaDTO->setStrStaTarjaAssinatura(TarjaAssinaturaRN::$TT_ASSINATURA_SENHA);
        } else {
          $objTarjaAssinaturaDTO->setStrStaTarjaAssinatura(TarjaAssinaturaRN::$TT_ASSINATURA_CERTIFICADO_DIGITAL);
        }
      }else{
        if ($objAssinaturaDTO->getStrStaFormaAutenticacao() == AssinaturaRN::$TA_SENHA) {
          $objTarjaAssinaturaDTO->setStrStaTarjaAssinatura(TarjaAssinaturaRN::$TT_AUTENTICACAO_SENHA);
        } else {
          $objTarjaAssinaturaDTO->setStrStaTarjaAssinatura(TarjaAssinaturaRN::$TT_AUTENTICACAO_CERTIFICADO_DIGITAL);
        }
      }

      $objTarjaAssinaturaRN = new TarjaAssinaturaRN();
      $objTarjaAssinaturaDTO = $objTarjaAssinaturaRN->consultar($objTarjaAssinaturaDTO);

      $strAgrupador = null;
      if ($objAssinaturaDTO->getStrStaFormaAutenticacao() == AssinaturaRN::$TA_CERTIFICADO_DIGITAL){
        $strAgrupador = InfraUtil::gerarUUID();
      }

      $objAtividadeRN = new AtividadeRN();
      $arrObjAssinaturaDTO = array();
      $arrObjDocumentoDTOCredencialAssinatura = array();
      foreach($arrObjProtocoloDTO as $objProtocoloDTO){

        $numIdAtividade = null;
        if ($objAssinaturaDTO->getStrStaFormaAutenticacao()==AssinaturaRN::$TA_SENHA){

          //lan�a tarefa de assinatura
          $arrObjAtributoAndamentoDTO = array();
          $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
          $objAtributoAndamentoDTO->setStrNome('DOCUMENTO');
          $objAtributoAndamentoDTO->setStrValor($objProtocoloDTO->getStrProtocoloFormatado());
          $objAtributoAndamentoDTO->setStrIdOrigem($objProtocoloDTO->getDblIdProtocolo());
          $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;

          $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
          $objAtributoAndamentoDTO->setStrNome('USUARIO');
          $objAtributoAndamentoDTO->setStrValor($objUsuarioDTO->getStrSigla().'�'.$objUsuarioDTO->getStrNome());
          $objAtributoAndamentoDTO->setStrIdOrigem($objUsuarioDTO->getNumIdUsuario());
          $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;

          //Define se o prop�sito da opera��o � assinar ou autenticar o documento
          $numIdTarefaAssinatura = TarefaRN::$TI_ASSINATURA_DOCUMENTO;
          if($objProtocoloDTO->getStrStaProtocolo() == ProtocoloRN::$TP_DOCUMENTO_RECEBIDO) {
            $numIdTarefaAssinatura = TarefaRN::$TI_AUTENTICACAO_DOCUMENTO;
          }

          $objAtividadeDTO = new AtividadeDTO();
          $objAtividadeDTO->setDblIdProtocolo($objProtocoloDTO->getDblIdProcedimentoDocumento());
          $objAtividadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
          $objAtividadeDTO->setNumIdTarefa($numIdTarefaAssinatura);
          $objAtividadeDTO->setArrObjAtributoAndamentoDTO($arrObjAtributoAndamentoDTO);

          $objAtividadeDTO = $objAtividadeRN->gerarInternaRN0727($objAtividadeDTO);
          $numIdAtividade = $objAtividadeDTO->getNumIdAtividade();
        }

        //remove ocorr�ncia pendente, se existir
        $dto = new AssinaturaDTO();
        $dto->retNumIdAssinatura();
        $dto->setDblIdDocumento($objProtocoloDTO->getDblIdProtocolo());
        $dto->setNumIdUsuario($objAssinaturaDTO->getNumIdUsuario());
        $dto->setBolExclusaoLogica(false);
        $dto->setStrSinAtivo('N');
        $dto = $objAssinaturaRN->consultarRN1322($dto);

        if ($dto!=null){
          $objAssinaturaRN->excluirRN1321(array($dto));
        }

        $dto = new AssinaturaDTO();
        $dto->setDblIdDocumento($objProtocoloDTO->getDblIdProtocolo());
        $dto->setStrProtocoloDocumentoFormatado($objProtocoloDTO->getStrProtocoloFormatado());
        $dto->setNumIdUsuario($objAssinaturaDTO->getNumIdUsuario());
        $dto->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
        $dto->setNumIdAtividade($numIdAtividade);
        $dto->setNumIdTarjaAssinatura($objTarjaAssinaturaDTO->getNumIdTarjaAssinatura());
        $dto->setStrSiglaUsuario($objUsuarioDTO->getStrSigla());
        $dto->setStrNome($objUsuarioDTO->getStrNome());
        $dto->setStrTratamento($objAssinaturaDTO->getStrCargoFuncao());
        $dto->setDblCpf($objUsuarioDTO->getDblCpfContato());
        $dto->setStrStaFormaAutenticacao($objAssinaturaDTO->getStrStaFormaAutenticacao());
        $dto->setStrP7sBase64(null);
        $dto->setStrAgrupador($strAgrupador);

        if ($objAssinaturaDTO->getStrStaFormaAutenticacao()==AssinaturaRN::$TA_CERTIFICADO_DIGITAL){
          $dto->setStrSinAtivo('N');
        }else{
          $dto->setStrSinAtivo('S');
        }

        $arrObjAssinaturaDTO[] = $objAssinaturaRN->cadastrarRN1319($dto);

        if ($objAssinaturaDTO->getStrStaFormaAutenticacao()==AssinaturaRN::$TA_SENHA && $objProtocoloDTO->getStrSinCredencialAssinatura()=='S'){
          $objDocumentoDTO = new DocumentoDTO();
          $objDocumentoDTO->setDblIdDocumento($objProtocoloDTO->getDblIdProtocolo());
          $arrObjDocumentoDTOCredencialAssinatura[] = $objDocumentoDTO;
        }
      }

      if (count($arrObjDocumentoDTOCredencialAssinatura)){
        $objAtividadeRN->concluirCredencialAssinatura($arrObjDocumentoDTOCredencialAssinatura);
      }

      if (count($SEI_MODULOS) && $objAssinaturaDTO->getStrStaFormaAutenticacao()==AssinaturaRN::$TA_SENHA){
        $arrObjDocumentoAPI = array();
        foreach($arrObjProtocoloDTO as $objProtocoloDTO){
          $objDocumentoAPI = new DocumentoAPI();
          $objDocumentoAPI->setIdDocumento($objProtocoloDTO->getDblIdProtocolo());
          $objDocumentoAPI->setIdProcedimento($objProtocoloDTO->getDblIdProcedimentoDocumento());
          $objDocumentoAPI->setNumeroProtocolo($objProtocoloDTO->getStrProtocoloFormatado());
          $objDocumentoAPI->setIdSerie($objProtocoloDTO->getNumIdSerieDocumento());
          $objDocumentoAPI->setIdUnidadeGeradora($objProtocoloDTO->getNumIdUnidadeGeradora());
          $objDocumentoAPI->setIdOrgaoUnidadeGeradora($objProtocoloDTO->getNumIdOrgaoUnidadeGeradora());
          $objDocumentoAPI->setIdUsuarioGerador($objProtocoloDTO->getNumIdUsuarioGerador());
          $objDocumentoAPI->setTipo($objProtocoloDTO->getStrStaProtocolo());
          $objDocumentoAPI->setSubTipo($objProtocoloDTO->getStrStaDocumentoDocumento());
          $objDocumentoAPI->setNivelAcesso($objProtocoloDTO->getStrStaNivelAcessoGlobal());
          $arrObjDocumentoAPI[] = $objDocumentoAPI;
        }
        
        foreach($SEI_MODULOS as $seiModulo){
          $seiModulo->executar('assinarDocumento', $arrObjDocumentoAPI);
        }
      }


      return $arrObjAssinaturaDTO;

    }catch(Exception $e){
      throw new InfraException('Erro assinando documento.',$e);
    }
  }

  private function gerarQrCode(ProtocoloDTO $objProtocoloDTO, DocumentoConteudoDTO $objDocumentoConteudoDTO){
    try{

      $objAnexoRN = new AnexoRN();
      $strArquivoQRCaminhoCompleto = DIR_SEI_TEMP.'/'.$objAnexoRN->gerarNomeArquivoTemporario();
      $strUrlVerificacao = ConfiguracaoSEI::getInstance()->getValor('SEI','URL').'/controlador_externo.php?acao=documento_conferir&id_orgao_acesso_externo='.$objProtocoloDTO->getNumIdOrgaoUnidadeGeradora().'&cv='.$objProtocoloDTO->getStrProtocoloFormatado().'&crc='.$objDocumentoConteudoDTO->getStrCrcAssinatura();

      InfraQRCode::gerar($strUrlVerificacao, $strArquivoQRCaminhoCompleto,'L',2,1);

      $objInfraException = new InfraException();


      if (!file_exists($strArquivoQRCaminhoCompleto)){
        $objInfraException->lancarValidacao('Arquivo do QRCode n�o encontrado.');
      }

      if (filesize($strArquivoQRCaminhoCompleto)==0){
        $objInfraException->lancarValidacao('Arquivo do QRCode vazio.');
      }

      if (($binQrCode = file_get_contents($strArquivoQRCaminhoCompleto))===false){
        $objInfraException->lancarValidacao('N�o foi poss�vel ler o arquivo do QRCode.');
      }

      $objDocumentoConteudoDTO->setStrQrCodeAssinatura(base64_encode($binQrCode));

      unlink($strArquivoQRCaminhoCompleto);

    }catch(Exception $e){
      throw new InfraException('Erro gerando QRCode da assinatura.',$e);
    }
  }

  public function confirmarAssinatura(AssinaturaDTO $objAssinaturaDTO){

    $objDocumentoDTO = $this->confirmarAssinaturaInterno($objAssinaturaDTO);

    if ($objDocumentoDTO!=null){

      $objIndexacaoDTO = new IndexacaoDTO();
      $objIndexacaoDTO->setArrIdProtocolos(array($objDocumentoDTO->getDblIdDocumento()));
      $objIndexacaoDTO->setStrStaOperacao(IndexacaoRN::$TO_PROTOCOLO_METADADOS);

      $objIndexacaoRN = new IndexacaoRN();
      $objIndexacaoRN->indexarProtocolo($objIndexacaoDTO);
    }
  }

  protected function confirmarAssinaturaInternoControlado(AssinaturaDTO $parObjAssinaturaDTO) {
    try{

      global $SEI_MODULOS;

      //Regras de Negocio
      $objInfraException = new InfraException();
      //$objInfraException->lancarValidacoes();

      $objDocumentoDTO = new DocumentoDTO();
      $objDocumentoDTO->retDblIdDocumento();
      $objDocumentoDTO->retDblIdProcedimento();
      $objDocumentoDTO->retStrProtocoloDocumentoFormatado();
      $objDocumentoDTO->retStrStaDocumento();

      $objAssinaturaRN = new AssinaturaRN();
      $objAssinaturaDTO = new AssinaturaDTO();
      $objAssinaturaDTO->retDblIdDocumento();
      $objAssinaturaDTO->retNumIdAssinatura();
      $objAssinaturaDTO->retStrSinAtivo();
      $objAssinaturaDTO->retNumIdUnidade();
      $objAssinaturaDTO->retNumIdUsuario();
      $objAssinaturaDTO->retStrSiglaUsuario();
      $objAssinaturaDTO->retStrSiglaOrgaoUsuario();
      $objAssinaturaDTO->retStrNomeUsuario();
      $objAssinaturaDTO->retDblCpf();
      $objAssinaturaDTO->setBolExclusaoLogica(false);

      if ($parObjAssinaturaDTO->isSetNumIdAssinatura()){
        // editor interno
        $objAssinaturaDTO->setNumIdAssinatura($parObjAssinaturaDTO->getNumIdAssinatura());
        $objAssinaturaDTO = $objAssinaturaRN->consultarRN1322($objAssinaturaDTO);

        if ($objAssinaturaDTO==null){
          $objInfraException->lancarValidacao('Assinatura '.$parObjAssinaturaDTO->getNumIdAssinatura().' n�o localizada no SEI.');
        }

        $objDocumentoDTO->setDblIdDocumento($objAssinaturaDTO->getDblIdDocumento());
        $objDocumentoDTO = $this->consultarRN0005($objDocumentoDTO);

        $parObjAssinaturaDTO->setDblCpf($objAssinaturaDTO->getDblCpf());
        $parObjAssinaturaDTO->setStrSiglaUsuario($objAssinaturaDTO->getStrSiglaUsuario());
        $parObjAssinaturaDTO->setStrSiglaOrgaoUsuario($objAssinaturaDTO->getStrSiglaOrgaoUsuario());
        $parObjAssinaturaDTO->setDblIdDocumento($objAssinaturaDTO->getDblIdDocumento());
        $parObjAssinaturaDTO->setNumIdAssinatura($objAssinaturaDTO->getNumIdAssinatura());
        $objAssinaturaRN->validarAssinaturaDocumento($parObjAssinaturaDTO, $objInfraException);

      }else if ($parObjAssinaturaDTO->isSetDblIdDocumentoEdoc()){
        // editor edoc
        $objDocumentoDTO->setDblIdDocumentoEdoc($parObjAssinaturaDTO->getDblIdDocumentoEdoc());
        $objDocumentoDTO = $this->consultarRN0005($objDocumentoDTO);

        if ($objDocumentoDTO==null){
          $objInfraException->lancarValidacao('Documento '.$parObjAssinaturaDTO->getDblIdDocumentoEdoc().' n�o possui correspond�ncia no SEI.');
        }

        $objAssinaturaDTO->setDblIdDocumento($objDocumentoDTO->getDblIdDocumento());
        $objAssinaturaDTO->setStrSiglaUsuario($parObjAssinaturaDTO->getStrSiglaUsuario());
        $objAssinaturaDTO->setDblCpf($parObjAssinaturaDTO->getDblCpf());
        $objAssinaturaDTO = $objAssinaturaRN->consultarRN1322($objAssinaturaDTO);

        $parObjAssinaturaDTO->setNumIdAssinatura($objAssinaturaDTO->getNumIdAssinatura());

      }else{
        $objInfraException->lancarValidacao('Documento para confirma��o de assinatura n�o informado.');
      }

      if ($objAssinaturaDTO->getStrSinAtivo()=='S'){
        $objInfraException->lancarValidacao('N�o existe assinatura pendente para o documento '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado().'/'.$objDocumentoDTO->getDblIdDocumento().' no SEI.');
      }

      SessaoSEI::getInstance()->setNumIdUsuario($objAssinaturaDTO->getNumIdUsuario());
      SessaoSEI::getInstance()->setNumIdUnidadeAtual($objAssinaturaDTO->getNumIdUnidade());

      $objAtividadeRN = new AtividadeRN();


      //verifica permiss�o de acesso ao documento
      $objPesquisaProtocoloDTO = new PesquisaProtocoloDTO();
      $objPesquisaProtocoloDTO->setStrStaTipo(ProtocoloRN::$TPP_DOCUMENTOS);
      $objPesquisaProtocoloDTO->setStrStaAcesso(ProtocoloRN::$TAP_AUTORIZADO);
      $objPesquisaProtocoloDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdDocumento());

      $objProtocoloRN = new ProtocoloRN();
      $arrObjProtocoloDTO = $objProtocoloRN->pesquisarRN0967($objPesquisaProtocoloDTO);

      if (count($arrObjProtocoloDTO)==0){
        $objInfraException->lancarValidacao('Documento '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado().' n�o est� dispon�vel para assinatura.');
      }

      if ($arrObjProtocoloDTO[0]->getStrSinCredencialAssinatura()=='S'){
        $objAtividadeRN->concluirCredencialAssinatura(array($objDocumentoDTO));
      }

      $objProtocoloDTOProcedimento = new ProtocoloDTO();
      $objProtocoloDTOProcedimento->retStrProtocoloFormatado();
      $objProtocoloDTOProcedimento->retStrStaEstado();
      $objProtocoloDTOProcedimento->setDblIdProtocolo($objDocumentoDTO->getDblIdProcedimento());

      $objProtocoloRN = new ProtocoloRN();
      $objProcedimentoRN = new ProcedimentoRN();
      $objProcedimentoRN->verificarEstadoProcedimento($objProtocoloRN->consultarRN0186($objProtocoloDTOProcedimento));

      //lan�a tarefa de assinatura
      $arrObjAtributoAndamentoDTO = array();
      $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
      $objAtributoAndamentoDTO->setStrNome('DOCUMENTO');
      $objAtributoAndamentoDTO->setStrValor($objDocumentoDTO->getStrProtocoloDocumentoFormatado());
      $objAtributoAndamentoDTO->setStrIdOrigem($objDocumentoDTO->getDblIdDocumento());
      $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;

      $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
      $objAtributoAndamentoDTO->setStrNome('USUARIO');
      $objAtributoAndamentoDTO->setStrValor($objAssinaturaDTO->getStrSiglaUsuario().'�'.$objAssinaturaDTO->getStrNomeUsuario());
      $objAtributoAndamentoDTO->setStrIdOrigem($objAssinaturaDTO->getNumIdUsuario());
      $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;

      //Define se o prop�sito da opera��o � assinar ou autenticar o documento
      $numIdTarefaAssinatura = TarefaRN::$TI_ASSINATURA_DOCUMENTO;
      if($objDocumentoDTO->getStrStaDocumento() == DocumentoRN::$TD_EXTERNO) {
        $numIdTarefaAssinatura = TarefaRN::$TI_AUTENTICACAO_DOCUMENTO;
      }

      $objAtividadeDTO = new AtividadeDTO();
      $objAtividadeDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdProcedimento());
      $objAtividadeDTO->setNumIdUnidadeOrigem($objAssinaturaDTO->getNumIdUnidade());
      $objAtividadeDTO->setNumIdUnidade($objAssinaturaDTO->getNumIdUnidade());
      $objAtividadeDTO->setNumIdTarefa($numIdTarefaAssinatura);
      $objAtividadeDTO->setArrObjAtributoAndamentoDTO($arrObjAtributoAndamentoDTO);


      $objAtividadeDTO = $objAtividadeRN->gerarInternaRN0727($objAtividadeDTO);

      $dto = new AssinaturaDTO();
      $dto->setStrSinAtivo('S');
      $dto->setNumIdAtividade($objAtividadeDTO->getNumIdAtividade());
      if ($objDocumentoDTO->getStrStaDocumento()!=DocumentoRN::$TD_EDITOR_EDOC){
        $dto->setStrNumeroSerieCertificado($parObjAssinaturaDTO->getStrNumeroSerieCertificado());
        $dto->setStrP7sBase64($parObjAssinaturaDTO->getStrP7sBase64());
      }
      $dto->setNumIdAssinatura($parObjAssinaturaDTO->getNumIdAssinatura());
      $objAssinaturaRN->alterarRN1320($dto);


      if (count($SEI_MODULOS)){

        $objProtocoloDTO = $arrObjProtocoloDTO[0];

        $objDocumentoAPI = new DocumentoAPI();
        $objDocumentoAPI->setIdDocumento($objProtocoloDTO->getDblIdProtocolo());
        $objDocumentoAPI->setIdProcedimento($objProtocoloDTO->getDblIdProcedimentoDocumento());
        $objDocumentoAPI->setNumeroProtocolo($objProtocoloDTO->getStrProtocoloFormatado());
        $objDocumentoAPI->setIdSerie($objProtocoloDTO->getNumIdSerieDocumento());
        $objDocumentoAPI->setIdUnidadeGeradora($objProtocoloDTO->getNumIdUnidadeGeradora());
        $objDocumentoAPI->setIdOrgaoUnidadeGeradora($objProtocoloDTO->getNumIdOrgaoUnidadeGeradora());
        $objDocumentoAPI->setIdUsuarioGerador($objProtocoloDTO->getNumIdUsuarioGerador());
        $objDocumentoAPI->setTipo($objProtocoloDTO->getStrStaProtocolo());
        $objDocumentoAPI->setSubTipo($objProtocoloDTO->getStrStaDocumentoDocumento());
        $objDocumentoAPI->setNivelAcesso($objProtocoloDTO->getStrStaNivelAcessoGlobal());

        $arrObjDocumentoAPI = array($objDocumentoAPI);

        foreach($SEI_MODULOS as $seiModulo){
          $seiModulo->executar('assinarDocumento', $arrObjDocumentoAPI);
        }
      }

      return $objDocumentoDTO;

      //Auditoria

    }catch(Exception $e){
      throw new InfraException('Erro confirmando assinatura.',$e);
    }
  }

  protected function validarDocumentoPublicadoRN1211Controlado(DocumentoDTO $parObjDocumentoDTO){

    $objInfraException = new InfraException();

    $objDocumentoDTO = new DocumentoDTO();
    $objDocumentoDTO->retObjPublicacaoDTO();
    $objDocumentoDTO->retStrProtocoloDocumentoFormatado();
    $objDocumentoDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());

    $objDocumentoDTO = $this->consultarRN0005($objDocumentoDTO);

    $objPublicacaoDTO = $objDocumentoDTO->getObjPublicacaoDTO();

    if ($objPublicacaoDTO != null){
      if ($objPublicacaoDTO->getStrStaEstado()==PublicacaoRN::$TE_AGENDADO){
        $objInfraException->lancarValidacao('Documento '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado().' agendado para publica��o em '.$objPublicacaoDTO->getDtaDisponibilizacao().'.');
      }else{
        $objInfraException->lancarValidacao('Documento '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado().' foi publicado em '.$objPublicacaoDTO->getDtaPublicacao().'.');
      }
    }
  }

  private function validarStrNumeroRN0010(DocumentoDTO $objDocumentoDTO, InfraException $objInfraException){

    $objSerieDTO = new SerieDTO();
    $objSerieDTO->setBolExclusaoLogica(false);
    $objSerieDTO->retStrNome();
    $objSerieDTO->retStrStaNumeracao();
    $objSerieDTO->setNumIdSerie($objDocumentoDTO->getNumIdSerie());

    $objSerieRN = new SerieRN();
    $objSerieDTO = $objSerieRN->consultarRN0644($objSerieDTO);

    $strStaNumeracao = $objSerieDTO->getStrStaNumeracao();
    $strNomeSerie = $objSerieDTO->getStrNome();

    if ($strStaNumeracao == SerieRN::$TN_INFORMADA){
      if (!InfraString::isBolVazia($objDocumentoDTO->getStrNumero())){
        $objInfraException->adicionarValidacao('N�mero n�o informado.');
      }else{

        $this->validarTamanhoNumeroRN0993($objDocumentoDTO, $objInfraException);
      }
    }else{

      $dto = new DocumentoDTO();
      $dto->retStrNumero();
      $dto->setDblIdDocumento($objDocumentoDTO->getDblIdDocumento());
      $dto = $this->consultarRN0005($dto);

      if ($dto->getStrNumero() != $objDocumentoDTO->getStrNumero()){
        $objInfraException->adicionarValidacao('N�o � poss�vel alterar a numera��o porque o tipo '.$strNomeSerie.' n�o aceita que o n�mero seja informado.');
      }
    }
  }

  protected function cancelarAssinaturaControlado(DocumentoDTO $parObjDocumentoDTO){
    try {

      $objInfraException = new InfraException();

      $objAssinaturaDTO = new AssinaturaDTO();
      $objAssinaturaDTO->retNumIdAssinatura();
      $objAssinaturaDTO->retNumIdUnidade();
      $objAssinaturaDTO->retStrStaTipoUsuario();
      $objAssinaturaDTO->retStrSiglaUsuario();
      $objAssinaturaDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());

      $objAssinaturaRN = new AssinaturaRN();
      $arrObjAssinaturaDTO = $objAssinaturaRN->listarRN1323($objAssinaturaDTO);

      if (count($arrObjAssinaturaDTO)>0){

        foreach($arrObjAssinaturaDTO as $objAssinaturaDTO){
          if ($objAssinaturaDTO->getStrStaTipoUsuario()==UsuarioRN::$TU_EXTERNO_PENDENTE || $objAssinaturaDTO->getStrStaTipoUsuario()==UsuarioRN::$TU_EXTERNO){
            $objInfraException->adicionarValidacao('Documento foi assinado pelo usu�rio externo "'.$objAssinaturaDTO->getStrSiglaUsuario().'".');
          }
        }

        $objDocumentoDTO = new DocumentoDTO();
        $objDocumentoDTO->retDblIdDocumento();
        $objDocumentoDTO->retDblIdProcedimento();
        $objDocumentoDTO->retStrProtocoloDocumentoFormatado();
        $objDocumentoDTO->retNumIdUnidadeGeradoraProtocolo();
        $objDocumentoDTO->retStrSinBloqueado();
        $objDocumentoDTO->retStrStaDocumento();
        $objDocumentoDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());

        $objDocumentoDTO = $this->consultarRN0005($objDocumentoDTO);

        if ($objDocumentoDTO->getNumIdUnidadeGeradoraProtocolo()!=SessaoSEI::getInstance()->getNumIdUnidadeAtual()){
          foreach($arrObjAssinaturaDTO as $objAssinaturaDTO){
            if ($objAssinaturaDTO->getNumIdUnidade()!=SessaoSEI::getInstance()->getNumIdUnidadeAtual()){
              $objInfraException->lancarValidacao('Documento foi assinado por outra unidade.');
            }
          }
        }

        if ($objDocumentoDTO->getStrSinBloqueado()=='S'){
          $objInfraException->lancarValidacao('A assinatura do documento '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado().' n�o pode mais ser cancelada.');
        }

        $objInfraException->lancarValidacoes();

        $dto = new DocumentoDTO();
        $dto->setStrSinBloqueado('N');
        $dto->setDblIdDocumento($objDocumentoDTO->getDblIdDocumento());

        $objDocumentoBD = new DocumentoBD($this->getObjInfraIBanco());
        $objDocumentoBD->alterar($dto);

        $dto = new DocumentoConteudoDTO();
        $dto->setStrConteudoAssinatura(null);
        $dto->setStrCrcAssinatura(null);
        $dto->setStrQrCodeAssinatura(null);
        $dto->setDblIdDocumento($objDocumentoDTO->getDblIdDocumento());

        $objDocumentoConteudoBD = new DocumentoConteudoBD($this->getObjInfraIBanco());
        $objDocumentoConteudoBD->alterar($dto);


        $objAssinaturaRN->excluirRN1321($arrObjAssinaturaDTO);


        //lan�a tarefa de cancelamento de assinatura
        $arrObjAtributoAndamentoDTO = array();
        $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
        $objAtributoAndamentoDTO->setStrNome('DOCUMENTO');
        $objAtributoAndamentoDTO->setStrValor($objDocumentoDTO->getStrProtocoloDocumentoFormatado());
        $objAtributoAndamentoDTO->setStrIdOrigem($parObjDocumentoDTO->getDblIdDocumento());
        $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;

        $numIdTarefaCancelamentoAssinatura = TarefaRN::$TI_CANCELAMENTO_ASSINATURA;
        if($objDocumentoDTO->getStrStaDocumento() == DocumentoRN::$TD_EXTERNO) {
          $numIdTarefaCancelamentoAssinatura = TarefaRN::$TI_CANCELAMENTO_AUTENTICACAO;
        }

        $objAtividadeDTO = new AtividadeDTO();
        $objAtividadeDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdProcedimento());
        $objAtividadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
        $objAtividadeDTO->setNumIdTarefa($numIdTarefaCancelamentoAssinatura);
        $objAtividadeDTO->setArrObjAtributoAndamentoDTO($arrObjAtributoAndamentoDTO);

        $objAtividadeRN = new AtividadeRN();
        $objAtividadeRN->gerarInternaRN0727($objAtividadeDTO);

        $objIndexacaoDTO = new IndexacaoDTO();
        $objIndexacaoDTO->setArrIdProtocolos(array($parObjDocumentoDTO->getDblIdDocumento()));
        $objIndexacaoDTO->setStrStaOperacao(IndexacaoRN::$TO_PROTOCOLO_METADADOS);

        $objIndexacaoRN = new IndexacaoRN();
        $objIndexacaoRN->indexarProtocolo($objIndexacaoDTO);
      }

    }catch(Exception $e){
      throw new InfraException('Erro cancelando assinatura.',$e);
    }
  }

  public function verificarSelecaoEmail(DocumentoDTO $objDocumentoDTO){

    //exclui cancelados
    //documento externos
    //formul�rios autom�ticos
    //gerados/formul�rios assinados ou publicados

    return ($objDocumentoDTO->getStrStaEstadoProtocolo() != ProtocoloRN::$TE_DOCUMENTO_CANCELADO
             &&
             (
              $objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_EXTERNO
                 ||
              $objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_FORMULARIO_AUTOMATICO
                 ||
              (
                  ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_EDITOR_INTERNO  || $objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_FORMULARIO_GERADO || ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_EDITOR_EDOC && $objDocumentoDTO->getDblIdDocumentoEdoc()!=null))
                  &&
                  ($objDocumentoDTO->getStrSinAssinado()=='S' || $objDocumentoDTO->getStrSinPublicado()=='S')
              )
             )
           );
  }

  public function verificarSelecaoDuplicacao(DocumentoDTO $objDocumentoDTO){

    //exclui cancelados
    //documento externos
    //gerados/formul�rios/edoc (da unidade atual ou assinados ou publicados)

    return ($objDocumentoDTO->getStrStaEstadoProtocolo() != ProtocoloRN::$TE_DOCUMENTO_CANCELADO
            &&
             ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_EXTERNO ||
               (
                   $this->verificarConteudoGerado($objDocumentoDTO)
                   &&
                   ($objDocumentoDTO->getNumIdUnidadeGeradoraProtocolo()==SessaoSEI::getInstance()->getNumIdUnidadeAtual() || $objDocumentoDTO->getStrSinAssinado()=='S' || $objDocumentoDTO->getStrSinPublicado()=='S')
               )
             )
           );
  }

  public function verificarSelecaoGeracaoPdf(DocumentoDTO $objDocumentoDTO){

    //exclui cancelados
    //documento externos (pdf, text, html)
    //formularios
    //gerados pela unidade atual [inclui rascunhos]
    //gerados assinados
    //gerados publicados

    if ($objDocumentoDTO->getStrStaEstadoProtocolo() == ProtocoloRN::$TE_DOCUMENTO_CANCELADO){
      return false;
    }

    if ($objDocumentoDTO->getStrStaProtocoloProtocolo()==ProtocoloRN::$TP_DOCUMENTO_RECEBIDO){
      $objAnexoDTO = new AnexoDTO();
      $objAnexoDTO->retNumIdAnexo();
      $objAnexoDTO->retStrNome();
      $objAnexoDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdDocumento());
      $objAnexoRN = new AnexoRN();
      $objAnexoDTO = $objAnexoRN->consultarRN0736($objAnexoDTO);

      if ($objAnexoDTO!=null){
        if (InfraUtil::getStrMimeType($objAnexoDTO->getStrNome()) == 'application/pdf' ||
            InfraUtil::getStrMimeType($objAnexoDTO->getStrNome()) == 'text/plain' ||
            InfraUtil::getStrMimeType($objAnexoDTO->getStrNome()) == 'text/html' ||
            $this->ehUmaExtensaoDeImagemPermitida($objAnexoDTO->getStrNome())){
          return true;
        }
        if ($this->processarArquivoOpenOffice($objAnexoDTO->getStrNome())){
          return true;
        }
      }
    }

    if ($objDocumentoDTO->getStrStaProtocoloProtocolo()==ProtocoloRN::$TP_DOCUMENTO_GERADO){

      if ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_FORMULARIO_AUTOMATICO){
        return true;
      }

      if ($this->verificarConteudoGerado($objDocumentoDTO)){
        if ($objDocumentoDTO->getNumIdUnidadeGeradoraProtocolo()==SessaoSEI::getInstance()->getNumIdUnidadeAtual() ||
            $objDocumentoDTO->getStrSinAssinado()=='S' ||
            $objDocumentoDTO->getStrSinPublicado()=='S'){
          return true;
        }
      }
    }

    return false;
  }

  public function verificarSelecaoGeracaoZip(DocumentoDTO $objDocumentoDTO){
    //exclui cancelados
    //documento externos
    //formularios
    //gerados pela unidade atual [inclui rascunhos]
    //gerados assinados
    //gerados publicados

    if ($objDocumentoDTO->getStrStaEstadoProtocolo() == ProtocoloRN::$TE_DOCUMENTO_CANCELADO){
      return false;
    }

    if ($objDocumentoDTO->getStrStaProtocoloProtocolo()==ProtocoloRN::$TP_DOCUMENTO_RECEBIDO){
      $objAnexoDTO = new AnexoDTO();
      $objAnexoDTO->retNumIdAnexo();
      $objAnexoDTO->retStrNome();
      $objAnexoDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdDocumento());
      $objAnexoRN = new AnexoRN();
      $objAnexoDTO = $objAnexoRN->consultarRN0736($objAnexoDTO);

      if ($objAnexoDTO!=null) {
        return true;
      }
    }

    if ($objDocumentoDTO->getStrStaProtocoloProtocolo()==ProtocoloRN::$TP_DOCUMENTO_GERADO){

      if ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_FORMULARIO_AUTOMATICO){
        return true;
      }

      if ($this->verificarConteudoGerado($objDocumentoDTO)){
        if ($objDocumentoDTO->getNumIdUnidadeGeradoraProtocolo()==SessaoSEI::getInstance()->getNumIdUnidadeAtual() ||
            $objDocumentoDTO->getStrSinAssinado()=='S' ||
            $objDocumentoDTO->getStrSinPublicado()=='S'){
          return true;
        }
      }
    }

    return false;
  }

  public function verificarSelecaoBlocoAssinatura(DocumentoDTO $objDocumentoDTO){

    //exclui sigilosos
    //exclui cancelados
    //documentos/formul�rios gerados
    //unidade geradora igual a unidade atual

    return ($objDocumentoDTO->getStrStaNivelAcessoGlobalProtocolo() != ProtocoloRN::$NA_SIGILOSO &&
            $objDocumentoDTO->getStrStaEstadoProtocolo() != ProtocoloRN::$TE_DOCUMENTO_CANCELADO  &&
           ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_EDITOR_INTERNO || $objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_FORMULARIO_GERADO) &&
            $objDocumentoDTO->getNumIdUnidadeGeradoraProtocolo()==SessaoSEI::getInstance()->getNumIdUnidadeAtual());
  }

  public function verificarSelecaoAcessoExterno(DocumentoDTO $objDocumentoDTO){

    //exclui cancelados
    //documentos externos
    //formularios
    //documentos gerados assinados ou publicados

    return ($objDocumentoDTO->getStrStaEstadoProtocolo() != ProtocoloRN::$TE_DOCUMENTO_CANCELADO
             &&
             (
                 $objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_EXTERNO
                 ||
                 $objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_FORMULARIO_AUTOMATICO
                 ||
                 ($this->verificarConteudoGerado($objDocumentoDTO) && ($objDocumentoDTO->getStrSinAssinado()=='S' || $objDocumentoDTO->getStrSinPublicado()=='S'))
             )
           );
  }

  public function verificarSelecaoAssinaturaExterna(DocumentoDTO $objDocumentoDTO){

    //exclui cancelados
    //documentos assinados
    //documentos n�o publicados
    //internos ou edoc com conteudo

    return ($objDocumentoDTO->getStrStaEstadoProtocolo()!=ProtocoloRN::$TE_DOCUMENTO_CANCELADO &&
        //$objDocumentoDTO->getStrSinAssinado()=='S' &&
        $objDocumentoDTO->getStrSinPublicado()=='N' &&
        $this->verificarConteudoGerado($objDocumentoDTO));
  }

  public function verificarConteudoGerado(DocumentoDTO $objDocumentoDTO){
    //editor interno
    //editor edoc com conteudo
    return ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_EDITOR_INTERNO ||
           ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_FORMULARIO_GERADO) ||
           ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_EDITOR_EDOC && $objDocumentoDTO->getDblIdDocumentoEdoc()!=null));
  }

  public function verificarSelecaoNotificacao(DocumentoDTO $objDocumentoDTO){

    //exclui cancelados
    //documento externos
    //gerados assinados ou publicados

    return ($objDocumentoDTO->getStrStaEstadoProtocolo() != ProtocoloRN::$TE_DOCUMENTO_CANCELADO  &&
        ($objDocumentoDTO->getStrStaProtocoloProtocolo()==ProtocoloRN::$TP_DOCUMENTO_RECEBIDO || ($objDocumentoDTO->getStrStaProtocoloProtocolo()==ProtocoloRN::$TP_DOCUMENTO_GERADO && ($objDocumentoDTO->getStrSinAssinado()=='S' || $objDocumentoDTO->getStrSinPublicado()=='S'))));
  }

  protected function obterLinkAcessoControlado(LinkAcessoDTO $objLinkAcessoDTO){
    try{

      $objInfraException = new InfraException();

      $objProtocoloDTO = new ProtocoloDTO();
      $objProtocoloDTO->retDblIdProtocolo();
      $objProtocoloDTO->retStrProtocoloFormatado();
      $objProtocoloDTO->retStrStaNivelAcessoGlobal();
      $objProtocoloDTO->setStrProtocoloFormatado($objLinkAcessoDTO->getStrProtocoloDocumentoFormatado());
      $objProtocoloDTO->setStrStaProtocolo(array(ProtocoloRN::$TP_DOCUMENTO_GERADO,ProtocoloRN::$TP_DOCUMENTO_RECEBIDO),InfraDTO::$OPER_IN);
      $objProtocoloDTO->setStrStaNivelAcessoGlobal(ProtocoloRN::$NA_SIGILOSO, InfraDTO::$OPER_DIFERENTE);

      $objProtocoloRN = new ProtocoloRN();
      $objProtocoloDTO = $objProtocoloRN->consultarRN0186($objProtocoloDTO);

      if ($objProtocoloDTO==null){
        $objInfraException->lancarValidacao('Documento '.$objLinkAcessoDTO->getStrProtocoloDocumentoFormatado().' n�o encontrado.');
      }

      //if ($objProtocoloDTO->getStrStaNivelAcessoGlobal()!=ProtocoloRN::$NA_PUBLICO){
      //  $objInfraException->lancarValidacao('Documento '.$objLinkAcessoDTO->getStrProtocoloDocumentoFormatado().' n�o � p�blico.');
      //}

      //obtem processo do documento
      $objRelProtocoloProtocoloDTO = new RelProtocoloProtocoloDTO();
      $objRelProtocoloProtocoloDTO->retDblIdProtocolo1();
      $objRelProtocoloProtocoloDTO->retStrProtocoloFormatadoProtocolo1();
      $objRelProtocoloProtocoloDTO->setDblIdProtocolo2($objProtocoloDTO->getDblIdProtocolo());
      $objRelProtocoloProtocoloDTO->setStrStaAssociacao(RelProtocoloProtocoloRN::$TA_DOCUMENTO_ASSOCIADO);

      $objRelProtocoloProtocoloRN = new RelProtocoloProtocoloRN();
      $objRelProtocoloProtocoloDTO = $objRelProtocoloProtocoloRN->consultarRN0841($objRelProtocoloProtocoloDTO);

      $dblIdProcedimento = $objRelProtocoloProtocoloDTO->getDblIdProtocolo1();
      $strProtocoloProcedimentoFormatado = $objRelProtocoloProtocoloDTO->getStrProtocoloFormatadoProtocolo1();

      $dblIdDocumento = $objProtocoloDTO->getDblIdProtocolo();
      $strProtocoloDocumentoFormatado = $objProtocoloDTO->getStrProtocoloFormatado();


      $objLinkAcessoDTO = new $objLinkAcessoDTO();
      $objLinkAcessoDTO->setDblIdProcedimento($dblIdProcedimento);
      $objLinkAcessoDTO->setStrProtocoloProcedimentoFormatado($strProtocoloProcedimentoFormatado);
      $objLinkAcessoDTO->setStrLinkProcesso(ConfiguracaoSEI::getInstance()->getValor('SEI','URL').'/controlador.php?acao=procedimento_trabalhar&id_procedimento='.$dblIdProcedimento);
      $objLinkAcessoDTO->setDblIdDocumento($dblIdDocumento);
      $objLinkAcessoDTO->setStrProtocoloDocumentoFormatado($strProtocoloDocumentoFormatado);
      $objLinkAcessoDTO->setStrLinkDocumento(ConfiguracaoSEI::getInstance()->getValor('SEI','URL').'/controlador.php?acao=procedimento_trabalhar&id_procedimento='.$dblIdProcedimento.'&id_documento='.$dblIdDocumento);

      return $objLinkAcessoDTO;

    }catch(Exception $e){
      throw new InfraException('Erro obtendo link de acesso.',$e);
    }
  }

  protected function obterDocumentoAutenticidadeConectado(DocumentoDTO $parObjDocumentoDTO){
    try{

      $objInfraException = new InfraException();

      $this->validarStrCodigoVerificador($parObjDocumentoDTO, $objInfraException);
      $this->validarStrCrcAssinatura($parObjDocumentoDTO, $objInfraException);

      $strCodigoVerificador = $this->prepararCodigoVerificador($parObjDocumentoDTO->getStrCodigoVerificador());

      $objDocumentoDTO = new DocumentoDTO();
      $objDocumentoDTO->retDblIdDocumento();
      $objDocumentoDTO->retDblIdProcedimento();
      $objDocumentoDTO->retStrProtocoloDocumentoFormatado();
      $objDocumentoDTO->retStrNomeSerie();
      $objDocumentoDTO->retStrNumero();
      $objDocumentoDTO->retStrCrcAssinatura();
      $objDocumentoDTO->retStrStaDocumento();
      $objDocumentoDTO->retStrStaEstadoProtocolo();
      $objDocumentoDTO->retStrStaProtocoloProtocolo();
      $objDocumentoDTO->retStrConteudoAssinatura();
      $objDocumentoDTO->retStrSinBloqueado();
      $objDocumentoDTO->retNumIdUnidadeGeradoraProtocolo();
      $objDocumentoDTO->setStrProtocoloDocumentoFormatado($strCodigoVerificador);

      $objDocumentoDTO2 = $this->consultarRN0005($objDocumentoDTO);

      if ($objDocumentoDTO2==null){
        $objDocumentoDTO->unSetStrProtocoloDocumentoFormatado();
        $objDocumentoDTO->setDblIdDocumentoEdoc($strCodigoVerificador);
        $objDocumentoDTO2 = $this->consultarRN0005($objDocumentoDTO);
      }

      $objDocumentoDTO = $objDocumentoDTO2;

      if ($objDocumentoDTO==null){
        $objInfraException->lancarValidacao('Nenhum documento encontrado para o c�digo verificador informado.');
      }

      if ($objDocumentoDTO->getStrStaEstadoProtocolo()==ProtocoloRN::$TE_DOCUMENTO_CANCELADO){
        $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
        $objAtributoAndamentoDTO->retDthAberturaAtividade();
        $objAtributoAndamentoDTO->setStrNome('DOCUMENTO');
        $objAtributoAndamentoDTO->setStrIdOrigem($objDocumentoDTO->getDblIdDocumento());
        $objAtributoAndamentoDTO->setNumIdTarefaAtividade(TarefaRN::$TI_CANCELAMENTO_DOCUMENTO);
        $objAtributoAndamentoDTO->setDblIdProtocoloAtividade($objDocumentoDTO->getDblIdProcedimento());

        $objAtributoAndamentoRN = new AtributoAndamentoRN();
        $objAtributoAndamentoDTO = $objAtributoAndamentoRN->consultarRN1366($objAtributoAndamentoDTO);

        $objInfraException->lancarValidacao('Este documento foi cancelado em '.$objAtributoAndamentoDTO->getDthAberturaAtividade().'.');
      }

      if ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_EDITOR_INTERNO){

        if ($objDocumentoDTO->getStrCrcAssinatura() != $parObjDocumentoDTO->getStrCrcAssinatura()){
          $objInfraException->lancarValidacao('O c�digo CRC informado n�o confere com a �ltima vers�o do documento.');
        }

        $objEditorDTO = new EditorDTO();
        $objEditorDTO->setDblIdDocumento($objDocumentoDTO->getDblIdDocumento());
        $objEditorDTO->setNumIdBaseConhecimento(null);
        $objEditorDTO->setStrSinCabecalho('S');
        $objEditorDTO->setStrSinRodape('S');
        $objEditorDTO->setStrSinCarimboPublicacao('N');
        $objEditorDTO->setStrSinIdentificacaoVersao('N');
        $objEditorRN = new EditorRN();
        $objDocumentoDTO->setStrConteudo($objEditorRN->consultarHtmlVersao($objEditorDTO));

      }else if ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_EDITOR_EDOC){


        if ($objDocumentoDTO->getStrCrcAssinatura() != $parObjDocumentoDTO->getStrCrcAssinatura()) {
          $objInfraException->lancarValidacao('O c�digo CRC informado n�o confere com a �ltima vers�o do documento.');
        }
        $objEDocRN = new EDocRN();
        $objDocumentoDTO->setDblIdDocumentoEdoc($strCodigoVerificador);
        $objDocumentoDTO->setStrConteudo($objEDocRN->consultarHTMLDocumentoRN1204($objDocumentoDTO));

      }else if ($objDocumentoDTO->getStrStaProtocoloProtocolo()==ProtocoloRN::$TP_DOCUMENTO_RECEBIDO) {

        if ($objDocumentoDTO->getStrCrcAssinatura() != $parObjDocumentoDTO->getStrCrcAssinatura()) {
          $objInfraException->lancarValidacao('O c�digo CRC informado n�o confere com a �ltima vers�o do documento.');
        }

      }else if ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_FORMULARIO_GERADO){

        if ($objDocumentoDTO->getStrCrcAssinatura() != $parObjDocumentoDTO->getStrCrcAssinatura()) {
          $objInfraException->lancarValidacao('O c�digo CRC informado n�o confere com a �ltima vers�o do documento.');
        }

        $objDocumentoDTO->setStrConteudo($this->consultarHtmlFormulario($objDocumentoDTO));

      }else{
        $objInfraException->lancarValidacao('Nenhum documento encontrado para o c�digo verificador informado.');
      }


      $objAssinaturaDTO = new AssinaturaDTO();
      $objAssinaturaDTO->retNumIdAssinatura();
      $objAssinaturaDTO->retDblIdDocumento();
      $objAssinaturaDTO->retDblIdProcedimentoDocumento();
      $objAssinaturaDTO->retStrNome();
      $objAssinaturaDTO->retStrTratamento();
      $objAssinaturaDTO->retDblCpf();
      $objAssinaturaDTO->retStrNumeroSerieCertificado();
      $objAssinaturaDTO->retDthAberturaAtividade();
      $objAssinaturaDTO->retStrStaFormaAutenticacao();
      $objAssinaturaDTO->retStrSiglaUnidade();
      $objAssinaturaDTO->retStrDescricaoUnidade();
      $objAssinaturaDTO->retStrStaProtocoloProtocolo();
      $objAssinaturaDTO->retStrStaDocumentoDocumento();
      $objAssinaturaDTO->retStrP7sBase64();
      $objAssinaturaDTO->setDblIdDocumento($objDocumentoDTO->getDblIdDocumento());
      $objAssinaturaDTO->setOrdStrNomeUsuario(InfraDTO::$TIPO_ORDENACAO_ASC);

      $objAssinaturaRN = new AssinaturaRN();
      $objDocumentoDTO->setArrObjAssinaturaDTO($objAssinaturaRN->listarRN1323($objAssinaturaDTO));

      return $objDocumentoDTO;

    }catch(Exception $e){
      throw new InfraException('Erro obtendo documento para confer�ncia de autentidade.',$e);
    }
  }

  protected function obterHashDocumentoAssinaturaConectado(AssinaturaDTO $parObjAssinaturaDTO){
    try{

      $ret = null;

      $objAssinaturaDTO = new AssinaturaDTO();
      $objAssinaturaDTO->setBolExclusaoLogica(false);
      $objAssinaturaDTO->retNumIdAssinatura();
      $objAssinaturaDTO->setNumIdAssinatura($parObjAssinaturaDTO->getNumIdAssinatura());
      $objAssinaturaDTO->setDblIdDocumento($parObjAssinaturaDTO->getDblIdDocumento());
      $objAssinaturaDTO->setStrSinAtivo('N');
      $objAssinaturaDTO->setNumMaxRegistrosRetorno(1);

      $objAssinaturaRN = new AssinaturaRN();
      if ($objAssinaturaRN->consultarRN1322($objAssinaturaDTO) == null){
        throw new InfraException('Assinatura pendente n�o encontrada.');
      }

      $objDocumentoDTO = new DocumentoDTO();
      $objDocumentoDTO->retDblIdDocumento();
      $objDocumentoDTO->retStrStaProtocoloProtocolo();
      $objDocumentoDTO->retStrConteudoAssinatura();
      $objDocumentoDTO->setDblIdDocumento($parObjAssinaturaDTO->getDblIdDocumento());

      $objDocumentoDTO = $this->consultarRN0005($objDocumentoDTO);

      if ($objDocumentoDTO==null){
        throw new InfraException('Documento para assinatura n�o encontrado.');
      }

      if ($objDocumentoDTO->getStrStaProtocoloProtocolo()==ProtocoloRN::$TP_DOCUMENTO_GERADO){

        $ret = hash('sha512', $objDocumentoDTO->getStrConteudoAssinatura(), true);

      }else{

        $objAnexoDTO = new AnexoDTO();
        $objAnexoDTO->retNumIdAnexo();
        $objAnexoDTO->retStrNome();
        $objAnexoDTO->retDblIdProtocolo();
        $objAnexoDTO->retDthInclusao();
        $objAnexoDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdDocumento());

        $objAnexoRN = new AnexoRN();
        $objAnexoDTO = $objAnexoRN->consultarRN0736($objAnexoDTO);

        if ($objAnexoDTO==null){
          throw new InfraException('Anexo do documento para assinatura n�o encontrado.');
        }

        $ret = hash_file('sha512', $objAnexoRN->obterLocalizacao($objAnexoDTO), true);
      }

      return $ret;

    }catch(Exception $e){
      throw new InfraException('Erro obtendo documento para assinatura.',$e);
    }
  }

  private function ehUmaExtensaoDeImagemPermitida($strNomeArquivo){
    switch(InfraUtil::getStrMimeType($strNomeArquivo)){
      case 'image/jpeg':
        return true;
        break;
      case 'image/png':
        return true;
        break;
      case 'image/gif':
        return true;
        break;
      case 'image/bmp':
        return true;
        break;
      default:
        return false;

    }
  }

  private function processarArquivoOpenOffice($strNomeArquivo){

    if (!ConfiguracaoSEI::getInstance()->isSetValor('JODConverter','Servidor') || ConfiguracaoSEI::getInstance()->getValor('JODConverter','Servidor')==''){
      return false;
    }

    switch(InfraUtil::getStrMimeType($strNomeArquivo)){
      case 'text/csv':
      case 'application/msword':
      case 'application/vnd.oasis.opendocument.spreadsheet':
      case 'application/vnd.oasis.opendocument.text':
      case 'application/vnd.ms-powerpoint':
      case 'text/rtf':
      case 'application/vnd.ms-excel':
      case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
      case 'application/vnd.openxmlformats-officedocument.spreadsheetml.template':
      case 'application/vnd.openxmlformats-officedocument.presentationml.template':
      case 'application/vnd.openxmlformats-officedocument.presentationml.slideshow':
      case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
      case 'application/vnd.openxmlformats-officedocument.presentationml.slide':
      case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
      case 'application/vnd.openxmlformats-officedocument.wordprocessingml.template':
      case 'application/vnd.ms-excel.addin.macroEnabled.12':
      case 'application/vnd.ms-excel.sheet.binary.macroEnabled.12':
      case 'application/vnd.oasis.opendocument.text-template':
      case 'application/vnd.oasis.opendocument.presentation':
        return true;
        break;
      default:
        return false;
    }
  }

  protected function gerarPdfConectado($parArrObjDocumentoDTO) {
    try{

      SessaoSEI::getInstance()->validarAuditarPermissao('procedimento_gerar_pdf',__METHOD__,$parArrObjDocumentoDTO);

      ini_set('max_execution_time','300');

      $objInfraException = new InfraException();

      $parArrObjDocumentoDTO = InfraArray::indexarArrInfraDTO($parArrObjDocumentoDTO,'IdDocumento');

      $arrIdDocumentos = array_keys($parArrObjDocumentoDTO);

      $objDocumentoDTO = new DocumentoDTO();
      $objDocumentoDTO->retDblIdDocumento();
      $objDocumentoDTO->retDblIdProcedimento();
      $objDocumentoDTO->retStrStaProtocoloProtocolo();
      $objDocumentoDTO->retStrNumero();
      $objDocumentoDTO->retStrNomeSerie();
      $objDocumentoDTO->retStrProtocoloDocumentoFormatado();
      $objDocumentoDTO->retStrProtocoloProcedimentoFormatado();
      $objDocumentoDTO->retStrSiglaUnidadeGeradoraProtocolo();
      $objDocumentoDTO->retStrStaDocumento();
      $objDocumentoDTO->retDblIdDocumentoEdoc();
      //$objDocumentoDTO->retStrConteudo();
      $objDocumentoDTO->setDblIdDocumento($arrIdDocumentos, InfraDTO::$OPER_IN);

      $arrObjDocumentoDTO = $this->listarRN0008($objDocumentoDTO);

      if (count($arrObjDocumentoDTO)==0){
        throw new InfraException('Nenhum documento informado.');
      }

      $strProtocoloProcedimentoFormatado = $arrObjDocumentoDTO[0]->getStrProtocoloProcedimentoFormatado();

      $arrObjDocumentoDTO = InfraArray::indexarArrInfraDTO($arrObjDocumentoDTO,'IdDocumento');

      $strDocumentosGeracaoPdf = '';
      $arrComandoExecucao = array();
      $numParteArquivoPdf = 1;
      $arrArquivoPdfParcial = array();
      $arrArquivoTemp = array();
      $objAnexoRN = new AnexoRN();

      foreach($arrIdDocumentos as $dblIdDocumento){
        $objDocumentoDTO = $arrObjDocumentoDTO[$dblIdDocumento];
        if ($strDocumentosGeracaoPdf != ''){
          $strDocumentosGeracaoPdf .= '�';
        }

        $strIdentificacaoDocumento = DocumentoINT::montarIdentificacaoArvore($objDocumentoDTO);

        //PDFBox n�o suporta alguns caracteres
        for($i=0;$i<strlen($strIdentificacaoDocumento);$i++){
          $numCodigoCaracter = ord($strIdentificacaoDocumento{$i});
          if ($numCodigoCaracter==150 || $numCodigoCaracter==151){
            $strIdentificacaoDocumento{$i} = '-';
          }else if ($numCodigoCaracter==145 || $numCodigoCaracter==146){
            $strIdentificacaoDocumento{$i} = "'";
          }else if ($numCodigoCaracter==147 || $numCodigoCaracter==148){
            $strIdentificacaoDocumento{$i} = '"';
          }else if ($numCodigoCaracter==149){
          $strIdentificacaoDocumento{$i} = '*';
          }
        }

        $strEscalaCinza = '';
        if ($parArrObjDocumentoDTO[$dblIdDocumento]->isSetStrSinPdfEscalaCinza() && $parArrObjDocumentoDTO[$dblIdDocumento]->getStrSinPdfEscalaCinza()=='S'){
          $strEscalaCinza = ' --grayscale';
        }

        $strDocumentosGeracaoPdf .= base64_encode($strIdentificacaoDocumento);

        $strDocumento = '';
        if ($objDocumentoDTO->getStrStaProtocoloProtocolo() == ProtocoloRN::$TP_DOCUMENTO_GERADO){
          if ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_EDITOR_EDOC){
            if ($objDocumentoDTO->getDblIdDocumentoEdoc()==null){
              $strDocumento .= 'Documento e-Doc '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado() .' n�o encontrado.';
              $objInfraException->adicionarValidacao('Documento e-Doc '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado() .' n�o encontrado.');
            }else{
              $strDocumento .= EDocINT::montarVisualizacaoDocumento($objDocumentoDTO->getDblIdDocumentoEdoc());
            }
          }else if ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_EDITOR_INTERNO){

            $objEditorDTO = new EditorDTO();
            $objEditorDTO->setDblIdDocumento($objDocumentoDTO->getDblIdDocumento());
            $objEditorDTO->setNumIdBaseConhecimento(null);
            $objEditorDTO->setStrSinCabecalho('S');
            $objEditorDTO->setStrSinRodape('S');
            $objEditorDTO->setStrSinCarimboPublicacao('S');
            $objEditorDTO->setStrSinIdentificacaoVersao('N');

            $objEditorRN = new EditorRN();
            $strDocumento .= $objEditorRN->consultarHtmlVersao($objEditorDTO);
          }else{

            // email, por exemplo
            $strDocumento .= $this->consultarHtmlFormulario($objDocumentoDTO);
          }

          $strArquivoHtmlTemp = DIR_SEI_TEMP.'/'.$objAnexoRN->gerarNomeArquivoTemporario('.html');
          $arrArquivoTemp[] = $strArquivoHtmlTemp;
          if (file_put_contents($strArquivoHtmlTemp,$strDocumento) === false){
            throw new InfraException('Erro criando arquivo html tempor�rio para cria��o de pdf.');
          }
          $strArquivoPdfParcial = DIR_SEI_TEMP.'/'.$objAnexoRN->gerarNomeArquivoTemporario('-parte'.$numParteArquivoPdf++.'.pdf');
          $arrArquivoPdfParcial[] = $strArquivoPdfParcial;
          $arrComandoExecucao[] = DIR_SEI_BIN.'/wkhtmltopdf-amd64 '.$strEscalaCinza.' --quiet --title processo-'.InfraUtil::retirarFormatacao($objDocumentoDTO->getStrProtocoloProcedimentoFormatado(),false) .' ' .$strArquivoHtmlTemp.' ' .$strArquivoPdfParcial .' 2>&1';
        }else if ($objDocumentoDTO->getStrStaProtocoloProtocolo()==ProtocoloRN::$TP_DOCUMENTO_RECEBIDO){
          $objAnexoDTO = new AnexoDTO();
          $objAnexoDTO->retNumIdAnexo();
          $objAnexoDTO->retStrNome();
          $objAnexoDTO->retDthInclusao();
          $objAnexoDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdDocumento());
          $objAnexoRN = new AnexoRN();
          $objAnexoDTO = $objAnexoRN->consultarRN0736($objAnexoDTO);
          if (InfraUtil::getStrMimeType($objAnexoDTO->getStrNome()) == 'application/pdf' || InfraUtil::getStrMimeType($objAnexoDTO->getStrNome()) == 'text/html' || InfraUtil::getStrMimeType($objAnexoDTO->getStrNome()) == 'text/plain' || $this->ehUmaExtensaoDeImagemPermitida($objAnexoDTO->getStrNome()) || $this->processarArquivoOpenOffice($objAnexoDTO->getStrNome())){
            if ($objAnexoDTO==null){
              $objInfraException->adicionarValidacao('Documento '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado() .' n�o encontrado.');
            }else{

              if (InfraUtil::getStrMimeType($objAnexoDTO->getStrNome()) == 'application/pdf'){
                $strArquivoPdfParcial = DIR_SEI_TEMP.'/'.$objAnexoRN->gerarNomeArquivoTemporario('-parte'.$numParteArquivoPdf++.'.pdf');
                if (copy($objAnexoRN->obterLocalizacao($objAnexoDTO), $strArquivoPdfParcial) === false){
                  throw new InfraException('Erro criando arquivo pdf tempor�rio para cria��o de pdf.');
                }
                $arrArquivoPdfParcial[] = $strArquivoPdfParcial;
              }else if (InfraUtil::getStrMimeType($objAnexoDTO->getStrNome()) == 'text/html'){
                $strArquivoHtmlTemp = DIR_SEI_TEMP.'/'.$objAnexoRN->gerarNomeArquivoTemporario('.html');
                $arrArquivoTemp[] = $strArquivoHtmlTemp;
                if (copy($objAnexoRN->obterLocalizacao($objAnexoDTO), $strArquivoHtmlTemp) === false){
                  throw new InfraException('Erro criando arquivo html tempor�rio para cria��o de pdf.');
                }
                $this->prepararHtmlToPdf($strArquivoHtmlTemp);
                $strArquivoPdfParcial = DIR_SEI_TEMP.'/'.$objAnexoRN->gerarNomeArquivoTemporario('-parte'.$numParteArquivoPdf++.'.pdf');
                $arrArquivoPdfParcial[] = $strArquivoPdfParcial;
                $arrComandoExecucao[] = DIR_SEI_BIN.'/wkhtmltopdf-amd64 '.$strEscalaCinza.' --quiet --title processo-'.InfraUtil::retirarFormatacao($objDocumentoDTO->getStrProtocoloProcedimentoFormatado(),false) .' ' .$strArquivoHtmlTemp  .' ' .$strArquivoPdfParcial .' 2>&1';
              }else if (InfraUtil::getStrMimeType($objAnexoDTO->getStrNome()) == 'text/plain'){
                $strCaminhoCompletoArquivoTxt = DIR_SEI_TEMP.'/'.$objAnexoRN->gerarNomeArquivoTemporario('.txt');
                if (copy($objAnexoRN->obterLocalizacao($objAnexoDTO), $strCaminhoCompletoArquivoTxt) === false){
                  throw new InfraException('Erro criando arquivo txt tempor�rio para cria��o de pdf.');
                }
                $strArquivoPdfParcial = DIR_SEI_TEMP.'/'.$objAnexoRN->gerarNomeArquivoTemporario('-parte'.$numParteArquivoPdf++.'.pdf');
                $arrArquivoPdfParcial[] = $strArquivoPdfParcial;
                $arrComandoExecucao[] = DIR_SEI_BIN.'/wkhtmltopdf-amd64 '.$strEscalaCinza.' --quiet --title processo-'.InfraUtil::retirarFormatacao($objDocumentoDTO->getStrProtocoloProcedimentoFormatado(),false) .' ' .$strCaminhoCompletoArquivoTxt  .' ' .$strArquivoPdfParcial .' 2>&1';
              }else if ($this->ehUmaExtensaoDeImagemPermitida($objAnexoDTO->getStrNome())){
                // criar imagem temp
                $strCaminhoCompletoArquivoImagem = DIR_SEI_TEMP.'/'.$objAnexoRN->gerarNomeArquivoTemporario('.imagem');
                $arrArquivoTemp[] = $strCaminhoCompletoArquivoImagem;
                if (copy($objAnexoRN->obterLocalizacao($objAnexoDTO), $strCaminhoCompletoArquivoImagem) === false){
                  throw new InfraException('Erro criando arquivo de imagem tempor�ria para cria��o de html.');
                }

                // criar html que contenha a imagem
                $strDocumentoHTML = "<html>\n<head>\n<title>Anexo Imagem</title>\n";
                $strDocumentoHTML .= "</head>\n<body>\n";
                $strDocumentoHTML .= "<img src=\"". $strCaminhoCompletoArquivoImagem . "\">";
                $strDocumentoHTML .= "</body>\n</html>";
                $strArquivoHtmlTemp = DIR_SEI_TEMP.'/'.$objAnexoRN->gerarNomeArquivoTemporario('.html');
                $arrArquivoTemp[] = $strArquivoHtmlTemp;
                if (file_put_contents($strArquivoHtmlTemp,$strDocumentoHTML) === false){
                  throw new InfraException('Erro criando arquivo html com imagem tempor�rio para cria��o de pdf.');
                }
                $strArquivoPdfParcial = DIR_SEI_TEMP.'/'.$objAnexoRN->gerarNomeArquivoTemporario('-parte'.$numParteArquivoPdf++.'.pdf');
                $arrArquivoPdfParcial[] = $strArquivoPdfParcial;
                $arrComandoExecucao[] = DIR_SEI_BIN.'/wkhtmltopdf-amd64 '.$strEscalaCinza.' --quiet --title processo-'.InfraUtil::retirarFormatacao($objDocumentoDTO->getStrProtocoloProcedimentoFormatado(),false) .' ' .$strArquivoHtmlTemp  .' ' .$strArquivoPdfParcial .' 2>&1';

              }else if ($this->processarArquivoOpenOffice($objAnexoDTO->getStrNome())){
                $strCaminhoCompletoArquivoOpenOffice = DIR_SEI_TEMP.'/'.$objAnexoRN->gerarNomeArquivoTemporario('.oo');
                $arrArquivoTemp[] = $strCaminhoCompletoArquivoOpenOffice;
                if (copy($objAnexoRN->obterLocalizacao($objAnexoDTO), $strCaminhoCompletoArquivoOpenOffice) === false){
                  throw new InfraException('Erro criando arquivo openoffice tempor�rio para cria��o de pdf.');
                }
                $strArquivoPdfParcial = DIR_SEI_TEMP.'/'.$objAnexoRN->gerarNomeArquivoTemporario('-parte'.$numParteArquivoPdf++.'.pdf');
                $arrArquivoPdfParcial[] = $strArquivoPdfParcial;
                $arrComandoExecucao[] = 'wget --no-proxy --quiet ' .ConfiguracaoSEI::getInstance()->getValor('JODConverter','Servidor') .' --post-file=' .$strCaminhoCompletoArquivoOpenOffice .' --header="Content-Type: ' .InfraUtil::getStrMimeType($objAnexoDTO->getStrNome()) .'" --header="Accept: application/pdf" --output-document=' .$strArquivoPdfParcial .' 2>&1';
              }
            }
          }
        }else{
          $objInfraException->adicionarValidacao('N�o foi poss�vel detectar o tipo do documento '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado());
        }
      }

      foreach($arrComandoExecucao as $strComandoExecucao){
        $ret = shell_exec($strComandoExecucao);
        if ($ret != ''){
          throw new InfraException('Erro gerando PDF.', null, "Comando - ".$strComandoExecucao."\n\nRetorno - ".$ret);
        }
      }

      $objInfraException->lancarValidacoes();

      $strCaminhoCompletoArquivoPdfTotal = DIR_SEI_TEMP.'/'.$objAnexoRN->gerarNomeArquivoTemporario();

      $strComandoExecucao = 'LANG=pt_BR.iso-8859-1 ';
      
      if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN') {
        $strComandoExecucao .= ' timeout '.ini_get('max_execution_time').'s';
      }

      $numMaxMemoriaPdfGb = ConfiguracaoSEI::getInstance()->getValor('SEI', 'MaxMemoriaPdfGb', false, 2);

      $strComandoExecucao .= ' java -Dpdfbox.fontcache='.DIR_SEI_TEMP.' -Xmx'.$numMaxMemoriaPdfGb.'G -jar ' .DIR_SEI_BIN.'/pdfboxmerge.jar -p ' .$strProtocoloProcedimentoFormatado .' -o ' .$strCaminhoCompletoArquivoPdfTotal .' -d ' .$strDocumentosGeracaoPdf.' -i '.implode(',',$arrArquivoPdfParcial) .' 2>&1';

      $ret = shell_exec($strComandoExecucao);

      if ($ret != ''){
      	if (preg_match('/<INFRA_VALIDACAO>(.*)<\/INFRA_VALIDACAO>/', $ret, $matches)){
      		$objInfraException = new InfraException();
      		$objInfraException->lancarValidacao('Erro gerando PDF.\n\n'.$matches[1].'\n\nTente regerar o PDF sem o documento que apresentou problema.');
      	}else{
        	//LogSEI::getInstance()->gravar("Erro gerando PDF.\n\nComando:\n".$strComandoExecucao."\n\nRetorno:\n".$ret);
          throw new InfraException('Erro gerando PDF.', null, $strComandoExecucao."\n\nRetorno:\n".$ret);
        }
      }

      foreach($arrArquivoPdfParcial as $strArquivoPdfParcial){
        unlink($strArquivoPdfParcial);
      }

      foreach($arrArquivoTemp as $strArquivoTemp){
        unlink($strArquivoTemp);
      }

      if (file_exists($strCaminhoCompletoArquivoPdfTotal.'-watermarked.pdf')){
        unlink($strCaminhoCompletoArquivoPdfTotal.'-watermarked.pdf');
      }

      $objAnexoDTO = new AnexoDTO();
      $arrNomeArquivo = explode('/',$strCaminhoCompletoArquivoPdfTotal);
      $objAnexoDTO->setStrNome($arrNomeArquivo[count($arrNomeArquivo)-1]);

      return $objAnexoDTO;

    }catch(Exception $e){
      throw new InfraException('Erro gerando pdf.',$e);
    }
  }

  private function prepararHtmlToPdf($strNomeArquivo){
    //contorna erro do wkhtmltopdf removendo numeros apos referencias para css e js
    $strHtml = file_get_contents($strNomeArquivo);
    $strHtml = preg_replace('/(<link href=\".*.css)(\?.*?)(\")/','$1$3', $strHtml);
    $strHtml = preg_replace('/(<script .*?src=\".*.js)\?.*?\"/','$1"', $strHtml);
    file_put_contents($strNomeArquivo,$strHtml);
  }

  protected function gerarZipConectado($parArrObjDocumentoDTO) {
    try{

      SessaoSEI::getInstance()->validarAuditarPermissao('procedimento_gerar_zip',__METHOD__,$parArrObjDocumentoDTO);

      ini_set('max_execution_time','300');

      $objInfraException = new InfraException();

      $arrIdDocumentos = InfraArray::converterArrInfraDTO($parArrObjDocumentoDTO,'IdDocumento');

      $objDocumentoDTO = new DocumentoDTO();
      $objDocumentoDTO->retDblIdDocumento();
      $objDocumentoDTO->retDblIdProcedimento();
      $objDocumentoDTO->retStrStaProtocoloProtocolo();
      $objDocumentoDTO->retStrNumero();
      $objDocumentoDTO->retStrNomeSerie();
      $objDocumentoDTO->retStrProtocoloDocumentoFormatado();
      $objDocumentoDTO->retStrProtocoloProcedimentoFormatado();
      //$objDocumentoDTO->retStrSiglaUnidadeGeradoraProtocolo();
      $objDocumentoDTO->retStrStaDocumento();
      $objDocumentoDTO->retDblIdDocumentoEdoc();
      //$objDocumentoDTO->retStrConteudo();
      $objDocumentoDTO->setDblIdDocumento($arrIdDocumentos, InfraDTO::$OPER_IN);

      $arrObjDocumentoDTO = $this->listarRN0008($objDocumentoDTO);

      if (count($arrObjDocumentoDTO)==0){
        throw new InfraException('Nenhum documento informado.');
      }

      $objAnexoRN = new AnexoRN();
      $strCaminhoCompletoArquivoZip = DIR_SEI_TEMP.'/'.$objAnexoRN->gerarNomeArquivoTemporario();

      $zipFile= new ZipArchive();
      $zipFile->open($strCaminhoCompletoArquivoZip, ZIPARCHIVE::CREATE);

      $arrObjDocumentoDTO = InfraArray::indexarArrInfraDTO($arrObjDocumentoDTO,'IdDocumento');
      $numCasas=floor(log10(count($arrObjDocumentoDTO)))+1;
      $numSequencial = 0;

      foreach($arrIdDocumentos as $dblIdDocumento){
        $numSequencial++;
        $numDocumento=str_pad($numSequencial, $numCasas, "0", STR_PAD_LEFT);
        $objDocumentoDTO = $arrObjDocumentoDTO[$dblIdDocumento];
        $strDocumento = '';
        if ($objDocumentoDTO->getStrStaProtocoloProtocolo() == ProtocoloRN::$TP_DOCUMENTO_GERADO){
          if ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_EDITOR_EDOC){
            if ($objDocumentoDTO->getDblIdDocumentoEdoc()==null){
              $strDocumento .= 'Documento e-Doc '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado() .' n�o encontrado.';
              $objInfraException->adicionarValidacao('Documento e-Doc '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado() .' n�o encontrado.');
            }else{
              $strDocumento .= EDocINT::montarVisualizacaoDocumento($objDocumentoDTO->getDblIdDocumentoEdoc());
            }
          }else if ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_EDITOR_INTERNO){
            $objEditorDTO = new EditorDTO();
            $objEditorDTO->setDblIdDocumento($objDocumentoDTO->getDblIdDocumento());
            $objEditorDTO->setNumIdBaseConhecimento(null);
            $objEditorDTO->setStrSinCabecalho('S');
            $objEditorDTO->setStrSinRodape('S');
            $objEditorDTO->setStrSinCarimboPublicacao('S');
            $objEditorDTO->setStrSinIdentificacaoVersao('N');

            $objEditorRN = new EditorRN();
            $strDocumento .= $objEditorRN->consultarHtmlVersao($objEditorDTO);
          }else{
            // email, por exemplo
            $strDocumento .= $this->consultarHtmlFormulario($objDocumentoDTO);
          }

          $strNomeArquivo = $objDocumentoDTO->getStrProtocoloDocumentoFormatado().'-'.$objDocumentoDTO->getStrNomeSerie();
          if (!InfraString::isBolVazia($objDocumentoDTO->getStrNumero())){
            $strNomeArquivo .= '-'.$objDocumentoDTO->getStrNumero();
          }
          $strNomeArquivo .='.html';

          if ($zipFile->addFromString('['.$numDocumento.']-'.InfraUtil::formatarNomeArquivo($strNomeArquivo),$strDocumento) === false){
            throw new InfraException('Erro adicionando conte�do html ao zip.');
          }
        }else if ($objDocumentoDTO->getStrStaProtocoloProtocolo()==ProtocoloRN::$TP_DOCUMENTO_RECEBIDO){
          $objAnexoDTO = new AnexoDTO();
          $objAnexoDTO->retNumIdAnexo();
          $objAnexoDTO->retStrNome();
          $objAnexoDTO->retDthInclusao();
          $objAnexoDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdDocumento());
          $objAnexoRN = new AnexoRN();
          $objAnexoDTO = $objAnexoRN->consultarRN0736($objAnexoDTO);
          if ($objAnexoDTO==null){
            $objInfraException->adicionarValidacao('Documento '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado() .' n�o encontrado.');
          }else{
            $ext = explode('.',$objAnexoDTO->getStrNome());
            $ext = strtolower($ext[count($ext)-1]);
            $strNomeArquivo = $objDocumentoDTO->getStrProtocoloDocumentoFormatado().'-'.$objDocumentoDTO->getStrNomeSerie();
            if (!InfraString::isBolVazia($objDocumentoDTO->getStrNumero())){
              $strNomeArquivo .= '-'.$objDocumentoDTO->getStrNumero();
            }
            $strNomeArquivo .='.'.$ext;
            if ($zipFile->addFile($objAnexoRN->obterLocalizacao($objAnexoDTO),'['.$numDocumento.']-'.InfraUtil::formatarNomeArquivo($strNomeArquivo)) === false){
              throw new InfraException('Erro adicionando arquivo externo ao zip.');
            }
          }
        }else{
          $objInfraException->adicionarValidacao('N�o foi poss�vel detectar o tipo do documento '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado().'.');
        }
      }
      $objInfraException->lancarValidacoes();

      if ($zipFile->close() === false) {
        throw new InfraException('N�o foi poss�vel fechar arquivo zip.');
      }

      $objAnexoDTO = new AnexoDTO();
      $arrNomeArquivo = explode('/',$strCaminhoCompletoArquivoZip);
      $objAnexoDTO->setStrNome($arrNomeArquivo[count($arrNomeArquivo)-1]);

      return $objAnexoDTO;

    }catch(Exception $e){
      throw new InfraException('Erro gerando zip.',$e);
    }
  }

  public function mover(MoverDocumentoDTO $objMoverDocumentoDTO){

    $bolAcumulacaoPrevia = FeedSEIProtocolos::getInstance()->isBolAcumularFeeds();

    FeedSEIProtocolos::getInstance()->setBolAcumularFeeds(true);

    $objRelProtocoloProtodoloDTO = $this->moverInterno($objMoverDocumentoDTO);

    $objIndexacaoDTO = new IndexacaoDTO();
    $objIndexacaoDTO->setArrIdProtocolos(array($objMoverDocumentoDTO->getDblIdDocumento()));
    $objIndexacaoDTO->setStrStaOperacao(IndexacaoRN::$TO_PROTOCOLO_METADADOS);
    
    $objIndexacaoRN = new IndexacaoRN();
    $objIndexacaoRN->indexarProtocolo($objIndexacaoDTO);

    if (!$bolAcumulacaoPrevia){
      FeedSEIProtocolos::getInstance()->setBolAcumularFeeds(false);
      FeedSEIProtocolos::getInstance()->indexarFeeds();
    }

    return $objRelProtocoloProtodoloDTO;
        
  }
  
  protected function moverInternoControlado(MoverDocumentoDTO $objMoverDocumentoDTO){
    try {

      global $SEI_MODULOS;

      //Valida Permissao
      SessaoSEI::getInstance()->validarAuditarPermissao('documento_mover',__METHOD__,$objMoverDocumentoDTO);
  
      //Regras de Negocio
      $objInfraException = new InfraException();
       
      $objProtocoloRN = new ProtocoloRN();
      $objRelProtocoloProtocoloRN = new RelProtocoloProtocoloRN();
      $objAtividadeRN = new AtividadeRN();
  
      $objProtocoloDTOAtual = new ProtocoloDTO();
      $objProtocoloDTOAtual->retDblIdProtocolo();
      $objProtocoloDTOAtual->retStrStaProtocolo();
      $objProtocoloDTOAtual->retStrStaEstado();
      $objProtocoloDTOAtual->retStrStaNivelAcessoGlobal();
      $objProtocoloDTOAtual->retStrProtocoloFormatado();
      $objProtocoloDTOAtual->setDblIdProtocolo($objMoverDocumentoDTO->getDblIdProcedimentoOrigem());
  
      $objProtocoloDTOAtual = $objProtocoloRN->consultarRN0186($objProtocoloDTOAtual);
      
      if ($objProtocoloDTOAtual==null){
        throw new InfraException('Processo origem n�o encontrado.');
      }
  
      if($objProtocoloDTOAtual->getStrStaProtocolo() != ProtocoloRN::$TP_PROCEDIMENTO){
        $objInfraException->lancarValidacao('Protocolo '.$objProtocoloDTOAtual->getStrProtocoloFormatado().' n�o � um processo.');
      }
  
      if($objProtocoloDTOAtual->getStrStaNivelAcessoGlobal() == ProtocoloRN::$NA_SIGILOSO){
        $objInfraException->lancarValidacao('Processo '.$objProtocoloDTOAtual->getStrProtocoloFormatado().' n�o pode ser sigiloso.');
      }

      $objProcedimentoRN = new ProcedimentoRN();
      $objProcedimentoRN->verificarEstadoProcedimento($objProtocoloDTOAtual);


      $objProtocoloDTODestino = new ProtocoloDTO();
      $objProtocoloDTODestino->retDblIdProtocolo();
      $objProtocoloDTODestino->retStrStaProtocolo();
      $objProtocoloDTODestino->retStrStaEstado();
      $objProtocoloDTODestino->retStrProtocoloFormatado();
      $objProtocoloDTODestino->retStrStaNivelAcessoGlobal();
      $objProtocoloDTODestino->setDblIdProtocolo($objMoverDocumentoDTO->getDblIdProcedimentoDestino());
      	
      $objProtocoloDTODestino = $objProtocoloRN->consultarRN0186($objProtocoloDTODestino);
      	
      if ($objProtocoloDTODestino==null){
        throw new InfraException('Processo destino n�o encontrado.');
      }
      
      if($objProtocoloDTODestino->getStrStaProtocolo() != ProtocoloRN::$TP_PROCEDIMENTO){
        $objInfraException->lancarValidacao('Protocolo '.$objProtocoloDTODestino->getStrProtocoloFormatado().' n�o � um processo.');
      }
      	
      if ($objProtocoloDTOAtual->getDblIdProtocolo() == $objProtocoloDTODestino->getDblIdProtocolo()){
        $objInfraException->lancarValidacao('Processo destino deve ser diferente do processo de origem.');
      }
      	
      if($objProtocoloDTODestino->getStrStaNivelAcessoGlobal() == ProtocoloRN::$NA_SIGILOSO){
        $objInfraException->lancarValidacao('Processo '.$objProtocoloDTODestino->getStrProtocoloFormatado().' n�o pode ser sigiloso.');
      }
      	
      if($objProtocoloDTODestino->getStrStaEstado() == ProtocoloRN::$TE_PROCEDIMENTO_SOBRESTADO){
        $objInfraException->lancarValidacao('Processo '.$objProtocoloDTODestino->getStrProtocoloFormatado().' est� sobrestado.');
      }
      	
      if($objProtocoloDTODestino->getStrStaEstado() == ProtocoloRN::$TE_PROCEDIMENTO_ANEXADO){
        $objInfraException->lancarValidacao('Processo '.$objProtocoloDTODestino->getStrProtocoloFormatado().' n�o pode estar anexado a outro processo.');
      }

      $objProcedimentoRN->verificarEstadoProcedimento($objProtocoloDTODestino);
  
      $objDocumentoDTO = new DocumentoDTO();
      $objDocumentoDTO->retDblIdDocumento();
      $objDocumentoDTO->retStrStaProtocoloProtocolo();
      $objDocumentoDTO->retStrProtocoloDocumentoFormatado();
      $objDocumentoDTO->setDblIdDocumento($objMoverDocumentoDTO->getDblIdDocumento());
      $objDocumentoDTO = $this->consultarRN0005($objDocumentoDTO);
      
      if ($objDocumentoDTO==null){
        throw new InfraException('Documento n�o encontrado.');
      }
      
      if($objDocumentoDTO->getStrStaProtocoloProtocolo() != ProtocoloRN::$TP_DOCUMENTO_RECEBIDO){
        $objInfraException->lancarValidacao('Somente documentos externos podem ser movidos.');
      }
      
      //muda o processo do documento
      $objDocumentoDTO2 = new DocumentoDTO();
      $objDocumentoDTO2->setDblIdProcedimento($objMoverDocumentoDTO->getDblIdProcedimentoDestino());
      $objDocumentoDTO2->setDblIdDocumento($objDocumentoDTO->getDblIdDocumento());
      
      $objDocumentoBD = new DocumentoBD($this->getObjInfraIBanco());
      $objDocumentoBD->alterar($objDocumentoDTO2);

      //muda o tipo da associacao do documento com o processo antigo
      $objRelProtocoloProtocoloDTO = new RelProtocoloProtocoloDTO();
      $objRelProtocoloProtocoloDTO->retDblIdRelProtocoloProtocolo();
      $objRelProtocoloProtocoloDTO->setDblIdProtocolo1($objMoverDocumentoDTO->getDblIdProcedimentoOrigem());
      $objRelProtocoloProtocoloDTO->setDblIdProtocolo2($objMoverDocumentoDTO->getDblIdDocumento());
      $objRelProtocoloProtocoloDTO->setStrStaAssociacao(RelProtocoloProtocoloRN::$TA_DOCUMENTO_ASSOCIADO);
      $objRelProtocoloProtocoloDTOAtual = $objRelProtocoloProtocoloRN->consultarRN0841($objRelProtocoloProtocoloDTO);

      if ($objRelProtocoloProtocoloDTOAtual==null){
        $objInfraException->lancarValidacao('Documento n�o est� associado com o processo origem.');
      }

      $objRelProtocoloProtocoloDTOAtual->setStrStaAssociacao(RelProtocoloProtocoloRN::$TA_DOCUMENTO_MOVIDO);
      $objRelProtocoloProtocoloRN->alterar($objRelProtocoloProtocoloDTOAtual);

      $objProtocoloDTO = new ProtocoloDTO();
      $objProtocoloDTO->setDblIdProcedimento($objMoverDocumentoDTO->getDblIdProcedimentoDestino());
      $numSequencia = $objProtocoloRN->obterSequencia($objProtocoloDTO);
      
      //Criar associa��o entre o documento e o processo novo
      $objRelProtocoloProtocoloDTO = new RelProtocoloProtocoloDTO();
      $objRelProtocoloProtocoloDTO->setDblIdRelProtocoloProtocolo(null);
      $objRelProtocoloProtocoloDTO->setDblIdProtocolo1($objMoverDocumentoDTO->getDblIdProcedimentoDestino());
      $objRelProtocoloProtocoloDTO->setDblIdProtocolo2($objMoverDocumentoDTO->getDblIdDocumento());
      $objRelProtocoloProtocoloDTO->setNumIdUsuario(SessaoSEI::getInstance()->getNumIdUsuario());
      $objRelProtocoloProtocoloDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
      $objRelProtocoloProtocoloDTO->setStrStaAssociacao(RelProtocoloProtocoloRN::$TA_DOCUMENTO_ASSOCIADO);
      $objRelProtocoloProtocoloDTO->setNumSequencia($numSequencia);
      $objRelProtocoloProtocoloDTO->setDthAssociacao(InfraData::getStrDataHoraAtual());
      $objRelProtocoloProtocoloDTODestino = $objRelProtocoloProtocoloRN->cadastrarRN0839($objRelProtocoloProtocoloDTO);
      
      //recalcular n�vel de acesso do processo origem
      $objMudarNivelAcessoDTO = new MudarNivelAcessoDTO();
      $objMudarNivelAcessoDTO->setStrStaOperacao(ProtocoloRN::$TMN_MOVIMENTACAO);
      $objMudarNivelAcessoDTO->setDblIdProtocolo($objMoverDocumentoDTO->getDblIdProcedimentoOrigem());
      $objMudarNivelAcessoDTO->setStrStaNivel(null);
      $objProtocoloRN->mudarNivelAcesso($objMudarNivelAcessoDTO);      

      //recalcular n�vel de acesso do processo destino
      $objMudarNivelAcessoDTO = new MudarNivelAcessoDTO();
      $objMudarNivelAcessoDTO->setStrStaOperacao(ProtocoloRN::$TMN_MOVIMENTACAO);
      $objMudarNivelAcessoDTO->setDblIdProtocolo($objMoverDocumentoDTO->getDblIdProcedimentoDestino());
      $objMudarNivelAcessoDTO->setStrStaNivel(null);
      $objProtocoloRN->mudarNivelAcesso($objMudarNivelAcessoDTO);

      $arrObjAtributoAndamentoDTO = array();
      $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
      $objAtributoAndamentoDTO->setStrNome('DOCUMENTO');
      $objAtributoAndamentoDTO->setStrValor($objDocumentoDTO->getStrProtocoloDocumentoFormatado());
      $objAtributoAndamentoDTO->setStrIdOrigem($objDocumentoDTO->getDblIdDocumento());
      $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;

      $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
      $objAtributoAndamentoDTO->setStrNome('PROCESSO');
      $objAtributoAndamentoDTO->setStrValor($objProtocoloDTODestino->getStrProtocoloFormatado());
      $objAtributoAndamentoDTO->setStrIdOrigem($objProtocoloDTODestino->getDblIdProtocolo());
      $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;

      $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
      $objAtributoAndamentoDTO->setStrNome('MOTIVO');
      $objAtributoAndamentoDTO->setStrValor($objMoverDocumentoDTO->getStrMotivo());
      $objAtributoAndamentoDTO->setStrIdOrigem($objRelProtocoloProtocoloDTOAtual->getDblIdRelProtocoloProtocolo());
      $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;
      
      $objAtividadeDTO = new AtividadeDTO();
      $objAtividadeDTO->setDblIdProtocolo($objProtocoloDTOAtual->getDblIdProtocolo());
      $objAtividadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
      $objAtividadeDTO->setNumIdTarefa(TarefaRN::$TI_DOCUMENTO_MOVIDO_PARA_PROCESSO);
      $objAtividadeDTO->setArrObjAtributoAndamentoDTO($arrObjAtributoAndamentoDTO);
      $objAtividadeRN->gerarInternaRN0727($objAtividadeDTO);

      $arrObjAtributoAndamentoDTO = array();
      $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
      $objAtributoAndamentoDTO->setStrNome('DOCUMENTO');
      $objAtributoAndamentoDTO->setStrValor($objDocumentoDTO->getStrProtocoloDocumentoFormatado());
      $objAtributoAndamentoDTO->setStrIdOrigem($objDocumentoDTO->getDblIdDocumento());
      $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;
      
      $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
      $objAtributoAndamentoDTO->setStrNome('PROCESSO');
      $objAtributoAndamentoDTO->setStrValor($objProtocoloDTOAtual->getStrProtocoloFormatado());
      $objAtributoAndamentoDTO->setStrIdOrigem($objProtocoloDTOAtual->getDblIdProtocolo());
      $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;
       
      $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
      $objAtributoAndamentoDTO->setStrNome('MOTIVO');
      $objAtributoAndamentoDTO->setStrValor($objMoverDocumentoDTO->getStrMotivo());
      $objAtributoAndamentoDTO->setStrIdOrigem($objRelProtocoloProtocoloDTODestino->getDblIdRelProtocoloProtocolo());
      $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;
      
      $objAtividadeDTO = new AtividadeDTO();
      $objAtividadeDTO->setDblIdProtocolo($objProtocoloDTODestino->getDblIdProtocolo());
      $objAtividadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
      $objAtividadeDTO->setNumIdTarefa(TarefaRN::$TI_DOCUMENTO_MOVIDO_DO_PROCESSO);
      $objAtividadeDTO->setArrObjAtributoAndamentoDTO($arrObjAtributoAndamentoDTO);
      $objAtividadeRN->gerarInternaRN0727($objAtividadeDTO);


      if (count($SEI_MODULOS)) {
        $objDocumentoAPI = new DocumentoAPI();
        $objDocumentoAPI->setIdDocumento($objDocumentoDTO->getDblIdDocumento());
        $objDocumentoAPI->setNumeroProtocolo($objDocumentoDTO->getStrProtocoloDocumentoFormatado());

        $objProcedimentoAPIOrigem = new ProcedimentoAPI();
        $objProcedimentoAPIOrigem->setIdProcedimento($objProtocoloDTOAtual->getDblIdProtocolo());
        $objProcedimentoAPIOrigem->setNumeroProtocolo($objProtocoloDTOAtual->getStrProtocoloFormatado());

        $objProcedimentoAPIDestino = new ProcedimentoAPI();
        $objProcedimentoAPIDestino->setIdProcedimento($objProtocoloDTODestino->getDblIdProtocolo());
        $objProcedimentoAPIDestino->setNumeroProtocolo($objProtocoloDTODestino->getStrProtocoloFormatado());

        foreach ($SEI_MODULOS as $seiModulo) {
          $seiModulo->executar('moverDocumento', $objDocumentoAPI, $objProcedimentoAPIOrigem, $objProcedimentoAPIDestino);
        }
      }

      return $objRelProtocoloProtocoloDTOAtual;

    }catch(Exception $e){
      throw new InfraException('Erro movendo documento.',$e);
    }
  }

  protected function consultarHtmlFormularioConectado(DocumentoDTO $parObjDocumentoDTO){

    if (!$parObjDocumentoDTO->isSetObjInfraSessao()){
      $parObjDocumentoDTO->setObjInfraSessao(null);
    }

    if (!$parObjDocumentoDTO->isSetStrLinkDownload()){
      $parObjDocumentoDTO->setStrLinkDownload(null);
    }

    $objDocumentoDTO = new DocumentoDTO();
    $objDocumentoDTO->retDblIdDocumento();
    $objDocumentoDTO->retStrProtocoloDocumentoFormatado();
    $objDocumentoDTO->retNumIdUnidadeGeradoraProtocolo();
    $objDocumentoDTO->retStrStaProtocoloProtocolo();
    $objDocumentoDTO->retStrNomeSerie();
    $objDocumentoDTO->retStrStaDocumento();
    $objDocumentoDTO->retStrSinBloqueado();
    $objDocumentoDTO->retStrConteudo();
    $objDocumentoDTO->retStrCrcAssinatura();
    $objDocumentoDTO->retStrQrCodeAssinatura();
    $objDocumentoDTO->retNumIdTipoFormulario();
    $objDocumentoDTO->retStrDescricaoTipoConferencia();
    $objDocumentoDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());

    $objDocumentoRN = new DocumentoRN();
    $objDocumentoDTO = $objDocumentoRN->consultarRN0005($objDocumentoDTO);

    if ($objDocumentoDTO==null){
      throw new InfraException('Documento n�o encontrado.');
    }

    if ($objDocumentoDTO->getStrStaDocumento()!=DocumentoRN::$TD_FORMULARIO_AUTOMATICO && $objDocumentoDTO->getStrStaDocumento()!=DocumentoRN::$TD_FORMULARIO_GERADO){
      throw new InfraException('Documento '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado().' n�o � um formul�rio.');
    }

    $objDocumentoRN->bloquearConsultado($objDocumentoDTO);

    $strHtml = '';
    $strHtml .= '<!DOCTYPE html>'."\n";
    $strHtml .= '<html lang="pt-br" >'."\n";
    $strHtml .= '<head>'."\n";
    $strHtml .= '<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">'."\n";
    $strHtml .= '<title>'.DocumentoINT::montarTitulo($objDocumentoDTO).'</title>'."\n";
    $strHtml .= '<style type="text/css" >'."\n";
    $strHtml .= '*{ '."\n";
    $strHtml .= ' font-style:normal;'."\n";
    $strHtml .= ' font-weight:normal;'."\n";
    $strHtml .= ' color:black;'."\n";
    $strHtml .= '}'."\n\n";

    $strHtml .= 'body{'."\n";
    $strHtml .= ' font-size:10pt;'."\n";
    $strHtml .= ' font-family:Arial,Verdana,Helvetica,Sans-serif;'."\n";
    $strHtml .= ' text-align:left;'."\n";
    $strHtml .= ' overflow-y:scroll;'."\n";
    $strHtml .= '}'."\n\n";

    $strHtml .= '#titulo {'."\n";
    $strHtml .= ' padding: 2px 0;'."\n";
    $strHtml .= ' text-align:center;'."\n";
    $strHtml .= ' vertical-align:middle;'."\n";
    $strHtml .= ' width:100%;'."\n";
    $strHtml .= ' background-color:#dfdfdf;'."\n";
    $strHtml .= ' border-bottom: 4px solid white;'."\n";
    $strHtml .= ' overflow:hidden;'."\n";
    $strHtml .= '}'."\n\n";

    $strHtml .= '#titulo label {'."\n";
    $strHtml .= ' font-size:11pt;'."\n";
    $strHtml .= ' font-weight:bold;'."\n";
    $strHtml .= ' color:#666;'."\n";
    $strHtml .= ' background-color:#dfdfdf;'."\n";
    $strHtml .= '}'."\n\n";

    $strHtml .= 'div b {font-weight:bold;}'."\n";
    $strHtml .= 'div i {font-style:italic;}'."\n";

    $strHtml .= '</style>'."\n";
    $strHtml .= '</head>'."\n";
    $strHtml .= '<body>'."\n";
    $strHtml .= '<div id="titulo">'."\n";
    $strHtml .= '<label>'.$objDocumentoDTO->getStrNomeSerie().' - '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado().'</label>'."\n";
    $strHtml .= '</div>'."\n";
    $strHtml .= '<div id="conteudo">'."\n";
    $strHtml .= DocumentoINT::formatarExibicaoConteudo(DocumentoINT::$TV_HTML, $objDocumentoDTO->getStrConteudo(), $parObjDocumentoDTO->getObjInfraSessao(), $parObjDocumentoDTO->getStrLinkDownload());
    $strHtml .= '</div>'."\n";
    $strHtml .= '<br />'."\n";

    if ($objDocumentoDTO->getNumIdTipoFormulario()!=null) {

      $objAssinaturaRN = new AssinaturaRN();
      $strHtmlAssinaturas = $objAssinaturaRN->montarTarjas($objDocumentoDTO);

      if ($strHtmlAssinaturas != ''){
        $strHtml .= '<div id="assinaturas">'."\n";
        $strHtml .= $strHtmlAssinaturas;
        $strHtml .= '</div>'."\n";
      }

    }

    $strHtml .= '</body>'."\n";
    $strHtml .= '</html>'."\n";

    SeiINT::validarXss($strHtml, $objDocumentoDTO->getStrProtocoloDocumentoFormatado());

    return $strHtml;
  }

  private function montarConteudoFormulario($arrObjRelProtocoloAtributoDTO){
    try{

      $ret = null;

      if (count($arrObjRelProtocoloAtributoDTO)) {

        $arrIdAtributos = InfraArray::converterArrInfraDTO($arrObjRelProtocoloAtributoDTO, 'IdAtributo');

        $objAtributoDTO = new AtributoDTO();
        $objAtributoDTO->setBolExclusaoLogica(false);
        $objAtributoDTO->retNumIdAtributo();
        $objAtributoDTO->retStrNome();
        $objAtributoDTO->retStrRotulo();
        $objAtributoDTO->retNumOrdem();
        $objAtributoDTO->retStrStaTipo();
        $objAtributoDTO->setNumIdAtributo($arrIdAtributos, InfraDTO::$OPER_IN);
        $objAtributoDTO->setOrdNumOrdem(InfraDTO::$TIPO_ORDENACAO_ASC);
        $objAtributoDTO->setOrdStrRotulo(InfraDTO::$TIPO_ORDENACAO_ASC);

        $objAtributoRN = new AtributoRN();
        $arrObjAtributoDTO = $objAtributoRN->listarRN0165($objAtributoDTO);

        $objDominioDTO = new DominioDTO();
        $objDominioDTO->setBolExclusaoLogica(false);
        $objDominioDTO->retNumIdDominio();
        $objDominioDTO->retNumIdAtributo();
        $objDominioDTO->retStrRotulo();
        $objDominioDTO->retStrValor();
        $objDominioDTO->setNumIdAtributo($arrIdAtributos, InfraDTO::$OPER_IN);

        $objDominioRN = new DominioRN();
        $arrObjDominioDTO = InfraArray::indexarArrInfraDTO($objDominioRN->listarRN0199($objDominioDTO),'IdAtributo',true);

        $ret = '';
        $ret .= '<?xml version="1.0" encoding="iso-8859-1"?>' . "\n";
        $ret .= '<formulario>' . "\n";

        foreach($arrObjAtributoDTO as $objAtributoDTO){

          foreach ($arrObjRelProtocoloAtributoDTO as $objRelProtocoloAtributoDTO) {

            if ($objAtributoDTO->getNumIdAtributo()==$objRelProtocoloAtributoDTO->getNumIdAtributo()) {

              $ret .= '<atributo id="' . $objAtributoDTO->getNumIdAtributo() . '" nome="' . InfraString::formatarXML($objAtributoDTO->getStrNome()) . '" tipo="'.$objAtributoDTO->getStrStaTipo().'">'."\n";

              $ret .= '<rotulo>' . InfraString::formatarXML($objAtributoDTO->getStrRotulo()) . '</rotulo>'."\n";

              if ($objRelProtocoloAtributoDTO->getStrValor() != null) {

                if ($objAtributoDTO->getStrStaTipo() == AtributoRN::$TA_LISTA || $objAtributoDTO->getStrStaTipo() == AtributoRN::$TA_OPCOES) {

                  $objDominioDTOUtilizado = null;
                  foreach ($arrObjDominioDTO[$objAtributoDTO->getNumIdAtributo()] as $objDominioDTO) {
                    if ($objDominioDTO->getStrValor() == $objRelProtocoloAtributoDTO->getStrValor()) {
                      $objDominioDTOUtilizado = $objDominioDTO;
                      break;
                    }
                  }

                  if ($objDominioDTOUtilizado!=null){
                    $ret .= '<dominio id="'.$objDominioDTOUtilizado->getNumIdDominio().'" valor="'.InfraString::formatarXML($objDominioDTOUtilizado->getStrValor()).'">'.InfraString::formatarXML($objDominioDTOUtilizado->getStrRotulo()).'</dominio>'."\n";
                  }

                } else {
                  $ret .= '<valor>'.InfraString::formatarXML($objRelProtocoloAtributoDTO->getStrValor()).'</valor>'."\n";
                }
              }
              $ret .= '</atributo>' . "\n";

              break;
            }
          }
        }
        $ret .= '</formulario>';

        $ret = InfraUtil::filtrarISO88591($ret);
      }

      return $ret;

    }catch(Exception $e){
      throw new InfraException('Erro montando conte�do do Formul�rio.',$e);
    }
  }

  protected function verificarSobrestamento(DocumentoDTO $objDocumentoDTO){
    try{

      $objProtocoloDTO = new ProtocoloDTO();
      $objProtocoloDTO->retStrStaEstado();
      $objProtocoloDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdProcedimento());

      $objProtocoloRN = new ProtocoloRN();
      $objProtocoloDTO = $objProtocoloRN->consultarRN0186($objProtocoloDTO);

      if ($objProtocoloDTO->getStrStaEstado()==ProtocoloRN::$TE_PROCEDIMENTO_SOBRESTADO){
        $objRelProtocoloProtocoloDTO = new RelProtocoloProtocoloDTO();
        $objRelProtocoloProtocoloDTO->setDblIdProtocolo2($objDocumentoDTO->getDblIdProcedimento());

        $objProcedimentoRN = new ProcedimentoRN();
        $objProcedimentoRN->removerSobrestamentoRN1017(array($objRelProtocoloProtocoloDTO));
      }

    }catch(Exception $e){
      throw new InfraException('Erro verificando sobrestamento do processo.',$e);
    }
  }

  protected function configurarDocumentoEdocRN1175Controlado(DocumentoDTO $parObjDocumentoDTO){
    try{

      $objDocumentoDTO = new DocumentoDTO();
      $objDocumentoDTO->retStrProtocoloDocumentoFormatado();
      $objDocumentoDTO->retDblIdDocumentoEdoc();
      $objDocumentoDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());

      $objDocumentoDTO = $this->consultarRN0005($objDocumentoDTO);

      if ($objDocumentoDTO->getDblIdDocumentoEdoc()!=null){
        throw new InfraException('Documento '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado().' j� possui documento associado no eDoc.');
      }


      $objDocumentoDTO = new DocumentoDTO();
      $objDocumentoDTO->setDblIdDocumentoEdoc($parObjDocumentoDTO->getDblIdDocumentoEdoc());
      $objDocumentoDTO->setStrConteudo($parObjDocumentoDTO->getStrConteudo());
      $objDocumentoDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());

      $objDocumentoBD = new DocumentoBD($this->getObjInfraIBanco());
      $objDocumentoBD->alterar($objDocumentoDTO);

    }catch(Exception $e){
      throw new InfraException('Erro configurando documento do eDoc.',$e);
    }
  }

  public function gerarDocumentoCircular(DocumentoCircularDTO $objDocumentoCircularDTO){

    $bolAcumulacaoPrevia = FeedSEIProtocolos::getInstance()->isBolAcumularFeeds();

    FeedSEIProtocolos::getInstance()->setBolAcumularFeeds(true);

    $ret = $this->gerarDocumentoCircularInterno($objDocumentoCircularDTO);

    if (!$bolAcumulacaoPrevia){
      FeedSEIProtocolos::getInstance()->setBolAcumularFeeds(false);
      FeedSEIProtocolos::getInstance()->indexarFeeds();
    }

    return $ret;
  }

  protected function gerarDocumentoCircularInternoControlado(DocumentoCircularDTO $objDocumentoCircularDTO){
    try{

      $ret = array();

      $objInfraException = new InfraException();

      $objDocumentoDTO = new DocumentoDTO();
      //$objDocumentoDTO->retStrStaDocumento();
      $objDocumentoDTO->retNumIdSerie();
      $objDocumentoDTO->retStrSinDestinatarioSerie();
      $objDocumentoDTO->retStrNumero();
      $objDocumentoDTO->setDblIdDocumento($objDocumentoCircularDTO->getDblIdDocumento());

      $objDocumentoDTO = $this->consultarRN0005($objDocumentoDTO);

      //if ($objDocumentoDTO->getStrStaDocumento() != DocumentoRN::$TD_EDITOR_INTERNO) {
      //  $objInfraException->lancarValidacao(('Somente documentos internos podem ser usados na gera��o de circular.');
      //}

      if ($objDocumentoDTO->getStrSinDestinatarioSerie()=='N'){
        $objInfraException->lancarValidacao('Tipo do documento n�o permite destinat�rios.');
      }

      $arrIdDestinatario = $objDocumentoCircularDTO->getArrNumIdDestinatario();

      if (count($arrIdDestinatario)==0){
        $objInfraException->lancarValidacao('Nenhum destinat�rio informado.');
      }

      $objInfraException->lancarValidacoes();

      $objContatoDTO = new ContatoDTO();
      $objContatoDTO->setBolExclusaoLogica(false);
      $objContatoDTO->retNumIdContato();
      $objContatoDTO->retStrExpressaoTratamentoCargo();
      $objContatoDTO->retStrExpressaoCargo();
      $objContatoDTO->retStrNome();
      $objContatoDTO->setNumIdContato($arrIdDestinatario,InfraDTO::$OPER_IN);

      $objContatoRN = new ContatoRN();
      $arrObjContatoDTO = InfraArray::indexarArrInfraDTO($objContatoRN->listarRN0325($objContatoDTO),'IdContato');

      $arrObjRelBlocoProtocoloDTO = array();

      foreach($arrIdDestinatario as $numIdDestinatario){

        $objDocumentoClonarDTO = new DocumentoDTO();
        $objDocumentoClonarDTO->setDblIdDocumento($objDocumentoCircularDTO->getDblIdDocumento());
        $objDocumentoClonarDTO->setDblIdProcedimento($objDocumentoCircularDTO->getDblIdProcedimento());
        $objDocumentoClonarDTO = $this->prepararCloneRN1110($objDocumentoClonarDTO);

        if ($objDocumentoDTO->getStrNumero()!=null && $objDocumentoClonarDTO->getStrNumero()==null){
          $objDocumentoClonarDTO->setStrNumero($objDocumentoDTO->getStrNumero());
        }

        $arrObjParticipanteDTOOriginal = $objDocumentoClonarDTO->getObjProtocoloDTO()->getArrObjParticipanteDTO();
        $arrObjParticipanteDTOFiltrado = array();
        foreach($arrObjParticipanteDTOOriginal as $objParticipanteDTO){
          if ($objParticipanteDTO->getStrStaParticipacao()==ParticipanteRN::$TP_INTERESSADO || $objParticipanteDTO->getStrStaParticipacao()==ParticipanteRN::$TP_REMETENTE){
            $arrObjParticipanteDTOFiltrado[] = $objParticipanteDTO;
          }
        }

        $objParticipanteDTO = new ParticipanteDTO();
        $objParticipanteDTO->setNumIdContato($numIdDestinatario);
        $objParticipanteDTO->setStrStaParticipacao(ParticipanteRN::$TP_DESTINATARIO);
        $objParticipanteDTO->setNumSequencia(0);

        $arrObjParticipanteDTOFiltrado[] = $objParticipanteDTO;

        $objDocumentoClonarDTO->getObjProtocoloDTO()->setArrObjParticipanteDTO($arrObjParticipanteDTOFiltrado);

        $objDocumentoDTONovo = $this->cadastrarRN0003($objDocumentoClonarDTO);

        $objRelProtocoloProtocoloDTO = new RelProtocoloProtocoloDTO();
        $objRelProtocoloProtocoloDTO->setDblIdRelProtocoloProtocolo(null);
        $objRelProtocoloProtocoloDTO->setDblIdProtocolo1($objDocumentoCircularDTO->getDblIdDocumento());
        $objRelProtocoloProtocoloDTO->setDblIdProtocolo2($objDocumentoDTONovo->getDblIdDocumento());
        $objRelProtocoloProtocoloDTO->setNumIdUsuario(SessaoSEI::getInstance()->getNumIdUsuario());
        $objRelProtocoloProtocoloDTO->setNumIdUnidade (SessaoSEI::getInstance()->getNumIdUnidadeAtual());
        $objRelProtocoloProtocoloDTO->setNumSequencia(0);
        $objRelProtocoloProtocoloDTO->setStrStaAssociacao(RelProtocoloProtocoloRN::$TA_DOCUMENTO_CIRCULAR);
        $objRelProtocoloProtocoloDTO->setDthAssociacao(InfraData::getStrDataHoraAtual());

        $objRelProtocoloProtocoloRN = new RelProtocoloProtocoloRN();
        $objRelProtocoloProtocoloRN->cadastrarRN0839($objRelProtocoloProtocoloDTO);

        if (!InfraString::isBolVazia($objDocumentoCircularDTO->getNumIdBloco())) {

          $objContatoDTO = $arrObjContatoDTO[$numIdDestinatario];

          $objRelBlocoProtocoloDTO = new RelBlocoProtocoloDTO();
          $objRelBlocoProtocoloDTO->setNumIdBloco($objDocumentoCircularDTO->getNumIdBloco());
          $objRelBlocoProtocoloDTO->setDblIdProtocolo($objDocumentoDTONovo->getDblIdDocumento());
          $objRelBlocoProtocoloDTO->setStrAnotacao(($objContatoDTO->getStrExpressaoTratamentoCargo()!=null?$objContatoDTO->getStrExpressaoTratamentoCargo().' ':'').$objContatoDTO->getStrNome().($objContatoDTO->getStrExpressaoCargo()!=null?' ('.$objContatoDTO->getStrExpressaoCargo().')':''));
          $arrObjRelBlocoProtocoloDTO[] = $objRelBlocoProtocoloDTO;
        }

        $ret[] = $objDocumentoDTONovo;
      }

      if (count($arrObjRelBlocoProtocoloDTO)) {
        $objRelBlocoProtocoloRN = new RelBlocoProtocoloRN();
        $objRelBlocoProtocoloRN->cadastrarMultiplo($arrObjRelBlocoProtocoloDTO);
      }

      return $ret;

    }catch(Exception $e){
      throw new InfraException('Erro gerando documento circular.',$e);
    }
  }

  protected function listarDocumentoCircularConectado(DocumentoDTO $parObjDocumentoDTO){
    try{

      $ret = array();

      $objRelProtocoloProtocoloDTO = new RelProtocoloProtocoloDTO();
      $objRelProtocoloProtocoloDTO->retDblIdProtocolo2();
      $objRelProtocoloProtocoloDTO->setStrStaAssociacao(RelProtocoloProtocoloRN::$TA_DOCUMENTO_CIRCULAR);
      $objRelProtocoloProtocoloDTO->setDblIdProtocolo1($parObjDocumentoDTO->getDblIdDocumento());

      $objRelProtocoloProtocoloRN = new RelProtocoloProtocoloRN();
      $arrIdDocumentosCirculares = InfraArray::converterArrInfraDTO($objRelProtocoloProtocoloRN->listarRN0187($objRelProtocoloProtocoloDTO),'IdProtocolo2');

      if (count($arrIdDocumentosCirculares)){

        $objDocumentoDTO = new DocumentoDTO();
        $objDocumentoDTO->retDblIdDocumento();
        $objDocumentoDTO->retStrNomeSerie();
        $objDocumentoDTO->retStrNumero();
        $objDocumentoDTO->retStrProtocoloDocumentoFormatado();
        $objDocumentoDTO->setDblIdDocumento($arrIdDocumentosCirculares,InfraDTO::$OPER_IN);

        $objDocumentoRN = new DocumentoRN();
        $arrObjDocumentoDTO = InfraArray::indexarArrInfraDTO($objDocumentoRN->listarRN0008($objDocumentoDTO),'IdDocumento');

        $arrObjDocumentoCircularDTO = array();
        foreach($arrObjDocumentoDTO as $objDocumentoDTO){
          $objDocumentoCircularDTO = new DocumentoCircularDTO();
          $objDocumentoCircularDTO->setDblIdDocumento($objDocumentoDTO->getDblIdDocumento());
          $objDocumentoCircularDTO->setStrNomeSerie($objDocumentoDTO->getStrNomeSerie());
          $objDocumentoCircularDTO->setStrNumero($objDocumentoDTO->getStrNumero());
          $objDocumentoCircularDTO->setStrProtocoloDocumentoFormatado($objDocumentoDTO->getStrProtocoloDocumentoFormatado());
          $arrObjDocumentoCircularDTO[$objDocumentoDTO->getDblIdDocumento()] = $objDocumentoCircularDTO;
        }


        $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
        $objAtributoAndamentoDTO->retStrValor();
        $objAtributoAndamentoDTO->retStrIdOrigem();
        $objAtributoAndamentoDTO->setStrNome('DOCUMENTO_CIRCULAR');
        $objAtributoAndamentoDTO->setStrIdOrigem($arrIdDocumentosCirculares,InfraDTO::$OPER_IN);

        $objAtributoAndamentoRN = new AtributoAndamentoRN();
        $arrObjAtributoAndamentoDTO = $objAtributoAndamentoRN->listarRN1367($objAtributoAndamentoDTO);

        if (count($arrObjAtributoAndamentoDTO)){
          $objDocumentoDTO = new DocumentoDTO();
          $objDocumentoDTO->retDblIdDocumento();
          $objDocumentoDTO->retStrNomeSerie();
          $objDocumentoDTO->retStrProtocoloDocumentoFormatado();
          $objDocumentoDTO->setDblIdDocumento(InfraArray::converterArrInfraDTO($arrObjAtributoAndamentoDTO,'Valor'),InfraDTO::$OPER_IN);
          $arrObjDocumentoDTOEmail = InfraArray::indexarArrInfraDTO($objDocumentoRN->listarRN0008($objDocumentoDTO),'IdDocumento');
        }else{
          $arrObjDocumentoDTOEmail = array();
        }

        $arrObjAtributoAndamentoDTO = InfraArray::indexarArrInfraDTO($arrObjAtributoAndamentoDTO, 'IdOrigem', true);

        $objParticipanteDTO = new ParticipanteDTO();
        $objParticipanteDTO->retDblIdProtocolo();
        $objParticipanteDTO->retStrNomeContato();
        $objParticipanteDTO->retStrEmailContato();
        $objParticipanteDTO->setStrStaParticipacao(ParticipanteRN::$TP_DESTINATARIO);
        $objParticipanteDTO->setDblIdProtocolo($arrIdDocumentosCirculares, InfraDTO::$OPER_IN);
        $objParticipanteDTO->setOrdStrNomeContato(InfraDTO::$TIPO_ORDENACAO_ASC);

        $objParticipanteRN = new ParticipanteRN();
        $arrObjParticipanteDTO = InfraArray::indexarArrInfraDTO($objParticipanteRN->listarRN0189($objParticipanteDTO), 'IdProtocolo', true);

        //ordena retorno pelo nome do destinat�rio
        foreach($arrObjParticipanteDTO as $dblIdProtocolo => $arrObjParticipanteDTOProtocolo){

          $arrObjDocumentoCircularDTO[$dblIdProtocolo]->setArrObjParticipanteDTO($arrObjParticipanteDTOProtocolo);
          $ret[$dblIdProtocolo] = $arrObjDocumentoCircularDTO[$dblIdProtocolo];
        }


        $objAssinaturaDTO = new AssinaturaDTO();
        $objAssinaturaDTO->retDblIdDocumento();
        $objAssinaturaDTO->setDblIdDocumento($arrIdDocumentosCirculares, InfraDTO::$OPER_IN);

        $objAssinaturaRN = new AssinaturaRN();
        $arrObjAssinaturaDTO = InfraArray::indexarArrInfraDTO($objAssinaturaRN->listarRN1323($objAssinaturaDTO),'IdDocumento',true);


        //adiciona documentos sem destinatarios cadastrados no fim (se existirem)
        foreach($arrObjDocumentoCircularDTO as $objDocumentoCircularDTO){

          $dblIdDocumentoCircular = $objDocumentoCircularDTO->getDblIdDocumento();

          if (!$objDocumentoCircularDTO->isSetArrObjParticipanteDTO()){
            $objDocumentoCircularDTO->setArrObjParticipanteDTO(array());
            $ret[$dblIdDocumentoCircular] = $objDocumentoCircularDTO;
          }

          $arr = array();
          if (isset($arrObjAtributoAndamentoDTO[$dblIdDocumentoCircular])) {
            foreach($arrObjAtributoAndamentoDTO[$dblIdDocumentoCircular] as $objAtributoAndamentoDTO){
              $arr[] = $arrObjDocumentoDTOEmail[$objAtributoAndamentoDTO->getStrValor()];
            }
          }
          $objDocumentoCircularDTO->setArrObjDocumentoDTOEmail($arr);

          if (isset($arrObjAssinaturaDTO[$dblIdDocumentoCircular])){
            $objDocumentoCircularDTO->setStrSinAssinado('S');
          }else{
            $objDocumentoCircularDTO->setStrSinAssinado('N');
          }
        }
      }

      return $ret;

    }catch(Exception $e){
      throw new InfraException('Erro listando documento circular.',$e);
    }
  }

  public function cancelar(DocumentoDTO $objDocumentoDTO){
    
    $bolAcumulacaoPrevia = FeedSEIProtocolos::getInstance()->isBolAcumularFeeds();

    FeedSEIProtocolos::getInstance()->setBolAcumularFeeds(true);

    $objIndexacaoDTO = new IndexacaoDTO();
    $objIndexacaoDTO->setArrIdProtocolos(array($objDocumentoDTO->getDblIdDocumento()));

    $objIndexacaoRN	= new IndexacaoRN();
    $objIndexacaoRN->prepararRemocaoProtocolo($objIndexacaoDTO);

    $this->cancelarInterno($objDocumentoDTO);

    if (!$bolAcumulacaoPrevia){
      FeedSEIProtocolos::getInstance()->setBolAcumularFeeds(false);
      FeedSEIProtocolos::getInstance()->indexarFeeds();
    }
  }

  protected function cancelarInternoControlado(DocumentoDTO $parObjDocumentoDTO){
    try {

      global $SEI_MODULOS;

      //Regras de Negocio
      $objInfraException = new InfraException();

      if (InfraString::isBolVazia($parObjDocumentoDTO->getStrMotivoCancelamento())){
        $objInfraException->lancarValidacao('Motivo n�o informado.');
      }

      $objDocumentoDTO = new DocumentoDTO();
      $objDocumentoDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());
      $objDocumentoDTO = $this->bloquear($objDocumentoDTO);

      if ($objDocumentoDTO==null){
        $objInfraException->lancarValidacao('Documento n�o encontrado.');
      }

      $objDocumentoDTO = new DocumentoDTO();
      $objDocumentoDTO->retDblIdDocumento();
      $objDocumentoDTO->retDblIdProcedimento();
      $objDocumentoDTO->retStrProtocoloProcedimentoFormatado();
      $objDocumentoDTO->retStrStaEstadoProcedimento();
      $objDocumentoDTO->retNumIdUnidadeGeradoraProtocolo();
      $objDocumentoDTO->retStrProtocoloDocumentoFormatado();
      $objDocumentoDTO->retDblIdProtocoloProtocolo();
      $objDocumentoDTO->retStrStaProtocoloProtocolo();
      $objDocumentoDTO->retStrStaNivelAcessoLocalProtocolo();
      $objDocumentoDTO->retStrStaNivelAcessoGlobalProtocolo();
      $objDocumentoDTO->retStrStaEstadoProtocolo();
      $objDocumentoDTO->retStrConteudo();
      $objDocumentoDTO->retNumIdSerie();
      $objDocumentoDTO->retStrStaDocumento();
      $objDocumentoDTO->retObjPublicacaoDTO();
      $objDocumentoDTO->setDblIdDocumento($parObjDocumentoDTO->getDblIdDocumento());
      $objDocumentoDTO = $this->consultarRN0005($objDocumentoDTO);

      $parObjDocumentoDTO->setStrConteudo($this->obterConteudoAuditoriaExclusaoCancelamento($objDocumentoDTO));

      SessaoSEI::getInstance()->validarAuditarPermissao('documento_cancelar', __METHOD__, $parObjDocumentoDTO);

      if ($objDocumentoDTO->getStrStaEstadoProtocolo()==ProtocoloRN::$TE_DOCUMENTO_CANCELADO){
        $objInfraException->lancarValidacao('Documento j� foi cancelado.');
      }

      if ($objDocumentoDTO->getStrStaDocumento()==DocumentoRN::$TD_FORMULARIO_AUTOMATICO){
        $objInfraException->lancarValidacao('Formul�rio '.$objDocumentoDTO->getStrProtocoloDocumentoFormatado().' n�o pode ser cancelado.');
      }

      if ($objDocumentoDTO->getNumIdUnidadeGeradoraProtocolo()!=SessaoSEI::getInstance()->getNumIdUnidadeAtual()){
        $objInfraException->lancarValidacao('Documento n�o foi '.($objDocumentoDTO->getStrStaProtocoloProtocolo()==ProtocoloRN::$TP_DOCUMENTO_GERADO?'gerado':'recebido').' pela unidade atual.');
      }

      if ($objDocumentoDTO->getStrStaProtocoloProtocolo()==ProtocoloRN::$TP_DOCUMENTO_GERADO){
        if ($objDocumentoDTO->getObjPublicacaoDTO()!=null ){

          if ($objDocumentoDTO->getObjPublicacaoDTO()->getStrStaEstado()==PublicacaoRN::$TE_PUBLICADO){
            $objInfraException->lancarValidacao('N�o � poss�vel cancelar um documento publicado.');
          }

          if ($objDocumentoDTO->getObjPublicacaoDTO()->getStrStaEstado()==PublicacaoRN::$TE_AGENDADO){
            $objInfraException->lancarValidacao('N�o � poss�vel cancelar um documento agendado para publica��o.');
          }
        }
      }

      if ($objDocumentoDTO->getStrStaNivelAcessoGlobalProtocolo()==ProtocoloRN::$NA_SIGILOSO){
        $objAtividadeRN = new AtividadeRN();
        $arrObjAtividadeDTO = $objAtividadeRN->listarCredenciaisAssinatura($objDocumentoDTO);
        foreach($arrObjAtividadeDTO as $objAtividadeDTO){
          if ($objAtividadeDTO->getNumIdTarefa()==TarefaRN::$TI_CONCESSAO_CREDENCIAL_ASSINATURA){
            $objInfraException->lancarValidacao('Documento possui credencial para assinatura ativa.');
            break;
          }
        }
      }

      $objProcedimentoRN = new ProcedimentoRN();
      $objProcedimentoRN->verificarEstadoProcedimento($objDocumentoDTO);

      $objArquivamentoDTO = new ArquivamentoDTO();
      $objArquivamentoDTO->retStrStaArquivamento();
      $objArquivamentoDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdDocumento());

      $objArquivamentoRN = new ArquivamentoRN();
      $objArquivamentoDTO = $objArquivamentoRN->consultar($objArquivamentoDTO);

      if ($objArquivamentoDTO!=null) {
        if ($objArquivamentoDTO->getStrStaArquivamento() == ArquivamentoRN::$TA_ARQUIVADO) {
          $objInfraException->lancarValidacao('N�o � poss�vel cancelar um documento arquivado.');
        } else if ($objArquivamentoDTO->getStrStaArquivamento() == ArquivamentoRN::$TA_SOLICITADO_DESARQUIVAMENTO) {
          $objInfraException->lancarValidacao('N�o � poss�vel cancelar um documento com solicita��o de desarquivamento.');
        }
      }

      $objInfraException->lancarValidacoes();

      if (count($SEI_MODULOS)) {

        $objDocumentoAPI = new DocumentoAPI();
        $objDocumentoAPI->setIdDocumento($objDocumentoDTO->getDblIdDocumento());
        $objDocumentoAPI->setNumeroProtocolo($objDocumentoDTO->getStrProtocoloDocumentoFormatado());
        $objDocumentoAPI->setIdSerie($objDocumentoDTO->getNumIdSerie());
        $objDocumentoAPI->setIdUnidadeGeradora($objDocumentoDTO->getNumIdUnidadeGeradoraProtocolo());
        $objDocumentoAPI->setTipo($objDocumentoDTO->getStrStaProtocoloProtocolo());
        $objDocumentoAPI->setNivelAcesso($objDocumentoDTO->getStrStaNivelAcessoGlobalProtocolo());
        $objDocumentoAPI->setSubTipo($objDocumentoDTO->getStrStaDocumento());
        
        foreach ($SEI_MODULOS as $seiModulo) {
          $seiModulo->executar('cancelarDocumento', $objDocumentoAPI);
        }
      }

      $objRelBlocoProtocoloDTO = new RelBlocoProtocoloDTO();
      $objRelBlocoProtocoloDTO->retNumIdBloco();
      $objRelBlocoProtocoloDTO->retDblIdProtocolo();
      $objRelBlocoProtocoloDTO->setDblIdProtocolo($parObjDocumentoDTO->getDblIdDocumento());

      $objRelBlocoProtocoloRN = new RelBlocoProtocoloRN();
      $objRelBlocoProtocoloRN->excluirRN1289($objRelBlocoProtocoloRN->listarRN1291($objRelBlocoProtocoloDTO));

      $arrObjAtributoAndamentoDTO = array();
      $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
      $objAtributoAndamentoDTO->setStrNome('DOCUMENTO');
      $objAtributoAndamentoDTO->setStrValor($objDocumentoDTO->getStrProtocoloDocumentoFormatado());
      $objAtributoAndamentoDTO->setStrIdOrigem($parObjDocumentoDTO->getDblIdDocumento());
      $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;

      $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
      $objAtributoAndamentoDTO->setStrNome('MOTIVO');
      $objAtributoAndamentoDTO->setStrValor($parObjDocumentoDTO->getStrMotivoCancelamento());
      $objAtributoAndamentoDTO->setStrIdOrigem($parObjDocumentoDTO->getDblIdDocumento());
      $arrObjAtributoAndamentoDTO[] = $objAtributoAndamentoDTO;

      $objAtividadeDTO = new AtividadeDTO();
      $objAtividadeDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdProcedimento());
      $objAtividadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
      $objAtividadeDTO->setNumIdTarefa(TarefaRN::$TI_CANCELAMENTO_DOCUMENTO);
      $objAtividadeDTO->setArrObjAtributoAndamentoDTO($arrObjAtributoAndamentoDTO);

      $objAtividadeRN  = new AtividadeRN();
      $objAtividadeRN->gerarInternaRN0727($objAtividadeDTO);

      $objProtocoloDTO = new ProtocoloDTO();
      $objProtocoloDTO->setStrStaNivelAcessoLocal($objDocumentoDTO->getStrStaNivelAcessoLocalProtocolo());
      $objProtocoloDTO->setDblIdProtocolo($parObjDocumentoDTO->getDblIdDocumento());

      $objProtocoloRN = new ProtocoloRN();
      $objProtocoloRN->cancelar($objProtocoloDTO);

      //Auditoria

    }catch(Exception $e){
      throw new InfraException('Erro cancelando documento.',$e);
    }
  }

  private function obterConteudoAuditoriaExclusaoCancelamento(DocumentoDTO $objDocumentoDTO){
    try{

      $ret = null;

      if ($objDocumentoDTO->getStrStaProtocoloProtocolo()==ProtocoloRN::$TP_DOCUMENTO_RECEBIDO){

        $objAnexoDTO = new AnexoDTO();
        $objAnexoDTO->retNumIdAnexo();
        $objAnexoDTO->retDthInclusao();
        $objAnexoDTO->retStrNome();
        $objAnexoDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdDocumento());

        $objAnexoRN = new AnexoRN();
        $objAnexoDTO = $objAnexoRN->consultarRN0736($objAnexoDTO);

        if ($objAnexoDTO!=null) {
          $ret = '[Nome do Anexo] => '.$objAnexoDTO->getStrNome()."\n".
              '[Inclus�o] => '.$objAnexoDTO->getDthInclusao()."\n".
              '[Localiza��o] => '.str_replace(ConfiguracaoSEI::getInstance()->getValor('SEI','RepositorioArquivos'), '', $objAnexoRN->obterLocalizacao($objAnexoDTO));
        }else{
          $ret = '[Documento sem anexo]';
        }

      }else{
        $ret = $objDocumentoDTO->getStrConteudo();
      }

      return "\n\n".$ret."\n\n";

    }catch(Exception $e){
      throw new InfraException('Erro preenchendo conte�do para auditoria.');
    }
  }

}
?>