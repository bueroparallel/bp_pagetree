<?php
$EM_CONF[$_EXTKEY] = array(
	'title' => 'Faster Backend Pagetree (v9)',
	'description' => 'Performance-optimized backend page tree for large TYPO3 v9 installations (> 10k pages)',
	'category' => 'be',
	'author' => 'bueroparallel',
	'author_email' => 'kontakt@bueroparallel.de',
	'author_company' => 'bueroparallel',
	'state' => 'beta',
	'internal' => '',
	'uploadfolder' => '0',
	'createDirs' => '',
	'clearCacheOnLoad' => true,
	'version' => '1.0.0',
	'constraints' => array(
		'depends' => array(
			'typo3' => '9.5.0-9.9.99',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
);