<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'smarty.php';


use nntmux\Contents;

$page = new AdminPage();

if (isset($_GET['id']))
{
	$contents = new Contents();
	$contents->delete($_GET['id']);
}

$referrer = $_SERVER['HTTP_REFERER'];
header("Location: " . $referrer);

