document.getElementById('feedbackForm').addEventListener('submit', function(e) {
    e.preventDefault(); // Prevent form from refreshing the page

    // Get the values from the form
    const title = document.getElementById('title').value;
    const description = document.getElementById('description').value;
    const category = document.getElementById('category').value;

    // Create a new list item for the feedback
    const feedbackItem = document.createElement('li');
    feedbackItem.innerHTML = `<strong>${title}</strong> (${category})<br>${description}<br><span>Just now</span>`;

    // Append the new feedback to the list
    document.getElementById('feedbackDisplay').appendChild(feedbackItem);

    // Reset the form fields
    document.getElementById('title').value = '';
    document.getElementById('description').value = '';
    document.getElementById('category').value = 'general';
});
