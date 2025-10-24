(function(){
  var galleryRegistry = {};

  function registerGallery(productId, entry) {
    if (!productId) {
      return;
    }
    if (!galleryRegistry[productId]) {
      galleryRegistry[productId] = [];
    }
    galleryRegistry[productId].push(entry);
  }

  function getGalleries(productId) {
    if (!productId || !galleryRegistry[productId]) {
      return [];
    }
    return galleryRegistry[productId];
  }

  function updateSlideImage(slide, image, imageId) {
    if (!slide || !image) {
      return;
    }

    if (imageId && imageId > 0) {
      slide.dataset.imageId = String(imageId);
    } else {
      delete slide.dataset.imageId;
    }

    var wrap = slide.querySelector('.lf-bg-wrap') || slide;
    var img = wrap.querySelector('img');
    if (!img) {
      img = document.createElement('img');
      wrap.appendChild(img);
    }

    var src = image.src || image.full_src || image.url || '';
    if (src) {
      img.src = src;
    }

    if (image.srcset) {
      img.setAttribute('srcset', image.srcset);
    } else {
      img.removeAttribute('srcset');
    }

    if (image.sizes) {
      img.setAttribute('sizes', image.sizes);
    } else {
      img.removeAttribute('sizes');
    }

    img.alt = image.alt || '';

    if (image.full_src) {
      img.setAttribute('data-full', image.full_src);
    } else {
      img.removeAttribute('data-full');
    }
  }

  function updateThumbImage(slide, image, imageId) {
    if (!slide || !image) {
      return;
    }

    if (imageId && imageId > 0) {
      slide.dataset.imageId = String(imageId);
    } else {
      delete slide.dataset.imageId;
    }

    var img = slide.querySelector('img');
    if (!img) {
      return;
    }

    var thumbSrc = image.gallery_thumbnail_src || image.thumb_src || image.src || image.full_src || image.url || '';
    if (thumbSrc) {
      img.src = thumbSrc;
    }

    if (image.gallery_thumbnail_srcset) {
      img.setAttribute('srcset', image.gallery_thumbnail_srcset);
    } else if (image.srcset) {
      img.setAttribute('srcset', image.srcset);
    } else {
      img.removeAttribute('srcset');
    }

    if (image.gallery_thumbnail_sizes) {
      img.setAttribute('sizes', image.gallery_thumbnail_sizes);
    } else if (image.sizes) {
      img.setAttribute('sizes', image.sizes);
    } else {
      img.removeAttribute('sizes');
    }

    img.alt = image.alt || '';
  }

  function restoreGallery(entry) {
    if (!entry) {
      return;
    }

    if (entry.dynamicActive) {
      var mainSlide = entry.mainSlides[entry.dynamicIndex];
      if (mainSlide && typeof entry.originalMainHtml !== 'undefined') {
        mainSlide.innerHTML = entry.originalMainHtml;
        if (typeof entry.originalMainId !== 'undefined' && entry.originalMainId !== undefined) {
          if (entry.originalMainId !== '') {
            mainSlide.dataset.imageId = entry.originalMainId;
          } else {
            delete mainSlide.dataset.imageId;
          }
        }
      }

      var thumbSlide = entry.thumbSlides[entry.dynamicIndex];
      if (thumbSlide && typeof entry.originalThumbHtml !== 'undefined') {
        thumbSlide.innerHTML = entry.originalThumbHtml;
        if (typeof entry.originalThumbId !== 'undefined' && entry.originalThumbId !== undefined) {
          if (entry.originalThumbId !== '') {
            thumbSlide.dataset.imageId = entry.originalThumbId;
          } else {
            delete thumbSlide.dataset.imageId;
          }
        }
      }

      entry.dynamicActive = false;
    }

    if (entry.mainSwiper) {
      entry.mainSwiper.slideTo(entry.dynamicIndex);
      entry.mainSwiper.updateAutoHeight(0);
      entry.mainSwiper.updateSlides();
      entry.mainSwiper.updateSize();
    }
    if (entry.thumbsSwiper) {
      entry.thumbsSwiper.updateSlides();
      entry.thumbsSwiper.updateSize();
      entry.thumbsSwiper.slideTo(entry.dynamicIndex);
    }
  }

  function updateGalleryForVariation(entry, variation) {
    if (!entry || !variation) {
      return;
    }

    var image = variation.image || {};
    var imageId = parseInt(variation.image_id, 10);
    if (isNaN(imageId)) {
      imageId = 0;
    }

    var mainSlides = entry.mainSlides || [];
    var existingIndex = -1;
    if (imageId > 0) {
      for (var i = 0; i < mainSlides.length; i++) {
        var slideId = parseInt(mainSlides[i].dataset.imageId || '0', 10);
        if (imageId === slideId) {
          existingIndex = i;
          break;
        }
      }
    }

    if (existingIndex >= 0) {
      if (entry.dynamicActive) {
        restoreGallery(entry);
      }
      if (entry.mainSwiper) {
        entry.mainSwiper.slideTo(existingIndex);
      }
      if (entry.thumbsSwiper) {
        entry.thumbsSwiper.slideTo(existingIndex);
      }
      return;
    }

    var src = image.src || image.full_src || image.url || '';
    if (!src) {
      restoreGallery(entry);
      return;
    }

    var mainSlide = mainSlides[entry.dynamicIndex];
    if (!mainSlide) {
      return;
    }

    if (typeof entry.originalMainHtml === 'undefined') {
      entry.originalMainHtml = mainSlide.innerHTML;
      entry.originalMainId = mainSlide.dataset.imageId || '';
    }

    var thumbSlide = entry.thumbSlides[entry.dynamicIndex];
    if (thumbSlide && typeof entry.originalThumbHtml === 'undefined') {
      entry.originalThumbHtml = thumbSlide.innerHTML;
      entry.originalThumbId = thumbSlide.dataset.imageId || '';
    }

    updateSlideImage(mainSlide, image, imageId);
    if (thumbSlide) {
      updateThumbImage(thumbSlide, image, imageId);
    }

    entry.dynamicActive = true;

    if (entry.mainSwiper) {
      entry.mainSwiper.slideTo(entry.dynamicIndex);
      entry.mainSwiper.updateAutoHeight(0);
      entry.mainSwiper.updateSlides();
      entry.mainSwiper.updateSize();
    }
    if (entry.thumbsSwiper) {
      entry.thumbsSwiper.updateSlides();
      entry.thumbsSwiper.updateSize();
      entry.thumbsSwiper.slideTo(entry.dynamicIndex);
    }
  }

  function handleFoundVariation(event, variation) {
    var form = event.currentTarget;
    var productId = form.getAttribute('data-product_id') || (form.dataset ? form.dataset.productId : '');
    if (!productId) {
      return;
    }

    var galleries = getGalleries(productId);
    if (!galleries.length) {
      return;
    }

    if (!variation) {
      galleries.forEach(function(entry){
        restoreGallery(entry);
      });
      return;
    }

    galleries.forEach(function(entry){
      updateGalleryForVariation(entry, variation);
    });
  }

  function handleResetVariation(event) {
    var form = event.currentTarget;
    var productId = form.getAttribute('data-product_id') || (form.dataset ? form.dataset.productId : '');
    if (!productId) {
      return;
    }

    var galleries = getGalleries(productId);
    if (!galleries.length) {
      return;
    }

    galleries.forEach(function(entry){
      restoreGallery(entry);
    });
  }

  function bindVariationEvents(){
    if (!window.jQuery) {
      return;
    }

    var $ = window.jQuery;
    $('form.variations_form').each(function(){
      var $form = $(this);
      if ($form.data('lfGalleryBound')) {
        return;
      }
      $form.on('found_variation', handleFoundVariation);
      $form.on('reset_data', handleResetVariation);
      $form.data('lfGalleryBound', true);
    });
  }

  function initGallery(container){
    if (typeof window.Swiper === 'undefined') {
      return;
    }

    const mainEl   = container.querySelector('.lf-bg-gallery__main.swiper');
    const thumbsEl = container.querySelector('.lf-bg-gallery__thumbs.swiper');
    if (!mainEl || !thumbsEl) {
      return;
    }

    const prevEl = container.querySelector('.lf-bg-gallery__prev');
    const nextEl = container.querySelector('.lf-bg-gallery__next');

    const desktop = parseInt(container.dataset.columns || '4', 10) || 4;
    const tablet  = parseInt(container.dataset.columnsTablet || '3', 10) || desktop;
    const mobile  = parseInt(container.dataset.columnsMobile || '2', 10) || tablet;

    const thumbsSwiper = new Swiper(thumbsEl, {
      spaceBetween: 14,
      slidesPerView: mobile,
      watchSlidesProgress: true,
      breakpoints: {
        576: {
          slidesPerView: tablet,
        },
        992: {
          slidesPerView: desktop,
        },
      },
    });

    const mainSwiper = new Swiper(mainEl, {
      slidesPerView: 1,
      spaceBetween: 10,
      effect: 'fade',
      fadeEffect: { crossFade: true },
      autoHeight: true,
      navigation: {
        prevEl: prevEl || undefined,
        nextEl: nextEl || undefined,
      },
      thumbs: {
        swiper: thumbsSwiper,
      },
    });

    const mainSlides = Array.prototype.slice.call(mainEl.querySelectorAll('.swiper-slide'));
    const thumbSlides = Array.prototype.slice.call(thumbsEl.querySelectorAll('.swiper-slide'));

    const entry = {
      container: container,
      productId: container.dataset.productId || '',
      mainSwiper: mainSwiper,
      thumbsSwiper: thumbsSwiper,
      mainSlides: mainSlides,
      thumbSlides: thumbSlides,
      dynamicIndex: 0,
      dynamicActive: false,
      originalMainHtml: mainSlides[0] ? mainSlides[0].innerHTML : undefined,
      originalMainId: mainSlides[0] ? (mainSlides[0].dataset.imageId || '') : undefined,
      originalThumbHtml: thumbSlides[0] ? thumbSlides[0].innerHTML : undefined,
      originalThumbId: thumbSlides[0] ? (thumbSlides[0].dataset.imageId || '') : undefined,
    };

    container._lfGallery = entry;
    if (entry.productId) {
      registerGallery(entry.productId, entry);
    }

    // Ensure initial measurements
    setTimeout(function(){
      mainSwiper.updateAutoHeight(0);
      mainSwiper.updateSlides();
      mainSwiper.updateSize();
      thumbsSwiper.updateSlides();
      thumbsSwiper.updateSize();
    }, 120);
  }

  function initAll(){
    document.querySelectorAll('.lf-bg-gallery--slider').forEach(function(container){
      if (container.dataset.lfGalleryInit === '1') {
        return;
      }
      container.dataset.lfGalleryInit = '1';
      initGallery(container);
    });
    bindVariationEvents();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }

  if (window.jQuery) {
    window.jQuery(document).on('wc_variation_form', bindVariationEvents);
  }
})();
