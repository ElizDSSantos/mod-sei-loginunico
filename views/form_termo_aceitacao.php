<!DOCTYPE html>
<html lang="pt-br">
<head>
  <link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
  <link href="modulos/loginunico/css/style.css" rel="stylesheet" type="text/css" media="screen" />
  <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
  <meta name="robots" content="noindex" />
  <title>SEI - Termo de Aceita��o</title>
</head>

<body>
<div class="blue">
  <img class="imgGov" src="<?= PaginaSEI::getInstance()->getDiretorioImagens() . '/sei_logo_login.png' ?>" />
</div>

<form id="frmAceite" class="mt-3" method="post" action="<?=SessaoSEIExterna::getInstance()->assinarLink('controlador_externo.php?acao=usuario_loginunico_aceite&id_orgao_acesso_externo='.$_GET['id_orgao_acesso_externo'])?>">
  <h1 class="textoGovBr center mb-2"><b>Autoriza��o de uso de dados pessoais</b></h1>
  
  <div class="center mt-100">
    <img class="mb-2" src="<?=  PaginaSEI::getInstance()->getDiretorioImagens() . '/logo_sei-01.png' ?>" height="250px;" width="350px;" />
  </div>

  <h1 style="font-size: 2em;" class="textoGovBr center mt-50 mb-8">Servi�o: <b>SEI - Conselho Adminstrativo de Defesa Econ�mica - CADE</b></h1>

  <div class="formatTxt">
    <p>Este servi�o precisa utilizar as seguintes informa��es pessoais do seu cadastro:</p>
    <ul class="mb-4">
      <li class="mb-2">Fazer login usando sua identidade.</li>
      <li>Visualizar seus Dados B�sicos: Nome Completo, Data de Nascimento, e-mail e telefone.</li>
    </ul>
    <p>A partir da sua aprova��o, a aplica��o SEI e a plataforma Acesso.gov.br utilizar�o as informa��es listadas acima, respeitando os termos de uso e a pol�tica de privacidade.</p>
  </div>

  <div class="center mt-5">
    <button id="btnNegar" name="btnNegar" value="negado" class="btnGov">Negar</button>
    <button type="submit" id="btnAutorizar" name="btnAutorizar" value="autorizado" class="btnGov btnAutorizaGov">Autorizar</button>
  </div>
</form>

</body>
</html>





