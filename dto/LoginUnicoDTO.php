<?php
/**
 * BASIS Tecnologia
 * 2020-02-14
 * Versão do Gerador de Código:
 * Versão no CVS/SVN:
 *
 * Mantém dados de domínio: usuario_login_unico
 *
 * SEI
 * Login Único
 *
 * @author Diego Tesch Gramelich <diego.tesch@basis.com.br>
 * @author Behatris Fiorentini <behatris.fiorentini@basis.com.br>
 */

require_once dirname(__FILE__) . '/../../../SEI.php';

class LoginUnicoDTO extends InfraDTO{

    public function getStrNomeTabela(){
        return 'usuario_login_unico';
    }

    public function montar (){

      $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_NUM, 'IdLogin', 'id_usuario_login_unico');
      $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_NUM, 'IdUsuario', 'id_usuario');
      $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_DBL, 'Cpf', 'cpf');
      $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_DTH, 'DataAtualizacao', 'dth_atualizacao');
      $this->adicionarAtributoTabela(InfraDTO::$PREFIXO_STR, 'Email', 'email');

      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_STR,'Sigla','a.sigla','usuario a');
      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_STR,'Nome','a.nome','usuario a');
      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_NUM,'IdOrgao','a.id_orgao','usuario a');
      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_STR,'SiglaOrgao','o.sigla','orgao o');
      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_STR,'DescricaoOrgao','o.descricao','orgao o');
      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_NUM,'IdContato','a.id_contato','usuario a');
      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_STR,'SinAtivo','a.sin_ativo','usuario a');
      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_STR,'TelefoneFixoContato','c.telefone_fixo','contato c');
      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_STR,'TelefoneCelularContato','c.telefone_celular','contato c');
      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_NUM,'IdUfContato','c.id_uf','contato c');
      $this->adicionarAtributoTabelaRelacionada(InfraDTO::$PREFIXO_NUM,'IdCidadeContato','c.id_cidade','contato c');

      //Campos de pesquisa
      $this->adicionarAtributo(InfraDTO::$PREFIXO_BOL, 'Selecionado');
    	$this->adicionarAtributo(InfraDTO::$PREFIXO_STR, 'PalavrasPesquisa');

      $this->configurarPK('IdLogin', InfraDTO::$TIPO_PK_INFORMADO);
      $this->configurarFK('IdUsuario', 'usuario a', 'a.id_usuario');
      $this->configurarFK('IdOrgao','orgao o','o.id_orgao');
      $this->configurarFK('IdContato','contato c','c.id_contato');
    }
}