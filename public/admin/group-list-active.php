<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'smarty.php';


use nntmux\Groups;

$page = new AdminPage();
$groups = new Groups(['Settings' => $page->settings]);

$gname = '';
if (!empty($_REQUEST['groupname'])) {
	$gname = $_REQUEST['groupname'];
}

$groupcount = $groups->getCount($gname, 1);

$offset = $_REQUEST['offset'] ?? 0;
$groupname = !empty($_REQUEST['groupname']) ? $_REQUEST['groupname'] : '';

$page->smarty->assign('groupname',$groupname);
$page->smarty->assign('pagertotalitems',$groupcount);
$page->smarty->assign('pageroffset',$offset);
$page->smarty->assign('pageritemsperpage',ITEMS_PER_PAGE);
$page->smarty->assign('pagerquerysuffix', '#results');

$groupsearch = $gname != '' ? 'groupname='.$gname.'&amp;' : '';
$page->smarty->assign('pagerquerybase', WWW_TOP.'/group-list-active.php?'.$groupsearch.'offset=');
$pager = $page->smarty->fetch('pager.tpl');
$page->smarty->assign('pager', $pager);

$grouplist = $groups->getRange($offset, ITEMS_PER_PAGE, $gname, 1);

$page->smarty->assign('grouplist',$grouplist);

$page->title = 'Group List';

$page->content = $page->smarty->fetch('group-list.tpl');
$page->render();
