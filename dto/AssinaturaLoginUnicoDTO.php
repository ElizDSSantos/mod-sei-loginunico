<?php
/**
 * BASIS Tecnologia
 * 2020-02-14
 * Versão do Gerador de Código:
 * Versão no CVS/SVN:
 *
 * Mantém dados de domínio: md_login_unico_usuario
 *
 * SEI
 * Login Único
 *
 * @author Diego Tesch Gramelich <diego.tesch@basis.com.br>
 * @author Behatris Fiorentini <behatris.fiorentini@basis.com.br>
 */

require_once dirname(__FILE__) . '/../../../SEI.php';

class AssinaturaLoginUnicoDTO extends InfraDTO{

    public function getStrNomeTabela(){
        return 'md_login_unico_assinatura';
    }

    public function montar (){

      $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_NUM, 'IdAssinaturaLoginUnico', 'id_assinatura_login_unico');
      $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_NUM, 'IdUsuario', 'id_usuario');
      $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_STR, 'Agrupador', 'agrupador');
      $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_STR, 'StateLoginUnico', 'state_login_unico');
      $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_STR, 'Operacao', 'operacao');
      $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_STR, 'AcaoOrigem', 'acao_origem');
      $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_NUM, 'IdAcessoExterno', 'id_acesso_externo');

      $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_DTH, 'DataAtualizacao', 'dth_atualizacao');

      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_STR,'Sigla','a.sigla','usuario a');
      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_STR,'Nome','a.nome','usuario a');
      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_NUM,'IdOrgao','a.id_orgao','usuario a');
      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_STR,'SiglaOrgao','o.sigla','orgao o');
      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_STR,'DescricaoOrgao','o.descricao','orgao o');
      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_NUM,'IdContato','a.id_contato','usuario a');
      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_STR,'SinAtivo','a.sin_ativo','usuario a');
      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_STR,'StaTipo','a.sta_tipo','usuario a');
      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_STR,'TelefoneResidencialContato','c.telefone_residencial','contato c');
      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_STR,'TelefoneComercialContato','c.telefone_comercial','contato c');
      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_STR,'TelefoneCelularContato','c.telefone_celular','contato c');
      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_NUM,'IdUfContato','c.id_uf','contato c');
      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_NUM,'IdCidadeContato','c.id_cidade','contato c');


      $this->configurarPK('IdAssinaturaLoginUnico', InfraDTO::$TIPO_PK_INFORMADO);
      $this->configurarFK('IdUsuario', 'usuario a', 'a.id_usuario');
      $this->configurarFK('IdOrgao','orgao o','o.id_orgao');
      $this->configurarFK('IdContato','contato c','c.id_contato');
    }
}