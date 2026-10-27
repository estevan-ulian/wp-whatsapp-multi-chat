/* global wpwcOptions, jQuery */
(function ($) {
  'use strict';

  /*
   * Display rules list. Each row is Ação · Onde · Qual · Item; "Qual" only
   * exists for archives and single views, and "Item" only for single views and
   * taxonomies. Hide rules always win over show rules (decided in PHP).
   */
  var data    = (wpwcOptions && wpwcOptions.rules) || null;
  var $list   = null;
  var counter = 0;

  function format(text, value) {
    return String(text).replace(/%[ds]/, value);
  }

  function whichOptions(where) {
    if (!data) return {};
    if (where === 'archive') return data.choices.archive;
    if (where === 'singular') return data.choices.singular;
    return {};
  }

  // Single views search posts of the chosen type; taxonomies search their terms.
  function itemSource(where, which) {
    if (where === 'singular' && which) return { action: 'wpwc_search_posts', post_type: which };
    if (where === 'archive' && which && which.indexOf('tax_') === 0) {
      return { action: 'wpwc_search_terms', taxonomy: which.slice(4) };
    }
    return null;
  }

  function field(labelText, $control) {
    var id = 'wpwc-rule-' + (counter++);
    $control.attr('id', id);
    return $('<span/>').addClass('wpwc-rule-field')
      .append($('<label/>').addClass('screen-reader-text').attr('for', id).text(labelText))
      .append($control);
  }

  function select(name, options, current) {
    var $select = $('<select/>').addClass('wpwc-rule-' + name);
    $.each(options, function (value, label) {
      $select.append($('<option/>').val(value).text(label));
    });
    // A saved value no longer offered stays visible so the admin can see and remove it.
    if (current && !Object.prototype.hasOwnProperty.call(options, current)) {
      $select.append($('<option/>').val(current).text(format(data.i18n.unknown, current)));
    }
    if (current) $select.val(current);
    return $select;
  }

  function buildRow(rule) {
    var $row = $('<li/>').addClass('wpwc-rule');

    $row.append(field(data.i18n.action, select('action', { show: data.i18n.show, hide: data.i18n.hide }, rule.action)));
    $row.append(field(data.i18n.whereLabel, select('where', data.where, rule.where)));
    $row.append($('<span/>').addClass('wpwc-rule-which-slot'));
    $row.append($('<span/>').addClass('wpwc-rule-item-slot'));
    $row.append(
      $('<button/>').attr({ type: 'button', 'aria-label': data.i18n.remove, title: data.i18n.remove })
        .addClass('wpwc-rule-remove button-link').html('&times;')
    );

    renderWhich($row, rule.which);
    renderItem($row, rule);
    return $row;
  }

  function renderWhich($row, current) {
    var where = $row.find('.wpwc-rule-where').val();
    var $slot = $row.find('.wpwc-rule-which-slot').empty();
    var options = whichOptions(where);

    if ($.isEmptyObject(options)) return;
    $slot.append(field(data.i18n.which, select('which', options, current)));
  }

  function renderItem($row, rule) {
    var where  = $row.find('.wpwc-rule-where').val();
    var which  = $row.find('.wpwc-rule-which').val() || '';
    var $slot  = $row.find('.wpwc-rule-item-slot').empty();
    var source = itemSource(where, which);

    if (!source) return;

    var item  = rule && rule.item ? parseInt(rule.item, 10) : 0;
    var title = '';
    if (item > 0) title = rule.missing ? format(data.i18n.unavailable, item) : rule.title;

    var $hidden = $('<input/>').attr('type', 'hidden').addClass('wpwc-rule-item-id').val(item);
    var $input  = $('<input/>').attr({ type: 'text', autocomplete: 'off', placeholder: data.i18n.searchHint })
      .addClass('wpwc-search-input wpwc-rule-item-search regular-text').val(title)
      .toggleClass('is-missing', !!(rule && rule.missing));
    var $results = $('<div/>').addClass('wpwc-search-results').attr('hidden', true);
    var $wrap    = $('<span/>').addClass('wpwc-search-wrap').append($hidden, field(data.i18n.item, $input), $results);

    $slot.append($wrap);
    bindSearch($wrap, source);
  }

  function bindSearch($wrap, source) {
    var $input   = $wrap.find('.wpwc-rule-item-search');
    var $hidden  = $wrap.find('.wpwc-rule-item-id');
    var $results = $wrap.find('.wpwc-search-results');
    var timerId  = null;

    function choose(id, title) {
      $hidden.val(id);
      $input.val(title).removeClass('is-missing');
      $results.attr('hidden', true);
    }

    $input.on('input', function () {
      var val = $.trim($input.val());
      clearTimeout(timerId);

      // An empty field means "Todos".
      if (val === '') {
        $hidden.val(0);
        $results.attr('hidden', true);
        return;
      }
      if (val.length < 2) return;

      timerId = setTimeout(function () {
        $wrap.addClass('is-loading');
        $.post(wpwcOptions.ajaxUrl, $.extend({ nonce: wpwcOptions.nonce, search: val }, source), function (response) {
          $wrap.removeClass('is-loading');
          $results.empty();
          if (!response.success || !response.data.length) {
            $results.append($('<div/>').addClass('wpwc-no-results').text(wpwcOptions.noResults));
          } else {
            $.each(response.data, function (i, found) {
              $('<button/>').attr('type', 'button').addClass('wpwc-result-item')
                .append($('<span/>').addClass('wpwc-result-title').text(found.title))
                .on('click', function () { choose(found.id, found.title); })
                .appendTo($results);
            });
          }
          $results.removeAttr('hidden');
        }).fail(function () {
          $wrap.removeClass('is-loading');
        });
      }, 300);
    });

    $input.on('keydown', function (e) {
      if (e.key === 'Escape') $results.attr('hidden', true);
    });
  }

  // Field names follow the row order so PHP receives rules[0..n].
  function reindex() {
    $list.children('.wpwc-rule').each(function (index) {
      var prefix = 'rules[' + index + ']';
      var $row = $(this);
      $row.find('.wpwc-rule-action').attr('name', prefix + '[action]');
      $row.find('.wpwc-rule-where').attr('name', prefix + '[where]');
      $row.find('.wpwc-rule-which').attr('name', prefix + '[which]');
      $row.find('.wpwc-rule-item-id').attr('name', prefix + '[item]');
    });
    $('.wpwc-rules-empty').prop('hidden', $list.children().length > 0);
  }

  function initRules() {
    $list = $('#wpwc-rules');
    if (!$list.length || !data) return;

    $.each(data.saved, function (i, rule) {
      $list.append(buildRow(rule));
    });
    reindex();

    $('#wpwc-add-rule').on('click', function () {
      var $row = buildRow({ action: 'show', where: 'entire_site', which: '', item: 0 });
      $list.append($row);
      reindex();
      $row.find('.wpwc-rule-where').trigger('focus');
    });

    $list.on('change', '.wpwc-rule-where', function () {
      var $row = $(this).closest('.wpwc-rule');
      renderWhich($row, '');
      renderItem($row, null);
      reindex();
    });

    $list.on('change', '.wpwc-rule-which', function () {
      var $row = $(this).closest('.wpwc-rule');
      renderItem($row, null);
      reindex();
    });

    $list.on('click', '.wpwc-rule-remove', function () {
      $(this).closest('.wpwc-rule').remove();
      reindex();
      $('#wpwc-add-rule').trigger('focus');
    });

    $(document).on('click.wpwcRules', function (e) {
      $list.find('.wpwc-search-wrap').each(function () {
        if (!$(this).is(e.target) && $(this).has(e.target).length === 0) {
          $(this).find('.wpwc-search-results').attr('hidden', true);
        }
      });
    });
  }

  /* -- init ---------------------------------------------------------------- */

  $(document).ready(function () {
    initRules();

    /* button style toggle: show/hide button-text row */
    $(document).on('change', 'input[name="button_style"]', function () {
      var $row = $('.wpwc-button-text-row');
      if ($(this).val() === 'icon-only') {
        $row.addClass('is-hidden');
      } else {
        $row.removeClass('is-hidden');
      }
    });

    /* colour picker <-> hex text sync */
    $(document).on('input', '.wpwc-color-input', function () {
      $(this).siblings('.wpwc-color-hex').val($(this).val().toUpperCase());
    });

    $(document).on('input', '.wpwc-color-hex', function () {
      var val = $(this).val();
      if (/^#[0-9a-fA-F]{6}$/.test(val)) {
        $(this).siblings('.wpwc-color-input').val(val);
      }
    });
    /* reset checkboxes → enable/disable submit button */
    function wpwcUpdateResetButton() {
      var $items   = $('#wpwc-reset-form input[name="wpwc_reset[]"]');
      var checked  = $items.filter(':checked').length;
      $('#wpwc-reset-form .wpwc-reset-button').prop('disabled', checked === 0);
      $('#wpwc-reset-select-all').prop('indeterminate', checked > 0 && checked < $items.length);
      $('#wpwc-reset-select-all').prop('checked', checked === $items.length && $items.length > 0);
    }
    wpwcUpdateResetButton();

    $(document).on('change', '#wpwc-reset-form input[name="wpwc_reset[]"]', wpwcUpdateResetButton);

    $(document).on('change', '#wpwc-reset-select-all', function () {
      $('#wpwc-reset-form input[name="wpwc_reset[]"]').prop('checked', $(this).is(':checked'));
      wpwcUpdateResetButton();
    });

    /* reset form – require explicit confirmation */
    $('#wpwc-reset-form').on('submit', function (e) {
      if (!window.confirm(wpwcOptions.resetConfirmText)) {
        e.preventDefault();
      }
    });
  });

})(jQuery);
