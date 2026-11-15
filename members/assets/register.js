(function () {
  // LGA list follows the chosen state.
  var state = document.getElementById('state');
  var lga = document.getElementById('lga');
  function fillLgas() {
    var list = (window.LGAS || {})[state.value] || [];
    var keep = lga.getAttribute('data-selected') || lga.value;
    lga.innerHTML = '';
    var first = document.createElement('option');
    first.value = '';
    first.textContent = list.length ? 'Choose…' : 'Choose a state first';
    lga.appendChild(first);
    list.forEach(function (name) {
      var o = document.createElement('option');
      o.value = name;
      o.textContent = name;
      if (name === keep) o.selected = true;
      lga.appendChild(o);
    });
    lga.setAttribute('data-selected', '');
  }
  if (state && lga) {
    state.addEventListener('change', fillLgas);
    fillLgas();
  }

  // Role cards: highlight the chosen one, show buyer-only fields.
  var radios = document.querySelectorAll('.role-card input[type=radio]');
  function syncRole() {
    radios.forEach(function (r) {
      r.closest('.role-card').classList.toggle('selected', r.checked);
      if (r.checked) {
        document.querySelectorAll('.buyer-only').forEach(function (el) {
          el.hidden = r.value !== 'buyer';
        });
      }
    });
  }
  radios.forEach(function (r) { r.addEventListener('change', syncRole); });
  syncRole();
})();
