(function(){
  const data = window.LimeFiltersAdmin || {};
  const attributeList = Array.isArray(data.attributes) ? data.attributes : [];
  const i18n = data.i18n || {};
  const placeholder = i18n.placeholder || 'Add attribute';
  const noMatches = i18n.noMatches || 'No matching attributes';
  const removeLabel = i18n.remove || 'Remove attribute';

  function findAttribute(slug){
    return attributeList.find(attr => attr.slug === slug) || null;
  }

  function renderPills(pillsWrap, items, onRemove){
    pillsWrap.innerHTML = '';
    items.forEach(slug => {
      const attr = findAttribute(slug);
      const label = attr ? attr.label : slug;

      const pill = document.createElement('span');
      pill.className = 'lf-attr-pill';

      const text = document.createElement('span');
      text.textContent = label;
      pill.appendChild(text);

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.setAttribute('aria-label', `${removeLabel} ${label}`);
      removeBtn.innerHTML = '&times;';
      removeBtn.addEventListener('click', () => onRemove(slug));
      pill.appendChild(removeBtn);

      pillsWrap.appendChild(pill);
    });
  }

  function buildSuggestionLabel(attr){
    if (!attr || !attr.slug) return '';
    const label = attr.label || attr.slug;
    return `${label} (${attr.slug})`;
  }

  function createSuggestionButton(attr){
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'lf-attr-suggestion';
    btn.dataset.slug = attr.slug;
    btn.textContent = buildSuggestionLabel(attr);
    return btn;
  }

  function enhanceField(input){
    if (!input || input.dataset.lfEnhanced) return;
    input.dataset.lfEnhanced = '1';

    let initial = [];
    try {
      if (input.dataset.initial) {
        const parsed = JSON.parse(input.dataset.initial);
        if (Array.isArray(parsed)) {
          initial = parsed.filter(Boolean);
        }
      }
    } catch (err) {
      initial = [];
    }

    let selected = initial.slice();

    const container = document.createElement('div');
    container.className = 'lf-attr-control';

    const pillsWrap = document.createElement('div');
    pillsWrap.className = 'lf-attr-pills';
    container.appendChild(pillsWrap);

    const search = document.createElement('input');
    search.type = 'text';
    search.className = 'lf-attr-search';
    search.placeholder = placeholder;
    search.autocomplete = 'off';
    container.appendChild(search);

    const suggestions = document.createElement('div');
    suggestions.className = 'lf-attr-suggestions';
    suggestions.hidden = true;
    container.appendChild(suggestions);

    input.classList.add('lf-attr-raw--hidden');
    input.parentNode.insertBefore(container, input.nextSibling);

    function updateInputValue(){
      input.value = selected.join(', ');
    }

    function addSelection(rawSlug){
      const slug = (rawSlug || '').trim();
      if (!slug) return;
      if (selected.includes(slug)) return;
      selected.push(slug);
      selected = selected.filter(Boolean);
      renderPills(pillsWrap, selected, removeSelection);
      updateInputValue();
    }

    function removeSelection(slug){
      selected = selected.filter(item => item !== slug);
      renderPills(pillsWrap, selected, removeSelection);
      updateInputValue();
    }

    function hideSuggestions(){
      suggestions.hidden = true;
      suggestions.innerHTML = '';
      activeIndex = -1;
    }

    function showSuggestions(list){
      suggestions.innerHTML = '';
      if (!list.length) {
        const empty = document.createElement('div');
        empty.className = 'lf-attr-suggestion is-empty';
        empty.textContent = noMatches;
        suggestions.appendChild(empty);
        suggestions.hidden = false;
        activeIndex = -1;
        return;
      }

      list.forEach(attr => {
        const btn = createSuggestionButton(attr);
        btn.addEventListener('click', () => {
          addSelection(btn.dataset.slug);
          search.value = '';
          hideSuggestions();
          search.focus();
        });
        suggestions.appendChild(btn);
      });

      suggestions.hidden = false;
      setActiveSuggestion(0);
    }

    function filterSuggestions(query){
      const term = query.trim().toLowerCase();
      const available = attributeList.filter(attr => !selected.includes(attr.slug));
      if (!term) {
        return available.slice(0, 10);
      }
      return available.filter(attr => {
        const label = (attr.label || '').toLowerCase();
        return attr.slug.toLowerCase().includes(term) || label.includes(term);
      }).slice(0, 10);
    }

    function handleInput(){
      const value = search.value;
      const matches = filterSuggestions(value);
      if (!matches.length && !value.trim()) {
        hideSuggestions();
        return;
      }
      showSuggestions(matches);
    }

    let activeIndex = -1;
    function setActiveSuggestion(newIndex){
      const items = suggestions.querySelectorAll('.lf-attr-suggestion');
      if (!items.length) {
        activeIndex = -1;
        return;
      }
      if (newIndex < 0) newIndex = items.length - 1;
      if (newIndex >= items.length) newIndex = 0;
      items.forEach(item => item.classList.remove('is-active'));
      items[newIndex].classList.add('is-active');
      items[newIndex].scrollIntoView({ block: 'nearest' });
      activeIndex = newIndex;
    }

    function selectActiveSuggestion(){
      if (activeIndex < 0) return false;
      const items = suggestions.querySelectorAll('.lf-attr-suggestion');
      const target = items[activeIndex];
      if (!target) return false;
      addSelection(target.dataset.slug);
      search.value = '';
      hideSuggestions();
      return true;
    }

    search.addEventListener('input', handleInput);

    search.addEventListener('keydown', function(event){
      if (event.key === 'ArrowDown') {
        const items = suggestions.querySelectorAll('.lf-attr-suggestion');
        if (items.length) {
          event.preventDefault();
          setActiveSuggestion(activeIndex + 1);
        }
      } else if (event.key === 'ArrowUp') {
        const items = suggestions.querySelectorAll('.lf-attr-suggestion');
        if (items.length) {
          event.preventDefault();
          setActiveSuggestion(activeIndex - 1);
        }
      } else if (event.key === 'Enter') {
        if (selectActiveSuggestion()) {
          event.preventDefault();
        } else if (search.value.trim()) {
          event.preventDefault();
          addSelection(search.value.trim());
          search.value = '';
          hideSuggestions();
        }
      } else if (event.key === 'Escape') {
        hideSuggestions();
      } else if (event.key === 'Backspace' && !search.value) {
        const last = selected[selected.length - 1];
        if (last) {
          removeSelection(last);
        }
      } else if (event.key === ',') {
        if (search.value.trim()) {
          event.preventDefault();
          addSelection(search.value.replace(',', '').trim());
          search.value = '';
          hideSuggestions();
        }
      }
    });

    document.addEventListener('click', function(event){
      if (!container.contains(event.target)) {
        hideSuggestions();
      }
    });

    renderPills(pillsWrap, selected, removeSelection);
    updateInputValue();
  }

  document.addEventListener('DOMContentLoaded', function(){
    const fields = document.querySelectorAll('.lf-attr-raw');
    fields.forEach(enhanceField);
  });
})();
