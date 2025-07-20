const mongoose = require('mongoose');

const feedbackPostSchema = new mongoose.Schema({
  title: {
    type: String,
    required: true
  },
  description: {
    type: String,
    required: true
  },
  author: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User',
    required: true
  },
  tags: [String],
  upvotes: {
    type: Number,
    default: 0
  },
  downvotes: {
    type: Number,
    default: 0
  },
  comments: [
    {
      commentText: String,
      commenter: {
        type: mongoose.Schema.Types.ObjectId,
        ref: 'User'
      },
      timestamp: {
        type: Date,
        default: Date.now
      }
    }
  ],
  visibility: {
    type: String,
    enum: ['public', 'private'],
    default: 'public'
  }
}, { timestamps: true });

const FeedbackPost = mongoose.model('FeedbackPost', feedbackPostSchema);

module.exports = FeedbackPost;
