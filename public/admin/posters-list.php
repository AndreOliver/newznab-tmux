<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'smarty.php';


use App\Models\MultigroupPosters;

$page   = new AdminPage();

$posters = MultigroupPosters::all('id', 'poster')->sortBy('poster');

$postersCheck = $posters->first();

$poster = isset($_REQUEST['poster']) && !empty($_REQUEST['poster']) ? $_REQUEST['poster'] : '';

$page->smarty->assign(
	[
		'poster' => $poster,
		'posters' => $posters,
		'check' => $postersCheck
	]
);

$page->title = 'MultiGroup Posters List';
$page->content = $page->smarty->fetch('posters-list.tpl');
$page->render();
