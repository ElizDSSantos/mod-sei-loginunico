<?php

date_default_timezone_set('America/Sao_Paulo');
require_once dirname(__FILE__) . '/../../../../web/SEI.php';
require_once dirname(__FILE__) . '/../vendor/autoload.php';
//require_once dirname(__FILE__) . '/../../../../web/bd/UsuarioBD.php';

use \Firebase\JWT\JWT;

final class LoginControladorRN extends InfraRN
{
    private $url_provider ;
    private $client_id;
    private $secret;
    private $redirect_uri;
    private $scope;
    private $url_servico;
    private $id_orgao;

    protected function inicializarObjInfraIBanco()
    {
        return BancoSEI::getInstance();
    }

    public function __construct()
    {
        $conf = new ConfiguracaoSEI();
        $this->client_id      = $conf->getArrConfiguracoes()['LoginUnico']['client_id'];
        $this->secret         = $conf->getArrConfiguracoes()['LoginUnico']['secret'];
        $this->url_provider   = $conf->getArrConfiguracoes()['LoginUnico']['url_provider'];
        $this->redirect_uri   = $conf->getArrConfiguracoes()['LoginUnico']['redirect_url'];
        $this->scope          = $conf->getArrConfiguracoes()['LoginUnico']['scope'];
        $this->url_servico    = $conf->getArrConfiguracoes()['LoginUnico']['url_servicos'];
        $this->selo_confianca = $conf->getArrConfiguracoes()['LoginUnico']['selo_confianca'];
        $this->id_orgao       = $conf->getArrConfiguracoes()['LoginUnico']['orgao'];
    }

     /**
      * Gerar URL para envio ao GovBr
      *
      * @return void
      */
    public function gerarURL()
    {
        $uri = $this->url_provider."/authorize?response_type=code"
            ."&client_id=". $this->client_id
            ."&scope=".$this->scope
            ."&redirect_uri=".urlencode($this->redirect_uri)
            ."&nonce=".$this->getRandomHex()
            ."&state=".$this->getRandomHex();
        return $uri;
    }

     /**
      * Undocumented function
      *
      * @param integer $num_bytes
      * @return void
      */
    private function getRandomHex($num_bytes=4)
    {
        return bin2hex(openssl_random_pseudo_bytes($num_bytes));
    }

     /**
      * Gera o access_token, token_type, expires_in, scope, id_token 
      *
      * @param [type] $dados
      * @return void
      */
    public function gerarAccessToken($dados)
    {
        $campos = array(
            'grant_type' => urlencode('authorization_code'),
            'code' => urlencode($dados['code']),
            'redirect_uri' => urlencode($this->redirect_uri)
        );

        $fields_string = '';
        foreach ($campos as $key=>$value) {
            $fields_string .= $key.'='.$value.'&';
        }

        rtrim($fields_string, '&');
        $ch_token = curl_init();
        curl_setopt($ch_token, CURLOPT_URL, $this->url_provider . "/token");
        curl_setopt($ch_token, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch_token, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_token, CURLOPT_SSL_VERIFYPEER, true);
        $headers = array(
            'Content-Type:application/x-www-form-urlencoded',
            'Authorization: Basic '. base64_encode($this->client_id.":".$this->secret)
        );
        curl_setopt($ch_token, CURLOPT_HTTPHEADER, $headers);
        $json_output_tokens = json_decode(curl_exec($ch_token), true);
        curl_close($ch_token);

        return $json_output_tokens;
    }

    /**
     * Extração das informações acerca do usuário e tempo de expiração do token.
     *
     * @return void
     */
    public function gerarJwk()
    {
        $url = $this->url_provider . "/jwk" ;
        $ch_jwk = curl_init();
        curl_setopt($ch_jwk, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch_jwk, CURLOPT_URL, $url);
        curl_setopt($ch_jwk, CURLOPT_RETURNTRANSFER, true);
        $json_output_jwk = json_decode(curl_exec($ch_jwk), true);
        curl_close($ch_jwk);

        return $json_output_jwk;
    }

    /**
     * Realiza autenticação do usuário com os dados vindos do GovBr
     *
     * @param array $dados
     * @return void
     */
    protected function autenticarControlado($dados)
    {
        $CODE = $dados["code"];

        if (isset($CODE) && (!SessaoSEIExterna::getInstance()->getAtributo('MD_LOGIN_EXTERNO_TOKEN') || $_SESSION['validar_assinatura'])) {
            $json_output_tokens = $this->gerarAccessToken($dados);
            $json_output_jwk = $this->gerarJwk();

            $access_token = $json_output_tokens['access_token'];
            $id_token = $json_output_tokens['id_token'];
            
            try {
                $json_output_payload_id_token = $this->processToClaims($id_token, $json_output_jwk);
                SessaoSEIExterna::getInstance()->setAtributo('MD_LOGIN_EXTERNO_TOKEN', $json_output_payload_id_token);
                $cpf = $json_output_payload_id_token['sub'];
                $selos = $this->obterSelos($id_token, $cpf);
                $dadosReceita = $this->obterDadosReceita($id_token);
                SessaoSEIExterna::getInstance()->setAtributo('MD_LOGIN_EXTERNO_TOKEN_ENDERECO', $dadosReceita);
                $ecnpj = $this->obterSeloCNPJ($json_output_payload_id_token, $json_output_tokens);
                $getDadosUser = $this->pesquisarUsuario($json_output_payload_id_token);
                $userSei = $getDadosUser['user'];
                $atualizarUser = $getDadosUser['update'];
                $sinSelo = $this->checkUserHasSelo($selos);
                SessaoSEIExterna::getInstance()->setAtributo('MD_LOGIN_UNICO_SIN_SELO', $sinSelo);
                $associar = false;
                $duplicidade = false;

                if($userSei && ($userSei->getDblCpfContato() === $json_output_payload_id_token['sub'])){
                    $associar = true;
                }else if($userSei && ($userSei->getDblCpfContato() !== $json_output_payload_id_token['sub'])){
                    $duplicidade = true;
                }

                if (!$userSei) {
                    $this->cadastraUsuario($json_output_payload_id_token, $sinSelo);
                    $getDadosUser = $this->pesquisarUsuario($json_output_payload_id_token);
                    $userSei = $getDadosUser['user'];
                    $atualizarUser = $getDadosUser['update'];
                }
                
            } catch (Exception $e) {
                throw $e;
            }
        } else {
            $getDadosUser = $this->pesquisarUsuario(SessaoSEIExterna::getInstance()->getAtributo('MD_LOGIN_EXTERNO_TOKEN'));
            $userSei = $getDadosUser['user'];
            $atualizarUser = $getDadosUser['update'];
        }

        $this->login($userSei, $atualizarUser, $associar, $duplicidade);
    }

     /**
      * Realiza o redirecionamento para a rota, de acordo com o cenário de cadastro
      *
      * @param boolean $atualizar
      * @param string $status
      * @return void
      */
    private function getDestination($atualizar, $sinAtivo, $staTipo)
    {
        if (!$atualizar) {
            if ($sinAtivo == 'N' || $staTipo != UsuarioRN::$TU_EXTERNO) {
                return array('action' => '../../controlador_externo.php?acao=usuario_externo_sair', 'erro' => true);
            }
            return array('action' => '../../controlador_externo.php?acao=usuario_externo_controle_acessos', 'erro' => false);
        }

        return array('action' => '../../controlador_externo.php?acao=usuario_loginunico_aceite', 'erro' => false);
    }

     /**
      * Realiza o login do usuário no SEI extereno via GovBr
      *
      * @param array $user
      * @param boolean $atualizar
      * @param boolean $associar
      * @param boolean $duplicidade
      * @return void
      */
    private function login($user, $atualizar, $associar, $duplicidade)
    {
        try {
            if (!BancoSEI::getInstance()->getIdConexao()) {
                BancoSEI::getInstance()->abrirConexao();
            }
            $_SESSION['EXTERNO_TOKEN'] = md5(uniqid(mt_rand()));
            $seqUserLogin = $this->getObjInfraIBanco()->getValorSequencia('seq_usuario_login_unico');
            SessaoSEIExterna::getInstance()->setAtributo('ID_USUARIO_EXTERNO', $user->getNumIdUsuario());
            SessaoSEIExterna::getInstance()->setAtributo('ID_USUARIO_LOGIN', $seqUserLogin);
            SessaoSEIExterna::getInstance()->setAtributo('SIGLA_USUARIO_EXTERNO', $user->getStrSigla());
            SessaoSEIExterna::getInstance()->setAtributo('NOME_USUARIO_EXTERNO', $user->getStrNome());
            SessaoSEIExterna::getInstance()->setAtributo('ID_ORGAO_USUARIO_EXTERNO', $user->getNumIdOrgao());
            SessaoSEIExterna::getInstance()->setAtributo('SIGLA_ORGAO_USUARIO_EXTERNO', $user->getStrSiglaOrgao());
            SessaoSEIExterna::getInstance()->setAtributo('DESCRICAO_ORGAO_USUARIO_EXTERNO', $user->getStrDescricaoOrgao());
            SessaoSEIExterna::getInstance()->setAtributo('ID_CONTATO_USUARIO_EXTERNO', $user->getNumIdContato());
            SessaoSEIExterna::getInstance()->setAtributo('RAND_USUARIO_EXTERNO', uniqid(mt_rand(), true));

            $objUsuarioDTOAuditoria = clone($user);

            //AuditoriaSEI::getInstance()->auditar('usuario_externo_logar', __METHOD__, $objUsuarioDTOAuditoria);

            $dest = $this->getDestination($atualizar, $user->getStrSinAtivo(), $user->getStrStaTipo());
            $action = $dest['action'];
            $erro = $dest['erro'];
            
            if($duplicidade){
            echo "<script type='text/javascript'>
                    alert('FALHA NA AUTENTICAÇÃO DO SISTEMA: Não é possível realizar a autenticação. Existe um outro usuário cadastrado no SEI, com o mesmo e-mail e CPF diferente. Entre em contato com a área responsável pelo sistema.');

                     location.href = '".SessaoSEIExterna::getInstance()->assinarLink('../../controlador_externo.php?acao=usuario_externo_sair&id_orgao_acesso_externo='.$user->getNumIdOrgao())."';
                  </script>";
                  die;
            }else{
                $url = $associar ? 
                SessaoSEIExterna::getInstance()->assinarLink('../../controlador_externo.php?acao=usuario_loginunico_associar&id_orgao_acesso_externo='.$user->getNumIdOrgao()) : 
                SessaoSEIExterna::getInstance()->assinarLink($action.'&id_orgao_acesso_externo='.$user->getNumIdOrgao());
            }
            
            header('Location: '.$url);
        } catch (Exception $e) {
            throw new InfraException('Erro ao realizar login (govbr).', $e);
        }
    }

     /**
      * Verifica se o usuário possui algum dos selos requeridos pelo ÓRGAO
      *
      * @param array $selos
      * @return void
      */
    public function checkUserHasSelo($selos)
    {
        foreach ($selos as $selosUser) {
            //if (in_array($selosUser['nivel'], $this->selo_confianca)) {
            if (in_array($selosUser['confiabilidade']['id'], $this->selo_confianca)) {
                return true;
            }
        }
        return false;
    }

     /**
      * Função que valida o token (access_token ou id_token) e valida o tempo de expiração e a assinatura
      *
      * @param array $token
      * @param array $jwk
      * @return void
      */
    public function processToClaims($token, $jwk)
    {
        $modulus = JWT::urlsafeB64Decode($jwk['keys'][0]['n']);
        $publicExponent = JWT::urlsafeB64Decode($jwk['keys'][0]['e']);


        $components = array(
            'modulus' => pack('Ca*a*', 2, $this->encodeLength(strlen($modulus)), $modulus),
            'publicExponent' => pack('Ca*a*', 2, $this->encodeLength(strlen($publicExponent)), $publicExponent)
        );

        $RSAPublicKey = pack(
            'Ca*a*a*',
            48,
            $this->encodeLength(strlen($components['modulus']) + strlen($components['publicExponent'])),
            $components['modulus'],
            $components['publicExponent']
        );
        $rsaOID = pack('H*', '300d06092a864886f70d0101010500'); // hex version of MA0GCSqGSIb3DQEBAQUA
        $RSAPublicKey = chr(0) . $RSAPublicKey;
        $RSAPublicKey = chr(3) . $this->encodeLength(strlen($RSAPublicKey)) . $RSAPublicKey;
        $RSAPublicKey = pack(
            'Ca*a*',
            48,
            $this->encodeLength(strlen($rsaOID . $RSAPublicKey)),
            $rsaOID . $RSAPublicKey
        );
        $RSAPublicKey = "-----BEGIN PUBLIC KEY-----\r\n" . chunk_split(base64_encode($RSAPublicKey), 64) . '-----END PUBLIC KEY-----';

        JWT::$leeway = 3 * 60;

        $decoded = JWT::decode($token, $RSAPublicKey, array('RS256'));
        return (array) $decoded;
    }

     /**
      * @param [type] $length
      * @return void
      */
    public function encodeLength($length)
    {
        if ($length <= 0x7F) {
            return chr($length);
        }
        $temp = ltrim(pack('N', $length), chr(0));
        return pack('Ca*', 0x80 | strlen($temp), $temp);
    }

     /**
      * Serviço para obter a foto do usuário
      *
      * @param $imagem
      * @param $access_token
      * @return void
      */
    public function obterFoto($imagem, $access_token)
    {
        $url = $imagem['picture'];
        $ch_user_picture = curl_init();
        curl_setopt($ch_user_picture, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch_user_picture, CURLOPT_URL, $url);
        curl_setopt($ch_user_picture, CURLOPT_RETURNTRANSFER, true);
        $headers = array(
            'Authorization: Bearer '. $access_token
        );
        curl_setopt($ch_user_picture, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch_user_picture, CURLOPT_VERBOSE, true);
        curl_setopt($ch_user_picture, CURLOPT_FAILONERROR, true);
        $json_output_user_picture = curl_exec($ch_user_picture);
        if (curl_error($ch_user_picture)) {
            $msg_error = curl_error($ch_user_picture);
        }
        curl_close($ch_user_picture);
    }

     /**
      * Obter selos de confiabilidade
      *
      * @param array $access_token
      * @return void
      */
    public function obterSelos($access_token, $cpf)
    {
        // $url = $this->url_servico . "/api/info/usuario/selo";
        $url = $this->url_servico . "/confiabilidades/v2/contas/$cpf/confiabilidades";
        $ch_confiabilidade = curl_init();
        curl_setopt($ch_confiabilidade, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch_confiabilidade, CURLOPT_URL, $url);
        curl_setopt($ch_confiabilidade, CURLOPT_RETURNTRANSFER, true);
        $headers = array(
            'Accept: application/json',
            'Authorization: Bearer '. $access_token
        );
        curl_setopt($ch_confiabilidade, CURLOPT_HTTPHEADER, $headers);

        $retorno = curl_exec($ch_confiabilidade);
        if($retorno === false){
            throw new InfraException(curl_error($ch_confiabilidade));
        }

        $json_output_confiabilidade = json_decode($retorno, true);
        curl_close($ch_confiabilidade);
        return $json_output_confiabilidade;
    }

     /**
      * Obter dados vindos do token da receita Federal (rfb_completo)
      *
      * @param $access_token
      * @return void
      */
    public function obterDadosReceita($access_token)
    {
        $url = $this->url_servico . "/cadastro/v1/usuario/escopo/rfb_completo";
        $ch_confiabilidade = curl_init();
        curl_setopt($ch_confiabilidade, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch_confiabilidade, CURLOPT_URL, $url);
        curl_setopt($ch_confiabilidade, CURLOPT_RETURNTRANSFER, true);
        $headers = array(
            'Accept: application/json',
            'Authorization: Bearer '. $access_token
        );
        curl_setopt($ch_confiabilidade, CURLOPT_HTTPHEADER, $headers);
        $json_output_confiabilidade = json_decode(curl_exec($ch_confiabilidade), true);
        curl_close($ch_confiabilidade);
        return $json_output_confiabilidade;
    }

     /**
      * Serviço de recuperação de empresas vinculadas, necessário o token, retornando empresas
      * vinculadas ao usuário logado
      *
      * @param [type] $json_output_payload_id_token
      * @param [type] $json_output_tokens
      * @return void
      */
    public function obterSeloCNPJ($json_output_payload_id_token, $json_output_tokens)
    {
        $ch_empresas_vinculadas = curl_init();
        $cpf = $json_output_payload_id_token['sub'];
        curl_setopt($ch_empresas_vinculadas, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch_empresas_vinculadas, CURLOPT_URL, $this->url_servico . "/empresas/v1/representantes/{$cpf}/empresas?visao=simples");
        curl_setopt($ch_empresas_vinculadas, CURLOPT_RETURNTRANSFER, true);
        $headers = array(
            'Accept: application/json',
            'Authorization: Bearer '. $json_output_tokens['access_token']
        );
        curl_setopt($ch_empresas_vinculadas, CURLOPT_HTTPHEADER, $headers);
        $json_output_empresas_vinculadas = json_decode(curl_exec($ch_empresas_vinculadas), true);

        curl_close($ch_empresas_vinculadas);

        foreach ($json_output_empresas_vinculadas['cnpjs'] as $cnpjEmpresa) {
            $ch_papel_empresa = curl_init();
            curl_setopt($ch_papel_empresa, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch_papel_empresa, CURLOPT_URL, $this->url_servico . "/empresas/v1/representantes/{$cpf}/empresas/{$cnpjEmpresa['cnpj']}");
            curl_setopt($ch_papel_empresa, CURLOPT_RETURNTRANSFER, true);
            $headers = array(
                'Accept: application/json',
                'Authorization: '. $json_output_tokens['access_token']
            );
            curl_setopt($ch_papel_empresa, CURLOPT_HTTPHEADER, $headers);
            $json_output_papel_empresa = json_decode(curl_exec($ch_papel_empresa), true);
            curl_close($ch_papel_empresa);
            $json_output_papel_empresa['vinculo'] = $json_output_empresas_vinculadas;
            
            return $json_output_papel_empresa;
        }
    }

    /**
     * Cadastra o usuário, caso não exista
     *
     * @param mixed $token
     * @param boolean $sinSelo
     * @return void
     */
    public function cadastraUsuario($token, $sinSelo)
    {
        try {
            if (!BancoSEI::getInstance()->getIdConexao()) {
                BancoSEI::getInstance()->abrirConexao();
            }
    
            BancoSEI::getInstance()->abrirTransacao();

            $seqContato = $this->getObjInfraIBanco()->getValorSequencia('seq_contato');
            $sinAtivo = $sinSelo ? 'S' : 'N';
            $data_cadastro = date('Y-m-d H:i:s');

            BancoSEI::getInstance()->executarSql(
                "INSERT INTO contato (
                    id_contato, nome, cpf, email, sin_ativo, sigla, sta_natureza,
                    telefone_celular, id_contato_associado, id_tipo_contato, 
                    sin_endereco_associado, dth_cadastro
                    ) VALUES (
                        $seqContato, '".utf8_decode($token['name'])."', '".$token['sub']."',
                        '".$token['email']."', '".$sinAtivo."', '".$token['email']."',
                        'F', '".$token['phone_number']."', 1, 3, 'N', '".$data_cadastro."'
                    )"
            );

            $objInfraSequencia = new InfraSequencia(BancoSEI::getInstance());
            $seqUsuario = $objInfraSequencia->obterProximaSequencia('usuario_sistema');

            BancoSEI::getInstance()->executarSql(
                "INSERT INTO usuario (
                    id_usuario, sin_ativo, sigla, nome,	id_contato,	id_orgao,
                    sta_tipo, sin_acessibilidade
                    ) VALUES (
                        $seqUsuario,  '".$sinAtivo."', '".$token['email']."',
                        '".utf8_decode($token['name'])."', $seqContato, 0, ".UsuarioRN::$TU_EXTERNO.", 'N'
                    )"
            );
    
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
    }

    /**
     * Pesquisa usuário está cadastrado no Login Único
     *
     * @param string $cpf
     * @param string $email
     * @return void
     */
    private function pesquisarUserLoginUnico($cpf, $email){
        $objLoginUnicoDto = new LoginUnicoDTO();
        $objLoginUnicoDto->setDblCpfContato($cpf);
        $objLoginUnicoDto->setStrEmail($email);
        $objLoginUnicoDto->retTodos(true);
        $loginBD  = new LoginUnicoBD($this->getObjInfraIBanco());
        $user = $loginBD->consultar($objLoginUnicoDto);

        return $user;
    }

    /**
     * Verifica se o usuário é cadsatrado no SEI externo ou Login Único
     *
     * @param array $token
     * @return void
     */
    protected function pesquisarUsuarioConectado($token)
    {
        try {
            $update = false;
            $user = $this->pesquisarUserLoginUnico($token['sub'], $token['email']);

            if (!$user) {
                $usuarioDTO = new UsuarioDTO();
                $usuarioDTO->setBolExclusaoLogica(false);
                $usuarioDTO->setStrSigla($token['email']);
                $usuarioDTO->setStrStaTipo(array(UsuarioRN::$TU_EXTERNO, UsuarioRN::$TU_EXTERNO_PENDENTE), InfraDTO::$OPER_IN);
                $usuarioDTO->retTodos(true);
        
                $usuarioDB  = new UsuarioBD($this->getObjInfraIBanco());
                $dados = $usuarioDB->consultar($usuarioDTO);
                $user = $dados;
                $update = true;
            }

            return array(
                'user' => $user, 
                'update' => $update, 
            );
        } catch (Exception $e) {
            throw new InfraException('Erro ao pesquisar usuário.', $e);
        }
    }

    /**
     * Pesquisa pelo nome da cidade e uf, retornando os IDs da Cidade e UF
     *
     * @param string $nomeCidade
     * @param string $siglaUf
     * @return void
     */
    private function pesquisarCidadeUf($nomeCidade, $siglaUf)
    {
        try {
            $cidade = $this->getObjInfraIBanco()->consultarSql('
            SELECT 
            c.id_cidade AS idcidade, 
            c.id_uf AS iduf
            FROM cidade c
            JOIN uf uf
            ON c.id_uf = uf.id_uf
            WHERE uf.sigla LIKE "%'.$siglaUf.'%"
            AND c.nome LIKE "%'.$nomeCidade.'%";
            ');

            return $cidade;
        } catch (Exception $e) {
            throw new InfraException('Erro ao pesquisar Cidade e UF.', $e);
        }
    }

    /**
     * Pesquisa pelo nome do país, retornado seu ID
     *
     * @param string $pais
     * @return void
     */
    public function pesquisarPais($pais)
    {
        try {
            $paisDTO = new PaisDTO();
            $paisDTO->setBolExclusaoLogica(false);
            $paisDTO->setStrNome("%$pais%", InfraDTO::$OPER_LIKE);
            $paisDTO->retNumIdPais();
            $paisBD  = new PaisBD($this->getObjInfraIBanco());
            $pais = $paisBD->listar($paisDTO);
            return $pais[0]->getNumIdPais();
        } catch (Exception $e) {
            throw new InfraException('Erro ao pesquisar país.', $e);
        }
    }

    /**
     * Pesquisa pela sigla do UF, retornando ID da UF
     *
     * @param string $siglaUf
     * @return void
     */
    private function pesquisarUf($siglaUf)
    {
        try {
            $estado = $this->getObjInfraIBanco()->consultarSql('
                    SELECT  
                    uf.id_uf AS iduf
                    FROM uf uf
                    WHERE uf.sigla LIKE "%'.$siglaUf.'%";
                ');
    
            return $estado[0];
        } catch (Exception $e) {
            throw new InfraException('Erro ao pesquisar UF.', $e);
        }
    }

    /**
     * Converte valores do token de Cidade e UF para IDs do Banco de Dados do SEI
     *
     * @param string $nomeCidade
     * @param string $siglaUf
     * @return void
     */
    public function convertDadoTokenSei($nomeCidade, $siglaUf)
    {
        try {
            $dados = $siglaUf && $nomeCidade ? 
            $this->pesquisarCidadeUf($nomeCidade, $siglaUf) :
            array( array('iduf' => "", 'idcidade' => "") );

            if (!$dados[0]['iduf'] && $siglaUf) {
                $idUf = $this->pesquisarUf($siglaUf);
                $dados = array(
                    'iduf' => $idUf['iduf'],
                    'idcidade' => ""
                );
                return $dados;
            }

            return $dados[0];
        } catch (Exception $e) {
            throw new InfraException('Erro ao pesquisar usuário.', $e);
        }
    }

    /**
     * Compara se a senha digitada do usuário externo é válida
     *
     * @param string $passDigitada
     * @param string $passSystem
     * @return void
     */
    public function validaUser($passDigitada, $passSystem)
    {
        $bcrypt = new InfraBcrypt();
        if (!$bcrypt->verificar($passDigitada, $passSystem)) {
            echo "<script type='text/javascript'>
            alert('Senha inválida.');
            location.href = '" . SessaoSEIExterna::getInstance()->assinarLink('controlador_externo.php?acao=usuario_loginunico_associar&id_orgao_acesso_externo=' . SessaoSEIExterna::getInstance()->getAtributo('ID_ORGAO_USUARIO_EXTERNO')) ."';
            </script>";
        }else{
            echo "<script>location.href = '" . SessaoSEIExterna::getInstance()->assinarLink('controlador_externo.php?acao=usuario_loginunico_atualizar&id_orgao_acesso_externo=' . SessaoSEIExterna::getInstance()->getAtributo('ID_ORGAO_USUARIO_EXTERNO')) ."';</script>";
        }
    }
}
