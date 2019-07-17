<?php
namespace Bueroparallel\Pagetree\Hook;

/**
 * This file is part of the "bp_pagetree" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use TYPO3\CMS\Backend\Controller\BackendController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BackendControllerHook
{
	/**
     * Adds necessary backend CSS
     *
     */
    public function addCss()
    {
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->addCssFile('EXT:bp_pagetree/Resources/Public/Css/AjaxPagetree.css');
    }
	
    /**
     * Adds pagetree JavaScript for dynamic toggling of page subtrees
     *
     * @param array $configuration
     * @param BackendController $backendController
     */
    public function addJavaScript(array $configuration, BackendController $backendController)
    {
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        /** @var UriBuilder $uriBuilder */
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $pageRenderer->loadRequireJsModule('TYPO3/CMS/BpPagetree/AjaxPagetree');
        $pageRenderer->addInlineSetting('AjaxPagetree', 'moduleUrl', (string)$uriBuilder->buildUriFromRoute('ajax_pagetree'));
    }
}
