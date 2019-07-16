<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Backend\\Tree\\Repository\\PageTreeRepository'] = array(
   'className' => 'Bueroparallel\\Pagetree\\Xclass\\Tree\\Repository\\PageTreeRepository'
);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/backend.php']['constructPostProcess'][] = 
\Bueroparallel\Pagetree\Hook\BackendControllerHook::class . '->addCss';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/backend.php']['constructPostProcess'][] = 
\Bueroparallel\Pagetree\Hook\BackendControllerHook::class . '->addJavaScript';

