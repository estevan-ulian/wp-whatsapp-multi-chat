/* Shows the account's own weekly schedule only when "Horário próprio" is selected. */
(function () {
  'use strict';

  function init() {
    var select = document.getElementById('wpwc_availability_mode');
    var hours  = document.querySelector('.wpwc-account-hours');
    if (!select || !hours) return;

    function sync() {
      hours.hidden = select.value !== 'custom';
    }

    select.addEventListener('change', sync);
    sync();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
