(function(){
  function activateTab(container, targetId) {
    if (!targetId) {
      return;
    }

    var tabs = container.querySelectorAll('.lf-tabs__tab');
    var panes = container.querySelectorAll('.lf-tabs__pane');

    tabs.forEach(function(tab){
      var isActive = tab.getAttribute('data-tab-target') === targetId;
      tab.classList.toggle('is-active', isActive);
      tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    panes.forEach(function(pane){
      var isActive = pane.getAttribute('data-tab-panel') === targetId;
      pane.classList.toggle('is-active', isActive);
    });
  }

  function initTabs(scope) {
    var containers = scope.querySelectorAll('.lf-tabs[data-lf-tabs="1"]');
    containers.forEach(function(container){
      if (container.dataset.lfTabsInit === '1') {
        return;
      }
      container.dataset.lfTabsInit = '1';

      var tabs = container.querySelectorAll('.lf-tabs__tab');
      tabs.forEach(function(tab){
        tab.addEventListener('click', function(){
          activateTab(container, tab.getAttribute('data-tab-target'));
        });
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){
      initTabs(document);
    });
  } else {
    initTabs(document);
  }

  if (window.jQuery) {
    window.jQuery(document).on('elementor/popup/show elementor/init elementor/frontend/init', function(event, panel){
      var scope = panel && panel[0] ? panel[0] : document;
      initTabs(scope);
    });
  }
})();
