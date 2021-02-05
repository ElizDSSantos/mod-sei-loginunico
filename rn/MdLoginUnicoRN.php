<?php

require_once dirname(__FILE__).'/../../../SEI.php';



class MdLoginUnicoRN extends InfraRN {

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
            
            //Selecionando vers�o a ser instalada
            $objInfraParametro = new InfraParametro(BancoSEI::getInstance());
            $strVersaoModuloAnterior = $objInfraParametro->getValor($this->nomeParametroModulo, false);
            
            $instalacao = array();
            switch($this->strVersaoModuloAnterior) {
                case '': //primeira instala��o
                    $instalacao = $this->instalarV100($strVersaoModuloAnterior);
                    break;
                default:
                    $instalacao["operacoes"] = null;
                    $instalacao["erro"] = "Erro instalando/atualizando $this->nomeModulo - Gov.br no SEI. Versao do modulo".$strVersaoModuloAnterior." inv�lida";
                    break;      
            }
            if (isset($instalacao["erro"])) {
                 $this->finalizar($instalacao["erro"],true);
            } else {
                 $this->logar("Instalacao/Atualizacao do m�dulo $this->nomeModulo realizada com sucesso");
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

            BancoSEI::getInstance()->executarSql("CREATE TABLE IF NOT EXISTS usuario_login_unico (
                id_usuario_login_unico " . $objInfraMetaBD->tipoNumero() . " NOT NULL,
                id_usuario " . $objInfraMetaBD->tipoNumero() . " NOT NULL,
                cpf " . $objInfraMetaBD->tipoNumeroGrande() . " NOT NULL,
                email " . $objInfraMetaBD->tipoTextoVariavel(200) . " NOT NULL,
                dth_atualizacao " . $objInfraMetaBD->tipoDataHora() . " NOT NULL)");

            $objInfraMetaBD->adicionarChavePrimaria('usuario_login_unico','pk_id_usuario_login_unico',array('id_usuario_login_unico'));
            $objInfraMetaBD->adicionarChaveEstrangeira('fk_usuario_login_unico','usuario_login_unico',array('id_usuario'),'usuario',array('id_usuario'));


               //$ad= BancoSEI::getInstance()->consultarSql("(SELECT (MAX(id_email_sistema) + 1) as maximo FROM email_sistema)");
               $ad= $objInfraMetaBD->obterMaxIdTabela('email_sistema');
               $ad=$ad[0]['maximo']+1; 
               $descricao = 'Login �nico - Usu�rio Habilitado';
                $de = '@sigla_sistema@ <@email_sistema@>';
                $para = '@email_usuario_externo@';
                $assunto = '@sigla_sistema@-@sigla_orgao@ - Cadastro de Usu�rio Externo';
                $conteudo = ':: Este � um e-mail autom�tico ::

                Prezado(a) @nome_usuario_externo@,

                Seu cadastro como Usu�rio Externo no SEI-@sigla_orgao@ foi realizado com sucesso.

                Para acessar o sistema clique no link abaixo ou copie e cole em seu navegador: 
                @link_login_usuario_externo@

                Para obter mais informa��es, envie e-mail para sei@cade.gov.br ou ligue para o Telefone: (61) 3031-1825.

                N�cleo Gestor do SEI
                @descricao_orgao@ - @sigla_orgao@
                SEPN 515, Conjunto D, Lote 4, Ed. Carlos Taurisano.
                CEP: 70770-504 - Bras�lia/DF.
                @descricao_orgao@


                ATEN��O: As informa��es contidas neste e-mail, incluindo seus anexos, podem ser restritas apenas � pessoa ou entidade para a qual foi endere�ada. Se voc� n�o � o destinat�rio ou a pessoa respons�vel por encaminhar esta mensagem ao destinat�rio, voc� est�, por meio desta, notificado que n�o dever� rever, retransmitir, imprimir, copiar, usar ou distribuir esta mensagem ou quaisquer anexos. Caso voc� tenha recebido esta mensagem por engano, por favor, contate o remetente imediatamente e em seguida apague esta mensagem favor, contate o remetente imediatamente e em seguida apague esta mensagem.';

                $ativo = 'S';
                $modulo = 'MD_LOGINUNICO_CADASTRO_USUARIO';

                BancoSEI::getInstance()->executarSql("INSERT INTO email_sistema (id_email_sistema, descricao, de, para, assunto, conteudo, sin_ativo, id_email_sistema_modulo)	
                VALUES ('$ad','$descricao','$de','$para','$assunto','$conteudo','$ativo','$modulo')");


            //Criacao de Sequencias
            if (BancoSEI::getInstance() instanceof InfraMySql){
                BancoSEI::getInstance()->executarSql('create table IF NOT EXISTS  seq_usuario_login_unico (id bigint not null primary key AUTO_INCREMENT, campo char(1) null) AUTO_INCREMENT = 1');
            } else if (BancoSEI::getInstance() instanceof InfraSqlServer){
                BancoSEI::getInstance()->executarSql('create table IF NOT EXISTS  seq_usuario_login_unico (id bigint identity(1,1), campo char(1) null)');
            } else if (BancoSEI::getInstance() instanceof InfraOracle){
                BancoSEI::getInstance()->criarSequencialNativa('seq_usuario_login_unico', 1);
            }


            $objInfraParametroDTO = new InfraParametroDTO();
            $objInfraParametroDTO->setStrNome($this->nomeParametroModulo);
            $objInfraParametroDTO->setStrValor('1.0.0');
            $objInfraParametroBD = new InfraParametroBD(BancoSEI::getInstance());
            $objInfraParametroBD->cadastrar($objInfraParametroDTO);
            
                
        }else if(trim($strVersaoModuloAnterior)==$versao){

            $resultado["erro"] = "Erro instalando/atualizando $this->nomeModulo no SEI. Versão ".$strVersaoModuloAnterior." já instalada";
            return $resultado;

        }
        
        return $resultado;
    }
    


}

?>