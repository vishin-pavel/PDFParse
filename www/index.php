<?php
error_reporting(E_ALL);
define('CONV_DIR_ROOT', dirname(__FILE__));
require_once('classes/converter.php');
function __autoload($classname) {
	$possibilities = array(
		'/\w+\\w+Engine/' => CONV_DIR_ROOT.'/classes/formatengines/'.strtolower(preg_replace('/\w+\\\(\w+Engine)/', '$1',$classname)).'.php',
		'/\w+Handler/' => CONV_DIR_ROOT.'/classes/'.strtolower($classname).'.php'
	);
	$included = false;
	foreach ($possibilities as $regexp => $path) {
		if (preg_match($regexp, $classname) == 1) {
			if (is_file($path)) {
				include($path);
				return;
			} else {
				throw new Exception('Unknown class '.$classname);
			}
		}
	}
}
$converter = new Converter('primer3.pdf');
$converter->run();

