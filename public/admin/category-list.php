<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'smarty.php';


use nntmux\Category;

$page = new AdminPage();

$category = new Category();

$page->title = "Category List";

$categorylist = $category->getFlat();
$page->smarty->assign('categorylist',$categorylist);

$page->content = $page->smarty->fetch('category-list.tpl');
$page->render();

