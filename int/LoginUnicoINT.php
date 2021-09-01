<?php

require_once DIR_SEI_WEB.'/SEI.php';

class LoginUnicoINT extends InfraINT {

    

    /** Salva em um array de sessão
     * @param string $contextoSessao Tipo da Sessao, opções 'SessaoSEIExterna' ou 'SessaoSEI'
     * @param string $atributo Nome do atributo desejado
     * @param string $state State que será a chave de busca
     * @param string $dados Dados para salvar
     * @return void
     */
    public static function adicionaEmArraySessao($contextoSessao,$atributo,$state,$dados){

        switch($contextoSessao){

            case "SessaoSEIExterna":
            $varSession=SessaoSEIExterna::getInstance()->getAtributo($atributo);
            $varSession=$varSession==""?array():$varSession;
            $varSession[$state]=$dados;
            SessaoSEIExterna::getInstance()->setAtributo($atributo, $varSession);
            break;
                
            case "SessaoSEI":
            $varSession=SessaoSEI::getInstance()->getAtributo($atributo);
            $varSession=$varSession==""?array():$varSession;
            $varSession[$state]=$dados;
            SessaoSEI::getInstance()->setAtributo($atributo, $varSession);
            break;
                


        }
    }

    public static function excluirDadosState($state){

        $sistema=ConfiguracaoSEI::getInstance()->getValor('SessaoSEI','SiglaSistema');
        $orgao=ConfiguracaoSEI::getInstance()->getValor('SessaoSEI','SiglaOrgaoSistema');
        // unset($_SESSION["INFRA_ATRIBUTOS"][$orgao][$sistema]['MD_LOGIN_UNICO_ASSINATURA_DTO'][$state]);
        unset($_SESSION["INFRA_ATRIBUTOS"][$orgao][$sistema]['MD_LOGIN_UNICO_HASHMAP'][$state]);
        

    }

    public static function obterDadosSessao($contextoSessao,$chave,$valor){


        switch($contextoSessao){

            case "SessaoSEIExterna":
            $varSession=SessaoSEIExterna::getInstance()->getAtributo($chave);
                if($varSession==null){
                    return null;
                }else{
                    return $varSession[$valor];
                }
                break;
            case "SessaoSEI":
            $varSession=SessaoSEI::getInstance()->getAtributo($chave);
                if($varSession==null){
                    return null;
                }else{
                    return $varSession[$valor];
                }
                break;


        }


    }




}