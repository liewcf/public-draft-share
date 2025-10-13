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
      $('<p/>', { class: 'pds-head' }).append($('<strong/>').text(PDS.i18n.shareableLink || 'Shareable link')),
      $('<p/>', { class: 'pds-link-wrap' }).append(
        $('<input/>', { type: 'text', class: 'widefat pds-link', readonly: true, value: link })
      ),
      $('<p/>', { class: 'pds-expires', text: (PDS.i18n.expires || 'Expires') + ': ' + (expiresHuman || (PDS.i18n.never || 'Never')) })
    );
    var $actions = $('<div/>', { class: 'pds-actions' });
    $actions.append(
      $('<button/>', { type: 'button', class: 'button button-link pds-btn pds-copy', text: PDS.i18n.copy || 'Copy' }), ' ',
      $('<button/>', { type: 'button', class: 'button button-link-delete pds-btn pds-disable', text: PDS.i18n.disable || 'Disable' })
    );
    $wrap.append($actions);
  }

  function renderDisabled($box, postId) {
    var $wrap = getWrap($box);
    $wrap.empty();
    var $p = $('<p/>', { class: 'pds-create-wrap' }).append(
      $('<label/>', { for: 'pds-expiry-' + postId, text: (PDS.i18n.expiresIn || 'Expires in') + ' ' }),
      (function(){
        var $s = $('<select/>', { id: 'pds-expiry-' + postId, class: 'pds-expiry' });
        var expiryOptions = PDS.expiryOptions || [1,3,7,14,30,0];
        var defaultExpiry = PDS.defaultExpiry || 7;
        expiryOptions.forEach(function (d) {
          var label = d ? (d + ' ' + (d === 1 ? (PDS.i18n.day || 'day') : (PDS.i18n.days || 'days'))) : (PDS.i18n.never || 'Never');
          var $opt = $('<option/>', { value: String(d), text: label });
          if (d === defaultExpiry) $opt.attr('selected', 'selected');
          $s.append($opt);
        });
        return $s;
      })()
    );
    var $btn = $('<button/>', { type: 'button', class: 'button button-primary pds-btn pds-create', text: PDS.i18n.createLink || 'Create Link' }).attr('data-post', postId);
    $wrap.append(
      $('<p/>', { class: 'pds-desc', text: PDS.i18n.description || 'Generate a secure link so anyone can view this draft without logging in.' }),
      $p,
      $btn
    );
  }

  $(document).on('click', '.pds-create', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var $box = closestMetaBox(this);
    var postId = $btn.data('post') || $('#post_ID').val();
    var days = parseInt(getWrap($box).find('.pds-expiry').val() || 7, 10);
    setLoading($btn, PDS.i18n.creating);
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
    var $btn = $(this);
    var $box = closestMetaBox(this);
    var $input = getWrap($box).find('input.pds-link');
    if ($input.length) {
      var url = $input.val();
      var originalText = $btn.text();
      
      // Use modern Clipboard API with fallback
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function() {
          showCopiedFeedback($btn, originalText);
        }).catch(function() {
          fallbackCopy($input[0], $btn, originalText);
        });
      } else {
        fallbackCopy($input[0], $btn, originalText);
      }
    }
  });

  function fallbackCopy(inputEl, $btn, originalText) {
    inputEl.select();
    try {
      document.execCommand('copy');
      showCopiedFeedback($btn, originalText);
    } catch (e) {
      // Silent fail
    }
  }

  function showCopiedFeedback($btn, originalText) {
    $btn.text(PDS.i18n.copied || 'Copied!');
    setTimeout(function() {
      $btn.text(originalText);
    }, 1500);
  }
})(jQuery);
