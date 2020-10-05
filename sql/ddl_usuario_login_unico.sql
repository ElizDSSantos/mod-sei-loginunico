-- Tabela de controle de usu�rios que possuem cadastro SEI 
-- j� atualizados pelo acesso.gov.br
CREATE TABLE IF NOT EXISTS `usuario_login_unico` (
  `id_usuario_login_unico` INT(11) NOT NULL,
  `id_usuario` INT(11) NOT NULL,
  `cpf` BIGINT(20) NOT NULL,
  `rg` BIGINT(20) NOT NULL,
  `dth_atualizacao` DATETIME,
  PRIMARY KEY (`id_usuario_login_unico`)
);

-- Criando FK com a tabela usuario
ALTER TABLE
	usuario_login_unico 
	ADD CONSTRAINT fk_usuario_login_unico
		FOREIGN KEY (id_usuario) 
		REFERENCES usuario (id_usuario)
		ON DELETE CASCADE;

-- Criando a tabela que armazena as sequencias de ID
CREATE TABLE IF NOT EXISTS seq_usuario_login_unico(
	id int not null AUTO_INCREMENT PRIMARY KEY,
	campo char(1)
);

-- Insere uma instancia de E-mail do sistema (para ser enviado quando um usu�rio
-- compativel com a regra dos selos for cadastrado)
SET @ad = (SELECT MAX(id_email_sistema) + 1 FROM email_sistema); 
SET @descricao = 'Login �nico - Usu�rio Habilitado';
SET @de = '@sigla_sistema@ <@email_sistema@>';
SET @para = '@email_usuario_externo@';
SET @assunto = '@sigla_sistema@-@sigla_orgao@ - Cadastro de Usu�rio Externo';
SET @conteudo = ':: Este � um e-mail autom�tico ::

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
SET @ativo = 'S';
SET @modulo = 'MD_LOGINUNICO_CADASTRO_USUARIO';
SET @ins = CONCAT('INSERT INTO email_sistema (id_email_sistema, descricao, de, para, assunto, conteudo, sin_ativo, id_email_sistema_modulo)	VALUES (',@ad,', "',@descricao,'", "', @de,'", "',@para,'", "',@assunto,'", "',@conteudo,'", "',@ativo,'", "',@modulo,'")');
PREPARE stmt1 FROM @ins;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;