/* global wpwcOptions, jQuery */
(function ($) {
  'use strict';

  function initPostSelector(element) {
    var $selector = $(element);
    var $input    = $selector.find('.wpwc-search-input');
    var $results  = $selector.find('.wpwc-search-results');
    var $list     = $selector.find('.wpwc-selected-items');
    var fieldName = $selector.data('field');
    var timerId   = null;

    /* -- helpers --------------------------------------------------------- */

    function getSelectedIds() {
      return $list.find('input[type="hidden"]').map(function () {
        return String($(this).val());
      }).get();
    }

    function addItem(id, title, type) {
      if (getSelectedIds().indexOf(String(id)) !== -1) return;

      var $li = $('<li/>')
        .attr('data-id', id)
        .append($('<span/>').addClass('wpwc-item-title').text(title))
        .append($('<span/>').addClass('wpwc-type-label').text(type))
        .append(
          $('<button/>')
            .attr({ type: 'button', 'aria-label': wpwcOptions.removeLabel })
            .addClass('wpwc-remove-item button-link')
            .html('&times;')
        )
        .append(
          $('<input/>').attr({ type: 'hidden', name: fieldName + '[]', value: id })
        );

      $list.append($li);
    }

    /* -- render dropdown results ----------------------------------------- */

    function renderResults(items) {
      $results.empty();

      if (!items || items.length === 0) {
        $results
          .append($('<div/>').addClass('wpwc-no-results').text(wpwcOptions.noResults))
          .removeAttr('hidden');
        return;
      }

      var selectedIds = getSelectedIds();

      $.each(items, function (i, item) {
        var isSelected = selectedIds.indexOf(String(item.id)) !== -1;

        var $item = $('<div/>')
          .addClass('wpwc-result-item')
          .toggleClass('wpwc-result-selected', isSelected)
          .append($('<span/>').addClass('wpwc-result-title').text(item.title))
          .append($('<span/>').addClass('wpwc-type-label').text(item.type));

        if (!isSelected) {
          $item.on('click', function () {
            addItem(item.id, item.title, item.type);
            $input.val('');
            $results.attr('hidden', true);
          });
        }

        $results.append($item);
      });

      $results.removeAttr('hidden');
    }

    /* -- search input ---------------------------------------------------- */

    $input.on('input', function () {
      var val = $.trim($(this).val());
      clearTimeout(timerId);

      if (val.length < 2) {
        $results.attr('hidden', true);
        return;
      }

      timerId = setTimeout(function () {
        $input.closest('.wpwc-search-wrap').addClass('is-loading');
        $.post(
          wpwcOptions.ajaxUrl,
          { action: 'wpwc_search_posts', nonce: wpwcOptions.nonce, search: val },
          function (response) {
            $input.closest('.wpwc-search-wrap').removeClass('is-loading');
            if (response.success) {
              renderResults(response.data);
            }
          }
        ).fail(function () {
          $input.closest('.wpwc-search-wrap').removeClass('is-loading');
        });
      }, 300);
    });

    /* -- remove selected item -------------------------------------------- */

    $list.on('click', '.wpwc-remove-item', function () {
      $(this).closest('li').remove();
    });

    /* -- close dropdown on outside click --------------------------------- */

    $(document).on('click.wpwcSelector', function (e) {
      if (!$selector.is(e.target) && $selector.has(e.target).length === 0) {
        $results.attr('hidden', true);
      }
    });
  }

  /* -- init ---------------------------------------------------------------- */

  $(document).ready(function () {
    $('.wpwc-post-selector').each(function () {
      initPostSelector(this);
    });

    /* display mode radio toggle */
    $(document).on('change', 'input[name="display_mode"]', function () {
      var $manual = $('.wpwc-manual-conditions');
      if ($(this).val() === 'everywhere') {
        $manual.addClass('is-hidden');
      } else {
        $manual.removeClass('is-hidden');
      }
    });

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
  });

})(jQuery);
