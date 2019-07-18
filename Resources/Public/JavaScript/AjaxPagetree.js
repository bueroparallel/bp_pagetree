/**
 *
 * JavaScript to handle pagetree subtree toggles and load subnodes via ajax if they don't exist
 * @exports TYPO3/CMS/BpPagetree/AjaxPagetree
 */
define(['jquery',
        'TYPO3/CMS/Backend/Icons',
        'd3',
        'TYPO3/CMS/Backend/PageTree/PageTreeDragDrop',
        'TYPO3/CMS/Backend/Tooltip',
        'TYPO3/CMS/Backend/SvgTree'
    ],
    function($, Icons, d3, PageTreeDragDrop) {
        'use strict';

        /**
         * AjaxPagetree class
         *
         * @constructor
         * @exports TYPO3/CMS/BpPagetree/AjaxPagetree
         */
        var AjaxPagetree = function() {

            this.settings = {
                toolbarSelector: '.tree-toolbar',
                target: '.svg-tree',
                searchInput: '.search-input'
            };

            /**
             * jQuery object wrapping the SvgTree
             *
             * @type {jQuery}
             */
            this.$treeWrapper = null;

            /**
             * SvgTree instance
             *
             * @type {SvgTree}
             */
            this.tree = null;

        };

        /**
         * Pagetree utilities initialization
         *
         * @param {String} treeSelector
         * @param {Object} settings
         */
        AjaxPagetree.prototype.initialize = function(treeSelector, settings) {
            var _this = this;
            _this.$treeWrapper = $(treeSelector);

            this.dragDrop = PageTreeDragDrop;
            this.dragDrop.init(this);
            if (!_this.$treeWrapper.data('svgtree-initialized') || typeof _this.$treeWrapper.data('svgtree') !== 'object') {
                //both this module and tree are loaded independently through require js,
                //so we don't know which is loaded first
                //in case of this being loaded first, we wait for an event from svgTree
                _this.$treeWrapper.on('svgTree.initialized', _this.render.bind(_this));
                return;
            }

            $.extend(this.settings, settings);
            this.render();
        };

        /**
         * Renders utilities
         */
        AjaxPagetree.prototype.render = function() {

            var _this = this;
            this.tree = this.$treeWrapper.data('svgtree');

            var $toolbar = $(this.settings.toolbarSelector);

            $toolbar.find(this.settings.searchInput).on('input', function() {
                _this.search.call(_this, this);
            });

            // TO DO: overwriting global tree method -> extend/bind to event
            this.tree.chevronClick = function(node) {
                
                if (node.expanded) {
                    this.hideChildren(node);
                } else {
                    this.showChildren(node);
                }

                this.prepareDataForVisibleNodes();
                this.update();
                this.wrapper.find('.node.identifier-0_-9999 .node-icon-container').remove();
                this.wrapper.find('.node.identifier-0_-9999 .node-name').attr('dx', 10);

                if (node.expanded) {
                    var refresh = false;
                    for (var i = 0; i < this.data.nodes.length; i++) {
                        var identifier = this.data.nodes[i].stateIdentifier;
                        var parents = this.data.nodes[i].parentsStateIdentifier;
                        if (parents.length > 0) {
                            parent = this.data.nodes[i].parentsStateIdentifier[0];
                            if (parent && parent === node.stateIdentifier && identifier.indexOf('_-9999') > 0) {
                                refresh = true;
                            }
                        }
                    }
                    this.refreshTreeAfterUpdatingUsersettings = refresh;
                    if (refresh === true) {
                        $('.svg-tree').find('.svg-tree-loader').show();
                        var ajaxPagetree = this;
                        $(document).ajaxComplete(function(event, xhr, settings) {
                            if (settings.url.indexOf('/typo3/index.php?route=%2Fajax%2Fusersettings%2Fprocess')===0) {
                                if (ajaxPagetree !== undefined && ajaxPagetree.refreshTreeAfterUpdatingUsersettings === true) {
                                    ajaxPagetree.refreshTree();
                                    ajaxPagetree.refreshTreeAfterUpdatingUsersettings = false;
                                }
                            }
                        });
                    }
                }

            }

        };

        /**
         * Refresh tree
         */
        AjaxPagetree.prototype.refreshTree = function() {
            this.tree.refreshTree();
        };

        /**
         * Finds and show all parents of node
         *
         * @param {Node} node
         * @returns {Boolean}
         */
        AjaxPagetree.prototype.showParents = function(node) {
            if (node.parents.length === 0) {
                return true;
            }

            var parent = this.tree.nodes[node.parents[0]];
            parent.hidden = false;

            //expand parent node
            parent.expanded = true;
            this.showParents(parent);
        };

        /**
         * Find node by name
         *
         * @param {HTMLElement} input
         */
        AjaxPagetree.prototype.search = function(input) {

            var _this = this;
            var name = $(input).val().trim();

            this.tree.nodes[0].expanded = false;
            this.tree.nodes.forEach(function(node) {
                var regex = new RegExp(name, 'i');
                if (node.identifier.toString() === name || regex.test(node.name) || regex.test(node.alias)) {
                    _this.showParents(node);
                    node.expanded = true;
                    node.hidden = false;
                } else if (node.depth !== 0) {
                    node.hidden = true;
                    node.expanded = false;
                }
            });

            this.tree.prepareDataForVisibleNodes();
            this.tree.update();

            if (name === '') {
                this.tree.refreshTree();
            }

        };

        function ajaxPagetreeInitSubscriber(mutations) {
            $.each(mutations, function(index, mutation) {
                if (mutation.addedNodes[0].id === 'typo3-pagetree') {
                    if (!$('.svg-tree').data('ajax-pagetree-initialized')) {
                        var ajaxPagetree = new AjaxPagetree();
                        ajaxPagetree.initialize('#typo3-pagetree-tree');
                        $('.svg-tree').data('ajax-pagetree-initialized', true);
                        ajaxPagetreeObserver.disconnect();
                    }
                }
            });
        }

        var ajaxPagetreeObserver = new MutationObserver(ajaxPagetreeInitSubscriber);
        ajaxPagetreeObserver.observe(document.querySelector('.scaffold-content-navigation'), {
            childList: true,
            subtree: true
        });

    });