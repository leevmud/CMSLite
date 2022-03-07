<?php if(!class_exists('Rain\Tpl')){exit;}?><div class="form-content" >
    <h1 class="home-content-title">Entrar</h1><span>Não possui uma conta?</span><a class="btn-register" tabindex="0">Criar Conta!</a>
    <i class="fas fa-times"></i>
    <p class="response-form"><p>
    <form name="loginForm" action="/login" method="POST">
        <input class="form-data" type="text" id="login" name="login" placeholder="Usuário" autocomplete="off" required pattern="[a-zA-Z][A-Za-z0-9]{4,16}"><br>
        <input class="form-data" type="password" id="pass" name="pass" placeholder="Senha" required pattern="(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z])(?=.*[!@#$*%]).{8,16}"><br>
        <input type="hidden" id="__token" value="<?php echo htmlspecialchars( $csrf_token, ENT_COMPAT, 'UTF-8', FALSE ); ?>">
        <a class="recover-pass" href="/forgot-password">Esqueceu a senha?</a>
        <div id='captcha-login'></div>
        <button id="btn-send-form-login"></button>
    </form>
</div>