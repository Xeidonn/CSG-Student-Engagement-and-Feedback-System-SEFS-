const mongoose = require('mongoose');

const surveyResponseSchema = new mongoose.Schema({
  surveyId: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'Survey',
    required: true
  },
  studentId: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User',
    required: true
  },
  answers: [
    {
      questionId: mongoose.Schema.Types.ObjectId,
      answer: String // Could be a selected option or a rating
    }
  ]
}, { timestamps: true });

const SurveyResponse = mongoose.model('SurveyResponse', surveyResponseSchema);

module.exports = SurveyResponse;
