const User = require('./models/user');

// Login and Redirect to Admin Dashboard
async function loginUser(email, password) {
  const user = await User.findOne({ email });

  if (!user) {
    throw new Error('User not found');
  }

  const isMatch = await user.comparePassword(password);
  
  if (!isMatch) {
    throw new Error('Invalid password');
  }

  if (user.role === 'admin') {
    // Redirect to Admin Dashboard
    return '/admin/dashboard';
  } else {
    // Redirect to Student Dashboard
    return '/student/dashboard';
  }
}

module.exports = loginUser;
