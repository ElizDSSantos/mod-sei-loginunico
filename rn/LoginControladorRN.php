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
    private $niveis_confiabilidade;
    private $url_revalidacao;
    private $client_id_validacao;
    private $secret_validacao;
    private $nivel_minimo_confiabilidade;
    private $nomeModulo;

    protected function inicializarObjInfraIBanco()
    {
        return BancoSEI::getInstance();
    }

    public function __construct()
    {
        $conf = new ConfiguracaoSEI();
        $this->client_id             =  $conf->getArrConfiguracoes()['LoginUnico']['client_id'];
        $this->secret                =  $conf->getArrConfiguracoes()['LoginUnico']['secret'];
        $this->url_provider          =  $conf->getArrConfiguracoes()['LoginUnico']['url_provider'];
        $this->redirect_uri          =  $conf->getArrConfiguracoes()['LoginUnico']['redirect_url'];
        $this->scope                 =  $conf->getArrConfiguracoes()['LoginUnico']['scope'];
        $this->url_servico           =  $conf->getArrConfiguracoes()['LoginUnico']['url_servicos'];
        $this->url_revalidacao       =  $conf->getArrConfiguracoes()['LoginUnico']['url_revalidacao'];
        $this->niveis_confiabilidade =  $conf->getArrConfiguracoes()['LoginUnico']['niveis_confiabilidade'];
        $this->id_orgao              =  $conf->getArrConfiguracoes()['LoginUnico']['orgao'];
        $this->client_id_validacao   =  $conf->getArrConfiguracoes()['LoginUnico']['client_id_validacao'];
        $this->secret_validacao      =  $conf->getArrConfiguracoes()['LoginUnico']['secret_validacao'];
        $this->nivel_minimo_confiabilidade = 1;
        $this->nomeModulo            =  "loginUnico";
    }

    public function getNomeModulo(){
        return $this->nomeModulo;
    }


     /**
      * Gerar URL para envio ao GovBr
      *
      * @return void
      */
    public function gerarURL($bolRevalidacao=false,$state=null)
    {
        $urlServico=$this->url_provider;
        $scope=$this->scope;
        if($state==null)$state=$this->getRandomHex(12);
        
        $client_id=$this->client_id;
        if($bolRevalidacao){
            $urlServico=$this->url_revalidacao ;
            $scope="password-validation";
            $client_id=$this->client_id_validacao;
        }
        $uri = $urlServico ."/authorize"
            ."?response_type=code"
            ."&client_id=". $client_id
            ."&scope=".$scope
            ."&redirect_uri=".urlencode($this->redirect_uri)
            ."&state=".$state;


        $uri.= $bolRevalidacao?"":"&nonce=".$this->getRandomHex();
            
        return $uri;
    }

     /**
      * Undocumented function
      *
      * @param integer $num_bytes
      * @return void
      */
    public function getRandomHex($num_bytes=4)
    {
        return bin2hex(openssl_random_pseudo_bytes($num_bytes));
    }

     /**
      * Gera o access_token, token_type, expires_in, scope, id_token 
      *
      * @param [type] $dados
      * @return void
      */
    public function gerarAccessToken($dados,$revalidacao=false)
    {
        $campos = array(
            'grant_type' => urlencode('authorization_code'),
            'code' => urlencode($dados['code']),
            'redirect_uri' => urlencode($this->redirect_uri)
        );

        if($revalidacao){
            $campos['client_id']=$this->client_id_validacao;
            $url_token=$this->url_revalidacao;
            $authBase64=$this->client_id_validacao .":". $this->secret_validacao;
        }else{
            $url_token=$this->url_provider;
            $authBase64=$this->client_id.":".$this->secret;
        }

        $fields_string = '';
        foreach ($campos as $key=>$value) {
            $fields_string .= $key.'='.$value.'&';
        }

        $fields_string=rtrim($fields_string, '&');
        $ch_token = curl_init();
        curl_setopt($ch_token, CURLOPT_URL, $url_token . "/token");
        curl_setopt($ch_token, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch_token, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_token, CURLOPT_SSL_VERIFYPEER, true);
        $headers = array(
            'Content-Type:application/x-www-form-urlencoded',
            'Authorization: Basic '. base64_encode($authBase64)
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
    public function gerarJwk($revalidacao=false)
    {
        $url = $revalidacao?$this->url_revalidacao."/jwks" :$this->url_provider."/jwk"  ;
        $ch_jwk = curl_init();
        curl_setopt($ch_jwk, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch_jwk, CURLOPT_URL, $url);
        curl_setopt($ch_jwk, CURLOPT_RETURNTRANSFER, true);
        $json_output_jwk = json_decode(curl_exec($ch_jwk), true);
        curl_close($ch_jwk);

        return $json_output_jwk;
    }

 
    
    protected function validarTokenAssinaturaControlado($dados){

        try{

            $doGet=$dados['doGet'];
            $cpfUsuarioSEI=$dados['usuario']->getDblCpfContato();
            $dataHora=$dados['dataHora'];

            $timestamp=time();
            $tokenRevalidacao=$this->revalidarAssinatura($doGet);
            $tokenLoginUnico=SessaoSEIExterna::getInstance()->getAtributo('MD_LOGIN_UNICO_TOKEN');
            
            if($timestamp >= $tokenRevalidacao['exp']){
                throw new InfraException('Token de Revalidação GovBR Expirado, execute a assinatura novamente');
            }

            $timestampBD=(InfraData::getTimestamp($dataHora) + 23 ) ;

            if($timestampBD < $timestamp ){
                throw new InfraException('Validade da assinatura expirou, tentar novamente');
            }

            if($tokenRevalidacao['sub'] != $tokenLoginUnico['sub'] || $tokenRevalidacao['sub'] !=$cpfUsuarioSEI  ){
                //SessaoSEIExterna::getInstance()->removerDadosSessao();
                // echo "<script>
                // window.opener.location.reload();
                // </script>";
                throw new InfraException("Usuário tentando assinar é diferente do usuário logado, 
                validacao=".$tokenRevalidacao['sub'] .", e os dados do govBR são loginUnico=".$tokenLoginUnico['sub'] .
                 ", nome=" . $tokenLoginUnico['name'] . ", email=" . $tokenLoginUnico['email'] . " usuario da assinatura=" . $cpfUsuarioSEI  );
                
            }else{
                SessaoSEI::getInstance()->validarAuditarPermissao('documento_assinar',__METHOD__,$tokenLoginUnico);
                return true;
            }
         

        }catch(Exception $e){
            
            throw new InfraException("Erro ao validar token de revalidação GovBr. Caso tenha logado pela senha interna do SEI, favor logar novamente pelo govbr",$e);

        }

        
    }

    protected function validarTokenAssinaturaInternaControlado($dados){

        
        try{
            $token=$this->revalidarAssinatura($dados["_GET"],false);
            $cpfUsuarioSEI=$dados['usuario']->getDblCpfContato();
            $timestamp=time();
            if($timestamp >= $token['exp']){
                throw new InfraException('Token do GovBR Expirado, execute a assinatura novamente');
            }

            $timestampBD=(InfraData::getTimestamp($dados['dataHora']) + 23 ) ;

            if($timestampBD < $timestamp ){
                throw new InfraException('Validade da assinatura expirou, tentar novamente');
            }

            if($token['sub'] != $cpfUsuarioSEI){
                //SessaoSEI::getInstance()->removerDadosSessao();
                echo "<script>
                window.opener.location.reload();
                </script>";
                throw new InfraException("Usuário tentando assinar é diferente do usuário logado no govBR:
                usuarioSEI=".$cpfUsuarioSEI ." e usuario do govBR =".$token['sub'] );
                
            }else{
                SessaoSEI::getInstance()->validarAuditarPermissao('documento_assinar',__METHOD__,$token);
                return $token;
            }
         

        }catch(Exception $e){
            
            throw new InfraException("Erro ao validar token de revalidação GovBr",$e);
        }

    }

    /**
     * Realiza revalidação da senha do usuário com os dados vindos do GovBr
     *
     * @param array $dados
     * @return void
     */
    public function revalidarAssinatura($dados,$bolExterno=true)
    {
        try {
            $json_output_tokens = $this->gerarAccessToken($dados, $bolExterno);
            $json_output_jwk = $this->gerarJwk($bolExterno);
            $access_token = $json_output_tokens['access_token'];
            return $this->processToClaims($access_token, $json_output_jwk);
        } catch (Exception $e) {
            throw new InfraException("Erro ao revalidar assinatura no GovBr, tente novamente", $e);
        }
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
        if (isset($CODE)) {
            $json_output_tokens = $this->gerarAccessToken($dados);
            $json_output_jwk = $this->gerarJwk();
            $access_token = $json_output_tokens['access_token'];
            $id_token = $json_output_tokens['id_token'];
            try {
                $json_output_payload_id_token = $this->processToClaims($id_token, $json_output_jwk);
                $cpf = $json_output_payload_id_token['sub'];
                SessaoSEIExterna::getInstance()->setAtributo('MD_LOGIN_UNICO_TOKEN', $json_output_payload_id_token);
                $niveis = $this->obterNiveis($access_token, $cpf);
                $dadosReceita = $this->obterDadosReceita($access_token);
                SessaoSEIExterna::getInstance()->setAtributo('MD_LOGIN_UNICO_TOKEN_ENDERECO', $dadosReceita);
                $getDadosUser = $this->pesquisarUsuario($json_output_payload_id_token);
                $userSei = $getDadosUser['user'];
                $atualizarUser = $getDadosUser['update'];
                $sinNivel = $this->checkUserHasNivel($niveis);
                SessaoSEIExterna::getInstance()->setAtributo('MD_LOGIN_UNICO_SIN_NIVEL', $sinNivel);
                $associar = false;
                $duplicidade = false;
                if($userSei && $atualizarUser && $userSei->getStrSenha()!=null && ($userSei->getDblCpfContato() == $json_output_payload_id_token['sub'])){
                    $associar = true;
                }else if($userSei && $atualizarUser && $userSei->getStrSenha()!=null && ($userSei->getDblCpfContato() != $json_output_payload_id_token['sub'])){
                    $duplicidade = true;
                }
                if (!$userSei) {
                    $this->cadastraUsuario($json_output_payload_id_token, $sinNivel);
                    $getDadosUser = $this->pesquisarUsuario($json_output_payload_id_token);
                    $userSei = $getDadosUser['user'];
                    $atualizarUser = $getDadosUser['update'];
                }
                
            } catch (Exception $e) {
                throw new InfraException("Erro ao autenticar pelo Acesso.gov.br, tente novamente", $e);
            }
        } else {
            // $getDadosUser = $this->pesquisarUsuario(SessaoSEIExterna::getInstance()->getAtributo('MD_LOGIN_UNICO_TOKEN'));
            // $userSei = $getDadosUser['user'];
            // $atualizarUser = $getDadosUser['update'];
            // $timestamp=time();
            // if($timestamp >= $getDadosUser['exp']){
            //     throw new InfraException('Token do GovBR Expirado, execute a assinatura novamente');
            // }
            throw new InfraException('Realize o login novamente');
        }

        $dados = [
            "user" => $userSei,
            "atualizarUser" => $atualizarUser,
            "associar" => $associar,
            "duplicidade" => $duplicidade
        ];
        $this->login($dados);      
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
    protected function loginControlado($dados)
    {
        try {
            $user=$dados["user"];
            $atualizar=$dados["atualizarUser"];
            $associar=$dados["associar"];
            $duplicidade=$dados["duplicidade"];
            
            SessaoSEIExterna::getInstance()->configurarAcessoExterno(null);

            
            $_SESSION['EXTERNO_TOKEN'] = md5(uniqid(mt_rand()));
            $seqUserLogin = $this->getObjInfraIBanco()->getValorSequencia('md_login_unico_seq_usuario');
            SessaoSEIExterna::getInstance()->setAtributo('ID_USUARIO_EXTERNO', $user->getNumIdUsuario());
            SessaoSEIExterna::getInstance()->setAtributo('ID_USUARIO_LOGIN', $seqUserLogin);
            SessaoSEIExterna::getInstance()->setAtributo('SIGLA_USUARIO_EXTERNO', $user->getStrSigla());
            SessaoSEIExterna::getInstance()->setAtributo('NOME_USUARIO_EXTERNO', $user->getStrNome());
            SessaoSEIExterna::getInstance()->setAtributo('ID_ORGAO_USUARIO_EXTERNO', $user->getNumIdOrgao());
            SessaoSEIExterna::getInstance()->setAtributo('SIGLA_ORGAO_USUARIO_EXTERNO', $user->getStrSiglaOrgao());
            SessaoSEIExterna::getInstance()->setAtributo('DESCRICAO_ORGAO_USUARIO_EXTERNO', $user->getStrDescricaoOrgao());
            SessaoSEIExterna::getInstance()->setAtributo('ID_CONTATO_USUARIO_EXTERNO', $user->getNumIdContato());
            SessaoSEIExterna::getInstance()->setAtributo('RAND_USUARIO_EXTERNO', uniqid(mt_rand(), true));
            SessaoSEIExterna::getInstance()->setAtributo('LOGIN_GOV_BR', true);

            $objUsuarioDTOAuditoria = clone($user);

            AuditoriaSEI::getInstance()->auditar('usuario_externo_logar', __METHOD__, $objUsuarioDTOAuditoria);

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
            if($erro){
                $objInfraException = new InfraException();
                $objInfraException->lancarValidacao('Usuário ainda não foi liberado.');
            }

        } catch (Exception $e) {
            throw new InfraException('Erro ao realizar login (govbr).', $e);
        }
    }

     /**
      * Verifica se o usuário possui algum dos niveis requeridos pelo ÓRGAO
      *
      * @param array $niveis
      * @return void
      */
    public function checkUserHasNivel($niveis)
    {
        foreach ($niveis as $nivelUser) {
            //if (in_array($selosUser['nivel'], $this->selo_confianca)) {
            if (in_array($nivelUser['id'], $this->niveis_confiabilidade) && $nivelUser['id']>$this->nivel_minimo_confiabilidade ) {
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
      * Obter niveis de confiabilidade
      *
      * @param array $access_token
      * @return void
      */
    public function obterNiveis($access_token, $cpf)
    {
        // $url = $this->url_servico . "/api/info/usuario/selo";
        $url = $this->url_servico . "confiabilidades/v3/contas/$cpf/niveis?response-type=ids";
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
     * @param boolean $sinNivel
     * @return void
     */
    private function cadastraUsuario($token, $sinNivel)
    {
        try {

            $seqContato = $this->getObjInfraIBanco()->getValorSequencia('seq_contato');
            $sinAtivo = $sinNivel ? 'S' : 'N';
            
            $data_cadastro = InfraData::getStrDataHoraAtual();


            $objContatoDTO=new ContatoDTO();
            $objContatoDTO->setNumIdContato($seqContato);
            $objContatoDTO->setStrNome(utf8_decode($token['name']));
            $objContatoDTO->setStrNomeRegistroCivil(utf8_decode($token['name']));
            $objContatoDTO->setDblCpf($token['sub']);
            $objContatoDTO->setStrEmail($token['email']);
            $objContatoDTO->setStrSinAtivo($sinAtivo);
            $objContatoDTO->setStrSigla($token['email']);
            $objContatoDTO->setStrStaNatureza("F");
            $objContatoDTO->setStrTelefoneCelular($token['phone_number']);
            $objContatoDTO->setNumIdContatoAssociado(1);
            $objContatoDTO->setNumIdTipoContato(3);
            $objContatoDTO->setStrSinEnderecoAssociado("N");
            $objContatoDTO->setDthCadastro($data_cadastro);
           
            $objContatoBD = new ContatoBD(BancoSEI::getInstance());
            $objContatoBD->cadastrar($objContatoDTO);



            $objInfraSequencia = new InfraSequencia(BancoSEI::getInstance());
            $seqUsuario = $objInfraSequencia->obterProximaSequencia('usuario_sistema');

           


            $objUsuarioDTO=new UsuarioDTO();
            $objUsuarioDTO->setNumIdUsuario($seqUsuario);
            $objUsuarioDTO->setStrSinAtivo($sinAtivo);
            $objUsuarioDTO->setStrSigla($token['email']);
            $objUsuarioDTO->setStrNome(utf8_decode($token['name']));
            $objUsuarioDTO->setStrNomeRegistroCivil(utf8_decode($token['name']));
            $objUsuarioDTO->setNumIdContato($seqContato);
            $objUsuarioDTO->setNumIdOrgao(0);
            $objUsuarioDTO->setStrStaTipo(UsuarioRN::$TU_EXTERNO);
            $objUsuarioDTO->setStrSinAcessibilidade("N");

            $objUsuarioBD = new UsuarioBD(BancoSEI::getInstance());
            $objUsuarioBD->cadastrar($objUsuarioDTO);





        } catch (Exception $e) {
                
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
                $user = $usuarioDB->consultar($usuarioDTO);
                $update = true;
                if(!$user){
                    $usuarioDTO = new UsuarioDTO();
                    $usuarioDTO->setDblCpfContato($token['sub']);
                    $usuarioDTO->retTodos(true);
                    $user = $usuarioDB->consultar($usuarioDTO);

                }
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
    protected function pesquisarPaisConectado($pais)
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
    protected function convertDadoTokenSeiConectado($dadosConversao)
    {
        try {
            $nomeCidade=$dadosConversao['municipio'];
            $siglaUf=$dadosConversao['uf'];
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

    protected function pesquisaUsuarioLoginUnicoControlado($idUser){

        $objLoginUnicoDTO=new LoginUnicoDTO();
        $objLoginUnicoDTO->setNumIdUsuario($idUser);
        $objLoginUnicoDTO->retTodos();

        $objLoginUnicoBD  = new LoginUnicoBD(BancoSEI::getInstance());
        $usuario = $objLoginUnicoBD->consultar($objLoginUnicoDTO);
        if($usuario!=null){
            return true;        
        }
        return false;
    }

    protected function getIdUsuarioLoginUnicoControlado(UsuarioAPI $objUsuarioAPI){

        $email=$objUsuarioAPI->getSigla();
        $objLoginUnicoDTO=new LoginUnicoDTO();
        $objLoginUnicoDTO->setStrEmail($email);
        $objLoginUnicoDTO->retTodos();

        $objLoginUnicoBD  = new LoginUnicoBD(BancoSEI::getInstance());
        $usuario = $objLoginUnicoBD->consultar($objLoginUnicoDTO);
        return $usuario;

    }

    private function geraScriptFechamento($strAcaoRetorno){


        switch($strAcaoRetorno)
        {
            case 'assinaturaPadrao':

                return "<script>
                        // let ifrm=window.opener.parent.document.getElementById('ifrArvore');
                        // ifrm.src=ifrm.src;
                        // window.opener.location.reload();
                        window.close();
                        </script>";

                break;

            case 'bloco_navegar':

                return "<script>
                        // window.opener.processarDocumento(window.opener.posAtual);
                        // window.opener.objAjaxAssinaturas.executar();
                        // window.opener.parent.parent.location.reload();
                        window.opener.parent.location.reload();
                        window.close();
                        </script>";

                break;

            case 'editor_montar':

                return "<script>
                        // window.opener.atualizarArvore(true);
                        // window.parent.atualizarArvore(true);
                        window.opener.parent.atualizarArvore(true);
                        window.close();
                        </script>";

                break;
            
            default:

                return "<script>
                        // window.opener.location.reload();
                        window.close();
                        </script>";

                break;

        }


    }

    protected function retornaOperacaoLoginUnicoControlado($state){

        try{

            $objAssinaturaLoginUnicoDTO=new AssinaturaLoginUnicoDTO();
            $objAssinaturaLoginUnicoDTO->setStrStateLoginUnico($state);
            $objAssinaturaLoginUnicoDTO->retStrOperacao();
            
            $objLoginUnicoBD = new LoginUnicoBD(BancoSEI::getInstance());
            $objAssinaturaLoginUnicoDTO=$objLoginUnicoBD->consultar($objAssinaturaLoginUnicoDTO);

            
        }catch(Exception $e){}
        
        if($objAssinaturaLoginUnicoDTO==null)return null;

        return $objAssinaturaLoginUnicoDTO->getStrOperacao();
    }

    protected function gravarAgrupadorControlado($objAssinaturaLoginUnicoDTO){

        $objLoginUnicoBD = new LoginUnicoBD(BancoSEI::getInstance());
        $objLoginUnicoBD->cadastrar($objAssinaturaLoginUnicoDTO);
        return;

    }


    protected function assinaturaLoginUnicoControlado($escopo){


        try{ 

        if($escopo=="externo"){

        SessaoSEIExterna::getInstance();

        $objAssinaturaLoginUnicoDTO=new AssinaturaLoginUnicoDTO();
        $objAssinaturaLoginUnicoDTO->setStrStateLoginUnico($_GET['state']);
        $objAssinaturaLoginUnicoDTO->retTodos();

        $objLoginUnicoBD = new LoginUnicoBD(BancoSEI::getInstance());
        $objAssinaturaLoginUnicoDTO=$objLoginUnicoBD->consultar($objAssinaturaLoginUnicoDTO);

        $objAssinaturaRN=new AssinaturaRN();
        $paramAssinaturaDTO = new AssinaturaDTO();
        $paramAssinaturaDTO->setStrAgrupador($objAssinaturaLoginUnicoDTO->getStrAgrupador());
        $paramAssinaturaDTO->retTodos();
        $paramAssinaturaDTO->retStrProtocoloDocumentoFormatado();
        $paramAssinaturaDTO->retDblIdProcedimentoDocumento();
        $paramAssinaturaDTO->setBolExclusaoLogica(false);
        $paramAssinaturaDTO->retStrSiglaUsuario();
        $paramAssinaturaDTO->setStrSinAtivo('N');
        $paramAssinaturaDTO = $objAssinaturaRN->consultarRN1322($paramAssinaturaDTO);

        $emailValidar=$paramAssinaturaDTO->getStrSiglaUsuario();

        $objLoginUnicoDTO=new LoginUnicoDTO();
        $objLoginUnicoDTO->setStrEmail($emailValidar);
        $objLoginUnicoDTO->retTodos();

        $objLoginUnicoBD  = new LoginUnicoBD(BancoSEI::getInstance());
        $usuario = $objLoginUnicoBD->consultar($objLoginUnicoDTO);

        $dataHora=$objAssinaturaLoginUnicoDTO->getDthDataAtualizacao();

        if($paramAssinaturaDTO->getDblCpf() !=  $usuario->getDblCpfContato() ){
            throw new InfraException('Erro ao validar CPF do assinante, tentar novamente');
        }

        $dados=[
            "doGet"=>$_GET,
            "usuario"=>$usuario,
            "dataHora"=>$dataHora
          ];

        $validacao = $this->validarTokenAssinatura($dados);

        if($validacao){          

            $stringIdDocumentos=$paramAssinaturaDTO->getDblIdDocumento();          

            if($objAssinaturaLoginUnicoDTO->getStrIdDocumentos() != $stringIdDocumentos){
                throw new InfraException('Erro ao obter id dos documentos do agrupador, tentar novamente');
            }

            if($paramAssinaturaDTO==null){
                throw new InfraException('Erro ao obter assinaturas do agrupador, tentar novamente');
            }

            $objConfiguracoesAssinatura = new ConfiguracoesAssinaturaAPI();
            $objConfiguracoesAssinatura->setNomeModulo($this->nomeModulo);
            $objConfiguracoesAssinatura->setBolValidacaoCertificado(false);
            $objConfiguracoesAssinatura->setBolTipoAssinaturaCertificado(false);

            $objDocumentoRN=new DocumentoRN();
            $objAssinaturaDTO = new AssinaturaDTO();
            $objAssinaturaDTO->setNumIdAssinatura($paramAssinaturaDTO->getNumIdAssinatura());
            $objAssinaturaDTO->setStrP7sBase64(null);				
            $objAssinaturaDTO->setBolAssinaturaModulo(true);	
            $objAssinaturaDTO->setObjConfiguracoesAssinaturaAPI($objConfiguracoesAssinatura);				
            $objDocumentoRN->confirmarAssinatura($objAssinaturaDTO);	
        	
        }

        LoginUnicoINT::excluirDadosState($_GET['state']);
        $strScriptFechamento=$this->geraScriptFechamento("default");
        echo $strScriptFechamento;
        return $validacao;


        }elseif($escopo=="interno"){

            $objAssinaturaLoginUnicoDTO=new AssinaturaLoginUnicoDTO();
            $objAssinaturaLoginUnicoDTO->setStrStateLoginUnico($_GET['state']);
            $objAssinaturaLoginUnicoDTO->retTodos();

            $objLoginUnicoBD = new LoginUnicoBD(BancoSEI::getInstance());
            $objAssinaturaLoginUnicoDTO=$objLoginUnicoBD->consultar($objAssinaturaLoginUnicoDTO);

            $acaoOrigem=$objAssinaturaLoginUnicoDTO->getStrAcaoOrigem();

            $objAssinaturaDTO = new AssinaturaDTO();
            $objAssinaturaRN=new AssinaturaRN();

            $objAssinaturaDTO->setStrAgrupador($objAssinaturaLoginUnicoDTO->getStrAgrupador());
            $objAssinaturaDTO->retTodos();
            $objAssinaturaDTO->setOrdDblIdDocumento(InfraDTO::$TIPO_ORDENACAO_ASC);
            $objAssinaturaDTO->retStrProtocoloDocumentoFormatado();
            $objAssinaturaDTO->retDblIdProcedimentoDocumento();
            $objAssinaturaDTO->retStrSiglaUsuario();
            $objAssinaturaDTO->setBolExclusaoLogica(false);
            $objAssinaturaDTO->setStrSinAtivo('N');
            $arrObjAssinaturaDTO = $objAssinaturaRN->listarRN1323($objAssinaturaDTO);

            $stringIdDocumentos='';
            foreach($arrObjAssinaturaDTO as $paramAssinaturaDTO){  
                if($stringIdDocumentos==''){
                    $stringIdDocumentos.=$paramAssinaturaDTO->getDblIdDocumento();
                }else{
                    $stringIdDocumentos.="," . $paramAssinaturaDTO->getDblIdDocumento();
                }
            }

            if($objAssinaturaLoginUnicoDTO->getStrIdDocumentos() != $stringIdDocumentos){
                throw new InfraException('Erro ao obter id dos documentos do agrupador, tentar novamente');
            }

            if($arrObjAssinaturaDTO==null){
                throw new InfraException('Erro ao obter assinaturas do agrupador, tentar novamente');
            }


            //email do primeiro assinante
            $emailValidar=$arrObjAssinaturaDTO[0]->getStrSiglaUsuario();

            $objLoginUnicoDTO=new LoginUnicoDTO();
            $objLoginUnicoDTO->setStrEmail($emailValidar);
            $objLoginUnicoDTO->retTodos();

            $objLoginUnicoBD  = new LoginUnicoBD(BancoSEI::getInstance());
            $usuario = $objLoginUnicoBD->consultar($objLoginUnicoDTO);

            $dataHora=$objAssinaturaLoginUnicoDTO->getDthDataAtualizacao();

            $paramGet=[
                    "_GET"=>$_GET,
                    "usuario"=>$usuario,
                    "dataHora"=>$dataHora
                ];              

            $validacao = $this->validarTokenAssinaturaInterna($paramGet);

            if($validacao){

                foreach($arrObjAssinaturaDTO as $paramAssinaturaDTO){   
                    
                    //verifica se o email continua o mesmo
                    $emailAtual=$paramAssinaturaDTO->getStrSiglaUsuario();
                    if($emailValidar!=$emailAtual){
                        throw new InfraException('Os documentos para assinatura possuem usuários GovBr diversos');
                    }
                    
                    $cpfAssinante=$paramAssinaturaDTO->getDblCpf();

                    if($cpfAssinante!=$validacao['sub']){
                        throw new InfraException('Os documentos para assinatura possuem usuários GovBr diversos');
                    }

                    $objConfiguracoesAssinatura = new ConfiguracoesAssinaturaAPI();
                    $objConfiguracoesAssinatura->setNomeModulo($this->nomeModulo);
                    $objConfiguracoesAssinatura->setBolValidacaoCertificado(false);
                    $objConfiguracoesAssinatura->setBolTipoAssinaturaCertificado(false);

                    $objDocumentoRN=new DocumentoRN();
                    $objAssinaturaDTO = new AssinaturaDTO();
                    $objAssinaturaDTO->setNumIdAssinatura($paramAssinaturaDTO->getNumIdAssinatura());
                    $objAssinaturaDTO->setStrP7sBase64(null);
                    $objAssinaturaDTO->setBolAssinaturaModulo(true);					
                    $objAssinaturaDTO->setObjConfiguracoesAssinaturaAPI($objConfiguracoesAssinatura);				
                    $objDocumentoRN->confirmarAssinatura($objAssinaturaDTO);	

                }
            }

            LoginUnicoINT::excluirDadosState($_GET['state']);
            $strScriptFechamento=$this->geraScriptFechamento($acaoOrigem);
            echo  $strScriptFechamento;
            return $validacao;



        }

    }catch(Exception $e){
            
        throw new InfraException("Erro ao assinar utilizando loginUnico",$e);

    }


    }



}
