<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'smarty.php';


use nntmux\Users;

$page = new AdminPage();

if (isset($_GET['id']))
{
	$users = new Users();
	$users->delete($_GET['id']);
}

if (isset($_GET['redir']))
{
	header("Location: " . $_GET['redir']);
}
else
{
	$referrer = $_SERVER['HTTP_REFERER'];
	header("Location: " . $referrer);
}
