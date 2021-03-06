<!DOCTYPE html>
<html>
{if isset($error) && $error != ''}
	<div class="alert alert-danger">{$error}</div>
{/if}
{if isset($notice) && $notice != ''}
	<div class="alert alert-info">{$notice}</div>
{/if}
{if isset($sent) && $sent != ''}
	<div class="alert alert-info">A link to reset your password has been sent to your e-mail account.</div>
{/if}
<head>
	<meta charset="UTF-8">
	<title>{$site->title} | Password reset</title>
	<meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
	<!-- Bootstrap 3.3.6 -->
	<link href="{$smarty.const.WWW_THEMES}/shared/assets/bootstrap-3.x/dist/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
	<!-- Font Awesome Icons -->
	<link href="{$smarty.const.WWW_THEMES}/shared/assets/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
	<!-- Theme style -->
	<link href="{$smarty.const.WWW_THEMES}/{$theme}/dist/css/AdminLTE.min.css" rel="stylesheet" type="text/css" />
	<!-- iCheck -->
	<link href="{$smarty.const.WWW_THEMES}/{$theme}/plugins/iCheck/square/blue.css" rel="stylesheet" type="text/css" />
	<!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
	<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
	<!--[if lt IE 9]>
	<script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
	<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
	<![endif]-->
</head>
{if $confirmed == '' && $sent == ''}
<body class="login-page">
<div class="login-box">
	<div class="login-logo">
		<a href="{$serverroot}"><b{$site->title}</b></a>
	</div><!-- /.login-logo -->
	<div class="login-box-body">
		<p class="login-box-msg">Please enter the email address you used to register and we will send an email to reset your password. If you
			cannot remember your email, or no longer have access to it, please <a href="{$smarty.const.WWW_TOP}/contact-us">contact
				us</a>.</p>
		<form action="forgottenpassword?action=submit" method="post">
			<div class="form-group has-feedback">
				<input autocomplete="off" id="email" name="email" value="{$email}" type="email" class="form-control" placeholder="Email"/>
				<span class="glyphicon glyphicon-envelope form-control-feedback"></span>
			</div>
			<div class="row">
				<div class="col-xs-6">
					{$page->smarty->fetch('captcha.tpl')}
				</div><!-- /.col -->
				<hr>
				<div class="col-xs-12">
					<button type="submit" class="btn btn-primary btn-block btn-flat">Request Password Reset</button>
				</div><!-- /.col -->
			</div>
		</form>
		<!-- jQuery 2.2.x -->
		<script src="{$smarty.const.WWW_THEMES}/shared/assets/jquery-2.2.x/dist/jquery.min.js"></script>
		<!-- Bootstrap 3.3.6 JS -->
		<script src="{$smarty.const.WWW_THEMES}/shared/assets/bootstrap-3.x/dist/js/bootstrap.min.js" type="text/javascript"></script>
		<!-- icheck -->
		<script src="{$smarty.const.WWW_THEMES}/shared/assets/icheck/icheck.min.js" type="text/javascript"></script>
</body>
{elseif $sent != ''}
	<p>
		A password reset request has been sent to your email.
	</p>
{else}
	<p>
		Your password has been reset and sent to you in an email.
	</p>
{/if}
</html>
