<?php
/**
 * LexFind - Floating AI Assistant widget.
 * Included once from includes/footer.php and includes/dash-footer.php, right
 * before </body>, so it appears on every page. Expects $base to already be
 * set (both footer files define it via basePath()).
 */
?>
<style>
  #lf-ai-launcher {
    position: fixed; bottom: 22px; right: 22px; z-index: 9999;
    width: 58px; height: 58px; border-radius: 50%;
    background: #1c1f27; color: #c9a84c; border: none;
    box-shadow: 0 6px 20px rgba(0,0,0,0.25);
    font-size: 1.4rem; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: transform 0.15s ease;
  }
  #lf-ai-launcher:hover { transform: scale(1.06); }

  #lf-ai-panel {
    position: fixed; bottom: 92px; right: 22px; z-index: 9999;
    width: 340px; max-width: calc(100vw - 32px);
    height: 460px; max-height: calc(100vh - 140px);
    background: #fff; border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.25);
    display: none; flex-direction: column; overflow: hidden;
    font-family: Helvetica, Arial, sans-serif;
  }
  #lf-ai-panel.lf-open { display: flex; }

  #lf-ai-head {
    background: #1c1f27; color: #fff; padding: 14px 16px;
    display: flex; justify-content: space-between; align-items: center;
    flex-shrink: 0;
  }
  #lf-ai-head strong { color: #c9a84c; font-size: 0.95rem; }
  #lf-ai-head span { display: block; font-size: 0.7rem; color: #b7b7bd; margin-top: 2px; }
  #lf-ai-close { background: none; border: none; color: #ccc; font-size: 1.1rem; cursor: pointer; }
  #lf-ai-close:hover { color: #fff; }

  #lf-ai-messages {
    flex: 1; overflow-y: auto; padding: 14px; background: #f7f5f0;
  }
  .lf-ai-msg { margin-bottom: 10px; display: flex; }
  .lf-ai-msg.lf-user { justify-content: flex-end; }
  .lf-ai-bubble {
    max-width: 82%; padding: 9px 12px; border-radius: 10px;
    font-size: 0.85rem; line-height: 1.4; white-space: pre-wrap;
  }
  .lf-ai-msg.lf-user .lf-ai-bubble { background: #1c1f27; color: #fff; border-bottom-right-radius: 2px; }
  .lf-ai-msg.lf-bot .lf-ai-bubble  { background: #fff; color: #222; border: 1px solid #e6e6e6; border-bottom-left-radius: 2px; }
  .lf-ai-msg.lf-error .lf-ai-bubble { background: #fdeceb; color: #a33; border: 1px solid #f3c8c4; }

  #lf-ai-typing { font-size: 0.78rem; color: #888; padding: 0 14px 8px; display: none; }

  #lf-ai-inputrow {
    display: flex; gap: 8px; padding: 10px; border-top: 1px solid #eee; flex-shrink: 0; background: #fff;
  }
  #lf-ai-input {
    flex: 1; border: 1px solid #ddd; border-radius: 8px; padding: 9px 11px;
    font-size: 0.85rem; resize: none; font-family: inherit;
  }
  #lf-ai-send {
    background: #c9a84c; color: #1c1f27; border: none; border-radius: 8px;
    padding: 0 16px; font-weight: 700; cursor: pointer; font-size: 0.85rem;
  }
  #lf-ai-send:disabled { opacity: 0.6; cursor: default; }

  #lf-ai-suggestions { padding: 0 14px 10px; display: flex; flex-wrap: wrap; gap: 6px; }
  .lf-ai-chip {
    background: #fff; border: 1px solid #ddd; border-radius: 14px;
    padding: 5px 10px; font-size: 0.72rem; cursor: pointer; color: #333;
  }
  .lf-ai-chip:hover { border-color: #c9a84c; color: #1c1f27; }

  @media (max-width: 420px) {
    #lf-ai-panel { right: 16px; left: 16px; width: auto; }
    #lf-ai-launcher { right: 16px; }
  }
</style>

<button id="lf-ai-launcher" type="button" aria-label="Open LexFind Assistant" title="Ask LexFind Assistant">
  <i class="fa-solid fa-comment-dots"></i>
</button>

<div id="lf-ai-panel">
  <div id="lf-ai-head">
    <div>
      <strong>LexFind Assistant</strong>
      <span>Ask about advisors, fees, or how LexFind works</span>
    </div>
    <button id="lf-ai-close" type="button" aria-label="Close">✕</button>
  </div>
  <div id="lf-ai-messages"></div>
  <div id="lf-ai-typing">LexFind Assistant is typing…</div>
  <div id="lf-ai-suggestions">
    <div class="lf-ai-chip" data-msg="How do I book a legal advisor?">How do I book an advisor?</div>
    <div class="lf-ai-chip" data-msg="I have a problem with my landlord not returning my deposit. Which advisor should I talk to?">Landlord won't return my deposit</div>
    <div class="lf-ai-chip" data-msg="How much does a consultation usually cost?">How much does it cost?</div>
  </div>
  <div id="lf-ai-inputrow">
    <textarea id="lf-ai-input" rows="1" placeholder="Type your question…" maxlength="600"></textarea>
    <button id="lf-ai-send" type="button">Send</button>
  </div>
</div>

<script>
(function () {
  // Chat now runs on a Cloudflare Worker instead of actions/ai-chat.php,
  // because InfinityFree's bot-protection layer blocks fetch()/AJAX calls
  // to .php files. Replace this with your actual deployed Worker URL.
  var endpoint = 'lex-chat.husain77202.workers.dev';

  var launcher = document.getElementById('lf-ai-launcher');
  var panel = document.getElementById('lf-ai-panel');
  var closeBtn = document.getElementById('lf-ai-close');
  var messagesEl = document.getElementById('lf-ai-messages');
  var typingEl = document.getElementById('lf-ai-typing');
  var input = document.getElementById('lf-ai-input');
  var sendBtn = document.getElementById('lf-ai-send');
  var suggestions = document.getElementById('lf-ai-suggestions');
  var opened = false;
  var greeted = false;

  function addBubble(text, cls) {
    var row = document.createElement('div');
    row.className = 'lf-ai-msg ' + cls;
    var bubble = document.createElement('div');
    bubble.className = 'lf-ai-bubble';
    bubble.textContent = text;
    row.appendChild(bubble);
    messagesEl.appendChild(row);
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function greet() {
    if (greeted) return;
    greeted = true;
    addBubble("Hi! I'm the LexFind Assistant. Ask me how the platform works, or describe a legal problem and I'll point you to the right kind of advisor.", 'lf-bot');
  }

  function openPanel() {
    panel.classList.add('lf-open');
    opened = true;
    greet();
    input.focus();
  }
  function closePanel() {
    panel.classList.remove('lf-open');
    opened = false;
  }

  launcher.addEventListener('click', function () {
    opened ? closePanel() : openPanel();
  });
  closeBtn.addEventListener('click', closePanel);

  function send(messageText) {
    var text = (messageText !== undefined ? messageText : input.value).trim();
    if (!text) return;

    suggestions.style.display = 'none';
    addBubble(text, 'lf-user');
    input.value = '';
    sendBtn.disabled = true;
    typingEl.style.display = 'block';
    messagesEl.scrollTop = messagesEl.scrollHeight;

    fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text })
    })
      .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
      .then(function (result) {
        typingEl.style.display = 'none';
        sendBtn.disabled = false;
        if (result.ok && result.data.reply) {
          addBubble(result.data.reply, 'lf-bot');
        } else {
          addBubble(result.data.error || 'Something went wrong. Please try again.', 'lf-error');
        }
      })
      .catch(function () {
        typingEl.style.display = 'none';
        sendBtn.disabled = false;
        addBubble("Sorry, I couldn't reach the assistant. Please check your connection and try again.", 'lf-error');
      });
  }

  sendBtn.addEventListener('click', function () { send(); });
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      send();
    }
  });
  Array.prototype.forEach.call(document.querySelectorAll('.lf-ai-chip'), function (chip) {
    chip.addEventListener('click', function () { send(chip.getAttribute('data-msg')); });
  });
})();
</script>
