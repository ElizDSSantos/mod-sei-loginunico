
function initBotao(urlLogout){

    window.addEventListener('load',()=>{
        document.querySelector("#selCargoFuncao").selectedIndex = 0;
        let divAutenticacao = document.getElementById("divAutenticacao");
        let botaoGovBr=document.getElementById("btnLoginUnico");
        divAutenticacao.appendChild(botaoGovBr);
        document.querySelector('#selCargoFuncao').addEventListener('change',()=>{
            window.open(urlLogout,"janelaGovBrLogout","width=50,height=50")
        })   
    }); 
}

function handleClickInterno(tipoAssinatura,hashSEI){
    document.getElementById("hdnFormaAutenticacao").value=tipoAssinatura;
    var inputLogin = document.createElement("input");
    inputLogin.setAttribute("value", hashSEI);
    inputLogin.setAttribute("name", "loginUnicoState");
    inputLogin.setAttribute("hidden", "true");
    var elform = document.getElementById("frmAssinaturas");
    elform.appendChild(inputLogin);

}

function handleClickTrocarAssinatura(){
    var inputLogin = document.createElement("input");
    inputLogin.setAttribute("value", true);
    inputLogin.setAttribute("name", "trocarAssinatura");
    inputLogin.setAttribute("hidden", "true");
    var elform = document.getElementById("frmAssinaturas");
    elform.appendChild(inputLogin);
    document.getElementById("frmAssinaturas").submit();

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
            document.getElementById("frmAssinaturas").submit();
        },
        error: function () {
            alert('Não foi possível recuperar o usuário loginUnico');
        }
    });
        
}

function trocarAssinatura(){
    window.addEventListener('load',()=>{
        let lblOuNovo=document.querySelector('#lblOu').cloneNode(true);
        document.querySelector('#divAutenticacao').appendChild(lblOuNovo)
        document.querySelector('#divAutenticacao').appendChild(document.querySelector('#retornarGovBr'))
    });  
}

