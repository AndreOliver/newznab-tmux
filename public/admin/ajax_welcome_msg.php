<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'smarty.php';


use nntmux\Sites;

// login check
$admin = new AdminPage;
$s = new Sites();

if (isset($_GET['action']))
{
	if ($_GET['action'] == "1")
		$s->updateItem("showadminwelcome", 1);
	else
		$s->updateItem("showadminwelcome", 0);
}

