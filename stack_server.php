<?php
$tmp = new stdClass;
$tmp->dir = getcwd();
$tmp->plaser = serialize($GLOBALS['plaser']->_getvars());
$GLOBALS['stack'][] = $tmp;
//debug($GLOBALS['stack'], 1);
?>