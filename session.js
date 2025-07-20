const mongoose = require('mongoose');

// Session Schema
const sessionSchema = new mongoose.Schema({
    userId: { 
        type: mongoose.Schema.Types.ObjectId, 
        ref: 'User', 
        required: true  // Reference to the User who owns the session
    },
    sessionToken: { 
        type: String, 
        required: true,  // The unique session token for the session
        unique: true 
    },
    dateStart: { 
        type: Date, 
        default: Date.now  // The start date of the session (when the user logs in)
    },
    dateExpired: { 
        type: Date, 
        required: true  // Expiry date/time for the session
    },
});

module.exports = mongoose.model('Session', sessionSchema);
