/* global jQuery, PDS */
(function ($) {
  function post(action, data) {
    return $.post(PDS.ajaxUrl, Object.assign({ action: action, nonce: PDS.nonce }, data));
  }

  function closestMetaBox(el) {
    return $(el).closest('#pds_meta');
  }

  function getWrap($box) {
    var $wrap = $box.find('.pds-box');
    if (!$wrap.length) $wrap = $box.find('.inside');
    return $wrap;
  }

  function setLoading($btn, loadingText) {
    $btn.data('origText', $btn.text());
    $btn.prop('disabled', true).text(loadingText || $btn.text());
  }

  function clearLoading($btn) {
    if ($btn.data('origText')) $btn.text($btn.data('origText'));
    $btn.prop('disabled', false);
  }

  function renderEnabled($box, link, expiresHuman) {
    var $wrap = getWrap($box);
    $wrap.empty();
    $wrap.append(
      $('<p/>', { class: 'pds-head' }).append($('<strong/>').text('Shareable link')),
      $('<p/>', { class: 'pds-link-wrap' }).append(
        $('<input/>', { type: 'text', class: 'widefat pds-link', readonly: true, value: link })
      ),
      $('<p/>', { class: 'pds-expires', text: 'Expires: ' + (expiresHuman || 'Never') })
    );
    var $actions = $('<div/>', { class: 'pds-actions' });
    var $select = $('<select/>', { class: 'pds-expiry', id: 'pds-expiry-dynamic' });
    [1,3,7,14,30,0].forEach(function (d) {
      var label = d ? (d + ' ' + (d === 1 ? 'day' : 'days')) : 'Never';
      $select.append($('<option/>', { value: String(d), text: label }));
    });
    $actions.append(
      $('<label/>', { text: 'Expires in', for: 'pds-expiry-dynamic' }), ' ',
      $select, ' ',
      $('<button/>', { type: 'button', class: 'button pds-btn pds-regen', text: 'Regenerate' }), ' ',
      $('<button/>', { type: 'button', class: 'button button-link pds-btn pds-copy', text: 'Copy' }), ' ',
      $('<button/>', { type: 'button', class: 'button button-link-delete pds-btn pds-disable', text: 'Disable' })
    );
    $wrap.append($actions);
  }

  function renderDisabled($box, postId) {
    var $wrap = getWrap($box);
    $wrap.empty();
    var $p = $('<p/>', { class: 'pds-create-wrap' }).append(
      $('<label/>', { for: 'pds-expiry-' + postId, text: 'Expires in ' }),
      (function(){
        var $s = $('<select/>', { id: 'pds-expiry-' + postId, class: 'pds-expiry' });
        [1,3,7,14,30,0].forEach(function (d) {
          var label = d ? (d + ' ' + (d === 1 ? 'day' : 'days')) : 'Never';
          var $opt = $('<option/>', { value: String(d), text: label });
          if (d === 14) $opt.attr('selected', 'selected');
          $s.append($opt);
        });
        return $s;
      })()
    );
    var $btn = $('<button/>', { type: 'button', class: 'button button-primary pds-btn pds-create', text: 'Create Link' }).attr('data-post', postId);
    $wrap.append(
      $('<p/>', { class: 'pds-desc', text: 'Generate a secure link so anyone can view this draft without logging in.' }),
      $p,
      $btn
    );
  }

  $(document).on('click', '.pds-create, .pds-regen', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var $box = closestMetaBox(this);
    var postId = $btn.data('post') || $('#post_ID').val();
    var days = parseInt(getWrap($box).find('.pds-expiry').val() || 14, 10);
    setLoading($btn, $btn.hasClass('pds-regen') ? PDS.i18n.regenerating : PDS.i18n.creating);
    post('pds_generate', { post_id: postId, expiry_days: days })
      .done(function (res) {
        if (res && res.success) {
          renderEnabled($box, res.data.link, res.data.expires_h);
        } else {
          window.alert(PDS.i18n.error);
        }
      })
      .fail(function () { window.alert(PDS.i18n.error); })
      .always(function () { clearLoading($btn); });
  });

  $(document).on('click', '.pds-disable', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var $box = closestMetaBox(this);
    var postId = $btn.data('post') || $('#post_ID').val();
    setLoading($btn, '…');
    post('pds_disable', { post_id: postId })
      .done(function (res) {
        if (res && res.success) {
          renderDisabled($box, postId);
        } else {
          window.alert(PDS.i18n.error);
        }
      })
      .fail(function () { window.alert(PDS.i18n.error); })
      .always(function () { clearLoading($btn); });
  });

  $(document).on('click', '.pds-copy', function (e) {
    e.preventDefault();
    var $box = closestMetaBox(this);
    var $input = getWrap($box).find('input.pds-link');
    if ($input.length) {
      $input[0].select();
      try { document.execCommand('copy'); } catch (e) {}
    }
  });
})(jQuery);
