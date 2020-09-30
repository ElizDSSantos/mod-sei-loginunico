-- Adicionando a coluna e-mail a tabela usuario_login_unico
-- e-mail é um identificador do SEI externo
ALTER TABLE usuario_login_unico ADD email varchar(200) not null;