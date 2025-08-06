const express = require('express');
const mongoose = require('mongoose');
const bodyParser = require('body-parser');
const dotenv = require('dotenv');
const path = require('path');
const User = require('./userModel');
const Comment = require('./commentModel');


dotenv.config();
const app = express();
const PORT = 3000;

// Simulated session storage (in-memory)
let currentSession = null;

// Connect to MongoDB
mongoose.connect('mongodb://localhost:27017/ITISDEV_MP', {
  useNewUrlParser: true,
  useUnifiedTopology: true
});

// Middleware
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));
app.use(express.static(path.join(__dirname, '../public')));

// Signup Route
app.post('/signup', async (req, res) => {
  const { name, userID, email, password, role } = req.body;
  const existing = await User.findOne({ email });
  if (existing) return res.status(400).send('Email already used');

  await User.create({ name, userID, email, password, role });
  res.redirect('/');
});

// Login Route
app.post('/login', async (req, res) => {
  const { email, password } = req.body;
  const user = await User.findOne({ email, password });
  if (!user) return res.status(401).send('Invalid credentials');

  // Set session
  currentSession = { name: user.name, role: user.role };
  res.redirect('/index.html');
});

// Logout Route
app.get('/logout', (req, res) => {
  currentSession = null;
  res.redirect('/');
});


// GET all posts sorted by upvotes + comment count
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

const UserPost = require('./postModel');

// Create Post Route (only if logged in)
app.post('/create-post', async (req, res) => {
  if (!currentSession) return res.status(403).send('Not logged in');

  const { title, content } = req.body;
  if (!title || !content) return res.status(400).send('Missing fields');

  await UserPost.create({
    title,
    content,
    authorID: currentSession.id || 'anonymous', // Optional safety
    authorName: currentSession.name,
    upvotes: [],
    downvotes: [],
    createdAt: new Date()
  });

  res.redirect('/');
});

app.get('/session', (req, res) => {
  if (currentSession) {
    res.json({ loggedIn: true, name: currentSession.name });
  } else {
    res.json({ loggedIn: false });
  }
});

app.post('/add-comment', async (req, res) => {
  if (!currentSession) return res.status(403).send('Not logged in');

  const { postId, content } = req.body;
  if (!postId || !content) return res.status(400).send('Missing data');

  await Comment.create({
    postId,
    content,
    authorID: currentSession.id || 'anon',
    authorName: currentSession.name,
    createdAt: new Date()
  });

  res.redirect('/');
});

app.get('/comments/:postId', async (req, res) => {
  try {
    const comments = await Comment.find({ postId: req.params.postId }).sort({ createdAt: -1 });
    res.json(comments);
  } catch (err) {
    res.status(500).send('Failed to fetch comments');
  }
});

app.post('/vote', async (req, res) => {
    if (!currentSession) return res.status(403).send('Not logged in');

    const { postId, voteType } = req.body;
    const userId = currentSession.id || currentSession.name;

    const post = await UserPost.findById(postId);
    if (!post) return res.status(404).send('Post not found');
    if (post.authorID === userId) return res.status(403).send('You cannot vote on your own post');

    // Remove user from both vote arrays to toggle
    post.upvotes = post.upvotes.filter(id => id !== userId);
    post.downvotes = post.downvotes.filter(id => id !== userId);

    // Add user to the correct vote array
    if (voteType === 'upvote') {
      post.upvotes.push(userId);
    } else if (voteType === 'downvote') {
      post.downvotes.push(userId);
    }

    await post.save();
    res.status(200).send('Vote recorded');
});

// V2 - Route to show the Edit Post form
app.get('/edit-post/:id', async (req, res) => {
  const post = await UserPost.findById(req.params.id);
  if (!post) return res.status(404).send('Post not found');

  // Redirect to the static HTML page, passing the post data in the query parameters
  res.redirect(`/edit-post.html?id=${post._id}&title=${post.title}&content=${post.content}`);
});



// V2 - Route to handle updating the post
app.post('/update-post/:id', async (req, res) => {
  const { title, content } = req.body;
  const post = await UserPost.findByIdAndUpdate(req.params.id, { title, content }, { new: true });
  
  if (!post) return res.status(404).send('Post not found');
  
  // Redirect to the updated post page
  res.redirect(`/post/${post._id}`);
});



// V2 - Route to delete a post
app.delete('/delete-post/:id', async (req, res) => {
  const post = await UserPost.findByIdAndDelete(req.params.id);
  if (!post) return res.status(404).send('Post not found');
  res.status(200).send('Post deleted');
});

// V2 - Route to display a specific post (view post) PENDING TO, TO BE MODIFIED
app.get('/post/:id', async (req, res) => {
  const post = await UserPost.findById(req.params.id);
  if (!post) return res.status(404).send('Post not found');

  // Render the post on an HTML page
  res.send(`
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>${post.title}</title>
    </head>
    <body>
      <h1>${post.title}</h1>
      <p>${post.content}</p>
      <p><strong>By: ${post.authorName}</strong></p>
      <hr>
      <a href="/edit-post/${post._id}">Edit Post</a>
    </body>
    </html>
  `);
});






// Start Server
app.listen(PORT, () => console.log(`Server running at http://localhost:${PORT}`));

