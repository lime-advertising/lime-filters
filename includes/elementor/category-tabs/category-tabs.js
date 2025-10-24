function getSwiperConstructor() {
  if (typeof window.Swiper !== 'undefined') {
    return window.Swiper;
  }
  if (window.elementorFrontend && elementorFrontend.utils && elementorFrontend.utils.swiper) {
    return elementorFrontend.utils.swiper;
  }
  return null;
}

(function(){
  function parseSlides(value, fallback) {
    const parsed = parseInt(value, 10);
    return Number.isNaN(parsed) || parsed <= 0 ? fallback : parsed;
  }

  function initSlider(wrapper) {
    if (!wrapper) return;
    const sliderEl = wrapper.querySelector('.lf-products--slider.swiper');
    if (!sliderEl) return;
    const SwiperConstructor = getSwiperConstructor();
    if (!SwiperConstructor) {
      return;
    }

    if (wrapper.dataset.lfSliderInit === '1') {
      const existing = sliderEl.__lfSwiperInstance;
      if (existing && typeof existing.update === 'function') {
        existing.update();
        existing.updateProgress();
        existing.updateSlides();
      }
      return;
    }

    const slidesDesktop = parseSlides(wrapper.dataset.slidesDesktop, 4);
    const slidesTablet = parseSlides(wrapper.dataset.slidesTablet, Math.min(slidesDesktop, 3));
    const slidesMobile = parseSlides(wrapper.dataset.slidesMobile, Math.min(slidesTablet, 2));

    const config = {
      slidesPerView: slidesMobile,
      spaceBetween: 20,
      watchOverflow: true,
      navigation: {
        prevEl: wrapper.querySelector('.lf-category-tabs__prev'),
        nextEl: wrapper.querySelector('.lf-category-tabs__next'),
      },
      breakpoints: {
        576: {
          slidesPerView: slidesTablet,
        },
        992: {
          slidesPerView: slidesDesktop,
        },
      },
    };

    const swiper = new SwiperConstructor(sliderEl, config);
    sliderEl.__lfSwiperInstance = swiper;
    wrapper.dataset.lfSliderInit = '1';
  }

  function initSliders(scope) {
    const context = scope && scope instanceof HTMLElement ? scope : document;
    const wrappers = context.querySelectorAll('.lf-category-tabs__slider');
    if (!wrappers.length) {
      return;
    }
    wrappers.forEach(function(wrapper){
      initSlider(wrapper);
    });
  }

  function refreshActiveSliders(scope) {
    const context = scope && scope instanceof HTMLElement ? scope : document;
    const activePanes = context.querySelectorAll('.lf-category-tabs__pane.is-active');
    activePanes.forEach(function(pane){
      pane.querySelectorAll('.lf-products--slider.swiper').forEach(function(sliderEl){
        const instance = sliderEl.__lfSwiperInstance;
        if (instance && typeof instance.update === 'function') {
          setTimeout(function(){
            instance.update();
            instance.updateProgress();
            instance.updateSlides();
          }, 60);
        }
      });
    });
  }

  function activateTab(container, target) {
    const buttons = container.querySelectorAll('[data-tab-target]');
    const panes = container.querySelectorAll('[data-tab-panel]');
    buttons.forEach(function(btn){
      btn.classList.toggle('is-active', btn.dataset.tabTarget === target);
      btn.setAttribute('aria-selected', btn.dataset.tabTarget === target ? 'true' : 'false');
      btn.setAttribute('tabindex', btn.dataset.tabTarget === target ? '0' : '-1');
    });
    panes.forEach(function(pane){
      const isActive = pane.dataset.tabPanel === target;
      pane.classList.toggle('is-active', isActive);
      pane.setAttribute('aria-hidden', isActive ? 'false' : 'true');
    });

    requestAnimationFrame(function(){
      initSliders(container);
      refreshActiveSliders(container);
    });
  }

  function initTabs(container) {
    if (!container || container.dataset.lfTabsInit === '1') return;
    container.dataset.lfTabsInit = '1';

    const buttons = container.querySelectorAll('[data-tab-target]');
    if (!buttons.length) return;

    buttons.forEach(function(button){
      button.addEventListener('click', function(){
        activateTab(container, button.dataset.tabTarget);
      });
      button.addEventListener('keydown', function(event){
        if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') {
          return;
        }
        event.preventDefault();
        const delta = event.key === 'ArrowRight' ? 1 : -1;
        const list = Array.prototype.slice.call(buttons);
        const index = list.indexOf(button);
        const nextIndex = (index + delta + list.length) % list.length;
        const nextButton = list[nextIndex];
        if (nextButton) {
          nextButton.focus();
          activateTab(container, nextButton.dataset.tabTarget);
        }
      });
    });

    const initial = buttons[0];
    if (initial) {
      activateTab(container, initial.dataset.tabTarget);
    }

    initSliders(container);
    refreshActiveSliders(container);
  }

  function initAll(scope) {
    const context = scope && scope instanceof HTMLElement ? scope : document;
    context.querySelectorAll('.lf-category-tabs').forEach(function(container){
      initTabs(container);
    });
  }

  function bootstrap(scope) {
    initAll(scope || document);
    initSliders(scope || document);
    refreshActiveSliders(scope || document);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){
      bootstrap(document);
    });
  } else {
    bootstrap(document);
  }

  if (window.jQuery) {
    window.jQuery(window).on('elementor/frontend/init', function(){
      bootstrap(document);
      if (window.elementorFrontend && window.elementorFrontend.hooks) {
        window.elementorFrontend.hooks.addAction(
          'frontend/element_ready/lime-filters-category-tabs.default',
          function($scope){
            const node = $scope && $scope[0] ? $scope[0] : null;
            bootstrap(node || document);
          }
        );
      }
    });
  }
})();
