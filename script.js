document.getElementById('feedbackForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const title = document.getElementById('title').value;
    const description = document.getElementById('description').value;
    const category = document.getElementById('category').value;
    const time = new Date().toLocaleString();

    const feedbackItem = { title, description, category, time };

    const feedbackItems = JSON.parse(localStorage.getItem('feedbackItems')) || [];
    feedbackItems.push(feedbackItem);
    localStorage.setItem('feedbackItems', JSON.stringify(feedbackItems));

    // Create Reddit-style card
    const li = document.createElement('li');
    li.classList.add('reddit-post');
    li.innerHTML = `
        <div class="vote-column">⬆️<br>⬇️</div>
        <div class="post-content">
            <div class="post-title">${title}</div>
            <div class="post-meta">Category: ${category} • Posted just now</div>
            <div class="post-description">${description}</div>
        </div>
    `;
    document.getElementById('feedbackDisplay').appendChild(li);

    document.getElementById('feedbackForm').reset();
});
