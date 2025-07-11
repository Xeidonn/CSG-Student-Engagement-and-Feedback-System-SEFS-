// Event listener to handle feedback form submission
document.getElementById('feedbackForm').addEventListener('submit', function(e) {
    e.preventDefault(); // Prevent default form submission

    // Collect form data
    const title = document.getElementById('title').value;
    const description = document.getElementById('description').value;
    const category = document.getElementById('category').value;
    const time = new Date().toLocaleString();
    const imageUrl = document.getElementById('imageUrl').value; // Input for the image URL

    // Create a feedback item
    const feedbackItem = { title, description, category, time, imageUrl };

    // Store feedback in localStorage
    const feedbackItems = JSON.parse(localStorage.getItem('feedbackItems')) || [];
    feedbackItems.push(feedbackItem);
    localStorage.setItem('feedbackItems', JSON.stringify(feedbackItems));

    // Create Reddit-style post element
    const li = document.createElement('li');
    li.classList.add('reddit-post');
    li.innerHTML = `
        <div class="vote-column">⬆️<br>⬇️</div>
        <div class="post-content">
            <div class="post-title">${title}</div>
            <div class="post-meta">Category: ${category} • Posted just now</div>
            <div class="post-description">${description}</div>
            ${imageUrl ? `<img src="${imageUrl}" class="post-image" alt="Post Image">` : ''}
        </div>
    `;

    // Append the new post to the display list
    document.getElementById('feedbackDisplay').appendChild(li);

    // Reset the form for the next submission
    document.getElementById('feedbackForm').reset();
});

// Event listener to display all feedback posts when the page is loaded
window.addEventListener('DOMContentLoaded', () => {
    const feedbackList = JSON.parse(localStorage.getItem('feedbackItems')) || [];
    const listContainer = document.getElementById('feedbackDisplay');

    // Display a message if no feedback is available
    if (feedbackList.length === 0) {
        listContainer.innerHTML = '<p>No feedback yet.</p>';
        return;
    }

    // Loop through feedback items and display each one
    feedbackList.forEach(item => {
        const li = document.createElement('li');
        li.classList.add('reddit-post');
        li.innerHTML = `
            <div class="vote-column">⬆️<br>⬇️</div>
            <div class="post-content">
                <div class="post-title">${item.title}</div>
                <div class="post-meta">Category: ${item.category} • ${item.time}</div>
                <div class="post-description">${item.description}</div>
                ${item.imageUrl ? `<img src="${item.imageUrl}" class="post-image" alt="Post Image">` : ''}
            </div>
        `;
        listContainer.appendChild(li);
    });
});
