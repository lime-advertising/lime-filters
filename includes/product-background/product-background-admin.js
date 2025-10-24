(function($){
  $(function(){
    let frame = null;
    const $idField  = $('#lime_filters_product_bg_id');
    const $preview  = $('#lf-bg-preview');
    const $remove   = $('#lf-bg-remove');
    const defaultUrl = $preview.data('default') || '';
    const currentUrl = $preview.data('current') || '';

    function applyPreview(url, isCustom) {
      if (url) {
        $preview
          .css('background-image', 'url(' + url + ')')
          .addClass('has-image')
          .find('.lf-bg-preview__placeholder').hide();
      } else {
        if (defaultUrl) {
          $preview
            .css('background-image', 'url(' + defaultUrl + ')')
            .addClass('has-image')
            .find('.lf-bg-preview__placeholder').hide();
        } else {
          $preview
            .css('background-image', 'none')
            .removeClass('has-image')
            .find('.lf-bg-preview__placeholder')
            .show();
        }
      }

      if (isCustom) {
        $remove.prop('disabled', false);
      } else {
        $remove.prop('disabled', true);
      }
    }

    applyPreview(currentUrl || defaultUrl, !!currentUrl);

    $('#lf-bg-upload').on('click', function(e){
      e.preventDefault();

      if (frame) {
        frame.open();
        return;
      }

      frame = wp.media({
        title: LFProductBackground.chooseText,
        button: { text: LFProductBackground.buttonText },
        multiple: false
      });

      frame.on('select', function(){
        const attachment = frame.state().get('selection').first().toJSON();
        if (!attachment || !attachment.id) {
          return;
        }
        $idField.val(attachment.id);
        applyPreview(attachment.url, true);
      });

      frame.open();
    });

    $remove.on('click', function(e){
      e.preventDefault();
      if ($remove.is(':disabled')) return;
      $idField.val('');
      applyPreview('', false);
    });
  });
})(jQuery);
