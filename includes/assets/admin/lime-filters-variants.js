(function () {
  const root = document.getElementById('lf-variants-root');
  const hiddenInput = document.getElementById('lf-variant-payload');
  const toggle = document.getElementById('lf-variants-enabled');
  const appendToggle = document.getElementById('lf-variants-append-gallery');
  const data = window.LFVariantsAdmin || {};

  if (!root || !hiddenInput || !toggle) {
    return;
  }

  const attributes = Array.isArray(data.attributes) ? data.attributes : [];
  const attributeMap = {};
  attributes.forEach((attribute) => {
    if (attribute && attribute.slug) {
      attributeMap[attribute.slug] = attribute;
    }
  });

  const stores = typeof data.stores === 'object' && data.stores !== null ? data.stores : {};
  const storeKeys = Object.keys(stores);
  const i18n = data.i18n || {};
  const payloadVersion = data.version || 2;

  if (appendToggle) {
    appendToggle.checked = data.append_gallery === 'yes' || data.appendGallery === 'yes';
  }

  let selectedAttributes = normalizeSelectedAttributes(Array.isArray(data.selected_attributes) ? data.selected_attributes : []);
  let variants = normalizeVariantEntries(data.variants || {});
  let openVariantKey = null;

  if (!Object.keys(variants).length && hiddenInput.value) {
    try {
      const parsed = JSON.parse(hiddenInput.value);
      if (parsed && typeof parsed === 'object') {
        if (Array.isArray(parsed.attributes)) {
          selectedAttributes = normalizeSelectedAttributes(parsed.attributes);
        }
        if (parsed.variants && typeof parsed.variants === 'object') {
          variants = normalizeVariantEntries(parsed.variants);
        }
        const parsedKeys = Object.keys(variants);
        openVariantKey = parsedKeys.length ? parsedKeys[0] : null;
      }
    } catch (err) {
      variants = {};
    }
  }

  const initialKeys = variantKeysSorted();
  openVariantKey = initialKeys.length ? initialKeys[0] : null;

  function clone(source) {
    return JSON.parse(JSON.stringify(source || {}));
  }

  function normalizeSelectedAttributes(list) {
    const normalized = [];
    list.forEach((slug) => {
      const attributeSlug = sanitizeSlug(slug);
      if (attributeSlug && attributeMap[attributeSlug] && !normalized.includes(attributeSlug)) {
        normalized.push(attributeSlug);
      }
    });
    return normalized;
  }

  function sanitizeSlug(value) {
    if (typeof value !== 'string') {
      return '';
    }
    return value.trim();
  }

  function variantKey(combo) {
    const pairs = [];
    Object.keys(combo)
      .sort()
      .forEach((attributeSlug) => {
        pairs.push(`${attributeSlug}=${combo[attributeSlug]}`);
      });
    return pairs.join('|');
  }

  function variantKeysSorted() {
    return Object.keys(variants).sort();
  }

  function refreshOpenVariantKey() {
    const keys = variantKeysSorted();
    if (!keys.length) {
      openVariantKey = null;
      return;
    }
    if (!openVariantKey || !variants[openVariantKey]) {
      openVariantKey = keys[0];
    }
  }

  function makeSafeId(key) {
    return String(key || '')
      .toLowerCase()
      .replace(/[^a-z0-9_-]+/g, '-');
  }

  function handleRowToggle(key) {
    if (!key) {
      return;
    }
    if (openVariantKey === key) {
      openVariantKey = null;
    } else {
      openVariantKey = key;
    }
    updateAccordionState();
  }

  function clearOpenVariantIfNeeded(key) {
    if (openVariantKey === key) {
      openVariantKey = null;
    }
  }

  function updateAccordionState() {
    const rows = root.querySelectorAll('.lf-variants-row');
    if (!rows.length) {
      openVariantKey = null;
      return;
    }

    const hasOpen = openVariantKey && variants[openVariantKey];
    if (openVariantKey !== null && !hasOpen) {
      const keys = variantKeysSorted();
      openVariantKey = keys.length ? keys[0] : null;
    }

    rows.forEach((row) => {
      const key = row.dataset.variantKey;
      const isOpen = key === openVariantKey;
      row.classList.toggle('is-open', isOpen);
      const body = row.querySelector('.lf-variants-row__body');
      if (body) {
        body.hidden = !isOpen;
      }
      const toggleBtn = row.querySelector('.lf-variants-row__toggle');
      if (toggleBtn) {
        toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        toggleBtn.textContent = isOpen ? (i18n.collapseRow || 'Hide details') : (i18n.expandRow || 'Show details');
      }
    });
  }

  function ensureAffiliatePlaceholders(entry) {
    if (!entry.affiliates) {
      entry.affiliates = {};
    }
    storeKeys.forEach((key) => {
      if (typeof entry.affiliates[key] === 'undefined') {
        entry.affiliates[key] = '';
      }
    });
  }

  function normalizeVariantEntries(rawEntries) {
    const normalized = {};
    Object.keys(rawEntries).forEach((key) => {
      const entry = rawEntries[key];
      if (!entry || typeof entry !== 'object') {
        return;
      }

      const combo = {};
      const sourceCombo = entry.attributes || entry.combo || {};
      Object.keys(sourceCombo).forEach((attributeSlug) => {
        const attrSlug = sanitizeSlug(attributeSlug);
        const termSlug = sanitizeSlug(sourceCombo[attributeSlug]);
        if (attrSlug && termSlug && attributeMap[attrSlug] && attributeMap[attrSlug].terms && attributeMap[attrSlug].terms[termSlug]) {
          combo[attrSlug] = termSlug;
        }
      });

      if (!Object.keys(combo).length) {
        return;
      }

      const resolvedKey = variantKey(combo);
      normalized[resolvedKey] = {
        key: resolvedKey,
        attributes: combo,
        image_id: entry.image_id ? parseInt(entry.image_id, 10) : 0,
        image_url: entry.image_url || '',
        sku: entry.sku || '',
        upc: entry.upc || '',
        affiliates: clone(entry.affiliates || {}),
        extras: clone(entry.extras || {}),
      };
      ensureAffiliatePlaceholders(normalized[resolvedKey]);
    });

    return normalized;
  }

  function defaultVariant(combo) {
    const entry = {
      attributes: combo,
      image_id: 0,
      image_url: '',
      sku: '',
      upc: '',
      affiliates: {},
      extras: {},
    };
    ensureAffiliatePlaceholders(entry);
    return entry;
  }

  function ensureVariant(combo) {
    const key = variantKey(combo);
    if (!variants[key]) {
      variants[key] = {
        key,
        ...defaultVariant(combo),
      };
    }
    return variants[key];
  }

  function pruneVariantsForSelection() {
    if (!selectedAttributes.length) {
      return;
    }
    Object.keys(variants).forEach((key) => {
      const entry = variants[key];
      if (!entry || !entry.attributes) {
        delete variants[key];
        return;
      }
      const combo = entry.attributes;
      const missing = selectedAttributes.some((slug) => !combo[slug]);
      if (missing) {
        delete variants[key];
      }
    });
  }

  function speak(message) {
    if (typeof message !== 'string' || !message) {
      return;
    }
    if (window.wp && window.wp.a11y && typeof window.wp.a11y.speak === 'function') {
      window.wp.a11y.speak(message);
    }
  }

  function syncHiddenField() {
    const payload = {
      version: payloadVersion,
      attributes: selectedAttributes,
      variants: {},
    };

    Object.keys(variants).forEach((key) => {
      const entry = variants[key];
      if (!entry || !entry.attributes || !Object.keys(entry.attributes).length) {
        return;
      }
      const variantPayload = {
        attributes: entry.attributes,
      };
      if (entry.image_id) {
        variantPayload.image_id = entry.image_id;
      }
      if (entry.sku) {
        variantPayload.sku = entry.sku.trim();
      }
      if (entry.upc) {
        variantPayload.upc = entry.upc.trim();
      }
      if (entry.affiliates) {
        const affiliates = {};
        Object.keys(entry.affiliates).forEach((store) => {
          const value = entry.affiliates[store];
          if (typeof value === 'string' && value.trim() !== '') {
            affiliates[store] = value.trim();
          }
        });
        if (Object.keys(affiliates).length) {
          variantPayload.affiliates = affiliates;
        }
      }
      if (entry.extras && Object.keys(entry.extras).length) {
        variantPayload.extras = entry.extras;
      }
      payload.variants[key] = variantPayload;
    });

    hiddenInput.value = JSON.stringify(payload);
  }

  function removeVariant(key) {
    if (!variants[key]) {
      return;
    }
    delete variants[key];
    clearOpenVariantIfNeeded(key);
    render();
    syncHiddenField();
  }

  function handleImageSelection(key) {
    if (!window.wp || !wp.media) {
      return;
    }
    const entry = variants[key];
    if (!entry) {
      return;
    }

    const frame = wp.media({
      title: i18n.imageSelect || 'Select image',
      multiple: false,
    });

    frame.on('select', () => {
      const attachment = frame.state().get('selection').first().toJSON();
      entry.image_id = attachment.id;
      if (attachment.sizes && attachment.sizes.thumbnail) {
        entry.image_url = attachment.sizes.thumbnail.url;
      } else {
        entry.image_url = attachment.url;
      }
      render();
      syncHiddenField();
    });

    frame.open();
  }

  function renderAffiliateFields(container, entry) {
    if (!storeKeys.length) {
      return;
    }
    const wrap = createEl('div', 'lf-variants-affiliates');
    storeKeys.forEach((storeKey) => {
      const meta = stores[storeKey] || {};
      const labelText = meta.label || storeKey;
      const item = createEl('div', 'lf-variants-affiliates__item');
      const labelEl = createEl('label', null, labelText);
      labelEl.setAttribute('for', `lf-variant-affiliate-${entry.key}-${storeKey}`);
      const input = createEl('input');
      input.type = 'url';
      input.id = `lf-variant-affiliate-${entry.key}-${storeKey}`;
      input.value = entry.affiliates && entry.affiliates[storeKey] ? entry.affiliates[storeKey] : '';
      input.placeholder = i18n.affiliateLabel || 'Affiliate URL';
      input.addEventListener('input', () => {
        ensureAffiliatePlaceholders(entry);
        entry.affiliates[storeKey] = input.value;
        syncHiddenField();
      });
      item.appendChild(labelEl);
      item.appendChild(input);
      wrap.appendChild(item);
    });
    container.appendChild(wrap);
  }

  function formatCombinationLabel(combo) {
    const order = selectedAttributes.length ? selectedAttributes : Object.keys(combo).sort();
    const labels = [];
    order.forEach((attributeSlug) => {
      const attr = attributeMap[attributeSlug];
      const termSlug = combo[attributeSlug];
      if (!termSlug || !attr) {
        return;
      }
      const term = attr.terms && attr.terms[termSlug] ? attr.terms[termSlug] : null;
      const attrLabel = attr.label || attributeSlug;
      const termLabel = term && term.name ? term.name : termSlug;
      labels.push(`${attrLabel}: ${termLabel}`);
    });
    if (!labels.length) {
      return i18n.combinationLabel || 'Combination';
    }
    const separator = i18n.attributeBadgeSeparator || ' / ';
    return labels.join(separator);
  }

  function renderVariantRow(entry) {
    const row = createEl('div', 'lf-variants-row');
    row.dataset.variantKey = entry.key;
    const header = createEl('div', 'lf-variants-row__header');
    const title = createEl('h4', 'lf-variants-row__title', formatCombinationLabel(entry.attributes));
    header.appendChild(title);

    const actions = createEl('div', 'lf-variants-row__actions');
    const safeKey = makeSafeId(entry.key);
    const bodyId = `lf-variant-body-${safeKey}`;
    const toggleBtn = createEl('button', 'button-link lf-variants-row__toggle', i18n.expandRow || 'Show details');
    toggleBtn.type = 'button';
    toggleBtn.setAttribute('aria-expanded', 'false');
    toggleBtn.setAttribute('aria-controls', bodyId);
    toggleBtn.addEventListener('click', () => handleRowToggle(entry.key));
    actions.appendChild(toggleBtn);
    const removeBtn = createEl('button', 'button-link-delete', i18n.removeRow || 'Remove variant');
    removeBtn.type = 'button';
    removeBtn.addEventListener('click', () => removeVariant(entry.key));
    actions.appendChild(removeBtn);
    header.appendChild(actions);
    row.appendChild(header);

    const body = createEl('div', 'lf-variants-row__body');
    body.id = bodyId;
    body.hidden = true;

    const media = createEl('div', 'lf-variants-media');
    const preview = createEl('div', 'lf-variants-media__preview');
    if (entry.image_url) {
      const img = createEl('img');
      img.src = entry.image_url;
      img.alt = formatCombinationLabel(entry.attributes);
      preview.appendChild(img);
    } else {
      preview.textContent = i18n.imageSelect || 'Select image';
    }
    const mediaButtons = createEl('div', 'lf-variants-media__actions');
    const selectBtn = createEl(
      'button',
      'button button-secondary',
      entry.image_id ? (i18n.imageChange || 'Change image') : (i18n.imageSelect || 'Select image')
    );
    selectBtn.type = 'button';
    selectBtn.addEventListener('click', () => handleImageSelection(entry.key));
    mediaButtons.appendChild(selectBtn);

    if (entry.image_id) {
      const removeImg = createEl('button', 'button-link-delete', i18n.imageRemove || 'Remove image');
      removeImg.type = 'button';
      removeImg.addEventListener('click', () => {
        entry.image_id = 0;
        entry.image_url = '';
        render();
        syncHiddenField();
      });
      mediaButtons.appendChild(removeImg);
    }

    media.appendChild(preview);
    media.appendChild(mediaButtons);
    body.appendChild(media);

    const skuField = createEl('div', 'lf-variants-field');
    const skuLabel = createEl('label', null, i18n.skuLabel || 'SKU Override');
    skuLabel.setAttribute('for', `lf-variant-sku-${entry.key}`);
    const skuInput = createEl('input');
    skuInput.type = 'text';
    skuInput.id = `lf-variant-sku-${entry.key}`;
    skuInput.value = entry.sku || '';
    skuInput.addEventListener('input', () => {
      entry.sku = skuInput.value;
      syncHiddenField();
    });
    skuField.appendChild(skuLabel);
    skuField.appendChild(skuInput);
    body.appendChild(skuField);

    const upcField = createEl('div', 'lf-variants-field');
    const upcLabel = createEl('label', null, i18n.upcLabel || 'UPC');
    upcLabel.setAttribute('for', `lf-variant-upc-${entry.key}`);
    const upcInput = createEl('input');
    upcInput.type = 'text';
    upcInput.id = `lf-variant-upc-${entry.key}`;
    upcInput.value = entry.upc || '';
    upcInput.addEventListener('input', () => {
      entry.upc = upcInput.value;
      syncHiddenField();
    });
    upcField.appendChild(upcLabel);
    upcField.appendChild(upcInput);
    body.appendChild(upcField);

    renderAffiliateFields(body, entry);
    row.appendChild(body);
    return row;
  }

  function renderAttributeSelector() {
    const section = createEl('div', 'lf-variants-section lf-variants-section--attributes');
    const title = createEl('h3', 'lf-variants-section__title', i18n.attributeToggleLabel || 'Attributes used for variants');
    section.appendChild(title);

    const list = createEl('div', 'lf-variants-attribute-list');
    attributes.forEach((attribute) => {
      if (!attribute || !attribute.slug || !attribute.terms || !Object.keys(attribute.terms).length) {
        return;
      }
      const item = createEl('label', 'lf-variants-attribute');
      const input = createEl('input');
      input.type = 'checkbox';
      input.value = attribute.slug;
      input.checked = selectedAttributes.includes(attribute.slug);
      input.addEventListener('change', () => {
        if (input.checked) {
          if (!selectedAttributes.includes(attribute.slug)) {
            selectedAttributes.push(attribute.slug);
          }
        } else {
          selectedAttributes = selectedAttributes.filter((slug) => slug !== attribute.slug);
        }
        pruneVariantsForSelection();
        render();
        syncHiddenField();
      });
      item.appendChild(input);
      item.appendChild(createEl('span', null, attribute.label || attribute.slug));
      list.appendChild(item);
    });

    if (!list.children.length) {
      const empty = createEl('p', 'lf-variants-app__empty', i18n.noAttributes || 'Assign attributes to configure variants.');
      section.appendChild(empty);
    } else {
      section.appendChild(list);
    }

    return section;
  }

  function renderCombinationControls() {
    const section = createEl('div', 'lf-variants-section lf-variants-section--builder');
    const header = createEl('div', 'lf-variants-section__header');
    const title = createEl('h3', 'lf-variants-section__title', i18n.addVariant || 'Add Combination');
    header.appendChild(title);
    section.appendChild(header);

    if (!selectedAttributes.length) {
      const note = createEl('p', 'lf-variants-app__empty', i18n.noAttributeSelection || 'Select at least one attribute to build combinations.');
      section.appendChild(note);
      return section;
    }

    const form = createEl('div', 'lf-variants-combo-form');
    const selects = [];
    let hasEmptyTerms = false;

    selectedAttributes.forEach((attributeSlug) => {
      const attribute = attributeMap[attributeSlug];
      const control = createEl('div', 'lf-variants-combo-field');
      const label = createEl('label', null, attribute ? attribute.label || attribute.slug : attributeSlug);
      control.appendChild(label);

      const select = createEl('select');
      select.dataset.attribute = attributeSlug;
      const placeholder = createEl('option', null, i18n.termPlaceholder || 'Select a value');
      placeholder.value = '';
      select.appendChild(placeholder);

      const terms = attribute && attribute.terms ? Object.keys(attribute.terms) : [];
      if (!terms.length) {
        hasEmptyTerms = true;
      }

      terms.forEach((termSlug) => {
        const option = createEl('option', null, attribute.terms[termSlug].name || termSlug);
        option.value = termSlug;
        select.appendChild(option);
      });
      control.appendChild(select);
      form.appendChild(control);
      selects.push(select);
    });

    if (hasEmptyTerms) {
      const warning = createEl('p', 'lf-variants-warning', i18n.attributeNoTerms || 'At least one selected attribute has no values assigned.');
      form.appendChild(warning);
    }

    const actions = createEl('div', 'lf-variants-combo-actions');
    const addBtn = createEl('button', 'button button-secondary', i18n.addCombination || 'Add Combination');
    addBtn.type = 'button';
    addBtn.addEventListener('click', () => {
      const combo = {};
      for (const select of selects) {
        const attrSlug = select.dataset.attribute;
        const value = select.value;
        if (!value) {
          speak(i18n.termPlaceholder || 'Select a value');
          return;
        }
        combo[attrSlug] = value;
      }
      const key = variantKey(combo);
      if (variants[key]) {
        speak(i18n.existingCombo || 'This combination already exists.');
        return;
      }
      ensureVariant(combo);
      openVariantKey = key;
      render();
      syncHiddenField();
    });

    const generateBtn = createEl('button', 'button', i18n.generateAll || 'Generate combinations');
    generateBtn.type = 'button';
    generateBtn.addEventListener('click', () => {
      const combos = buildAllCombinations();
      let added = 0;
      let lastAddedKey = null;
      combos.forEach((combo) => {
        const key = variantKey(combo);
        if (!variants[key]) {
          ensureVariant(combo);
          added += 1;
          lastAddedKey = key;
        }
      });
      if (lastAddedKey) {
        openVariantKey = lastAddedKey;
      }
      render();
      syncHiddenField();
      if (added > 0) {
        const message = (i18n.generatedCount || 'Added %d new combinations.').replace('%d', added);
        speak(message);
      } else {
        speak(i18n.nothingGenerated || 'All possible combinations already exist.');
      }
    });

    actions.appendChild(addBtn);
    actions.appendChild(generateBtn);
    form.appendChild(actions);
    section.appendChild(form);
    return section;
  }

  function buildAllCombinations() {
    if (!selectedAttributes.length) {
      return [];
    }
    let combos = [{}];
    selectedAttributes.forEach((attributeSlug) => {
      const attribute = attributeMap[attributeSlug];
      const terms = attribute && attribute.terms ? Object.keys(attribute.terms) : [];
      if (!terms.length) {
        combos = [];
        return;
      }
      const next = [];
      combos.forEach((combo) => {
        terms.forEach((termSlug) => {
          const updated = { ...combo };
          updated[attributeSlug] = termSlug;
          next.push(updated);
        });
      });
      combos = next;
    });
    return combos;
  }

  function renderVariantList() {
    const section = createEl('div', 'lf-variants-section lf-variants-section--list');
    const keys = variantKeysSorted();
    if (!keys.length) {
      openVariantKey = null;
      const empty = createEl('p', 'lf-variants-app__empty', i18n.addVariant || 'Add Combination');
      section.appendChild(empty);
      return section;
    }

    keys.forEach((key) => {
      const entry = variants[key];
      if (entry) {
        section.appendChild(renderVariantRow(entry));
      }
    });

    return section;
  }

  function createEl(tag, className, text) {
    const el = document.createElement(tag);
    if (className) {
      el.className = className;
    }
    if (typeof text !== 'undefined') {
      el.textContent = text;
    }
    return el;
  }

  function render() {
    root.innerHTML = '';

    if (!attributes.length) {
      const empty = createEl('p', 'lf-variants-app__empty', i18n.noAttributes || 'Assign attributes to configure variants.');
      root.appendChild(empty);
      updateEnabledState();
      return;
    }

    root.appendChild(renderAttributeSelector());
    root.appendChild(renderCombinationControls());
    root.appendChild(renderVariantList());
    updateAccordionState();
    updateEnabledState();
  }

  function updateEnabledState() {
    const enabled = !!toggle.checked;
    root.classList.toggle('lf-variants-disabled', !enabled);
    if (appendToggle) {
      appendToggle.disabled = !enabled;
    }
  }

  toggle.addEventListener('change', updateEnabledState);

  render();
  syncHiddenField();
  updateEnabledState();
})();
