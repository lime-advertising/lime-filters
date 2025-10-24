(function($){
  const CHEVRON_SVG = '<svg class="lf-chevron" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"></path></svg>';

  function normalizeTerms(filter) {
    if (!filter || !Array.isArray(filter.terms)) {
      return [];
    }
    return filter.terms.reduce((acc, term) => {
      if (!term) return acc;
      const slug = term.slug ? String(term.slug) : '';
      const name = term.name ? String(term.name) : '';
      if (!slug || !name) {
        return acc;
      }
      const rawCount = typeof term.count !== 'undefined' ? parseInt(term.count, 10) : 0;
      const count = Number.isNaN(rawCount) ? 0 : rawCount;
      const checked = !!term.checked;
      if (!checked && count <= 0) {
        return acc;
      }
      acc.push({ slug, name, count, checked });
      return acc;
    }, []);
  }

  function buildTermLabel(term, showCounts) {
    const $label = $('<label>', {
      class: 'lf-check',
      'data-slug': term.slug,
      'data-count': term.count,
    });
    const $input = $('<input>', {
      type: 'checkbox',
      class: 'lf-checkbox',
      value: term.slug,
    });
    if (term.checked) {
      $input.prop('checked', true);
    }
    const $text = $('<span>', { class: 'lf-term-label' });
    $text.append($('<span>', { class: 'lf-term-name', text: term.name }));
    if (showCounts) {
      $text.append($('<span>', {
        class: 'lf-term-count',
        text: '(' + term.count + ')',
      }));
    }
    $label.append($input, $text);
    return $label;
  }

  function buildDropdownGroup(filter, terms, defaultState, showCounts) {
    const taxonomy = filter.taxonomy || '';
    const $details = $('<details>', {
      class: 'lf-group lf-dropdown',
      'data-tax': taxonomy,
      'data-type': filter.type || 'attribute',
    });
    const hasChecked = terms.some(term => term.checked);
    if (defaultState === 'expanded' || hasChecked) {
      $details.attr('open', true);
    }
    const $summary = $('<summary>', {
      class: 'lf-toggle',
      text: filter.label || '',
    });
    const $body = $('<div>', { class: 'lf-body' });
    terms.forEach(term => {
      $body.append(buildTermLabel(term, showCounts));
    });
    if (!$body.children().length) {
      return null;
    }
    $details.append($summary, $body);
    return $details;
  }

  function buildAccordionGroup(filter, terms, defaultState, showCounts, context) {
    const taxonomy = filter.taxonomy || '';
    const hasChecked = terms.some(term => term.checked);
    const expanded = context === 'modal' || defaultState === 'expanded' || hasChecked;
    const $group = $('<div>', {
      class: 'lf-group lf-accordion' + (expanded ? ' lf-open' : ''),
      'data-tax': taxonomy,
      'data-type': filter.type || 'attribute',
    });
    const $button = $('<button>', {
      class: 'lf-toggle',
      'aria-expanded': expanded ? 'true' : 'false',
    });
    $button.append($('<span>').text(filter.label || ''));
    $button.append($(CHEVRON_SVG));
    const $body = $('<div>', { class: 'lf-body' });
    terms.forEach(term => {
      $body.append(buildTermLabel(term, showCounts));
    });
    if (!$body.children().length) {
      return null;
    }
    $group.append($button, $body);
    return $group;
  }

  function renderFiltersInto($container, filters, layout, defaultState, showCounts, context) {
    $container.empty();
    if (!Array.isArray(filters) || !filters.length) {
      return;
    }
    filters.forEach(filter => {
      const terms = normalizeTerms(filter);
      if (!terms.length) {
        return;
      }
      const group = layout === 'horizontal'
        ? buildDropdownGroup(filter, terms, defaultState, showCounts)
        : buildAccordionGroup(filter, terms, defaultState, showCounts, context);
      if (group) {
        $container.append(group);
      }
    });
  }

  function updateFilterVisibility($root, hasFilters) {
    const $actions = $root.find('.lf-actions');
    if ($actions.length) {
      hasFilters ? $actions.show() : $actions.hide();
    }
    $root.find('.lf-empty').toggle(!hasFilters);
    const $mobileBar = $root.find('.lf-mobile-bar');
    if ($mobileBar.length) {
      $mobileBar.toggleClass('is-hidden', !hasFilters);
      $mobileBar.attr('aria-hidden', hasFilters ? 'false' : 'true');
      $mobileBar.find('[data-role="toggle-mobile-filters"]').prop('disabled', !hasFilters);
      if (!hasFilters) {
        $mobileBar.find('[data-role="toggle-mobile-filters"]').attr('aria-expanded', 'false');
      }
    }
    if (!hasFilters) {
      closeMobileFilters($root);
    }
  }

  function updateFilters($root, filters) {
    const layout = ($root.data('layout') === 'horizontal') ? 'horizontal' : 'sidebar';
    const defaultState = ($root.data('default') === 'expanded') ? 'expanded' : 'collapsed';
    const showCounts = ($root.data('show-counts') === 'yes');

    $root.find('.lf-filters').each(function(){
      const $container = $(this);
      const context = $container.data('context') || 'main';
      const containerLayout = (context === 'main' && layout === 'horizontal') ? 'horizontal' : 'sidebar';
      renderFiltersInto($container, filters, containerLayout, defaultState, showCounts, context);
    });

    const hasFilters = $root.find('.lf-filters[data-context="main"] .lf-group').length > 0;
    updateFilterVisibility($root, hasFilters);
    updateChips($root, filters);
  }

  function updateChips($root, filters) {
    const $chips = $root.find('.lf-chips');
    if (!$chips.length) return;

    const fragments = [];
    filters.forEach(filter => {
      const taxonomy = filter.taxonomy || '';
      const label = filter.label || '';
      if (!Array.isArray(filter.terms)) return;
      filter.terms.forEach(term => {
        if (!term || !term.checked) return;
        const slug = term.slug || '';
        const name = term.name || '';
        if (!slug || !name) return;
        const $chip = $('<button>', {
          type: 'button',
          class: 'lf-chip',
          'data-tax': taxonomy,
          'data-slug': slug,
        });
        $chip.append($('<div>', { class: 'lf-chip__label', text: name }));
        $chip.attr('aria-label', `Remove ${name}`);
        $chip.append($('<span>', { class: 'lf-chip__remove', html: '&times;' }));
        fragments.push($chip);
      });
    });

    $chips.empty();
    if (!fragments.length) {
      $chips.removeClass('is-active');
      return;
    }
    $chips.addClass('is-active');
    fragments.forEach($chip => $chips.append($chip));
  }

  const HISTORY_SUPPORTED = typeof window !== 'undefined' && window.history && typeof window.history.replaceState === 'function';

  function cleanFiltersObject(filters) {
    const cleaned = {};
    if (!filters || typeof filters !== 'object') {
      return cleaned;
    }
    Object.keys(filters).forEach(tax => {
      const vals = Array.isArray(filters[tax]) ? filters[tax].filter(Boolean) : [];
      if (vals.length) {
        cleaned[tax] = vals;
      }
    });
    return cleaned;
  }

  function updateUrlState(filters, orderby, page) {
    if (!HISTORY_SUPPORTED) {
      return;
    }

    let url;
    try {
      url = new URL(window.location.href);
    } catch (err) {
      return;
    }

    const cleaned = cleanFiltersObject(filters);

    url.searchParams.delete('lf_filters');
    url.searchParams.delete('lf_orderby');
    url.searchParams.delete('lf_page');

    if (Object.keys(cleaned).length) {
      try {
        url.searchParams.set('lf_filters', JSON.stringify(cleaned));
      } catch (err) {
        // ignore malformed JSON
      }
    }

    if (orderby && orderby !== 'menu_order') {
      url.searchParams.set('lf_orderby', orderby);
    }

    if (page && page > 1) {
      url.searchParams.set('lf_page', page);
    }

    window.history.replaceState(null, '', url.toString());
  }

  function openMobileFilters($root) {
    if (window.innerWidth >= 992) return;
    const $panel = $root.find('[data-role="mobile-filters"]');
    if (!$panel.length || $panel.hasClass('is-open')) return;
    const $backdrop = $root.find('.lf-offcanvas-backdrop');
    const $toggle = $root.find('[data-role="toggle-mobile-filters"]').first();
    if ($toggle.length) {
      $panel.data('return-focus', $toggle.get(0));
      $toggle.attr('aria-expanded', 'true');
    }
    $panel.addClass('is-open').attr('aria-hidden', 'false');
    if ($backdrop.length) {
      $backdrop.addClass('is-visible').attr('aria-hidden', 'false');
    }
    $root.addClass('lf-offcanvas-active');
    $('body').addClass('lf-offcanvas-open');
    const $close = $panel.find('[data-role="close-mobile-filters"]').first();
    if ($close.length) {
      setTimeout(() => $close.trigger('focus'), 30);
    }
  }

  function closeMobileFilters($root) {
    const $panel = $root.find('[data-role="mobile-filters"]');
    if (!$panel.length || !$panel.hasClass('is-open')) return;
    const $backdrop = $root.find('.lf-offcanvas-backdrop');
    $panel.removeClass('is-open').attr('aria-hidden', 'true');
    if ($backdrop.length) {
      $backdrop.removeClass('is-visible').attr('aria-hidden', 'true');
    }
    $root.removeClass('lf-offcanvas-active');
    const $toggle = $root.find('[data-role="toggle-mobile-filters"]').first();
    if ($toggle.length) {
      $toggle.attr('aria-expanded', 'false');
    }
    const returnFocus = $panel.data('return-focus');
    if (returnFocus && typeof returnFocus.focus === 'function') {
      returnFocus.focus();
    }
    $panel.removeData('return-focus');
    if (!$('.lime-filters.lf-offcanvas-active').length) {
      $('body').removeClass('lf-offcanvas-open');
    }
  }


  // State helpers
  function gatherSelections($root) {
    const data = {};
    $root.find('.lf-group').each(function(){
      const tax = $(this).data('tax');
      if (!tax) return;
      const vals = [];
      $(this).find('input.lf-checkbox:checked').each(function(){
        vals.push($(this).val());
      });
      if (vals.length) data[tax] = vals;
    });
    return data;
  }

  function currentSort($root) {
    const $sel = $root.find('.lf-sort-select').first();
    return $sel.length ? $sel.val() : 'menu_order';
  }

  function request($root, page) {
    const category = $root.data('category') || '';
    const filters  = gatherSelections($root);
    const orderby  = currentSort($root);
    const $results = $root.find('.lf-results').first();
    const paginationEnabled = ($root.data('pagination') || '') === 'yes';
    const nextPage = typeof page === 'number' ? page : 1;
    const perPageAttr = parseInt($root.data('per-page'), 10);
    const perPage = isNaN(perPageAttr) ? 0 : perPageAttr;
    const showCounts = ($root.data('show-counts') === 'yes') ? 'yes' : 'no';
    const columns = {
      desktop: parseInt($root.attr('data-columns-desktop'), 10) || 0,
      tablet: parseInt($root.attr('data-columns-tablet'), 10) || 0,
      mobile: parseInt($root.attr('data-columns-mobile'), 10) || 0,
    };

    $root.addClass('lf-loading');
    if ($results.length) {
      $results.addClass('lf-results--loading');
    }

    return $.post(LimeFilters.ajax, {
      action: 'lime_filter_products',
      nonce: LimeFilters.nonce,
      category: category,
      filters: filters,
      orderby: orderby,
      page: nextPage,
      pagination: paginationEnabled ? 'yes' : 'no',
      per_page: perPage,
      show_counts: showCounts,
      columns: columns
    }).done(function(resp){
      if (resp && resp.success && resp.data && resp.data.html) {
        if ($results.length) {
          $results.html(resp.data.html);
        } else {
          $root.after(resp.data.html);
        }
        if (resp.data.pagination !== undefined) {
          const $pagination = $root.find('.lf-pagination').first();
          if ($pagination.length) {
            $pagination.html(resp.data.pagination || '');
          }
        }
        if (resp.data.filters !== undefined) {
          const filtersPayload = Array.isArray(resp.data.filters) ? resp.data.filters : [];
          updateFilters($root, filtersPayload);
        } else {
          updateFilters($root, []);
        }
        if (resp.data.page) {
          $root.attr('data-page', resp.data.page);
        } else {
          $root.attr('data-page', nextPage);
        }
        if (typeof resp.data.per_page !== 'undefined') {
          $root.attr('data-per-page', resp.data.per_page);
        }
        if (resp.data.columns) {
          if (resp.data.columns.desktop) {
            $root.attr('data-columns-desktop', resp.data.columns.desktop);
          }
          if (resp.data.columns.tablet) {
            $root.attr('data-columns-tablet', resp.data.columns.tablet);
          }
          if (resp.data.columns.mobile) {
            $root.attr('data-columns-mobile', resp.data.columns.mobile);
          }
        }
        if (resp.data.wishlist !== undefined) {
          $(document).trigger('lf:wishlist:update', [resp.data.wishlist]);
        }

        const finalPage = resp && resp.data && resp.data.page ? parseInt(resp.data.page, 10) || nextPage : nextPage;
        const activeFilters = gatherSelections($root);
        updateUrlState(activeFilters, orderby, finalPage);
      }
    }).always(function(){
      $root.removeClass('lf-loading');
      if ($results.length) {
        $results.removeClass('lf-results--loading');
      }
    });
  }

  // Accordion toggle (sidebar custom)
  $(document).on('click', '.lf-accordion .lf-toggle', function(e){
    e.preventDefault();
    const $group = $(this).closest('.lf-group');
    const expanded = $(this).attr('aria-expanded') === 'true';
    $(this).attr('aria-expanded', expanded ? 'false':'true');
    $group.toggleClass('lf-open');
  });

  // Checkbox change triggers request (desktop)
  $(document).on('change', '.lime-filters input.lf-checkbox', function(){
    const $root = $(this).closest('.lime-filters');
    request($root, 1);
  });

  // Sort change triggers request
  $(document).on('change', '.lime-filters .lf-sort-select', function(){
    const $root = $(this).closest('.lime-filters');
    request($root, 1);
  });

  // Clear all
  $(document).on('click', '.lime-filters .lf-clear', function(e){
    e.preventDefault();
    const $scope = $(this).closest('.lime-filters');
    $scope.find('input.lf-checkbox').prop('checked', false);
    request($scope, 1);
  });

  // Chip remove
  $(document).on('click', '.lf-chip', function(){
    const tax = $(this).data('tax') || '';
    const slug = $(this).data('slug') || '';
    if (!tax || !slug) return;
    const $root = $(this).closest('.lime-filters');
    const selector = '.lf-group[data-tax="'+tax+'"] input.lf-checkbox[value="'+slug+'"]';
    const $checkboxes = $root.find(selector);
    if (!$checkboxes.length) return;
    $checkboxes.prop('checked', false).trigger('change');
  });

  $(document).on('click', '[data-role="toggle-mobile-filters"]', function(e){
    e.preventDefault();
    openMobileFilters($(this).closest('.lime-filters'));
  });

  $(document).on('click', '[data-role="close-mobile-filters"]', function(e){
    e.preventDefault();
    closeMobileFilters($(this).closest('.lime-filters'));
  });

  $(document).on('keyup', function(e){
    if (e.key === 'Escape') {
      $('.lime-filters.lf-offcanvas-active').each(function(){
        closeMobileFilters($(this));
      });
    }
  });

  $(window).on('resize', function(){
    if (window.innerWidth >= 992) {
      $('.lime-filters.lf-offcanvas-active').each(function(){
        closeMobileFilters($(this));
      });
    }
  });

  // Pagination
  $(document).on('click', '.lime-filters .lf-page', function(){
    const page = parseInt($(this).data('page'), 10) || 1;
    const $root = $(this).closest('.lime-filters');
    if ($(this).hasClass('is-active')) {
      return;
    }
    request($root, page);
  });

  $(function(){
    $('.lime-filters').each(function(){
      const $root = $(this);
      const raw = $root.attr('data-filters-initial');
      if (!raw) {
        updateFilters($root, []);
        return;
      }
      try {
        const parsed = JSON.parse(raw);
        updateFilters($root, Array.isArray(parsed) ? parsed : []);
      } catch (err) {
        updateFilters($root, []);
      }
      $root.removeAttr('data-filters-initial');
    });
  });

})(jQuery);
