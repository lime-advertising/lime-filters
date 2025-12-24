(function () {
  const data = window.LimeFiltersVariants || {};
  const products = data.products || {};
  const selections = {};
  let loaderEl = null;

  if (!Object.keys(products).length) {
    return;
  }

  function getProduct(productId) {
    return products[String(productId)] || null;
  }

  function getSelection(productId) {
    if (!selections[productId]) {
      selections[productId] = {};
    }
    return selections[productId];
  }

  function variantKey(combo) {
    const parts = [];
    Object.keys(combo)
      .sort()
      .forEach((slug) => {
        parts.push(`${slug}=${combo[slug]}`);
      });
    return parts.join('|');
  }

  function getRequiredAttributes(product) {
    const required = Array.isArray(product.required) ? product.required : [];
    return required.filter((slug) => slug);
  }

  function findVariant(productId, attributeSlug, termSlug) {
    const product = getProduct(productId);
    if (!product || !product.variants) {
      return null;
    }

    const required = getRequiredAttributes(product);
    const selection = getSelection(productId);

    if (!required.length) {
      // Legacy mode: respond to single attribute choices.
      const variants = product.variants;
      const keys = Object.keys(variants);
      for (let i = 0; i < keys.length; i++) {
        const entry = variants[keys[i]];
        if (entry && entry.attributes && entry.attributes[attributeSlug] === termSlug) {
          return entry;
        }
      }
      return null;
    }

    const combo = {};
    for (let i = 0; i < required.length; i++) {
      const slug = required[i];
      const value = slug === attributeSlug ? termSlug : selection[slug];
      if (!value) {
        return null;
      }
      combo[slug] = value;
    }

    const key = variantKey(combo);
    return product.variants[key] || null;
  }

  function mergeAffiliates(product, variant) {
    // If a variant is selected, use only its affiliates (non-empty). Otherwise fall back to product defaults.
    if (variant && variant.affiliates) {
      const variantLinks = {};
      Object.keys(variant.affiliates).forEach((store) => {
        const url = variant.affiliates[store];
        if (typeof url === 'string' && url.trim() !== '') {
          variantLinks[store] = url;
        }
      });
      return variantLinks;
    }

    return (product.default && product.default.affiliates) ? { ...product.default.affiliates } : {};
  }

  function updateAffiliateLinks(productId, variant, product) {
    const affiliates = mergeAffiliates(product, variant);
    const sku = (variant && variant.sku) ? variant.sku : (product.default ? product.default.sku : '');

    const selector = `.lf-affiliates [data-affiliate-link][data-product-id="${productId}"]`;
    document.querySelectorAll(selector).forEach((link) => {
      const store = link.getAttribute('data-store');
      const url = affiliates[store] || '';
      if (url) {
        link.setAttribute('href', url);
        link.classList.remove('is-disabled');
        link.removeAttribute('aria-disabled');
      } else {
        link.setAttribute('href', '#');
        link.classList.add('is-disabled');
        link.setAttribute('aria-disabled', 'true');
      }

      if (sku) {
        link.setAttribute('data-sku', sku);
      } else {
        link.removeAttribute('data-sku');
      }
    });
  }

  function updateSkuDisplay(productId, variant, product) {
    const sku = (variant && variant.sku) ? variant.sku : (product.default ? product.default.sku : '');
    const skuEl = document.querySelector('.product_meta .sku');
    if (skuEl) {
      if (sku) {
        skuEl.textContent = sku;
      } else if (product.default && product.default.sku) {
        skuEl.textContent = product.default.sku;
      }
    }
  }

  function setImageAttributes(img, image) {
    if (!img || !image || !image.src) {
      return;
    }

    img.src = image.src;
    img.setAttribute('data-src', image.src);

    if (image.srcset) {
      img.srcset = image.srcset;
      img.setAttribute('data-srcset', image.srcset);
    } else {
      img.removeAttribute('srcset');
      img.removeAttribute('data-srcset');
    }

    if (image.sizes) {
      img.sizes = image.sizes;
      img.setAttribute('data-sizes', image.sizes);
    } else {
      img.removeAttribute('sizes');
      img.removeAttribute('data-sizes');
    }

    if (image.alt) {
      img.alt = image.alt;
    }

    if (image.full) {
      img.setAttribute('data-large_image', image.full);
      const anchor = img.closest('a');
      if (anchor) {
        anchor.href = image.full;
      }
    }

    if (image.width) {
      img.setAttribute('data-large_image_width', image.width);
    } else {
      img.removeAttribute('data-large_image_width');
    }
    if (image.height) {
      img.setAttribute('data-large_image_height', image.height);
    } else {
      img.removeAttribute('data-large_image_height');
    }

    const wrapper = img.closest('.woocommerce-product-gallery__image');
    if (wrapper && image.thumb) {
      wrapper.setAttribute('data-thumb', image.thumb);
    }

  }

  function ensureGalleryVariantSlide(container, image, allowAppend) {
    if (!container || !image || !image.src) {
      return -1;
    }

    const entry = container._lfGallery;
    if (!entry || !entry.mainSlides || !entry.mainSlides.length) {
      return -1;
    }

    const imageId = image.id ? String(image.id) : '';
    const imageSrc = image.full || image.src || '';
    const mainSlides = entry.mainSlides;
    let existingIndex = -1;

    for (let i = 0; i < mainSlides.length; i++) {
      const slide = mainSlides[i];
      if (!slide) {
        continue;
      }
      const slideId = slide.dataset ? String(slide.dataset.imageId || '') : '';
      if (imageId && slideId === imageId) {
        existingIndex = i;
        break;
      }
      if (!imageId && imageSrc) {
        const imgEl = slide.querySelector('img');
        if (imgEl && imgEl.src === imageSrc) {
          existingIndex = i;
          break;
        }
      }
    }

    if (existingIndex >= 0) {
      return existingIndex;
    }

    if (!allowAppend) {
      return -1;
    }

    const templateMain = mainSlides[0];
    const mainWrapper = templateMain ? templateMain.parentElement : null;
    if (!templateMain || !mainWrapper) {
      return -1;
    }

    const stateClasses = [
      'swiper-slide-active',
      'swiper-slide-next',
      'swiper-slide-prev',
      'swiper-slide-duplicate',
      'swiper-slide-duplicate-active',
      'swiper-slide-duplicate-next',
      'swiper-slide-duplicate-prev',
    ];

    const cloneMain = templateMain.cloneNode(true);
    stateClasses.forEach((cls) => cloneMain.classList.remove(cls));

    const datasetId = imageId || (imageSrc ? `variant-${imageSrc.replace(/[^a-zA-Z0-9]+/g, '-')}` : `variant-${Date.now()}`);
    cloneMain.dataset.imageId = datasetId;

    const mainImg = cloneMain.querySelector('img');
    if (mainImg) {
      setImageAttributes(mainImg, image);
    }

    mainWrapper.appendChild(cloneMain);
    entry.mainSlides.push(cloneMain);

    if (entry.mainSwiper) {
      entry.mainSwiper.updateSlides();
      entry.mainSwiper.updateAutoHeight(0);
      entry.mainSwiper.updateSize();
    }

    if (entry.thumbSlides && entry.thumbSlides.length) {
      const templateThumb = entry.thumbSlides[0];
      const thumbWrapper = templateThumb ? templateThumb.parentElement : null;
      if (templateThumb && thumbWrapper) {
        const cloneThumb = templateThumb.cloneNode(true);
        stateClasses.forEach((cls) => cloneThumb.classList.remove(cls));
        cloneThumb.dataset.imageId = datasetId;

        const thumbImg = cloneThumb.querySelector('img');
        if (thumbImg) {
          const thumbSrc = image.thumb || image.src || '';
          if (thumbSrc) {
            thumbImg.src = thumbSrc;
          }
          if (image.alt) {
            thumbImg.alt = image.alt;
          }
          if (image.srcset) {
            thumbImg.setAttribute('srcset', image.srcset);
          } else {
            thumbImg.removeAttribute('srcset');
          }
          if (image.sizes) {
            thumbImg.setAttribute('sizes', image.sizes);
          } else {
            thumbImg.removeAttribute('sizes');
          }
        }

        thumbWrapper.appendChild(cloneThumb);
        entry.thumbSlides.push(cloneThumb);
        if (entry.thumbsSwiper) {
          entry.thumbsSwiper.updateSlides();
          entry.thumbsSwiper.updateSize();
        }
      }
    }

    return entry.mainSlides.length - 1;
  }

  function updateProductImage(productId, variant, product) {
    const image = (variant && variant.image) ? variant.image : (product.default ? product.default.image : null);
    if (!image || !image.src) {
      return;
    }

    const allowAppend = product && (product.append_gallery === 'yes' || product.appendGallery === 'yes');

    const galleries = document.querySelectorAll(`.lf-bg-gallery[data-product-id="${productId}"]`);
    galleries.forEach((container) => {
      const index = ensureGalleryVariantSlide(container, image, allowAppend);
      const entry = container._lfGallery;
      if (entry && entry.mainSwiper && index >= 0) {
        entry.mainSwiper.slideTo(index);
        if (entry.thumbsSwiper) {
          entry.thumbsSwiper.slideTo(index);
        }
      } else if (!entry && index >= 0) {
        const slides = container.querySelectorAll('.lf-bg-gallery__main .swiper-slide');
        slides.forEach((slide, idx) => {
          slide.classList.toggle('is-active', idx === index);
        });
      }
    });

    const selectors = [
      '.woocommerce-product-gallery__wrapper .woocommerce-product-gallery__image img',
      '.woocommerce-product-gallery img',
      '.product .images img',
      '.wp-post-image',
      `[data-lf-product-image="${productId}"] img`,
      `[data-lf-product-image="${productId}"]`,
      '.lf-product-background img',
    ];

    const seen = new Set();
    const targets = [];

    selectors.forEach((selector) => {
      document.querySelectorAll(selector).forEach((el) => {
        if (!(el instanceof HTMLImageElement)) {
          return;
        }
        const key = el;
        if (!seen.has(key)) {
          seen.add(key);
          targets.push(el);
        }
      });
    });

    if (targets.length) {
      setImageAttributes(targets[0], image);
    }
  }

  function ensureLoader() {
    if (!document.getElementById('lf-variant-loader-style')) {
      const style = document.createElement('style');
      style.id = 'lf-variant-loader-style';
      style.textContent = `
        .lf-variant-loader{position:fixed;inset:0;background:rgba(17,24,39,0.4);display:none;align-items:center;justify-content:center;z-index:99999;pointer-events:auto}
        .lf-variant-loader.is-active{display:flex}
        .lf-variant-loader__spinner{width:56px;height:56px;border:4px solid rgba(255,255,255,0.35);border-top-color:var(--lf-accent,#dd7210);border-radius:50%;animation:lf-variant-spin 0.75s linear infinite;background:rgba(17,24,39,0.75);display:grid;place-items:center;padding:6px;box-shadow:0 8px 24px rgba(0,0,0,0.25)}
        .lf-variant-loader__spinner .screen-reader-text{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0}
        @keyframes lf-variant-spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
      `;
      document.head.appendChild(style);
    }

    if (loaderEl && document.body.contains(loaderEl)) {
      return loaderEl;
    }
    loaderEl = document.createElement('div');
    loaderEl.className = 'lf-variant-loader';
    loaderEl.innerHTML = '<div class="lf-variant-loader__spinner" role="status" aria-live="polite"><span class="screen-reader-text">Updating selection…</span></div>';
    document.body.appendChild(loaderEl);
    return loaderEl;
  }

  function showLoader() {
    const el = ensureLoader();
    el.classList.add('is-active');
    document.body.classList.add('lf-loading');
  }

  function hideLoader() {
    if (!loaderEl) {
      return;
    }
    window.setTimeout(() => {
      loaderEl.classList.remove('is-active');
      document.body.classList.remove('lf-loading');
    }, 120);
  }

  function applyVariantSelection(productId, attributeSlug, termSlug) {
    const product = getProduct(productId);
    if (!product) {
      return;
    }
    showLoader();
    try {
      const variant = termSlug ? findVariant(productId, attributeSlug, termSlug) : null;
      updateProductImage(productId, variant, product);
      updateAffiliateLinks(productId, variant, product);
      updateSkuDisplay(productId, variant, product);
    } finally {
      hideLoader();
    }
  }

  function setActivePill(group, button) {
    if (!group) {
      return;
    }
    group.querySelectorAll('.lf-pill.is-active').forEach((pill) => {
      pill.classList.remove('is-active');
    });
    if (button) {
      button.classList.add('is-active');
    }
  }

  function hasCompleteSelection(productId, product) {
    const selection = getSelection(productId);
    const required = getRequiredAttributes(product);
    if (!required.length) {
      return Object.keys(selection).length > 0;
    }
    for (let i = 0; i < required.length; i++) {
      if (!selection[required[i]]) {
        return false;
      }
    }
    return true;
  }

  function initializeSelections() {
    document.querySelectorAll('.lf-pill.is-active[data-product-id][data-attribute][data-term]').forEach((pill) => {
      const pid = pill.getAttribute('data-product-id');
      const attr = pill.getAttribute('data-attribute');
      const term = pill.getAttribute('data-term');
      if (pid && attr && term) {
        const selection = getSelection(pid);
        selection[attr] = term;
      }
    });

    Object.keys(products).forEach((pid) => {
      const product = getProduct(pid);
      if (!product || !hasCompleteSelection(pid, product)) {
        return;
      }
      const selection = getSelection(pid);
      const required = getRequiredAttributes(product);
      const attrSlug = required.length ? required[0] : Object.keys(selection)[0];
      const termSlug = attrSlug ? selection[attrSlug] : null;
      if (attrSlug && termSlug) {
        applyVariantSelection(pid, attrSlug, termSlug);
      }
    });
  }

  initializeSelections();

  document.addEventListener('click', (event) => {
    const button = event.target.closest('.lf-pill[data-product-id][data-attribute][data-term]');
    if (!button) {
      return;
    }

    const productId = button.getAttribute('data-product-id');
    const attributeSlug = button.getAttribute('data-attribute');
    const termSlug = button.getAttribute('data-term');

    if (!productId || !attributeSlug || !termSlug) {
      return;
    }

    const group = button.closest('.lf-attribute-group__pills');
    const selection = getSelection(productId);
    const isActive = button.classList.contains('is-active');

    if (isActive) {
      delete selection[attributeSlug];
      setActivePill(group, null);
      applyVariantSelection(productId, attributeSlug, null);
      return;
    }

    selection[attributeSlug] = termSlug;
    setActivePill(group, button);
    applyVariantSelection(productId, attributeSlug, termSlug);
  });
})();
