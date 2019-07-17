# TYPO3 Extension ``bp_pagetree``

This extension replaces and extends parts of the new page tree component in TYPO3 9. By default, it loads
subpage trees up to 3 nesting levels, adding all subpage trees that have been explicitly opened by the current 
backend user.  This is helpful for large installations with a lot of pages, in which the v9 core component can run 
into performance issues (server- and client-side).

Should be regarded as a workaround until the core component has been refactored to load all subtrees asynchronously.

## Installation

As yet, only manual installation is possible. Addition to TER is planned for the near future.

## Known limitations

- Using the filter/search function within the component will only work for pages/nodes that are currently open 
or have been since the last page tree refresh. This part would have to be completely rewritten for an asynchronous 
server-side solution, as the v9 core component does all the filtering client-side.
- Dragging and dropping onto previously closed page nodes will not open their subpages automatically. 
To move or copy a page there, please open the target subtree first. In case you see a permanent "..." 
placeholder where a subtree should be, please simply refresh the page tree in the top right corner of the component.
- The extension hasn't been tested with complex workspace/versioning setups or deep database mounts yet. Any feedback
is very much appreciated.
