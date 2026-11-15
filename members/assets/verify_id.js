(function () {
  var HELP = {
    nin: ['National Identification Number', '11 digits, on your NIN slip or the NIMC app.', '12345678901'],
    drivers_licence: ["Driver's licence number", 'As printed on the front of the licence.', 'ABC12345AA12'],
    passport: ['Passport number', 'A letter followed by 8 digits, top right of the data page.', 'A12345678'],
    voters_card: ['Voter identification number (VIN)', '19 characters, printed on the front of the PVC.', '90F5B1234567890123']
  };

  // ---------------------------------------------------------------- ID type
  var radios = document.querySelectorAll('input[name=id_type]');
  function syncType() {
    var type = document.querySelector('input[name=id_type]:checked').value;
    radios.forEach(function (r) { r.closest('.id-type').classList.toggle('selected', r.checked); });
    var h = HELP[type];
    document.getElementById('id_number_label').textContent = h[0];
    document.getElementById('id_number_help').textContent = h[1];
    var input = document.getElementById('id_number');
    input.placeholder = h[2];
    input.inputMode = type === 'nin' ? 'numeric' : 'text';
    input.maxLength = type === 'nin' ? 11 : 24;
    document.querySelectorAll('.doc-only').forEach(function (el) { el.hidden = type === 'nin'; });
    document.querySelector('.step-num-selfie').textContent = type === 'nin' ? '2' : '3';
  }
  radios.forEach(function (r) { r.addEventListener('change', syncType); });
  syncType();

  // ---------------------------------------------------------------- image helpers
  // Draws a source onto a canvas no larger than maxSide and returns a JPEG data URL.
  function toJpeg(source, w, h, maxSide) {
    var scale = Math.min(1, maxSide / Math.max(w, h));
    var c = document.createElement('canvas');
    c.width = Math.round(w * scale);
    c.height = Math.round(h * scale);
    c.getContext('2d').drawImage(source, 0, 0, c.width, c.height);
    return c.toDataURL('image/jpeg', 0.85);
  }

  function fileToJpeg(file, maxSide, done) {
    var url = URL.createObjectURL(file);
    var img = new Image();
    img.onload = function () {
      done(toJpeg(img, img.naturalWidth, img.naturalHeight, maxSide));
      URL.revokeObjectURL(url);
    };
    img.onerror = function () { done(null); URL.revokeObjectURL(url); };
    img.src = url;
  }

  // ---------------------------------------------------------------- document photo
  var docFile = document.getElementById('document_file');
  docFile.addEventListener('change', function () {
    var f = docFile.files[0];
    if (!f) return;
    docFile.nextElementSibling.textContent = f.name;
    fileToJpeg(f, 1600, function (data) {
      if (!data) return;
      document.getElementById('document_data').value = data;
      var prev = document.getElementById('document_preview');
      prev.src = data;
      prev.hidden = false;
      docFile.value = ''; // send the resized copy only
    });
  });

  // ---------------------------------------------------------------- selfie
  var video = document.getElementById('selfie_video');
  var preview = document.getElementById('selfie_preview');
  var placeholder = document.getElementById('selfie_placeholder');
  var oval = document.getElementById('selfie_oval');
  var btnStart = document.getElementById('selfie_start');
  var btnCapture = document.getElementById('selfie_capture');
  var btnRetake = document.getElementById('selfie_retake');
  var selfieData = document.getElementById('selfie_data');
  var selfieFile = document.getElementById('selfie_file');
  var stream = null;

  function stopCamera() {
    if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
    video.hidden = true;
    oval.hidden = true;
  }

  function showSelfie(data) {
    selfieData.value = data;
    preview.src = data;
    preview.hidden = false;
    placeholder.hidden = true;
    btnStart.hidden = true;
    btnCapture.hidden = true;
    btnRetake.hidden = false;
  }

  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    btnStart.hidden = true; // no camera API (or not https): upload only
  }

  btnStart.addEventListener('click', function () {
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } }, audio: false })
      .then(function (s) {
        stream = s;
        video.srcObject = s;
        video.play();
        video.hidden = false;
        oval.hidden = false;
        placeholder.hidden = true;
        preview.hidden = true;
        btnStart.hidden = true;
        btnCapture.hidden = false;
      })
      .catch(function () {
        alert('We could not open your camera. Allow camera access, or upload a selfie instead.');
      });
  });

  btnCapture.addEventListener('click', function () {
    if (!video.videoWidth) return;
    var data = toJpeg(video, video.videoWidth, video.videoHeight, 800);
    stopCamera();
    showSelfie(data);
  });

  btnRetake.addEventListener('click', function () {
    selfieData.value = '';
    preview.hidden = true;
    btnRetake.hidden = true;
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
      btnStart.click();
    } else {
      placeholder.hidden = false;
    }
  });

  selfieFile.addEventListener('change', function () {
    var f = selfieFile.files[0];
    if (!f) return;
    stopCamera();
    fileToJpeg(f, 800, function (data) {
      if (data) { showSelfie(data); selfieFile.value = ''; }
    });
  });

  // ---------------------------------------------------------------- submit
  document.getElementById('idForm').addEventListener('submit', function () {
    stopCamera();
    var b = document.getElementById('submit_id');
    b.disabled = true;
    b.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Checking your ID…';
  });
})();
