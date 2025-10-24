(function($){
  if (typeof LimeFiltersWishlist === 'undefined') {
    return;
  }

  const state = {
    items: Array.isArray(LimeFiltersWishlist.items) ? LimeFiltersWishlist.items.slice() : []
  };

  const labels = LimeFiltersWishlist.labels || {
    add: 'Add to Wishlist',
    remove: 'Remove from Wishlist'
  };

  function updateBadge(count) {
    const value = parseInt(count, 10);
    const safeValue = isNaN(value) || value < 0 ? 0 : value;

    $('.wishlist_toggle').each(function(){
      const $wrapper = $(this);
      const $icon = $wrapper.find('.elementor-icon').first();
      let $badge = $wrapper.find('.lf-wishlist-count').first();

      if (!$badge.length) {
        $badge = $('<span>', {
          class: 'lf-wishlist-count',
          ariaHidden: 'true'
        });
        if ($icon.length) {
          $icon.append($badge);
        } else {
          $wrapper.append($badge);
        }
      }

      $badge.text(safeValue);
      const isVisible = safeValue > 0;
      $badge.toggleClass('is-visible', isVisible);
      $badge.attr('aria-hidden', isVisible ? 'false' : 'true');
      $wrapper.toggleClass('has-wishlist-items', isVisible);
    });
  }

  function isActive(id) {
    id = parseInt(id, 10);
    if (isNaN(id) || id < 1) {
      return false;
    }
    return state.items.indexOf(id) !== -1;
  }

  function syncButtons(items) {
    if (Array.isArray(items)) {
      state.items = items.map(function(id){ return parseInt(id, 10); }).filter(function(id){ return !isNaN(id) && id > 0; });
      LimeFiltersWishlist.items = state.items.slice();
    }
    $('.lf-wishlist-toggle').each(function(){
      const $btn = $(this);
      const productId = parseInt($btn.attr('data-product-id'), 10);
      const active = isActive(productId);
      $btn.toggleClass('is-active', active);
      const label = active ? labels.remove : labels.add;
      $btn.attr({
        'aria-pressed': active ? 'true' : 'false',
        'aria-label': label
      });
      const $sr = $btn.find('.sr-only');
      if ($sr.length) {
        $sr.text(label);
      }
    });
    updateBadge(state.items.length);
  }

  function showToast(toast) {
    if (!toast || !toast.message) {
      return;
    }

    let $container = $('.lf-wishlist-toast');
    if (!$container.length) {
      $container = $('<div>', { class: 'lf-wishlist-toast' }).appendTo('body');
    }

    const $message = $('<div>', { class: 'lf-wishlist-toast__item', text: toast.message });
    if (toast.url) {
      const $link = $('<a>', {
        class: 'lf-wishlist-toast__link',
        href: toast.url,
        text: LimeFiltersWishlist.strings.view
      });
      $message.append($link);
    }

    $container.append($message);

    setTimeout(function(){
      $message.addClass('is-visible');
    }, 10);

    setTimeout(function(){
      $message.removeClass('is-visible');
      setTimeout(function(){
        $message.remove();
      }, 300);
    }, 4000);
  }

  function toggleWishlist($button) {
    const productId = parseInt($button.attr('data-product-id'), 10);
    if (isNaN(productId) || productId < 1) {
      return;
    }

    $button.prop('disabled', true);

    $.post(LimeFiltersWishlist.ajax, {
      action: 'lf_toggle_wishlist',
      nonce: LimeFiltersWishlist.nonce,
      product_id: productId
    }).done(function(resp){
      if (resp && resp.success && resp.data) {
        if (Array.isArray(resp.data.items)) {
          state.items = resp.data.items.map(function(id){ return parseInt(id, 10); }).filter(function(id){ return !isNaN(id) && id > 0; });
        }
        syncButtons(state.items);
        $(document).trigger('lf:wishlist:update', [state.items]);
        if (resp.data.toast) {
          showToast(resp.data.toast);
        }
      }
    }).always(function(){
      $button.prop('disabled', false);
    });
  }

  $(document).on('click', '.lf-wishlist-toggle', function(e){
    e.preventDefault();
    toggleWishlist($(this));
  });

  $(document).on('lf:wishlist:update', function(event, items){
    if (Array.isArray(items)) {
      syncButtons(items);
    }
  });

  $(function(){
    syncButtons(state.items);
    updateBadge(state.items.length);
  });

})(jQuery);
