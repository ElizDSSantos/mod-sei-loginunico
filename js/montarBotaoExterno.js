

function handleClickExterno(tipoAssinatura,hashSEI,nomeModulo){

    var inputTipoValidacao = document.createElement("input");
    inputTipoValidacao.setAttribute("value", tipoAssinatura );
    inputTipoValidacao.setAttribute("name", "hdnFormaAutenticacao");
    inputTipoValidacao.setAttribute("hidden", "true");

    var inputLogin = document.createElement("input");
    inputLogin.setAttribute("value", hashSEI);
    inputLogin.setAttribute("name", "loginUnicoState");
    inputLogin.setAttribute("hidden", "true");
    
    var inputNomeModulo = document.createElement("input");
    inputNomeModulo.setAttribute("value", nomeModulo);
    inputNomeModulo.setAttribute("name", "hdnModuloOrigem");
    inputNomeModulo.setAttribute("hidden", "true");

    var elForm = document.getElementById("frmAssinaturaUsuarioExterno");
    elForm.appendChild(inputLogin);
    elForm.appendChild(inputTipoValidacao);
    elForm.appendChild(inputNomeModulo);
    document.getElementById("hdnFlag").value=1; 


}

function handleClickTrocarAssinatura(){
    var inputLogin = document.createElement("input");
    inputLogin.setAttribute("value", true);
    inputLogin.setAttribute("name", "trocarAssinatura");
    inputLogin.setAttribute("hidden", "true");
    var elform = document.getElementById("frmAssinaturaUsuarioExterno");
    elform.appendChild(inputLogin);
    document.getElementById("frmAssinaturaUsuarioExterno").submit();

}

function abrirJanelaLoginUnico(strLinkAjaxUsuario){

    
    $.ajax({    
        url:strLinkAjaxUsuario,
        method:'POST',
        async: false,
        dataType:'html',
        cache: false,
        success:function(result) {   
            window.open(result,"loginUnicoValidacao","width=500,height=800");                           
            document.getElementById("frmAssinaturaUsuarioExterno").submit(); 
        },
        error: function () {
            alert('Não foi possível recuperar o usuário loginUnico');
        }
    });
        
}

function formatarBotaoRetorno(){
    window.addEventListener('load',()=>{
        document.querySelector('#frmAssinaturaUsuarioExterno').appendChild(document.querySelector('#retornarGovBr'))
    });
}