const mongoose = require('mongoose');

const surveySchema = new mongoose.Schema({
  surveyTitle: {
    type: String,
    required: true
  },
  questions: [
    {
      questionText: {
        type: String,
        required: true
      },
      type: {
        type: String,
        enum: ['multiple choice', 'rating', 'open-ended'],
        required: true
      },
      options: [String]
    }
  ],
  createdBy: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User', // Reference to CSG officer
    required: true
  },
  responseCount: {
    type: Number,
    default: 0
  }
}, { timestamps: true });

const Survey = mongoose.model('Survey', surveySchema);

module.exports = Survey;
