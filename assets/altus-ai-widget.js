(function () {
  function init() {
    var root = document.getElementById('altus-ai-root');
    if (!root || root.dataset.ready || !window.AltusAI) return;
    root.dataset.ready = '1';
    var launcher = document.getElementById('altus-ai-launcher');
    var panel = document.getElementById('altus-ai-panel');
    var closeBtn = document.getElementById('altus-ai-close');
    var msgs = document.getElementById('altus-ai-msgs');
    var form = document.getElementById('altus-ai-form');
    var input = document.getElementById('altus-ai-input');
    var creditN = document.getElementById('altus-ai-credit-n');
    var session = 's' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
    var captureStep = 0, leadName = '';
    function addMsg(text, who) {
      var d = document.createElement('div');
      d.className = 'altus-ai-msg ' + who;
      d.textContent = text;
      msgs.appendChild(d);
      msgs.scrollTop = msgs.scrollHeight;
    }
    function typing(on) {
      var t = msgs.querySelector('.altus-ai-typing');
      if (on && !t) {
        t = document.createElement('div');
        t.className = 'altus-ai-typing';
        t.innerHTML = '<span></span><span></span><span></span>';
        msgs.appendChild(t); msgs.scrollTop = msgs.scrollHeight;
      } else if (!on && t) { t.remove(); }
    }
    function open() {
      panel.hidden = false;
      if (!msgs.children.length) addMsg(window.AltusAI.welcome, 'bot');
      setTimeout(function () { input.focus(); }, 80);
    }
    launcher.addEventListener('click', function () { panel.hidden ? open() : (panel.hidden = true); });
    closeBtn.addEventListener('click', function () { panel.hidden = true; });
    document.querySelectorAll('.altus-ai-chips button').forEach(function (b) {
      b.addEventListener('click', function () { input.value = b.dataset.q; form.dispatchEvent(new Event('submit', { cancelable: true })); });
    });
    function sendLead(name, contact, notes) {
      var fd = new FormData();
      fd.append('action', 'altus_ai_lead'); fd.append('nonce', AltusAI.nonce);
      fd.append('name', name); fd.append('contact', contact); fd.append('notes', notes || '');
      fetch(AltusAI.ajax, { method: 'POST', body: fd, credentials: 'same-origin' });
    }
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var text = input.value.trim();
      if (!text) return;
      input.value = '';
      addMsg(text, 'user');
      if (captureStep === 1) { leadName = text; captureStep = 2; setTimeout(function () { addMsg('Thanks ' + leadName.split(' ')[0] + '! What\'s the best number or email to reach you on?', 'bot'); }, 500); return; }
      if (captureStep === 2) {
        captureStep = 0;
        sendLead(leadName, text, 'Callback request from website chat');
        setTimeout(function () { addMsg('All set — someone will be in touch shortly. Anything else I can help with?', 'bot'); }, 500);
        return;
      }
      typing(true);
      var fd = new FormData();
      fd.append('action', 'altus_ai_chat'); fd.append('nonce', AltusAI.nonce);
      fd.append('message', text); fd.append('session', session);
      fetch(AltusAI.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          typing(false);
          if (d.success) {
            if (typeof d.data.credits === 'number' && creditN) creditN.textContent = d.data.credits;
            addMsg(d.data.reply, 'bot');
            if (d.data.capture) { captureStep = 1; }
          } else { addMsg('Sorry, something glitched — please try again.', 'bot'); }
        })
        .catch(function () { typing(false); addMsg('Sorry, something glitched — please try again.', 'bot'); });
    });
  }
  window.AltusAIInit = init;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
