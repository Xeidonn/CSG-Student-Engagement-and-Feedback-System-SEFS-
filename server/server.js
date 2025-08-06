const express = require('express');
const mongoose = require('mongoose');
const bodyParser = require('body-parser');
const dotenv = require('dotenv');
const path = require('path');
const User = require('./userModel');
const Comment = require('./commentModel');
const UserPost = require('./postModel');

dotenv.config();
const app = express();
const PORT = 3000;

// Simulated session (in-memory)
let currentSession = null;

// ----------------------------------------------------------------------------------------------------------------------------------------------------
// ------------------------------------------------------------------DATABASE CONNECTION --------------------------------------------------------------
// ----------------------------------------------------------------------------------------------------------------------------------------------------

mongoose.connect('mongodb://localhost:27017/ITISDEV_MP', {
  useNewUrlParser: true,
  useUnifiedTopology: true
});

// ----------------------------------------------------------------------------------------------------------------------------------------------------
// ------------------------------------------------------------------MIDDLEWARE ------------------------------------------------------------------------
// ----------------------------------------------------------------------------------------------------------------------------------------------------

app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));
app.use(express.static(path.join(__dirname, '../public')));

// ----------------------------------------------------------------------------------------------------------------------------------------------------
// ------------------------------------------------------------------AUTH & SESSION ROUTES -------------------------------------------------------------
// ----------------------------------------------------------------------------------------------------------------------------------------------------

// Signup
app.post('/signup', async (req, res) => {
  const { name, userID, email, password, role } = req.body;
  const existing = await User.findOne({ email });
  if (existing) return res.status(400).send('Email already used');

  await User.create({ name, userID, email, password, role });
  res.redirect('/');
});

// Login
app.post('/login', async (req, res) => {
  const { email, password } = req.body;
  const user = await User.findOne({ email, password });
  if (!user) return res.status(401).send('Invalid credentials');

  currentSession = { name: user.name, role: user.role, id: user._id.toString() };
  res.redirect('/index.html');
});

// Logout
app.get('/logout', (req, res) => {
  currentSession = null;
  res.redirect('/');
});

// ----------------------------------------------------------------------------------------------------------------------------------------------------
// ------------------------------------------------------------------POST LOGIC ------------------------------------------------------------------------
// ----------------------------------------------------------------------------------------------------------------------------------------------------

// Create a post
app.post('/create-post', async (req, res) => {
  if (!currentSession) return res.status(403).send('Not logged in');
  const { title, content } = req.body;
  if (!title || !content) return res.status(400).send('Missing fields');

  await UserPost.create({
    title,
    content,
    authorID: currentSession.id,
    authorName: currentSession.name,
    upvotes: [],
    downvotes: [],
    createdAt: new Date()
  });

  res.redirect('/');
});

// View all posts (default: most liked)
app.get('/posts', async (req, res) => {
  try {
    const posts = await UserPost.aggregate([
      {
        $lookup: {
          from: 'comments',
          localField: '_id',
          foreignField: 'postId',
          as: 'comments'
        }
      },
      {
        $addFields: {
          voteScore: { $subtract: [{ $size: "$upvotes" }, { $size: "$downvotes" }] },
          commentCount: { $size: "$comments" }
        }
      },
      {
        $sort: { voteScore: -1, commentCount: -1, createdAt: -1 }
      }
    ]);
    res.json(posts);
  } catch (err) {
    res.status(500).send('Error fetching posts');
  }
});

// Edit post
app.get('/edit-post/:id', async (req, res) => {
  const post = await UserPost.findById(req.params.id);
  if (!post) return res.status(404).send('Post not found');

  res.redirect(`/edit-post.html?id=${post._id}&title=${post.title}&content=${post.content}`);
});

app.post('/update-post/:id', async (req, res) => {
  const { title, content } = req.body;
  const post = await UserPost.findByIdAndUpdate(req.params.id, { title, content }, { new: true });
  if (!post) return res.status(404).send('Post not found');

  res.redirect(`/post/${post._id}`);
});

// Delete post
app.delete('/delete-post/:id', async (req, res) => {
  const post = await UserPost.findByIdAndDelete(req.params.id);
  if (!post) return res.status(404).send('Post not found');
  res.status(200).send('Post deleted');
});

// Vote on post
app.post('/vote', async (req, res) => {
  if (!currentSession) return res.status(403).send('Not logged in');

  const { postId, voteType } = req.body;
  const userId = currentSession.id;

  const post = await UserPost.findById(postId);
  if (!post) return res.status(404).send('Post not found');
  if (post.authorID === userId) return res.status(403).send('You cannot vote on your own post');

  post.upvotes = post.upvotes.filter(id => id !== userId);
  post.downvotes = post.downvotes.filter(id => id !== userId);

  if (voteType === 'upvote') post.upvotes.push(userId);
  if (voteType === 'downvote') post.downvotes.push(userId);

  await post.save();
  res.status(200).send('Vote recorded');
});

// ----------------------------------------------------------------------------------------------------------------------------------------------------
// ------------------------------------------------------------------COMMENT LOGIC ---------------------------------------------------------------------
// ----------------------------------------------------------------------------------------------------------------------------------------------------

// Add comment
app.post('/add-comment', async (req, res) => {
  if (!currentSession) return res.status(403).send('Not logged in');

  const { postId, content } = req.body;
  if (!postId || !content) return res.status(400).send('Missing data');

  await Comment.create({
    postId,
    content,
    authorID: currentSession.id,
    authorName: currentSession.name,
    createdAt: new Date()
  });

  res.redirect('/');
});

// Get comments for post
app.get('/comments/:postId', async (req, res) => {
  try {
    const comments = await Comment.find({ postId: req.params.postId }).sort({ createdAt: -1 });
    res.json(comments);
  } catch (err) {
    res.status(500).send('Failed to fetch comments');
  }
});

// Update comment
app.post('/update-comment/:id', async (req, res) => {
  const { content } = req.body;
  if (!content) return res.status(400).send('Content is required');

  const comment = await Comment.findByIdAndUpdate(req.params.id, { content }, { new: true });
  if (!comment) return res.status(404).send('Comment not found');

  res.status(200).send('Comment updated');
});

// Delete comment
app.delete('/delete-comment/:id', async (req, res) => {
  const comment = await Comment.findByIdAndDelete(req.params.id);
  if (!comment) return res.status(404).send('Comment not found');
  res.status(200).send('Comment deleted');
});

// ----------------------------------------------------------------------------------------------------------------------------------------------------
// ------------------------------------------------------------------FILTERING ROUTES -----------------------------------------------------------------
// ----------------------------------------------------------------------------------------------------------------------------------------------------

// Most Recent
app.get('/posts/recent', async (req, res) => {
  const posts = await UserPost.aggregate([
    {
      $lookup: {
        from: 'comments',
        localField: '_id',
        foreignField: 'postId',
        as: 'comments'
      }
    },
    {
      $addFields: {
        voteScore: { $subtract: [{ $size: "$upvotes" }, { $size: "$downvotes" }] },
        commentCount: { $size: "$comments" }
      }
    },
    { $sort: { createdAt: -1 } }
  ]);
  res.json(posts);
});

// Most Liked
app.get('/posts/liked', async (req, res) => {
  const posts = await UserPost.aggregate([
    {
      $lookup: {
        from: 'comments',
        localField: '_id',
        foreignField: 'postId',
        as: 'comments'
      }
    },
    {
      $addFields: {
        voteScore: { $subtract: [{ $size: "$upvotes" }, { $size: "$downvotes" }] },
        commentCount: { $size: "$comments" }
      }
    },
    { $sort: { voteScore: -1, createdAt: -1 } }
  ]);
  res.json(posts);
});

// ----------------------------------------------------------------------------------------------------------------------------------------------------
// ------------------------------------------------------------------VIEW POST ROUTE ------------------------------------------------------------------
// ----------------------------------------------------------------------------------------------------------------------------------------------------

app.get('/post/:id', async (req, res) => {
  const post = await UserPost.findById(req.params.id);
  if (!post) return res.status(404).send('Post not found');

  res.send(`
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <title>${post.title}</title>
    </head>
    <body>
      <h1>${post.title}</h1>
      <p>${post.content}</p>
      <p><strong>By: ${post.authorName}</strong></p>
      <a href="/edit-post/${post._id}">Edit Post</a>
    </body>
    </html>
  `);
});

// ----------------------------------------------------------------------------------------------------------------------------------------------------
// ------------------------------------------------------------------SESSION CHECK ROUTE --------------------------------------------------------------
// ----------------------------------------------------------------------------------------------------------------------------------------------------

app.get('/session', (req, res) => {
  if (currentSession) {
    res.json({ loggedIn: true, name: currentSession.name });
  } else {
    res.json({ loggedIn: false });
  }
});

// ----------------------------------------------------------------------------------------------------------------------------------------------------
// ------------------------------------------------------------------START SERVER ---------------------------------------------------------------------
// ----------------------------------------------------------------------------------------------------------------------------------------------------

app.listen(PORT, () => console.log(`Server running at http://localhost:${PORT}`));
