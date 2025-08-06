const mongoose = require('mongoose');

const postSchema = new mongoose.Schema({
  title: String,
  content: String,
  authorID: String,
  authorName: String,
  upvotes: [String],
  downvotes: [String],
  createdAt: { type: Date, default: Date.now }
});

module.exports = mongoose.model('UserPost', postSchema);
