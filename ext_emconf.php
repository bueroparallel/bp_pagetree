<?php
$EM_CONF[$_EXTKEY] = array(
	'title' => 'Faster Backend Pagetree',
	'description' => 'Performance-optimized backend page tree for large TYPO3 v9 installations (> 10k pages)',
	'category' => 'backend',
	'author' => 'bueroparallel',
	'author_email' => 'kontakt@bueroparallel.de',
	'author_company' => 'bueroparallel',
	'state' => 'alpha',
	'internal' => '',
	'uploadfolder' => '0',
	'createDirs' => '',
	'clearCacheOnLoad' => true,
	'version' => '1.0',
	'constraints' => array(
		'depends' => array(
			'typo3' => '9.5-9.9.99',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
);