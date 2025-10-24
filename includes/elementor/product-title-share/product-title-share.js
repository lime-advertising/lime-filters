(function(){
  function initCopyButtons(scope) {
    const buttons = (scope || document).querySelectorAll('[data-share-copy]');
    if (!buttons.length) {
      return;
    }

    buttons.forEach(button => {
      if (button.dataset.copyInit === '1') {
        return;
      }
      button.dataset.copyInit = '1';
      button.addEventListener('click', function(){
        const wrapper = button.closest('[data-copy-success]');
        const success = wrapper ? wrapper.getAttribute('data-copy-success') || 'Link copied!' : 'Link copied!';
        const url = window.location.href;

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(url).then(() => {
            showCopied(button, success);
          }).catch(() => fallbackCopy(url, button, success));
        } else {
          fallbackCopy(url, button, success);
        }
      });
    });
  }

  function fallbackCopy(text, button, message) {
    const input = document.createElement('input');
    input.value = text;
    document.body.appendChild(input);
    input.select();
    try {
      document.execCommand('copy');
      showCopied(button, message);
    } catch (err) {
      console.warn('Copy failed', err);
    }
    document.body.removeChild(input);
  }

  function showCopied(button, message) {
    const original = button.querySelector('.lf-product-title-share__button-label');
    if (original) {
      const prev = original.textContent;
      original.textContent = message;
      setTimeout(() => {
        original.textContent = prev;
      }, 2000);
    }
    button.classList.add('is-copied');
    setTimeout(() => button.classList.remove('is-copied'), 2000);
  }

  function init() {
    document.querySelectorAll('.lf-product-title-share').forEach(el => initCopyButtons(el));
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  document.addEventListener('elementor/popup/show', init);
  document.addEventListener('elementor/render/callback', init);
})();
