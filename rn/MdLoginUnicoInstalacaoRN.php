<?php

require_once dirname(__FILE__).'/../../../SEI.php';



class MdLoginUnicoInstalacaoRN extends InfraRN {

    private $numSeg = 0;
    private $versaoAtualDesteModulo = '1.0.0';
    private $nomeParametroModulo = 'MD_LOGIN_UNICO';
    private $nomeModulo = 'Modulo Login Unico';

    public function __construct(){
        $this->inicializar('SEI - INICIALIZAR ' . $this->nomeModulo);
    }

    protected function inicializarObjInfraIBanco(){
        return BancoSEI::getInstance();
    }


    private function inicializar($strTitulo){

        ini_set('max_execution_time','0');
        ini_set('memory_limit','-1');
        
        try {
            @ini_set('zlib.output_compression','0');
            @ini_set('implicit_flush', '1');
        } catch(Exception $e) {}
        
        BancoSEI::getInstance()->abrirConexao();
        BancoSEI::getInstance()->abrirTransacao();
        
        ob_implicit_flush();
        
        InfraDebug::getInstance()->setBolLigado(true);
        InfraDebug::getInstance()->setBolDebugInfra(true);
        InfraDebug::getInstance()->setBolEcho(true);
        InfraDebug::getInstance()->limpar();
        
        $this->logar($strTitulo);

    }

    private function logar($strMsg){
        InfraDebug::getInstance()->gravar($strMsg);
        flush();
    }

    private function finalizar($strMsg=null, $bolErro){

        if (!$bolErro) {
            $this->numSeg = InfraUtil::verificarTempoProcessamento($this->numSeg);
            $this->logar('TEMPO TOTAL DE EXECUCAO: ' . $this->numSeg . ' s');
        } else {
            $strMsg = 'ERRO: '.$strMsg;
        }
        
        if ($strMsg!=null){
            $this->logar($strMsg);
        }

        InfraDebug::getInstance()->setBolLigado(false);
        InfraDebug::getInstance()->setBolDebugInfra(false);
        InfraDebug::getInstance()->setBolEcho(false);
        BancoSEI::getInstance()->cancelarTransacao();
        BancoSEI::getInstance()->fecharConexao();
        InfraDebug::getInstance()->limpar();
        $this->numSeg = 0;
        die;

    }

    /**
    * @throws InfraException
    */
    protected function atualizarVersaoControlado(){
        
        try {
            
            if (!(BancoSEI::getInstance() instanceof InfraMySql) && !(BancoSEI::getInstance() instanceof InfraSqlServer) && !(BancoSEI::getInstance() instanceof InfraOracle)){
                $this->finalizar('BANCO DE DADOS NAO SUPORTADO: '.get_parent_class(BancoSEI::getInstance()),true);
            }
            
            //Selecionando versão a ser instalada
            $objInfraParametro = new InfraParametro(BancoSEI::getInstance());
            $strVersaoModuloAnterior = $objInfraParametro->getValor($this->nomeParametroModulo, false);
            
            $instalacao = array();
            switch($this->strVersaoModuloAnterior) {
                case '': //primeira instalação
                    $instalacao = $this->instalarV100($strVersaoModuloAnterior);
                    break;
                default:
                    $instalacao["operacoes"] = null;
                    $instalacao["erro"] = "Erro instalando/atualizando $this->nomeModulo - Gov.br no SEI. Versao do modulo".$strVersaoModuloAnterior." inválida";
                    break;      
            }
            if (isset($instalacao["erro"])) {
                 $this->finalizar($instalacao["erro"],true);
            } else {
                 $this->logar("Instalacao/Atualizacao do módulo $this->nomeModulo realizada com sucesso");
                 $this->logar('FIM');
            }
            
            InfraDebug::getInstance()->setBolLigado(false);
            InfraDebug::getInstance()->setBolDebugInfra(false);
            InfraDebug::getInstance()->setBolEcho(false);
    
            LogSEI::getInstance()->gravar(InfraDebug::getInstance()->getStrDebug());
            
            BancoSEI::getInstance()->confirmarTransacao();
            BancoSEI::getInstance()->fecharConexao();
            InfraDebug::getInstance()->limpar();
            
        } catch(Exception $e) {
            
            InfraDebug::getInstance()->setBolLigado(false);
            InfraDebug::getInstance()->setBolDebugInfra(false);
            InfraDebug::getInstance()->setBolEcho(false);
            
            BancoSEI::getInstance()->cancelarTransacao();
            BancoSEI::getInstance()->fecharConexao();
    
            InfraDebug::getInstance()->limpar();
            throw new InfraException('Erro instalando/atualizando '. $this->nomeModulo . ' - Gov.br no SEI.', $e);
                    
        }
    
    }
  
    private function instalarV100($strVersaoModuloAnterior){

        $objInfraMetaBD = new InfraMetaBD(BancoSEI::getInstance());
        $versao = '1.0.0';
        $this->logar(" INICIANDO OPERACOES DA INSTALACAO DA VERSAO $versao DO $this->nomeModulo NA BASE DO SEI");


        $resultado = array();
        $resultado["operacoes"] = null;

        if(InfraString::isBolVazia($strVersaoModuloAnterior)){

            BancoSEI::getInstance()->executarSql("CREATE TABLE md_login_unico_usuario (
                id_usuario_login_unico " . $objInfraMetaBD->tipoNumero() . " NOT NULL,
                id_usuario " . $objInfraMetaBD->tipoNumero() . " NOT NULL,
                cpf " . $objInfraMetaBD->tipoNumeroGrande() . " NOT NULL,
                email " . $objInfraMetaBD->tipoTextoVariavel(200) . " NOT NULL,
                dth_atualizacao " . $objInfraMetaBD->tipoDataHora() . " NOT NULL)");

            $objInfraMetaBD->adicionarChavePrimaria('md_login_unico_usuario','pk_id_usuario_login_unico',array('id_usuario_login_unico'));
            $objInfraMetaBD->adicionarChaveEstrangeira('fk_usuario_login_unico','md_login_unico_usuario',array('id_usuario'),'usuario',array('id_usuario'));
            
            
            BancoSEI::getInstance()->executarSql("CREATE TABLE md_login_unico_assinatura (
                id_assinatura_login_unico " . $objInfraMetaBD->tipoNumero() . " NOT NULL,
                id_usuario " . $objInfraMetaBD->tipoNumero() . " NOT NULL,
                agrupador " . $objInfraMetaBD->tipoTextoVariavel(200) . " NOT NULL,
                state_login_unico " . $objInfraMetaBD->tipoTextoVariavel(200) . " NOT NULL,
                operacao " . $objInfraMetaBD->tipoTextoVariavel(200) . " NOT NULL,
                acao_origem " . $objInfraMetaBD->tipoTextoVariavel(200) . ",
                id_acesso_externo " . $objInfraMetaBD->tipoNumero() . ",
                id_documentos " . $objInfraMetaBD->tipoTextoVariavel(200) . " NOT NULL,
                dth_atualizacao " . $objInfraMetaBD->tipoDataHora() . " NOT NULL)");

            $objInfraMetaBD->adicionarChavePrimaria('md_login_unico_assinatura','pk_id_assinatura_login_unico',array('id_assinatura_login_unico'));
            $objInfraMetaBD->adicionarChaveEstrangeira('fk_assinatura_login_unico','md_login_unico_assinatura',array('id_usuario'),'usuario',array('id_usuario'));


               $ad= $objInfraMetaBD->obterMaxIdTabela('email_sistema');
               $ad=$ad[0]['maximo']+1; 
               $descricao = 'Login Único - Usuário Habilitado';
                $de = '@sigla_sistema@ <@email_sistema@>';
                $para = '@email_usuario_externo@';
                $assunto = '@sigla_sistema@-@sigla_orgao@ - Cadastro de Usuário Externo';
                $conteudo = ':: Este é um e-mail automático ::

                Prezado(a) @nome_usuario_externo@,

                Seu cadastro como Usuário Externo no SEI-@sigla_orgao@ foi realizado com sucesso.

                Para acessar o sistema clique no link abaixo ou copie e cole em seu navegador: 
                @link_login_usuario_externo@

                Para obter mais informações, envie e-mail para sei@cade.gov.br ou ligue para o Telefone: (61) 3031-1825.

                Núcleo Gestor do SEI
                @descricao_orgao@ - @sigla_orgao@
                SEPN 515, Conjunto D, Lote 4, Ed. Carlos Taurisano.
                CEP: 70770-504 - Brasília/DF.
                @descricao_orgao@


                ATENÇÃO: As informações contidas neste e-mail, incluindo seus anexos, podem ser restritas apenas à pessoa ou entidade para a qual foi endereçada. Se você não é o destinatário ou a pessoa responsável por encaminhar esta mensagem ao destinatário, você está, por meio desta, notificado que não deverá rever, retransmitir, imprimir, copiar, usar ou distribuir esta mensagem ou quaisquer anexos. Caso você tenha recebido esta mensagem por engano, por favor, contate o remetente imediatamente e em seguida apague esta mensagem favor, contate o remetente imediatamente e em seguida apague esta mensagem.';

                $ativo = 'S';
                $modulo = 'MD_LOGINUNICO_CADASTRO_USUARIO';

             


                $objEmailSistemaDTO=new EmailSistemaDTO();
                $objEmailSistemaDTO->setNumIdEmailSistema($ad);
                $objEmailSistemaDTO->setStrDescricao($descricao);
                $objEmailSistemaDTO->setStrDe($de);
                $objEmailSistemaDTO->setStrPara($para);
                $objEmailSistemaDTO->setStrAssunto($assunto);
                $objEmailSistemaDTO->setStrConteudo($conteudo);
                $objEmailSistemaDTO->setStrSinAtivo($ativo);
                $objEmailSistemaDTO->setStrIdEmailSistemaModulo($modulo);

                $objEmailSistemaBD = new EmailSistemaBD(BancoSEI::getInstance());
                $objEmailSistemaBD->cadastrar($objEmailSistemaDTO);


            //Criacao de Sequencias
            if (BancoSEI::getInstance() instanceof InfraMySql){
                BancoSEI::getInstance()->executarSql('create table IF NOT EXISTS  md_login_unico_seq_usuario (id bigint not null primary key AUTO_INCREMENT, campo char(1) null) AUTO_INCREMENT = 1');
                BancoSEI::getInstance()->executarSql('create table IF NOT EXISTS  md_login_unico_seq_assinatura (id bigint not null primary key AUTO_INCREMENT, campo char(1) null) AUTO_INCREMENT = 1');
            } else if (BancoSEI::getInstance() instanceof InfraSqlServer){
                BancoSEI::getInstance()->executarSql('create table md_login_unico_seq_usuario (id bigint identity(1,1), campo char(1) null)');
                BancoSEI::getInstance()->executarSql('create table md_login_unico_seq_assinatura (id bigint identity(1,1), campo char(1) null)');
            } else if (BancoSEI::getInstance() instanceof InfraOracle){
                BancoSEI::getInstance()->criarSequencialNativa('md_login_unico_seq_usuario', 1);
                BancoSEI::getInstance()->criarSequencialNativa('md_login_unico_seq_assinatura', 1);
            }


            $objInfraParametroDTO = new InfraParametroDTO();
            $objInfraParametroDTO->setStrNome($this->nomeParametroModulo);
            $objInfraParametroDTO->setStrValor('1.0.0');
            $objInfraParametroBD = new InfraParametroBD(BancoSEI::getInstance());
            $objInfraParametroBD->cadastrar($objInfraParametroDTO);
            
                
        }else if(trim($strVersaoModuloAnterior)==$versao){

            $resultado["erro"] = "Erro instalando/atualizando $this->nomeModulo no SEI. VersÃ£o ".$strVersaoModuloAnterior." jÃ¡ instalada";
            return $resultado;

        }
        
        return $resultado;
    }
    


}

?>