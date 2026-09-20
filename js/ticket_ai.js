(function () {
  var btn = document.getElementById('btn-ai-suggest');
  var statusEl = document.getElementById('ai-suggest-status');
  var box = document.getElementById('ai-suggest-box');
  var detail = document.getElementById('ai-suggest-detail');
  var subject = document.getElementById('subject');
  var description = document.getElementById('description');
  var category = document.getElementById('category');
  var priority = document.getElementById('priority');

  if (!btn) return;

  function setStatus(msg, isError) {
    statusEl.textContent = msg || '';
    statusEl.classList.toggle('is-error', !!isError);
  }

  btn.addEventListener('click', function () {
    var sub = (subject.value || '').trim();
    var desc = (description.value || '').trim();
    if (!sub && !desc) {
      setStatus('Type a subject or description first.', true);
      box.hidden = true;
      return;
    }

    btn.disabled = true;
    setStatus('Asking AI…', false);
    box.hidden = true;

    fetch('../logic/ai_suggest.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ subject: sub, description: desc }),
      credentials: 'same-origin',
    })
      .then(function (res) {
        return res.json().then(function (data) {
          return { okHttp: res.ok, data: data };
        });
      })
      .then(function (pack) {
        var data = pack.data || {};
        if (!data.ok) {
          setStatus(data.error || 'AI suggestion failed.', true);
          return;
        }

        if (data.category && category) {
          category.value = data.category;
        }
        if (data.priority && priority) {
          priority.value = data.priority;
        }

        var parts = [];
        parts.push('Category: ' + (data.category || '—'));
        parts.push('Priority: ' + (data.priority || '—'));
        if (data.confidence != null) {
          parts.push('Confidence: ' + Math.round(Number(data.confidence) * 100) + '%');
        }
        detail.textContent = parts.join(' · ');
        box.hidden = false;

        if (!data.priority) {
          setStatus('Category filled. Priority missing from AI response (check classifier JSON).', true);
        } else {
          setStatus('Suggestion applied. You can still change the dropdowns.', false);
        }
      })
      .catch(function () {
        setStatus('Network error talking to AI bridge.', true);
      })
      .finally(function () {
        btn.disabled = false;
      });
  });
})();
