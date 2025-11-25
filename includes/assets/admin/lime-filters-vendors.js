(function () {
  const container = document.getElementById('lf-affiliate-vendors-rows');
  const addButton = document.getElementById('lf-add-vendor');
  const templateEl = document.getElementById('tmpl-lf-affiliate-vendor-row');

  if (!container || !addButton || !templateEl) {
    return;
  }

  let index = container.querySelectorAll('.lf-affiliate-vendors__row').length;
  const template = templateEl.innerHTML.trim();

  addButton.addEventListener('click', (event) => {
    event.preventDefault();
    const html = template.replace(/__index__/g, String(index));
    container.insertAdjacentHTML('beforeend', html);
    index += 1;
  });

  container.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-row]');
    if (!button) {
      return;
    }
    event.preventDefault();
    const row = button.closest('tr');
    if (row) {
      row.remove();
    }
  });
})();
