document.addEventListener('DOMContentLoaded', async () => {
  const postsContainer = document.getElementById('posts');
  const postForm = document.getElementById('create-post');
  const authArea = document.getElementById('auth-area');

  // Check session
  const resSession = await fetch('/session');
  const session = await resSession.json();

  if (session.loggedIn) {
    postForm.style.display = 'block';
    authArea.innerHTML = `Welcome, ${session.name}! <a href="/logout">Logout</a>`;
  }

  // Fetch posts
  const resPosts = await fetch('/posts');
  const posts = await resPosts.json();

  posts.forEach(post => {
    const div = document.createElement('div');
    div.innerHTML = `
      <h4>${post.title}</h4>
      <p>${post.content}</p>
      <p>By: ${post.authorName}</p>
      <p>
        <button class="vote-btn" data-id="${post._id}" data-type="upvote">👍</button>
        <button class="vote-btn" data-id="${post._id}" data-type="downvote">👎</button>
        ${post.upvotes.length - post.downvotes.length} points | 💬 ${post.commentCount}
      </p>
      <div id="comments-${post._id}"><em>Loading comments...</em></div>
      ${session.loggedIn ? `
        <form class="comment-form" data-postid="${post._id}">
          <input type="text" name="content" placeholder="Write a comment..." required>
          <button type="submit">Post</button>
        </form>` : ''}
      <hr>
    `;
    postsContainer.appendChild(div);

    // Fetch comments
    fetch(`/comments/${post._id}`)
      .then(res => res.json())
      .then(comments => {
        const commentBox = document.getElementById(`comments-${post._id}`);
        if (comments.length === 0) {
          commentBox.innerHTML = "<p><em>No comments yet.</em></p>";
        } else {
          commentBox.innerHTML = comments.map(c => 
            `<p><strong>${c.authorName}:</strong> ${c.content}</p>`
          ).join('');
        }
      });
  });

  // Post submission
  document.getElementById('postForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const title = document.getElementById('title').value;
    const content = document.getElementById('content').value;

    await fetch('/create-post', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ title, content })
    });

    window.location.reload(); // refresh to show new post
  });

  // Comment submission
  document.addEventListener('submit', async (e) => {
    if (e.target.classList.contains('comment-form')) {
      e.preventDefault();
      const form = e.target;
      const postId = form.getAttribute('data-postid');
      const content = form.querySelector('input[name="content"]').value;

      await fetch('/add-comment', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ postId, content })
      });

      window.location.reload();
    }
  });

  // Voting
  document.addEventListener('click', async (e) => {
    if (e.target.classList.contains('vote-btn')) {
      const postId = e.target.getAttribute('data-id');
      const voteType = e.target.getAttribute('data-type');

      await fetch('/vote', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ postId, voteType })
      });

      window.location.reload();
    }
  });
});
