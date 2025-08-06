const mongoose = require('mongoose');

const commentSchema = new mongoose.Schema({
  postId: mongoose.Schema.Types.ObjectId,
  content: String,
  authorID: String,
  authorName: String,
  createdAt: { type: Date, default: Date.now }
});

module.exports = mongoose.model('Comment', commentSchema);
