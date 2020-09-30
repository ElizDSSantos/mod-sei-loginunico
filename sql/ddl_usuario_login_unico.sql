-- Tabela de controle de usuários que possuem cadastro SEI 
-- já atualizados pelo acesso.gov.br
CREATE TABLE IF NOT EXISTS `usuario_login_unico` (
  `id_usuario_login_unico` INT(11) NOT NULL,
  `id_usuario` INT(11) NOT NULL,
	`cpf` BIGINT(20) NOT NULL,
  `dth_atualizacao` DATETIME,
  PRIMARY KEY (`id_usuario_login_unico`)
);

-- Criando FK com a tabela usuario
ALTER TABLE
	usuario_login_unico 
	ADD CONSTRAINT fk_usuario_login_unico
		FOREIGN KEY (id_usuario) 
		REFERENCES usuario (id_usuario);

-- Criando a tabela que armazena as sequencias de ID
CREATE TABLE IF NOT EXISTS seq_usuario_login_unico(
	id int not null AUTO_INCREMENT PRIMARY KEY,
	campo char(1)
);

-- Insere uma instancia de E-mail do sistema (para ser enviado quando um usuário
-- compativel com a regra dos selos for cadastrado)
SET @ad = (SELECT MAX(id_email_sistema) + 1 FROM email_sistema); 
SET @descricao = 'Login Único - Usuário Habilitado';
SET @de = '@sigla_sistema@ <@email_sistema@>';
SET @para = '@email_usuario_externo@';
SET @assunto = '@sigla_sistema@-@sigla_orgao@ - Cadastro de Usuário Externo';
SET @conteudo = ':: Este é um e-mail automático ::

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
SET @ativo = 'S';
SET @modulo = 'MD_LOGINUNICO_CADASTRO_USUARIO';
SET @ins = CONCAT('INSERT INTO email_sistema (id_email_sistema, descricao, de, para, assunto, conteudo, sin_ativo, id_email_sistema_modulo)	VALUES (',@ad,', "',@descricao,'", "', @de,'", "',@para,'", "',@assunto,'", "',@conteudo,'", "',@ativo,'", "',@modulo,'")');
PREPARE stmt1 FROM @ins;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;