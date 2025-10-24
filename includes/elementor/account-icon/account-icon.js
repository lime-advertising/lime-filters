(function(){
  function debounce(fn, delay) {
    let timer = null;
    return function() {
      const context = this;
      const args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function(){
        fn.apply(context, args);
      }, delay);
    };
  }

  function buildBlock(widget, id, title) {
    const dropdown = widget.querySelector('.lf-account-icon__dropdown');
    if (!dropdown) {
      return null;
    }

    const header = dropdown.querySelector('.lf-account-icon__header');
    const list = dropdown.querySelector('.lf-account-icon__list');
    if (!list) {
      return null;
    }

    const block = document.createElement('div');
    block.className = 'lf-account-icon__mobile';
    block.setAttribute('data-lf-mobile-account', id);

    if (title) {
      const titleEl = document.createElement('div');
      titleEl.className = 'lf-account-icon__mobile-title';
      titleEl.textContent = title;
      block.appendChild(titleEl);
    }

    if (header) {
      block.appendChild(header.cloneNode(true));
    }

    block.appendChild(list.cloneNode(true));
    return block;
  }

  function setupWidget(widget) {
    if (widget.dataset.mobileInit === '1') {
      return;
    }
    widget.dataset.mobileInit = '1';

    const selector = widget.dataset.mobileAppend;
    if (!selector) {
      return;
    }

    const breakpoint = parseInt(widget.dataset.mobileBreakpoint || '768', 10);
    const id = widget.dataset.mobileId || ('lf-account-mobile-' + Math.random().toString(36).slice(2));
    widget.dataset.mobileId = id;
    const title = widget.dataset.mobileTitle || '';

    const update = () => {
      const targets = Array.from(document.querySelectorAll(selector));
      if (!targets.length) {
        return;
      }

      const shouldShow = (window.innerWidth || document.documentElement.clientWidth) <= breakpoint;
      targets.forEach(target => {
        const existing = target.querySelector('[data-lf-mobile-account="' + id + '"]');
        if (shouldShow) {
          const block = buildBlock(widget, id, title);
          if (!block) {
            if (existing) {
              existing.remove();
            }
            return;
          }
          if (existing) {
            existing.replaceWith(block);
          } else {
            target.appendChild(block);
          }
        } else if (existing) {
          existing.remove();
        }
      });
    };

    const debouncedUpdate = debounce(update, 120);
    update();
    window.addEventListener('resize', debouncedUpdate);
    window.addEventListener('orientationchange', debouncedUpdate);
  }

  function init() {
    const widgets = document.querySelectorAll('.lf-account-icon[data-mobile-append]');
    widgets.forEach(setupWidget);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
