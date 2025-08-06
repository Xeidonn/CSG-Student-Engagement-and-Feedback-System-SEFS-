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
      
      <i class="fas fa-comments show-comments-btn" data-postid="${post._id}" style="cursor: pointer;"></i>
      <div id="comments-${post._id}" style="display: none;"><em>Loading comments...</em></div>
      
      // CHANGES: Show Edit and Delete buttons if the logged-in user is the author
      ${session.name === post.authorName ? `
        <button class="edit-post-btn" data-postid="${post._id}">Edit Post</button>
        <button class="delete-post-btn" data-postid="${post._id}">Delete Post</button>
      ` : ''}

      <hr>
    `;
    postsContainer.appendChild(div);

    // Fetch comments but don't display them yet
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

    // Show comments when the comments icon is clicked
    const showCommentsBtn = div.querySelector('.show-comments-btn');
    showCommentsBtn.addEventListener('click', () => {
      const commentBox = div.querySelector(`#comments-${post._id}`);
      commentBox.style.display = commentBox.style.display === 'none' ? 'block' : 'none';
      showCommentsBtn.classList.toggle('fa-comments');
      showCommentsBtn.classList.toggle('fa-times');
    });

    // **CHANGES: Edit Post**
    const editPostBtn = div.querySelector('.edit-post-btn');
    if (editPostBtn) {
      editPostBtn.addEventListener('click', () => {
        // Redirect to an edit post page (or open an inline form for editing)
        window.location.href = `/edit-post/${post._id}`;
      });
    }

    // **CHANGES: Delete Post**
    const deletePostBtn = div.querySelector('.delete-post-btn');
    if (deletePostBtn) {
      deletePostBtn.addEventListener('click', async () => {
        const confirmDelete = confirm("Are you sure you want to delete this post?");
        if (confirmDelete) {
          await fetch(`/delete-post/${post._id}`, { method: 'DELETE' });
          window.location.reload(); // Refresh the page to reflect the deletion
        }
      });
    }
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
