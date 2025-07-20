const express = require('express');
const User = require('./models/user');
const router = express.Router();

// Middleware to check if user is admin
function isAdmin(req, res, next) {
  if (req.user && req.user.role === 'admin') {
    return next();
  }
  res.redirect('/student/dashboard');
}

router.get('/admin/dashboard', isAdmin, (req, res) => {
  // Render Admin Dashboard
  res.render('adminDashboard');
});

module.exports = router;
