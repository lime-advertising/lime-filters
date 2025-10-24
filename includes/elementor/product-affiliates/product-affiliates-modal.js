(function () {
  const STATE_KEY = 'lfAffiliateModalDismissed';
  const FOCUSABLE_SELECTOR = 'a[href],area[href],input:not([disabled]):not([type="hidden"]),select:not([disabled]),textarea:not([disabled]),button:not([disabled]),iframe,object,embed,[tabindex]:not([tabindex="-1"]),[contenteditable="true"]';
  const upsellCache = new Map();
  const raf = window.requestAnimationFrame ? window.requestAnimationFrame.bind(window) : (fn) => setTimeout(fn, 0);
  let activeState = null;

  function createCustomEvent(name, detail) {
    try {
      return new CustomEvent(name, { detail: detail });
    } catch (err) {
      const evt = document.createEvent('CustomEvent');
      evt.initCustomEvent(name, true, true, detail);
      return evt;
    }
  }

  function logEvent(name, detail) {
    if (typeof document === 'undefined') {
      return;
    }

    try {
      document.dispatchEvent(createCustomEvent(name, detail));
    } catch (err) {
      // Swallow logging errors to avoid breaking UX.
    }
  }

  function matches(el, selector) {
    if (!el || el === document) {
      return false;
    }
    if (el.matches) {
      return el.matches(selector);
    }
    if (el.msMatchesSelector) {
      return el.msMatchesSelector(selector);
    }
    if (el.webkitMatchesSelector) {
      return el.webkitMatchesSelector(selector);
    }
    return false;
  }

  function closest(element, selector) {
    let current = element;
    while (current && current !== document) {
      if (matches(current, selector)) {
        return current;
      }
      current = current.parentElement;
    }
    return null;
  }

  function getFocusableWithin(container) {
    if (!container) {
      return [];
    }
    const nodes = container.querySelectorAll(FOCUSABLE_SELECTOR);
    const focusables = [];
    nodes.forEach((el) => {
      const rects = typeof el.getClientRects === 'function' ? el.getClientRects() : null;
      const isHidden = el.offsetParent === null && (!rects || rects.length === 0);
      if (isHidden && el !== document.activeElement) {
        return;
      }
      if (el.hasAttribute('disabled')) {
        return;
      }
      focusables.push(el);
    });
    return focusables;
  }

  function shouldSkipForSession(productId) {
    if (!productId) {
      return false;
    }
    try {
      const raw = sessionStorage.getItem(STATE_KEY);
      if (!raw) {
        return false;
      }
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) && parsed.indexOf(productId) !== -1;
    } catch (err) {
      return false;
    }
  }

  function rememberSkip(productId) {
    if (!productId) {
      return;
    }
    try {
      const raw = sessionStorage.getItem(STATE_KEY);
      const parsed = raw ? JSON.parse(raw) : [];
      if (Array.isArray(parsed)) {
        if (parsed.indexOf(productId) === -1) {
          parsed.push(productId);
        }
        sessionStorage.setItem(STATE_KEY, JSON.stringify(parsed));
      } else {
        sessionStorage.setItem(STATE_KEY, JSON.stringify([productId]));
      }
    } catch (err) {
      // Ignore storage failures.
    }
  }

  function primeUpsellData(scope) {
    const context = scope || document;
    if (!context) {
      return;
    }

    const scripts = context.querySelectorAll('script[data-upsell-json]');
    scripts.forEach((script) => {
      if (script.dataset.lfUpsellParsed === '1') {
        return;
      }
      const productId = script.getAttribute('data-product-id');
      if (!productId || upsellCache.has(productId)) {
        script.dataset.lfUpsellParsed = '1';
        return;
      }

      let parsed = [];
      try {
        parsed = JSON.parse(script.textContent || '[]');
        if (!Array.isArray(parsed)) {
          parsed = [];
        }
      } catch (err) {
        parsed = [];
      }

      upsellCache.set(productId, parsed);
      script.dataset.lfUpsellParsed = '1';
    });
  }

  function getUpsells(productId) {
    if (!productId) {
      return [];
    }
    if (!upsellCache.has(productId)) {
      return [];
    }
    const value = upsellCache.get(productId);
    return Array.isArray(value) ? value : [];
  }

  function getAjaxEndpoint() {
    if (window.wc_add_to_cart_params && window.wc_add_to_cart_params.wc_ajax_url) {
      return window.wc_add_to_cart_params.wc_ajax_url.replace('%%endpoint%%', 'add_to_cart');
    }
    return '/?wc-ajax=add_to_cart';
  }

  function parseAjaxResponse(raw) {
    if (!raw) {
      return null;
    }
    if (typeof raw === 'string') {
      try {
        return JSON.parse(raw);
      } catch (err) {
        return null;
      }
    }
    return raw;
  }

  function submitAjaxAddToCart(productId) {
    const endpoint = getAjaxEndpoint();
    const payload = { product_id: productId, quantity: 1 };

    if (window.fetch) {
      const formData = new FormData();
      formData.append('product_id', productId);
      formData.append('quantity', 1);
      return fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
        },
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error('Unable to add product to the cart.');
          }
          return response.json();
        })
        .then((data) => {
          if (!data || data.error) {
            const message = data && data.message ? data.message : 'Unable to add product to the cart.';
            throw new Error(message);
          }
          return data;
        });
    }

    if (window.jQuery && window.jQuery.post) {
      return new Promise((resolve, reject) => {
        window.jQuery
          .post(endpoint, payload)
          .done((resp) => {
            const data = parseAjaxResponse(resp);
            if (!data || data.error) {
              reject(new Error((data && data.message) || 'Unable to add product to the cart.'));
              return;
            }
            resolve(data);
          })
          .fail(() => {
            reject(new Error('Unable to add product to the cart.'));
          });
      });
    }

    return Promise.reject(new Error('Add to cart is not available.'));
  }

  function createEl(tag, className, attrs) {
    const el = document.createElement(tag);
    if (className) {
      el.className = className;
    }
    if (attrs) {
      Object.keys(attrs).forEach((key) => {
        el.setAttribute(key, attrs[key]);
      });
    }
    return el;
  }

  function buildAccessoryCard(item) {
    const card = createEl('article', 'lf-affiliates-upsell-card');

    const media = createEl('div', 'lf-affiliates-upsell-card__media');
    if (item && item.image) {
      media.innerHTML = item.image;
    }

    const details = createEl('div', 'lf-affiliates-upsell-card__details');
    const title = createEl('h3', 'lf-affiliates-upsell-card__title');
    const name = item && item.title ? item.title : 'Accessory';

    if (item && item.url) {
      const link = createEl('a', 'lf-affiliates-upsell-card__link', {
        href: item.url,
        target: '_blank',
        rel: 'noopener noreferrer',
      });
      link.textContent = name;
      title.appendChild(link);
    } else {
      title.textContent = name;
    }

    details.appendChild(title);

    if (item && item.price) {
      const price = createEl('div', 'lf-affiliates-upsell-card__price');
      price.innerHTML = item.price;
      details.appendChild(price);
    }

    const canAdd = !!(item && item.can_add);
    const buttonAttrs = { type: 'button' };
    if (canAdd) {
      buttonAttrs['data-upsell-add'] = '1';
    }

    const button = createEl('button', 'lf-affiliates-upsell-card__cta', buttonAttrs);
    if (canAdd && item && typeof item.id !== 'undefined') {
      button.setAttribute('data-upsell-id', String(item.id));
      button.textContent = 'Add to cart & continue';
    } else {
      const status = item && item.status ? String(item.status) : '';
      const label = item && item.status_label ? String(item.status_label) : '';
      if (item && item.url) {
        button.setAttribute('data-upsell-visit', '1');
        button.setAttribute('data-upsell-url', item.url);
        if (status === 'requires_options') {
          button.textContent = label || 'Choose options';
        } else if (status === 'out_of_stock') {
          button.textContent = label || 'View product';
        } else {
          button.textContent = label || 'View product';
        }
      } else {
        button.textContent = label || 'Unavailable';
        button.disabled = true;
        button.setAttribute('aria-disabled', 'true');
        button.classList.add('is-disabled');
      }
    }

    details.appendChild(button);
    if (item && item.status_label && !canAdd && !(item && item.url)) {
      const statusNote = createEl('div', 'lf-affiliates-upsell-card__status');
      statusNote.textContent = item.status_label;
      details.appendChild(statusNote);
    }
    card.appendChild(media);
    card.appendChild(details);

    return { element: card, button: button };
  }

  function showNotice(state, message) {
    if (!state || !state.notice) {
      return;
    }
    state.notice.textContent = message;
    state.notice.hidden = !message;
  }

  function setButtonLoading(button, loading) {
    if (!button) {
      return;
    }
    if (!button.dataset.originalText) {
      button.dataset.originalText = button.textContent;
    }
    if (loading) {
      button.disabled = true;
      button.classList.add('is-loading');
      button.textContent = 'Adding...';
    } else {
      button.disabled = false;
      button.classList.remove('is-loading');
      const original = button.dataset.originalText;
      if (original) {
        button.textContent = original;
      }
    }
  }

  function redirectToAffiliate(state) {
    if (!state || !state.affiliateUrl) {
      return;
    }
    const target = state.target || '_self';
    if (target === '_blank') {
      const opened = window.open(state.affiliateUrl, target);
      if (!opened) {
        window.location.href = state.affiliateUrl;
      }
    } else {
      window.location.href = state.affiliateUrl;
    }
  }

  function trapFocus(event, state) {
    if (!state) {
      return;
    }
    const key = event.key || event.keyCode;
    if (key !== 'Tab' && key !== 9) {
      return;
    }

    const focusable = getFocusableWithin(state.dialog);
    if (!focusable.length) {
      event.preventDefault();
      state.dialog.focus();
      return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    const active = document.activeElement;
    const index = focusable.indexOf ? focusable.indexOf(active) : -1;

    if (event.shiftKey) {
      if (active === first || index === 0) {
        event.preventDefault();
        last.focus();
      }
      return;
    }

    if (active === last || index === focusable.length - 1) {
      event.preventDefault();
      first.focus();
    }
  }

  function handleDocumentKeydown(event) {
    if (!activeState) {
      return;
    }

    const key = event.key || event.keyCode;
    if (key === 'Escape' || key === 'Esc' || key === 27) {
      event.preventDefault();
      closeModal(activeState, { restoreFocus: true });
      return;
    }

    trapFocus(event, activeState);
  }

  function handleModalClick(event) {
    if (!activeState) {
      return;
    }
    const trigger = closest(event.target, '[data-upsell-close]');
    if (trigger) {
      event.preventDefault();
      closeModal(activeState, { restoreFocus: true });
    }
  }

  function focusInitialElement(state) {
    raf(() => {
      if (!state) {
        return;
      }
      const focusable = getFocusableWithin(state.dialog);
      if (focusable.length) {
        focusable[0].focus();
      } else {
        state.dialog.focus();
      }
    });
  }

  function attachActionHandlers(state) {
    state.addHandlers = new Map();
    state.visitHandlers = new Map();
    state.addButtons.forEach((btn) => {
      const handler = (event) => handleUpsellAddClick(event, state);
      btn.addEventListener('click', handler);
      state.addHandlers.set(btn, handler);
    });

    if (Array.isArray(state.visitButtons) && state.visitButtons.length) {
      state.visitButtons.forEach((btn) => {
        const handler = (event) => {
          event.preventDefault();
          const url = btn.getAttribute('data-upsell-url');
          if (!url) {
            return;
          }
          const opened = window.open(url, '_blank');
          if (!opened) {
            window.location.href = url;
          }
        };
        btn.addEventListener('click', handler);
        state.visitHandlers.set(btn, handler);
      });
    }

    state.skipHandler = (event) => {
      event.preventDefault();
      rememberSkip(state.productId);
      closeModal(state, { restoreFocus: false });
      redirectToAffiliate(state);
    };

    if (state.skipButton) {
      state.skipButton.addEventListener('click', state.skipHandler);
    }
  }

  function detachActionHandlers(state) {
    if (!state) {
      return;
    }
    if (state.addHandlers) {
      state.addHandlers.forEach((handler, btn) => {
        btn.removeEventListener('click', handler);
      });
      state.addHandlers.clear();
    }
    if (state.visitHandlers) {
      state.visitHandlers.forEach((handler, btn) => {
        btn.removeEventListener('click', handler);
      });
      state.visitHandlers.clear();
    }
    if (state.skipButton && state.skipHandler) {
      state.skipButton.removeEventListener('click', state.skipHandler);
    }
  }

  function renderModalContent(state, upsells) {
    const headingId = 'lf-upsell-title-' + state.productId;
    state.headingId = headingId;
    state.addButtons = [];
    state.visitButtons = [];
    state.content.innerHTML = '';

    const header = createEl('div', 'lf-affiliates-upsell-modal__header');
    const heading = createEl('h2', 'lf-affiliates-upsell-modal__heading');
    heading.id = headingId;
    heading.textContent = 'Before you go';
    const closeBtn = createEl('button', 'lf-affiliates-upsell-modal__close', {
      type: 'button',
      'data-upsell-close': '1',
    });
    closeBtn.textContent = 'Close';
    header.appendChild(heading);
    header.appendChild(closeBtn);

    const intro = createEl('p', 'lf-affiliates-upsell-modal__intro');
    intro.textContent = 'Add these accessories to your cart so you have everything you need.';

    const notice = createEl('div', 'lf-affiliates-upsell-modal__notice');
    notice.setAttribute('role', 'alert');
    notice.setAttribute('aria-live', 'assertive');
    notice.hidden = true;

    const list = createEl('div', 'lf-affiliates-upsell-modal__list');
    upsells.forEach((item) => {
      const card = buildAccessoryCard(item);
      if (card.button && card.button.dataset) {
        if (card.button.dataset.upsellAdd === '1') {
          state.addButtons.push(card.button);
        } else if (card.button.dataset.upsellVisit === '1') {
          state.visitButtons.push(card.button);
        }
      }
      list.appendChild(card.element);
    });

    const footer = createEl('div', 'lf-affiliates-upsell-modal__footer');
    const skip = createEl('button', 'lf-affiliates-upsell-modal__skip', {
      type: 'button',
      'data-upsell-skip': '1',
    });
    skip.textContent = 'Skip & go to store';
    footer.appendChild(skip);

    state.content.appendChild(header);
    state.content.appendChild(intro);
    state.content.appendChild(notice);
    state.content.appendChild(list);
    state.content.appendChild(footer);

    state.notice = notice;
    state.skipButton = skip;
    state.closeButton = closeBtn;
    state.dialog.setAttribute('aria-labelledby', headingId);
  }

  function bindStateEvents(state) {
    if (!state) {
      return;
    }
    state.keydownHandler = (event) => handleDocumentKeydown(event);
    state.clickHandler = (event) => handleModalClick(event);

    document.addEventListener('keydown', state.keydownHandler, true);
    state.modal.addEventListener('click', state.clickHandler);
    activeState = state;
  }

  function unbindStateEvents(state) {
    if (!state) {
      return;
    }
    if (state.keydownHandler) {
      document.removeEventListener('keydown', state.keydownHandler, true);
    }
    if (state.clickHandler) {
      state.modal.removeEventListener('click', state.clickHandler);
    }
    if (activeState === state) {
      activeState = null;
    }
  }

  function closeModal(state, options) {
    const settings = options || {};
    const context = state || activeState;
    if (!context) {
      return;
    }

    detachActionHandlers(context);
    unbindStateEvents(context);

    context.modal.classList.remove('is-open');
    context.modal.setAttribute('aria-hidden', 'true');
    context.modal.hidden = true;
    showNotice(context, '');

    if (settings.restoreFocus !== false && context.trigger && typeof context.trigger.focus === 'function') {
      context.trigger.focus();
    }
  }

  function handleUpsellAddClick(event, state) {
    event.preventDefault();
    const button = event.currentTarget;
    const upsellId = button.getAttribute('data-upsell-id');
    if (!upsellId) {
      redirectToAffiliate(state);
      return;
    }

    showNotice(state, '');
    setButtonLoading(button, true);

    submitAjaxAddToCart(upsellId)
      .then(() => {
        rememberSkip(state.productId);
        logEvent('lf:affiliate:upsellAdd', {
          productId: state.productId,
          upsellId: upsellId,
          store: state.store || '',
        });
        closeModal(state, { restoreFocus: false });
        redirectToAffiliate(state);
      })
      .catch((err) => {
        const message = err && err.message ? err.message : 'Unable to add product to the cart.';
        showNotice(state, message);
      })
      .then(() => {
        setButtonLoading(button, false);
      });
  }

  function openModal(button, modal, upsells) {
    const dialog = modal.querySelector('.lf-affiliates-upsell-modal__dialog');
    const content = modal.querySelector('[data-upsell-content]');
    if (!dialog || !content) {
      return false;
    }

    const productId = modal.getAttribute('data-product-id') || button.getAttribute('data-product-id');
    const state = {
      modal: modal,
      dialog: dialog,
      content: content,
      productId: productId,
      trigger: button,
      store: button.getAttribute('data-store') || '',
      affiliateUrl: button.href,
      target: button.getAttribute('target') || '_self',
    };

    renderModalContent(state, upsells);
    attachActionHandlers(state);
    bindStateEvents(state);

    modal.hidden = false;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');

    focusInitialElement(state);
    return true;
  }

  function findModalForButton(button) {
    const container = closest(button, '.lf-affiliates');
    if (!container) {
      return null;
    }
    return container.querySelector('[data-upsell-modal]');
  }

  function handleAffiliateClick(event) {
    const button = event.currentTarget;
    const productId = button.getAttribute('data-product-id');
    const store = button.getAttribute('data-store') || '';
    const target = button.getAttribute('target') || '_self';
    const sku = button.getAttribute('data-sku') || '';

    const modal = findModalForButton(button);
    if (!productId || !modal) {
      logEvent('lf:affiliate:click', {
        productId: productId || '',
        store: store,
        target: target,
        sku: sku,
        hasUpsells: false,
        skipped: true,
      });
      return;
    }

    const upsells = getUpsells(productId);
    const hasUpsells = Array.isArray(upsells) && upsells.length > 0;
    const sessionSkip = shouldSkipForSession(productId);

    logEvent('lf:affiliate:click', {
      productId: productId,
      store: store,
      target: target,
      sku: sku,
      hasUpsells: hasUpsells,
      skipped: sessionSkip || !hasUpsells,
    });

    if (!hasUpsells || sessionSkip) {
      return;
    }

    if (!modal.querySelector('.lf-affiliates-upsell-modal__dialog') || !modal.querySelector('[data-upsell-content]')) {
      return;
    }

    event.preventDefault();
    openModal(button, modal, upsells);
  }

  function initAffiliateButtons(scope) {
    const context = scope || document;
    if (!context) {
      return;
    }

    primeUpsellData(context);

    const buttons = context.querySelectorAll('[data-affiliate-link]');
    buttons.forEach((button) => {
      if (button.dataset.lfAffiliateInit === '1') {
        return;
      }
      button.dataset.lfAffiliateInit = '1';
      button.addEventListener('click', handleAffiliateClick);
    });
  }

  function init() {
    primeUpsellData(document);
    initAffiliateButtons(document);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  document.addEventListener('elementor/render/callback', (event) => {
    initAffiliateButtons(event.target);
  });
})();
