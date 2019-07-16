<?php
use Bueroparallel\Pagetree\Controller;

/**
 * Definitions for additional routes provided by EXT:backend
 *
 */
return [
  //
  'ajax_pagetree' => [
    'path' => '/ajax/pagetree',
    'target' => Controller\PagetreeController::class . '::ajaxPagetreeAction'
  ],
];