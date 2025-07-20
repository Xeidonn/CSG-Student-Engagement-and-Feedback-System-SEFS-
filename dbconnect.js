const { MongoClient } = require("mongodb");

const uri = "mongodb://localhost:27017";  // Connection string
const dbName = "SEFSdb";          // Database name
const client = new MongoClient(uri);      // MongoClient instance

async function connectDB() {
  try {
    await client.connect();
    console.log("Connected to MongoDB");
    return client.db(dbName);  // Return the database instance
  } catch (err) {
    console.error("Database connection error:", err);
  }
}

module.exports = connectDB;
