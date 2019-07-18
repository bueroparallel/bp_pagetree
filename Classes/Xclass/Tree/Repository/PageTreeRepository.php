<?php
declare(strict_types = 1);
namespace Bueroparallel\Pagetree\Xclass\Tree\Repository;

/**
 * This file is part of the "bp_pagetree" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use PDO;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\DataHandling\PlainDataResolver;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Versioning\VersionState;
use TYPO3\CMS\Backend\Configuration\BackendUserConfiguration;

class PageTreeRepository extends \TYPO3\CMS\Backend\Tree\Repository\PageTreeRepository {
	
	/**
     * Maximum standard subtree depth (without explicitly open nodes), might be made configurable per installation
     *
     * @var int
     */
    protected $maxDepth = 3;
    
    /**
     * Explicitly open nodes for current backend user
     *
     * @var array
     */
    protected $openNodes = [];
    
    /**
     * Child nodes of explicitly opened nodes by backend user
     *
     * @var array
     */
    protected $openNodeChildren = [];
    
    /**
     *
     * @var array
     */
    protected $depthByPageRecord = [];

    /**
     * @var BackendUserConfiguration
     */
    protected $backendUserConfiguration;

    /**
     * Main entry point for this repository, to fetch the tree data for a page.
     * Basically the page record, plus all child pages and their child pages recursively, stored within "_children" item.
     *
     * @param int $entryPoint the page ID to fetch the tree for
     * @param callable $callback a callback to be used to check for permissions and filter out pages not to be included.
     * @return array
     */
    public function getTree(int $entryPoint, callable $callback = null): array
    {
        $this->fetchAllPages();
        if ($entryPoint === 0) {
            $tree = $this->fullPageTree;
        } else {
            $tree = $this->findInPageTree($entryPoint, $this->fullPageTree);
        }
        if (!empty($tree) && $callback !== null) {
            $this->applyCallbackToChildren($tree, $callback);
        }
        return $tree;
    }

    /**
     * Removes items from a tree based on a callback, usually used for permission checks
     *
     * @param array $tree
     * @param callable $callback
     */
    protected function applyCallbackToChildren(array &$tree, callable $callback)
    {
        if (!isset($tree['_children'])) {
            return;
        } else if (count($tree['_children']) === 1) {
            // always include placeholders with dummy content, regardless of permissions
            // these will be respected when the tree is refreshed
            if (array_key_exists(0, $tree['_children']) && $tree['_children'][0]['uid'] === '-9999' ) {
                return;
            }
        }
        foreach ($tree['_children'] as $k => &$childPage) {
            if (!call_user_func_array($callback, [$childPage])) {
                unset($tree['_children'][$k]);
                continue;
            }
            $this->applyCallbackToChildren($childPage, $callback);
        }
    }

    /**
     * Fetch all non-deleted pages, regardless of permissions, provided they are below the max default level or have
     *   been opened explicitly by the current backend user.
     *
     * @return [] a segment of pages by page uid, not the whole tree
     */
    protected function fetchAllPages(): array
    {
        $entryPointId = 0;

        if (!empty($this->fullPageTree)) {
            return $this->fullPageTree;
        }
                
        $backendUserConfiguration = GeneralUtility::makeInstance(BackendUserConfiguration::class); 
        $pagetreeStates = $backendUserConfiguration->get('BackendComponents.States.Pagetree.stateHash');
        if (!empty($pagetreeStates)) {
            foreach ($pagetreeStates as $key => $state) {
                if (strpos((string) $key, '_') > 0 && $state == 1) {
                    $pageUid = (int) preg_replace('/(\d+)_/', '', $key);
                    $this->openNodes[] = $pageUid;
                }
            }
        }

        $queryBuilder = $this->createPreparedQueryBuilder();
        
        if (!empty($this->openNodes)) {
	     	$openNodeChildRecords = $this->getChildPageRecords($this->openNodes);
	        if (!empty($openNodeChildRecords)) { 
		        foreach ($openNodeChildRecords as $page) {
		            $this->openNodeChildren[] = $page['uid'];
		        }
	        }
        }

        // get all first level records as base
        $pageRecords = $this->getChildPageRecords([$entryPointId]);
            
        $pageRecordsByDepth = [
	        1 => []
        ];
        $this->depthByPageRecord[$entryPointId] = 1;
        
        foreach ($pageRecords as $page) {
	        $pageRecordsByDepth[1][] = $page['uid'];
        }
        
        for ($depth = 2; $depth <= 100; $depth++) {
	        $pageUids = $pageRecordsByDepth[$depth-1];
	        if ($depth > $this->maxDepth+1 && array_key_exists($depth-1, $pageRecordsByDepth)) {
		        $pageUids = [];
		        foreach ($pageRecordsByDepth[$depth-1] as $pageUid) {
			        if (in_array($pageUid, $this->openNodes, true) || in_array($pageUid, $this->openNodeChildren, true)) {
			            $pageUids[] = $pageUid;
			        }
		        }
	        }
	        if (!empty($pageUids)) {
		        $pageRecordsTmp = $this->getChildPageRecords($pageUids);
		        if (!empty($pageRecordsTmp)) {
		        	array_push($pageRecords,...$pageRecordsTmp);
					$pageRecordsByDepth[$depth] = [];
					foreach ($pageRecordsTmp as $page) {
					    $pageRecordsByDepth[$depth][] = $page['uid'];
				        $this->depthByPageRecord[$page['uid']] = $depth;
	        		}
	        	}
	        	unset($pageRecordsTmp);
	        }
        }

        $livePagePids = [];
        $movePlaceholderData = [];
        // This is necessary to resolve all IDs in a workspace
        if ($this->currentWorkspace !== 0 && !empty($pageRecords)) {
            $livePageIds = [];
            foreach ($pageRecords as $pageRecord) {
                $livePageIds[] = (int)$pageRecord['uid'];
                $livePagePids[(int)$pageRecord['uid']] = (int)$pageRecord['pid'];
                if ((int)$pageRecord['t3ver_state'] === VersionState::MOVE_PLACEHOLDER) {
                    $movePlaceholderData[$pageRecord['t3ver_move_id']] = [
                        'pid' => (int)$pageRecord['pid'],
                        'sorting' => (int)$pageRecord['sorting']
                    ];
                }
            }
            // Resolve placeholders of workspace versions
            $resolver = GeneralUtility::makeInstance(
                PlainDataResolver::class,
                'pages',
                $livePageIds
            );
            $resolver->setWorkspaceId($this->currentWorkspace);
            $resolver->setKeepDeletePlaceholder(false);
            $resolver->setKeepMovePlaceholder(false);
            $resolver->setKeepLiveIds(false);
            $recordIds = $resolver->get();

            $queryBuilder->getRestrictions()->removeAll();
            $pageRecords = $queryBuilder
                ->select(...$this->fields)
                ->from('pages')
                ->where(
                    $queryBuilder->expr()->in('uid', $recordIds)
                )
                ->execute()
                ->fetchAll();
        }

        // Now set up sorting, nesting (tree-structure) for all pages based on pid+sorting fields
        $groupedAndSortedPagesByPid = [];
        foreach ($pageRecords as $pageRecord) {
            $parentPageId = (int)$pageRecord['pid'];
            // In case this is a record from a workspace
            // The uid+pid of the live-version record is fetched
            // This is done in order to avoid fetching records again (e.g. via BackendUtility::workspaceOL()
            if ($parentPageId === -1) {
                // When a move pointer is found, the pid+sorting of the MOVE_PLACEHOLDER should be used (this is the
                // workspace record holding this information), also the t3ver_state is set to the MOVE_PLACEHOLDER
                // because the record is then added
                if ((int)$pageRecord['t3ver_state'] === VersionState::MOVE_POINTER && !empty($movePlaceholderData[$pageRecord['t3ver_oid']])) {
                    $parentPageId = (int)$movePlaceholderData[$pageRecord['t3ver_oid']]['pid'];
                    $pageRecord['sorting'] = (int)$movePlaceholderData[$pageRecord['t3ver_oid']]['sorting'];
                    $pageRecord['t3ver_state'] = VersionState::MOVE_PLACEHOLDER;
                } else {
                    // Just a record in a workspace (not moved etc)
                    $parentPageId = (int)$livePagePids[$pageRecord['t3ver_oid']];
                }
                // this is necessary so the links to the modules are still pointing to the live IDs
                $pageRecord['uid'] = (int)$pageRecord['t3ver_oid'];
                $pageRecord['pid'] = $parentPageId;
            }

            $sorting = (int)$pageRecord['sorting'];
            while (isset($groupedAndSortedPagesByPid[$parentPageId][$sorting])) {
                $sorting++;
            }
            $groupedAndSortedPagesByPid[$parentPageId][$sorting] = $pageRecord;
        }

        // free memory, just in case
        unset($pageRecords);

        $this->fullPageTree = [
            'uid' => 0,
            'title' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] ?: 'TYPO3'
        ];
        $this->addChildrenToPage($this->fullPageTree, $groupedAndSortedPagesByPid);

        // free memory, just in case
        unset($groupedAndSortedPagesByPid);

        return $this->fullPageTree;
    }

    /**
     * @return QueryBuilder
     */
    protected function createPreparedQueryBuilder(): QueryBuilder
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $this->currentWorkspace));

        if (!empty($this->additionalQueryRestrictions)) {
            foreach ($this->additionalQueryRestrictions as $additionalQueryRestriction) {
                $queryBuilder->getRestrictions()->add($additionalQueryRestriction);
            }
        }
        return $queryBuilder;
    }

    /**
     * Gets all children for a collection of pids
     *
     * @param array[] $parentUids
     * @param array[] $pages
     */
    protected function getChildPageRecords($parentUids): array
    {
        $queryBuilder = $this->createPreparedQueryBuilder();
        $pages = $queryBuilder
            ->select(...$this->fields)
            ->from('pages')
            ->where(
                // Only show records in default language
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT))
            )
            ->andWhere(
                $queryBuilder->expr()->in('pid', $parentUids)
            )
            ->execute()
            ->fetchAll();
        return $pages;
    }
    
    /**
     * Adds the property "_children" to a page record with the child pages
     *
     * @param array $page
     * @param array[] $groupedAndSortedPagesByPid
     */
    protected function addChildrenToPage(array &$page, array &$groupedAndSortedPagesByPid): void
    {
	    $pageUid = (int) $page['uid'];
	    $pageDepth = $this->depthByPageRecord[$pageUid];
	    $page['_children'] = $groupedAndSortedPagesByPid[$pageUid] ?? [];
	    if ($pageDepth >= $this->maxDepth && !empty($page['_children']) && !in_array($pageUid, $this->openNodes)) {
		    $page['_children'] = [['uid' => '-9999', 'title' => '...']];
	    }       
        ksort($page['_children']);
        foreach ($page['_children'] as &$child) {
            $this->addChildrenToPage($child, $groupedAndSortedPagesByPid);
        }
    }

    /**
     * Looking for a page by traversing the tree
     *
     * @param int $pageId the page ID to search for
     * @param array $pages the page tree to look for the page
     * @return array Array of the tree data, empty array if nothing was found
     */
    protected function findInPageTree(int $pageId, array $pages): array
    {
        foreach ($pages['_children'] as $childPage) {
            if ((int)$childPage['uid'] === $pageId) {
                return $childPage;
            }
            $result = $this->findInPageTree($pageId, $childPage);
            if (!empty($result)) {
                return $result;
            }
        }
        return [];
    }
	
}