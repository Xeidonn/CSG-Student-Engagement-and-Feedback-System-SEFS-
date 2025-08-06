document.addEventListener('DOMContentLoaded', async () => {
  const postsContainer = document.getElementById('posts');
  const postForm = document.getElementById('create-post');
  const authArea = document.getElementById('auth-area');

  // ----------------------------------------------------------------------------------------------------------------------------------------------------
  // ------------------------------------------------------------------SESSION & AUTH LOGIC -------------------------------------------------------------
  // ----------------------------------------------------------------------------------------------------------------------------------------------------

  const resSession = await fetch('/session');
  const session = await resSession.json();

  if (session.loggedIn) {
    postForm.style.display = 'block';
    authArea.innerHTML = `Welcome, ${session.name}! <a href="/logout">Logout</a>`;
  }

  // ----------------------------------------------------------------------------------------------------------------------------------------------------
  // ------------------------------------------------------------------POST RENDERING & FILTERING -------------------------------------------------------
  // ----------------------------------------------------------------------------------------------------------------------------------------------------

  const renderPosts = (posts) => {
    postsContainer.innerHTML = '';

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

        ${session.name === post.authorName ? `
          <button class="edit-post-btn" data-postid="${post._id}">Edit</button>
          <button class="delete-post-btn" data-postid="${post._id}">Delete</button>
        ` : ''}

        <div id="comments-${post._id}"><em>Loading comments...</em></div>

        ${session.loggedIn ? `
          <form class="comment-form" data-postid="${post._id}">
            <input type="text" name="content" placeholder="Write a comment..." required>
            <button type="submit">Post</button>
          </form>` : ''}

        <hr>
      `;
      postsContainer.appendChild(div);

      // Edit Post
      const editBtn = div.querySelector('.edit-post-btn');
      if (editBtn) {
        editBtn.addEventListener('click', () => {
          window.location.href = `/edit-post/${post._id}`;
        });
      }

      // Delete Post
      const deleteBtn = div.querySelector('.delete-post-btn');
      if (deleteBtn) {
        deleteBtn.addEventListener('click', async () => {
          const confirmDelete = confirm("Are you sure you want to delete this post?");
          if (confirmDelete) {
            await fetch(`/delete-post/${post._id}`, { method: 'DELETE' });
            window.location.reload();
          }
        });
      }

      // Load Comments
      fetch(`/comments/${post._id}`)
        .then(res => res.json())
        .then(comments => {
          const commentBox = div.querySelector(`#comments-${post._id}`);
          if (comments.length === 0) {
            commentBox.innerHTML = "<p><em>No comments yet.</em></p>";
          } else {
            commentBox.innerHTML = comments.map(c => `
              <div class="comment" id="comment-${c._id}">
                <p><strong>${c.authorName}:</strong> ${c.content}</p>
                ${session.name === c.authorName ? `
                  <button class="edit-comment-btn" data-commentid="${c._id}">Edit</button>
                  <button class="delete-comment-btn" data-commentid="${c._id}">Delete</button>
                ` : ''}
              </div>
            `).join('');
          }
        });
    });
  };

  const loadPosts = async (type = 'recent') => {
    let url = '/posts';
    if (type === 'recent') url = '/posts/recent';
    if (type === 'liked') url = '/posts/liked';

    const res = await fetch(url);
    const posts = await res.json();
    renderPosts(posts);
  };

  loadPosts('recent');

  document.getElementById('filterRecent')?.addEventListener('click', () => loadPosts('recent'));
  document.getElementById('filterLiked')?.addEventListener('click', () => loadPosts('liked'));

  // ----------------------------------------------------------------------------------------------------------------------------------------------------
  // ------------------------------------------------------------------CREATE POST LOGIC ----------------------------------------------------------------
  // ----------------------------------------------------------------------------------------------------------------------------------------------------

  document.getElementById('postForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const title = document.getElementById('title').value;
    const content = document.getElementById('content').value;

    await fetch('/create-post', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ title, content })
    });

    window.location.reload();
  });

  // ----------------------------------------------------------------------------------------------------------------------------------------------------
  // ------------------------------------------------------------------COMMENTS LOGIC --------------------------------------------------------------------
  // ----------------------------------------------------------------------------------------------------------------------------------------------------

  // Create Comment
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

  // Edit Comment
  document.addEventListener('click', async (e) => {
    if (e.target.classList.contains('edit-comment-btn')) {
      const commentId = e.target.getAttribute('data-commentid');
      const newContent = prompt('Edit your comment:');
      if (newContent) {
        await fetch(`/update-comment/${commentId}`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ content: newContent })
        });
        window.location.reload();
      }
    }
  });

  // Delete Comment
  document.addEventListener('click', async (e) => {
    if (e.target.classList.contains('delete-comment-btn')) {
      const commentId = e.target.getAttribute('data-commentid');
      const confirmDelete = confirm("Are you sure you want to delete this comment?");
      if (confirmDelete) {
        await fetch(`/delete-comment/${commentId}`, { method: 'DELETE' });
        window.location.reload();
      }
    }
  });

  // ----------------------------------------------------------------------------------------------------------------------------------------------------
  // ------------------------------------------------------------------VOTE LOGIC ------------------------------------------------------------------------
  // ----------------------------------------------------------------------------------------------------------------------------------------------------

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
