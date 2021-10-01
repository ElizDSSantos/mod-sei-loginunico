# Modulo Login Unico GovBR

O módulo **LoginUnico** é o responsável por integrar o Sistema Eletrônico de Informações - SEI à plataforma de login do Governo Federal (GovBr), no caso dos usuários externos.

## O REPOSITÓRIO

Este repositório no GitHub é o local oficial onde será mantido todo o desenvolvimento do módulo de Login Único. Além do código-fonte, também pode ser encontrado o pacote de distribuição para instalação do SEI, questões ou problema em aberto e planejamento de novas versões.


## DOWNLOAD

O download do pacote de instalação/atualização do mod-sei-pen pode ser encontrado na seção Releases deste projeto no GitHub. 


## DOCUMENTAÇÃO

Para instalação deste módulo seguir os passos abaixo:

### Pré-requisitos
 - **SEI versão 4.0.3 ou superior instalada**;
 - Usuário de acesso ao banco de dados do SEI e SIP com permissões para criar novas estruturas no banco de dados
 
 - **Fazer o Cadastro do Órgão junto ao Login Único**;
 - Como o fluxo utilizado pelo módulo é o OAUTH, é necessário que os sistemas se cadastrem junto ao serviço do GovBr para receber o ClientID e a sua senha em homologação. O link com as informações é o https://manual-roteiro-integracao-login-unico.servicos.gov.br/pt/stable/solicitarconfiguracao.html
 - Deve mencionar no Plano de Integração que necessita também de um clientID e secret para os serviço de **REVALIDAÇÃO DE SENHA**. Para continuar com a instalação, deverá possuir ao final desta etapa as seguintes informações: client_id,secret,redirect_url,url_logout,client_id_validacao,secret_validacao
 - Após os testes em homologação, solicitar as chaves para produção.


 ### Procedimentos:

### 1.1 Fazer backup dos bancos de dados do SEI, SIP e dos arquivos de configuração do sistema.

Todos os procedimentos de manutenção do sistema devem ser precedidos de backup completo de todo o sistema a fim de possibilitar a sua recuperação em caso de falha. A rotina de instalação descrita abaixo atualiza tanto o banco de dados, como os arquivos pré-instalados do módulo e, por isto, todas estas informações precisam ser resguardadas.

### 1.2. Baixar o arquivo de distribuição do **mod-loginUnico**

Necessário realizar o _download_ do pacote de distribuição do módulo neste projeto, na aba de releases.


### 1.3. Descompactar o pacote de instalação e atualizar os arquivos do sistema

Para implantação do Módulo Login Único, é necessário que o diretório “loginunico”, seja copiado para o diretório sei/web/modulos do servidor de produção.

### 1.4.  Habilitar módulo **mod-loginUnico** no arquivo de configuração do SEI

Esta etapa é padrão para a instalação de qualquer módulo no SEI para que ele possa ser carregado junto com o sistema. Será necessário editar o arquivo **sei/config/ConfiguracaoSEI.php** para adicionar a referência ao módulo (elemento “LoginUnico”):

##### Campos necessários:

Os campos marcados com * devem ser ajustados pelo órgao, deixando os demais com os valores mencionados abaixo.

-  **client_id****: Chave de acesso, que identifica o serviço consumidor fornecido pelo Login Único para a aplicação cadastrada

-  **secret****: Senha de acesso do serviço consumidor para autenticação no OAuth.

-  url_provider: URL de acesso ao SSO.

-  **redirect_url****: URI de retorno cadastrada para a aplicação cliente no formato URL Encode. Este parâmetro não pode conter caracteres especiais conforme consta na especificação auth 2.0 Redirection Endpoint.

-  **url_logout****:URI de retorno para o logout do usuário.

-  scope: Especifica os recursos que o serviço consumidor quer obter. Um ou mais escopos inseridos para a aplicação cadastrada. Informação a ser preenchida por padrão: openid+email+phone+profile+govbr_empresa+govbr_confiabilidades.

-  url_servico: URL de acesso a API do GovBr.

-  url_revalidacao: URL para revalidação de senha, utilizada no serviço de assinatura

-  **client_id_validacao****: client ID para revalidação de senha, utilizada no serviço de assinatura

-  **secret_validacao****: secret para revalidação de senha, utilizada no serviço de assinatura

-  niveis_confiabilidade: Array de níveis, existentes no <acesso.gov.br>, considerados confiaveis pelo órgão, que serão recuperados pelo SEI, para comparação, que permitirá a liberação da autenticação no SEI, via Login Único. O módulo não permite uso por usuário de perfil Bronze(Nível 1). 

-  **orgao****: Identificador do órgão que está instalando o Login Único no SEI (ID do banco de dados SEI).


Abaixo encontra-se o trecho de código de configuração do módulo Login Único, no ambiente de Homologação:



```php
'LoginUnico' => array(
                'client_id' => 'XXXXXXXX',
                'secret'    => 'XXXXXXXX',
                'url_provider' => 'https://sso.staging.acesso.gov.br/',
                'redirect_url'  => 'XXXXXXXXX',
                'url_logout' =>    'XXXXXXXXX',
                'scope'  => 'openid+email+phone+profile+govbr_empresa+govbr_confiabilidades',
                'url_servicos'   => 'https://api.staging.acesso.gov.br/',
                'url_revalidacao'   => 'https://oauth.staging.acesso.gov.br/v1/',
                'client_id_validacao'   => 'XXXXXXXXXXX',
                'secret_validacao'   => 'XXXXXXXXXXXX',
                'niveis_confiabilidade'  => array(2,3),
                'orgao'                          => XXXXXXX
            )    ,
```

Adicionar a referência ao módulo PEN na array da chave 'Modulos' indicada abaixo:

```php
'Modulos' => array("LoginUnicoIntegracao" => "loginunico")
```



### 1.5. Atualizar a base de dados do SEI com as tabelas do **loginUnico**

Nesta etapa é instalado/atualizado as tabelas de banco de dados vinculadas do **loginUnico**. 

Caso queira atualizar o email padrão que será enviado aos usuários, poderá atualizar no script de instalação(/rn/MdLoginUnicoInstalacaoRN.php) as seguintes variaveis:

-  @descricao- Descreve sobre o que se trata o e-mail cadastrado. Pode ser mudado conforme necessidade do órgão.

-  @assunto- Assunto do e-mail a ser enviado ao usuário. Atualmente retorna a sigla do sistema, hífen, sigla do órgão (@sigla_sistema@-@sigla_orgao@) e o título “Cadastro de Usuário Externo”. As variáveis entre arrobas, não devem ser alteradas, podendo apenas ser mudada sua posição no texto.

-  @conteudo- preenchido com o corpo do texto do e-mail a ser enviado para o usuário. Pode ser alterado conforme necessidade do órgão. Dentro do texto padrão, atualmente preenchido no script, existem alguns parâmetros que buscam dados do sistema. Essas variáveis, encontradas entre arrobas, não devem ser alteradas, podendo apenas ser mudada a posição no texto.

-  @nome_usuario_externo@: Nome do usuário para o qual o e-mail está sendo enviado. Buscado direto do sistema no momento do envio.

-  @sigla_orgao@: sigla do órgão em que o módulo login único está instalado. Buscado direto do sistema no momento do envio.

-  @link_login_usuario_externo@: Link de acesso para o SEI externo. Buscado direto do sistema no momento do envio.

-  @descricao_orgao@: Descrição do órgão atual, em que o módulo Login Único está instalado. Buscado direto do sistema no momento do envio.

O script de atualização da base de dados do SIP fica localizado em ```<DIRETÓRIO RAIZ DE INSTALAÇÃO DO SEI E SIP>/sei/web/modulos/loginUnico/script/scriptInstalacao.php``` E deve ser executado conforme abaixo

```bash
$ php -c /etc/php.ini <DIRETÓRIO RAIZ DE INSTALAÇÃO DO SEI E SIP>/sei/web/modulos/loginUnico/script/scriptInstalacao.php
```

### 1.6. Funcionalidades do Módulo Login Único

#### 1.6.1 Login de Usuário Externo

Ao acessar o link `<URL SEI>/sei/controlador_externo.php?acao=usuario_externo_logar&id_orgao_acesso_externo=0` iremos encontrar o botão de acesso externo com govBr. Assim, quando um usuário externo clicar neste botão pela primeira vez, será direcionado ao govBR para autenticação e autorização do compartilhamento de dados do SEI do órgão.
Ao retornao ao SEI, irá completar os dados de cadastro do SEI e criar uma senha local (como contingência para caso o govBR estiver fora do ar). 
Nos casos que o usuário já era cadastrado no SEI, ao realizar os passos acima, seu usuário antigo será vinculado ao serviço do govBR.

#### 1.6.2 Assinatura de Documentos

Após se cadastrar no serviço, ao assinar um documento, irá receber a opção de utilizar a senha do govBR ou a senha local do SEI.

