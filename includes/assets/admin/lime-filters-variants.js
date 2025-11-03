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
  const stores = typeof data.stores === 'object' && data.stores !== null ? data.stores : {};
  const storeKeys = Object.keys(stores);
  const i18n = data.i18n || {};
  const appendDefault = data.append_gallery === 'yes' || data.appendGallery === 'yes';

  if (appendToggle) {
    appendToggle.checked = appendDefault;
  }

  let variants = clone(data.variants || {});

  if (!Object.keys(variants).length && hiddenInput.value) {
    try {
      const parsed = JSON.parse(hiddenInput.value);
      if (parsed && typeof parsed === 'object') {
        variants = clone(parsed);
      }
    } catch (err) {
      variants = {};
    }
  }

  function clone(source) {
    return JSON.parse(JSON.stringify(source || {}));
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

  function ensureEntry(attributeSlug, termSlug) {
    if (!variants[attributeSlug]) {
      variants[attributeSlug] = {};
    }
    if (!variants[attributeSlug][termSlug]) {
      variants[attributeSlug][termSlug] = {
        image_id: 0,
        image_url: '',
        sku: '',
        affiliates: {},
        extras: {},
      };
    }
    if (!variants[attributeSlug][termSlug].affiliates) {
      variants[attributeSlug][termSlug].affiliates = {};
    }
    storeKeys.forEach((key) => {
      if (typeof variants[attributeSlug][termSlug].affiliates[key] === 'undefined') {
        variants[attributeSlug][termSlug].affiliates[key] = '';
      }
    });
  }

  function syncHiddenField() {
    const payload = {};

    Object.keys(variants).forEach((attributeSlug) => {
      const attributeEntries = variants[attributeSlug];
      if (!attributeEntries || typeof attributeEntries !== 'object') {
        return;
      }

      const cleaned = {};
      Object.keys(attributeEntries).forEach((termSlug) => {
        const entry = attributeEntries[termSlug];
        if (!entry || typeof entry !== 'object') {
          return;
        }

        const out = {};

        if (entry.image_id) {
          out.image_id = parseInt(entry.image_id, 10) || 0;
        }
        if (entry.sku) {
          out.sku = entry.sku.trim();
        }

        if (entry.affiliates && typeof entry.affiliates === 'object') {
          const affiliates = {};
          Object.keys(entry.affiliates).forEach((store) => {
            const value = entry.affiliates[store];
            if (typeof value === 'string' && value.trim() !== '') {
              affiliates[store] = value.trim();
            }
          });
          if (Object.keys(affiliates).length) {
            out.affiliates = affiliates;
          }
        }

        if (entry.extras && typeof entry.extras === 'object' && Object.keys(entry.extras).length) {
          out.extras = entry.extras;
        }

        if (Object.keys(out).length) {
          cleaned[termSlug] = out;
        }
      });

      if (Object.keys(cleaned).length) {
        payload[attributeSlug] = cleaned;
      }
    });

    hiddenInput.value = JSON.stringify(payload);
  }

  function removeVariant(attributeSlug, termSlug) {
    if (!variants[attributeSlug]) {
      return;
    }
    delete variants[attributeSlug][termSlug];
    if (!Object.keys(variants[attributeSlug]).length) {
      delete variants[attributeSlug];
    }
    render();
    syncHiddenField();
  }

  function handleImageSelection(attributeSlug, termSlug) {
    const frame = wp.media({
      title: i18n.imageSelect || 'Select image',
      multiple: false,
    });

    frame.on('select', () => {
      const attachment = frame.state().get('selection').first().toJSON();
      ensureEntry(attributeSlug, termSlug);
      variants[attributeSlug][termSlug].image_id = attachment.id;
      if (attachment.sizes && attachment.sizes.thumbnail) {
        variants[attributeSlug][termSlug].image_url = attachment.sizes.thumbnail.url;
      } else {
        variants[attributeSlug][termSlug].image_url = attachment.url;
      }
      render();
      syncHiddenField();
    });

    frame.open();
  }

  function renderAffiliateFields(container, attributeSlug, termSlug, entry) {
    const wrap = createEl('div', 'lf-variants-affiliates');
    storeKeys.forEach((storeKey) => {
      const store = stores[storeKey] || {};
      const label = store.label || storeKey;
      const item = createEl('div', 'lf-variants-affiliates__item');
      const labelEl = createEl('label', null, `${label}`);
      labelEl.setAttribute('for', `lf-variant-${attributeSlug}-${termSlug}-${storeKey}`);
      const input = createEl('input');
      input.type = 'url';
      input.id = `lf-variant-${attributeSlug}-${termSlug}-${storeKey}`;
      input.value = entry.affiliates && entry.affiliates[storeKey] ? entry.affiliates[storeKey] : '';
      input.placeholder = i18n.affiliateLabel || 'Affiliate URL';
      input.addEventListener('input', () => {
        ensureEntry(attributeSlug, termSlug);
        variants[attributeSlug][termSlug].affiliates[storeKey] = input.value;
        syncHiddenField();
      });
      item.appendChild(labelEl);
      item.appendChild(input);
      wrap.appendChild(item);
    });
    container.appendChild(wrap);
  }

  function renderVariantRow(attribute, termSlug, entry) {
    const term = attribute.terms && attribute.terms[termSlug] ? attribute.terms[termSlug] : { name: termSlug };

    ensureEntry(attribute.slug, termSlug);
    const row = createEl('div', 'lf-variants-row');

    const header = createEl('div', 'lf-variants-row__header');
    const title = createEl('h4', 'lf-variants-row__title', term.name || termSlug);
    header.appendChild(title);

    const actions = createEl('div', 'lf-variants-row__actions');
    const removeBtn = createEl('button', 'button-link-delete', i18n.removeRow || 'Remove');
    removeBtn.type = 'button';
    removeBtn.addEventListener('click', () => removeVariant(attribute.slug, termSlug));

    actions.appendChild(removeBtn);
    header.appendChild(actions);
    row.appendChild(header);

    const body = createEl('div', 'lf-variants-row__body');

    const media = createEl('div', 'lf-variants-media');
    const preview = createEl('div', 'lf-variants-media__preview');
    if (entry.image_url) {
      const img = createEl('img');
      img.src = entry.image_url;
      img.alt = term.name || termSlug;
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
    selectBtn.addEventListener('click', () => handleImageSelection(attribute.slug, termSlug));
    mediaButtons.appendChild(selectBtn);

    if (entry.image_id) {
      const removeImg = createEl('button', 'button-link-delete', i18n.imageRemove || 'Remove image');
      removeImg.type = 'button';
      removeImg.addEventListener('click', () => {
        ensureEntry(attribute.slug, termSlug);
        variants[attribute.slug][termSlug].image_id = 0;
        variants[attribute.slug][termSlug].image_url = '';
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
    skuLabel.setAttribute('for', `lf-variant-sku-${attribute.slug}-${termSlug}`);
    const skuInput = createEl('input');
    skuInput.type = 'text';
    skuInput.id = `lf-variant-sku-${attribute.slug}-${termSlug}`;
    skuInput.value = entry.sku || '';
    skuInput.addEventListener('input', () => {
      ensureEntry(attribute.slug, termSlug);
      variants[attribute.slug][termSlug].sku = skuInput.value;
      syncHiddenField();
    });

    skuField.appendChild(skuLabel);
    skuField.appendChild(skuInput);
    body.appendChild(skuField);

    renderAffiliateFields(body, attribute.slug, termSlug, entry);

    row.appendChild(body);
    return row;
  }

  function renderAttributeSection(attribute) {
    const section = createEl('div', 'lf-variants-section');

    const header = createEl('div', 'lf-variants-section__header');
    const title = createEl('h3', 'lf-variants-section__title', attribute.label || attribute.slug);
    header.appendChild(title);

    const addWrap = createEl('div', 'lf-variants-add');
    const select = createEl('select');
    select.className = 'lf-variants-add__select';
    const placeholder = createEl('option', null, i18n.termPlaceholder || 'Select a value');
    placeholder.value = '';
    select.appendChild(placeholder);

    const assignedTerms = variants[attribute.slug] ? Object.keys(variants[attribute.slug]) : [];
    const availableTerms = [];
    Object.keys(attribute.terms || {}).forEach((termSlug) => {
      if (!assignedTerms.includes(termSlug)) {
        availableTerms.push(termSlug);
      }
    });

    availableTerms.forEach((termSlug) => {
      const option = createEl('option', null, attribute.terms[termSlug].name || termSlug);
      option.value = termSlug;
      select.appendChild(option);
    });

    const addButton = createEl('button', 'button button-secondary', i18n.addVariant || 'Add Variant');
    addButton.type = 'button';
    addButton.disabled = availableTerms.length === 0;
    addButton.addEventListener('click', () => {
      const termSlug = select.value;
      if (!termSlug) {
        return;
      }
      ensureEntry(attribute.slug, termSlug);
      render();
      syncHiddenField();
    });

    addWrap.appendChild(select);
    addWrap.appendChild(addButton);
    header.appendChild(addWrap);
    section.appendChild(header);

    const currentVariants = variants[attribute.slug] || {};
    const rows = Object.keys(currentVariants);
    if (!rows.length) {
      const empty = createEl('p', 'lf-variants-app__empty', i18n.addVariant || 'Add Variant');
      section.appendChild(empty);
    } else {
      rows.forEach((termSlug) => {
        const entry = currentVariants[termSlug];
        section.appendChild(renderVariantRow(attribute, termSlug, entry));
      });
    }

    return section;
  }

  function render() {
    root.innerHTML = '';

    if (!attributes.length) {
      const empty = createEl('p', 'lf-variants-app__empty', i18n.noAttributes || 'Assign attributes to configure variants.');
      root.appendChild(empty);
      return;
    }

    attributes.forEach((attribute) => {
      if (!attribute || !attribute.terms || !Object.keys(attribute.terms).length) {
        return;
      }
      root.appendChild(renderAttributeSection(attribute));
    });

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
