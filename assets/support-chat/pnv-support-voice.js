(function (global) {
  'use strict';

  var MAX_RECORD_MS = 5 * 60 * 1000;
  var MAX_BYTES = 10 * 1024 * 1024;
  var activeAudio = null;
  var activePlayer = null;

  function safeDuration(audio) {
    if (!audio) {
      return 0;
    }
    var d = Number(audio.duration);
    return Number.isFinite(d) && d > 0 ? d : 0;
  }

  function formatDuration(seconds) {
    var sec = Math.max(0, Math.floor(Number(seconds) || 0));
    if (!Number.isFinite(sec)) {
      sec = 0;
    }
    var m = Math.floor(sec / 60);
    var s = sec % 60;
    return m + ':' + (s < 10 ? '0' : '') + s;
  }

  function pickMimeType() {
    if (typeof MediaRecorder === 'undefined') {
      return '';
    }
    var types = [
      'audio/webm;codecs=opus',
      'audio/webm',
      'audio/ogg;codecs=opus',
      'audio/ogg'
    ];
    for (var i = 0; i < types.length; i++) {
      if (MediaRecorder.isTypeSupported(types[i])) {
        return types[i];
      }
    }
    return '';
  }

  function extensionForMime(mime) {
    if (mime.indexOf('ogg') >= 0) {
      return 'ogg';
    }
    return 'webm';
  }

  function syncMicSend(textarea, micBtn, sendBtn) {
    if (!textarea || !micBtn || !sendBtn) {
      return;
    }
    var hasText = (textarea.value || '').trim().length > 0;
    micBtn.hidden = hasText;
    sendBtn.hidden = !hasText;
  }

  function pauseOtherPlayers(currentPlayer, currentAudio) {
    document.querySelectorAll('.pnv-voice-player.is-playing').forEach(function (p) {
      if (p === currentPlayer) {
        return;
      }
      p.classList.remove('is-playing');
      var ic = p.querySelector('.pnv-voice-play-icon');
      if (ic) {
        ic.textContent = '▶';
      }
      var a = p.querySelector('.pnv-voice-audio');
      var bars = p.querySelector('.pnv-voice-bars');
      var dur = p.querySelector('.pnv-voice-duration');
      if (a) {
        a.pause();
        a.currentTime = 0;
      }
      if (bars) {
        bars.style.setProperty('--voice-progress', '0%');
      }
      if (dur && a) {
        dur.textContent = formatDuration(safeDuration(a));
      }
    });
    if (activeAudio && activeAudio !== currentAudio) {
      activeAudio.pause();
      activeAudio.currentTime = 0;
    }
  }

  function bindVoicePlayers(root) {
    var scope = root || document;
    scope.querySelectorAll('.pnv-voice-player').forEach(function (player) {
      if (player.dataset.pnvVoiceBound === '1') {
        return;
      }
      player.dataset.pnvVoiceBound = '1';
      var audio = player.querySelector('.pnv-voice-audio');
      var btn = player.querySelector('.pnv-voice-play');
      var icon = player.querySelector('.pnv-voice-play-icon');
      var durationEl = player.querySelector('.pnv-voice-duration');
      var bars = player.querySelector('.pnv-voice-bars');
      if (!audio || !btn) {
        return;
      }

      if (bars) {
        bars.style.setProperty('--voice-progress', '0%');
      }

      function showTotalOnly() {
        if (!durationEl) {
          return;
        }
        durationEl.textContent = formatDuration(safeDuration(audio));
      }

      function showProgressTime() {
        if (!durationEl) {
          return;
        }
        var total = safeDuration(audio);
        if (total <= 0) {
          showTotalOnly();
          return;
        }
        durationEl.textContent =
          formatDuration(audio.currentTime) + ' / ' + formatDuration(total);
      }

      function syncProgressBar() {
        if (!bars) {
          return;
        }
        var total = safeDuration(audio);
        if (total <= 0) {
          bars.style.setProperty('--voice-progress', '0%');
          return;
        }
        var pct = Math.max(
          0,
          Math.min(100, (audio.currentTime / total) * 100)
        );
        bars.style.setProperty('--voice-progress', pct + '%');
      }

      function refreshDurationLabel() {
        if (player.classList.contains('is-playing')) {
          showProgressTime();
        } else {
          showTotalOnly();
        }
        syncProgressBar();
      }

      audio.addEventListener('loadedmetadata', refreshDurationLabel);
      audio.addEventListener('durationchange', refreshDurationLabel);
      audio.addEventListener('canplay', refreshDurationLabel);

      audio.addEventListener('timeupdate', function () {
        if (audio.paused) {
          return;
        }
        showProgressTime();
        syncProgressBar();
      });

      btn.addEventListener('click', function () {
        if (audio.paused) {
          pauseOtherPlayers(player, audio);
          activeAudio = audio;
          activePlayer = player;
          audio.play().catch(function () {});
          player.classList.add('is-playing');
          if (icon) {
            icon.textContent = '❚❚';
          }
          showProgressTime();
        } else {
          audio.pause();
          player.classList.remove('is-playing');
          if (icon) {
            icon.textContent = '▶';
          }
          showTotalOnly();
        }
      });

      audio.addEventListener('ended', function () {
        player.classList.remove('is-playing');
        if (icon) {
          icon.textContent = '▶';
        }
        audio.currentTime = 0;
        if (bars) {
          bars.style.setProperty('--voice-progress', '0%');
        }
        showTotalOnly();
        if (activeAudio === audio) {
          activeAudio = null;
          activePlayer = null;
        }
      });

      audio.addEventListener('pause', function () {
        if (!audio.ended && audio.currentTime > 0) {
          player.classList.remove('is-playing');
          if (icon) {
            icon.textContent = '▶';
          }
          syncProgressBar();
          showTotalOnly();
        }
      });

      showTotalOnly();
    });
  }

  function bindVoiceComposer(cfg) {
    cfg = cfg || {};
    var form = cfg.formEl;
    var textarea = cfg.textareaEl;
    var micBtn =
      cfg.micBtnEl ||
      (form ? form.querySelector('.msgIconBtn--mic') : null);
    var sendBtn =
      cfg.sendBtnEl ||
      (form ? form.querySelector('.msgIconBtn--send') : null);
    var audioInput =
      cfg.audioInputEl ||
      (cfg.audioInputId ? document.getElementById(cfg.audioInputId) : null) ||
      (form ? form.querySelector('input[type="file"][name="audio"]') : null);
    var recordingEl = form ? form.querySelector('.pnv-voice-recording') : null;
    var previewEl = form ? form.querySelector('.pnv-voice-preview') : null;
    var composerRow = form ? form.querySelector('.msgComposerRow') : null;

    if (!form || !textarea || !micBtn || !sendBtn || !audioInput) {
      return;
    }

    var mimeType = pickMimeType();
    var mediaRecorder = null;
    var mediaStream = null;
    var chunks = [];
    var recordStart = 0;
    var recordTimer = null;
    var pendingBlob = null;
    var pendingUrl = '';
    var discardRecording = false;
    var sendAfterStop = false;
    var voiceUploading = false;
    var previewAudio = previewEl ? previewEl.querySelector('audio') : null;
    var previewDuration = previewEl
      ? previewEl.querySelector('.pnv-voice-preview-duration')
      : null;
    var recordTimeEl = recordingEl
      ? recordingEl.querySelector('.pnv-voice-recording-time')
      : null;
    var recordingSendBtn = recordingEl
      ? recordingEl.querySelector('.pnv-voice-recording-stop')
      : null;

    syncMicSend(textarea, micBtn, sendBtn);
    textarea.addEventListener('input', function () {
      syncMicSend(textarea, micBtn, sendBtn);
    });

    function setComposerMode(mode) {
      var recording = mode === 'recording';
      var preview = mode === 'preview';
      if (recordingEl) {
        recordingEl.hidden = !recording;
      }
      if (previewEl) {
        previewEl.hidden = !preview;
      }
      if (composerRow) {
        composerRow.hidden = recording || preview;
      }
      form.dataset.pnvVoiceMode = mode;
    }

    function stopTracksOnly() {
      if (mediaStream) {
        mediaStream.getTracks().forEach(function (track) {
          track.stop();
        });
      }
      mediaStream = null;
      mediaRecorder = null;
    }

    function cleanupStream() {
      stopTracksOnly();
      chunks = [];
    }

    function clearPreview() {
      pendingBlob = null;
      if (pendingUrl) {
        URL.revokeObjectURL(pendingUrl);
        pendingUrl = '';
      }
      if (previewAudio) {
        previewAudio.removeAttribute('src');
        previewAudio.load();
      }
      if (previewDuration) {
        previewDuration.textContent = '0:00';
      }
      try {
        audioInput.value = '';
      } catch (e) {}
      setComposerMode('idle');
      syncMicSend(textarea, micBtn, sendBtn);
    }

    function stopTimer() {
      if (recordTimer) {
        clearInterval(recordTimer);
        recordTimer = null;
      }
    }

    function updateRecordTimer() {
      if (!recordTimeEl) {
        return;
      }
      var elapsed = Math.min(MAX_RECORD_MS, Date.now() - recordStart);
      recordTimeEl.textContent = formatDuration(elapsed / 1000);
      if (elapsed >= MAX_RECORD_MS) {
        sendRecording();
      }
    }

    function sendRecording() {
      if (!mediaRecorder || mediaRecorder.state === 'inactive') {
        return;
      }
      if (voiceUploading) {
        return;
      }
      sendAfterStop = true;
      if (recordingSendBtn) {
        recordingSendBtn.disabled = true;
      }
      stopTimer();
      mediaRecorder.stop();
    }

    function cancelRecording() {
      stopTimer();
      discardRecording = true;
      sendAfterStop = false;
      if (recordingSendBtn) {
        recordingSendBtn.disabled = false;
      }
      if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
      } else {
        discardRecording = false;
        cleanupStream();
        setComposerMode('idle');
        syncMicSend(textarea, micBtn, sendBtn);
      }
    }

    function showPreview(blob) {
      pendingBlob = blob;
      if (pendingUrl) {
        URL.revokeObjectURL(pendingUrl);
      }
      pendingUrl = URL.createObjectURL(blob);
      if (previewAudio) {
        previewAudio.src = pendingUrl;
        previewAudio.load();
        var onMeta = function () {
          if (previewDuration) {
            previewDuration.textContent = formatDuration(
              safeDuration(previewAudio)
            );
          }
        };
        previewAudio.onloadedmetadata = onMeta;
        previewAudio.ondurationchange = onMeta;
      }
      setComposerMode('preview');
    }

    function assignInputFile(blob) {
      var ext = extensionForMime(blob.type || mimeType);
      var name = 'voice_' + Date.now() + '.' + ext;
      var file = new File([blob], name, {
        type: blob.type || mimeType || 'audio/webm'
      });
      var dt = new DataTransfer();
      dt.items.add(file);
      audioInput.files = dt.files;
    }

    function submitVoiceBlob(blob) {
      if (!blob || voiceUploading) {
        return;
      }
      if (blob.size > MAX_BYTES) {
        alert('حجم پیام صوتی بیش از حد مجاز است');
        return;
      }
      voiceUploading = true;
      pendingBlob = blob;
      assignInputFile(blob);
      var fd = new FormData(form);
      fd.set('message', (textarea.value || '').trim());
      fd.delete('image');
      var file = audioInput.files && audioInput.files[0];
      if (file) {
        fd.set('audio', file, file.name);
      }
      var action = form.getAttribute('action') || window.location.href;
      fetch(action, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        redirect: 'follow'
      })
        .then(function (res) {
          voiceUploading = false;
          if (recordingSendBtn) {
            recordingSendBtn.disabled = false;
          }
          pendingBlob = null;
          if (res.redirected && res.url) {
            window.location.href = res.url;
            return;
          }
          window.location.reload();
        })
        .catch(function () {
          voiceUploading = false;
          if (recordingSendBtn) {
            recordingSendBtn.disabled = false;
          }
          alert('ارسال پیام صوتی ناموفق بود');
          showPreview(blob);
        });
    }

    function submitVoice() {
      if (pendingBlob) {
        submitVoiceBlob(pendingBlob);
      }
    }

    micBtn.addEventListener('click', function (e) {
      e.preventDefault();
      if (!mimeType || typeof navigator.mediaDevices === 'undefined') {
        alert('مرورگر شما ضبط صدا را پشتیبانی نمی‌کند');
        return;
      }
      navigator.mediaDevices
        .getUserMedia({ audio: true })
        .then(function (stream) {
          mediaStream = stream;
          chunks = [];
          sendAfterStop = false;
          discardRecording = false;
          if (recordingSendBtn) {
            recordingSendBtn.disabled = false;
          }
          mediaRecorder = new MediaRecorder(stream, { mimeType: mimeType });
          mediaRecorder.ondataavailable = function (ev) {
            if (ev.data && ev.data.size > 0) {
              chunks.push(ev.data);
            }
          };
          mediaRecorder.onstop = function () {
            var recordedChunks = chunks.slice();
            chunks = [];
            stopTracksOnly();

            if (discardRecording) {
              discardRecording = false;
              sendAfterStop = false;
              setComposerMode('idle');
              syncMicSend(textarea, micBtn, sendBtn);
              return;
            }

            if (!recordedChunks.length) {
              sendAfterStop = false;
              if (recordingSendBtn) {
                recordingSendBtn.disabled = false;
              }
              setComposerMode('idle');
              syncMicSend(textarea, micBtn, sendBtn);
              return;
            }

            var blob = new Blob(recordedChunks, {
              type: mimeType || 'audio/webm'
            });

            if (blob.size <= 0) {
              sendAfterStop = false;
              if (recordingSendBtn) {
                recordingSendBtn.disabled = false;
              }
              setComposerMode('idle');
              syncMicSend(textarea, micBtn, sendBtn);
              return;
            }

            if (sendAfterStop) {
              sendAfterStop = false;
              setComposerMode('idle');
              syncMicSend(textarea, micBtn, sendBtn);
              submitVoiceBlob(blob);
              return;
            }

            setComposerMode('idle');
            syncMicSend(textarea, micBtn, sendBtn);
          };
          recordStart = Date.now();
          setComposerMode('recording');
          mediaRecorder.start(250);
          stopTimer();
          recordTimer = setInterval(updateRecordTimer, 250);
          updateRecordTimer();
        })
        .catch(function () {
          alert('دسترسی به میکروفون داده نشد');
        });
    });

    if (recordingEl) {
      var cancelBtn = recordingEl.querySelector('.pnv-voice-recording-cancel');
      if (recordingSendBtn) {
        recordingSendBtn.addEventListener('click', function (e) {
          e.preventDefault();
          sendRecording();
        });
      }
      if (cancelBtn) {
        cancelBtn.addEventListener('click', function (e) {
          e.preventDefault();
          cancelRecording();
        });
      }
    }

    if (previewEl) {
      var previewPlay = previewEl.querySelector('.pnv-voice-preview-play');
      var previewSend = previewEl.querySelector('.pnv-voice-preview-send');
      var previewCancel = previewEl.querySelector('.pnv-voice-preview-cancel');
      if (previewPlay && previewAudio) {
        previewPlay.addEventListener('click', function (e) {
          e.preventDefault();
          if (previewAudio.paused) {
            previewAudio.play().catch(function () {});
          } else {
            previewAudio.pause();
          }
        });
      }
      if (previewSend) {
        previewSend.addEventListener('click', function (e) {
          e.preventDefault();
          submitVoice();
        });
      }
      if (previewCancel) {
        previewCancel.addEventListener('click', function (e) {
          e.preventDefault();
          clearPreview();
        });
      }
    }

    form.addEventListener('submit', function (e) {
      if (form.dataset.pnvVoiceMode === 'recording') {
        e.preventDefault();
      }
    });
  }

  global.PNVSupportVoice = {
    bindComposer: bindVoiceComposer,
    bindPlayers: bindVoicePlayers,
    formatDuration: formatDuration,
    safeDuration: safeDuration
  };
})(window);
