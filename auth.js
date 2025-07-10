const formTitle = document.getElementById("form-title");
const emailField = document.querySelector(".email-field");
const idField = document.querySelector(".id-field");
const confirmPasswordField = document.querySelector(".confirm-password-field");
const forgotLink = document.getElementById("forgot-link");
const authButton = document.getElementById("auth-button");
const toggleMsg = document.getElementById("toggle-msg");
const toggleLink = document.getElementById("toggle-link");

let isSignUp = false;

toggleLink.addEventListener("click", (e) => {
  e.preventDefault();
  isSignUp = !isSignUp;

  // Toggle title
  formTitle.textContent = isSignUp ? "Sign Up" : "Sign In";

  // Show/hide email and confirm password fields
  emailField.classList.toggle("hidden", !isSignUp);
  idField.classList.toggle("hidden", !isSignUp);
  confirmPasswordField.classList.toggle("hidden", !isSignUp);

  // Toggle forgot link visibility
  forgotLink.style.display = isSignUp ? "none" : "inline-block";

  // Toggle button text
  authButton.textContent = isSignUp ? "SIGN UP" : "SIGN IN";

  // Toggle bottom text and link
  toggleMsg.textContent = isSignUp
    ? "Already have an account?"
    : "Don't have an account?";
  toggleLink.textContent = isSignUp ? "SIGN IN NOW" : "SIGN UP NOW";
});
