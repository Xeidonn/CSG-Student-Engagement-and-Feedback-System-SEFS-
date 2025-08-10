<?php
// survey.php (front-end page for users)
$survey_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($survey_id <= 0) { http_response_code(400); echo "Invalid survey id"; exit; }
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Survey #<?= htmlspecialchars($survey_id) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-3">
  <div class="container">
    <h3 class="mb-3">Survey</h3>
    <form id="surveyForm" class="vstack gap-3"></form>
    <div class="mt-3">
      <button id="submitBtn" class="btn btn-primary">Submit</button>
      <a href="index.php" class="btn btn-light ms-2">Back</a>
    </div>
  </div>

<script>
const surveyId = <?= $survey_id ?>;
const form = document.getElementById('surveyForm');
const submitBtn = document.getElementById('submitBtn');

// Load questions
(async function load() {
  try {
    const res = await fetch(`api/surveys.php?action=questions&survey_id=${surveyId}`);
    const data = await res.json();
    if (!data.success) throw new Error(data.error || 'Failed to load questions');

    data.questions.forEach(q => {
      const group = document.createElement('div');
      group.className = 'card p-3';
      group.dataset.qid = q.question_id;

      let inputHTML = '';
      if (q.question_type === 'open_ended') {
        inputHTML = `<textarea class="form-control ans-text" rows="2" placeholder="Your answer..."></textarea>`;
      } else if (q.question_type === 'rating') {
        inputHTML = `
          <select class="form-select ans-rating">
            <option value="">-- Select rating --</option>
            <option value="1">1</option><option value="2">2</option>
            <option value="3">3</option><option value="4">4</option>
            <option value="5">5</option>
          </select>`;
      } else if (q.question_type === 'multiple_choice') {
        const opts = (q.options || []).map(o => `<option value="${o}">${o}</option>`).join('');
        inputHTML = `<select class="form-select ans-choice"><option value="">-- Choose --</option>${opts}</select>`;
      }

      group.innerHTML = `
        <label class="form-label fw-semibold">${q.question_text} ${q.is_required ? '<span class="text-danger">*</span>' : ''}</label>
        ${inputHTML}
      `;
      form.appendChild(group);
    });

  } catch (e) {
    alert('Failed to load survey: ' + e.message);
  }
})();

// Submit answers
submitBtn.addEventListener('click', async () => {
  const blocks = [...form.querySelectorAll('.card')];
  const responses = [];

  for (const b of blocks) {
    const qid = parseInt(b.dataset.qid, 10);
    let answer_text = null, answer_rating = null, answer_choice = null;

    const txt = b.querySelector('.ans-text');
    const rat = b.querySelector('.ans-rating');
    const cho = b.querySelector('.ans-choice');

    if (txt) answer_text = txt.value.trim() || null;
    if (rat) answer_rating = rat.value ? parseInt(rat.value, 10) : null;
    if (cho) answer_choice = cho.value || null;

    responses.push({ question_id: qid, answer_text, answer_rating, answer_choice });
  }

  try {
    const res = await fetch('api/surveys.php?action=respond', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ survey_id: surveyId, responses })
    });
    const raw = await res.text();
    let data; try { data = JSON.parse(raw); } catch { throw new Error(raw.slice(0,300)); }
    if (!res.ok || data.success !== true) throw new Error(data.error || 'Submit failed');

    alert('Thanks! Your response was submitted.');
window.location.href = 'index.php';
  } catch (e) {
    alert('Failed to submit: ' + e.message);
  }
});
</script>
</body>
</html>
