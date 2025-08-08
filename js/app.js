// SEFS Application JavaScript with better error handling

class SEFSApp {
  constructor() {
    this.currentUser = null
    this.currentSection = "home"
    this.posts = []
    this.categories = []
    this.currentPage = 0
    this.postsPerPage = 10
    this.currentPostId = null

    this.init()
  }

  async init() {
    console.log("Initializing SEFS App...")

    try {
      await this.checkAuthStatus()
      await this.loadCategories()
      this.setupEventListeners()
      this.showSection("home")
      console.log("SEFS App initialized successfully")
    } catch (error) {
      console.error("Failed to initialize app:", error)
      this.showAlert("Failed to initialize application. Please check console for details.", "danger")
    }
  }

  // Authentication Methods
  async checkAuthStatus() {
    try {
      console.log("Checking auth status...")
      const response = await fetch("api/auth.php?action=check")

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data = await response.json()
      console.log("Auth response:", data)

      if (data.authenticated) {
        this.currentUser = data.user
        this.updateUIForLoggedInUser()
      } else {
        this.updateUIForLoggedOutUser()
      }
    } catch (error) {
      console.error("Auth check failed:", error)
      this.updateUIForLoggedOutUser()
      // Don't show error for auth check failure - it's normal when not logged in
    }
  }

  updateUIForLoggedInUser() {
    console.log("Updating UI for logged in user:", this.currentUser)
    document.getElementById("authButtons").style.display = "none"
    document.getElementById("userMenu").style.display = "block"
    document.getElementById("userName").textContent = this.currentUser.name
    document.getElementById("createPostSection").style.display = "block"

    const addCommentSection = document.getElementById("addCommentSection")
    if (addCommentSection) {
      addCommentSection.style.display = "block"
    }

    if (this.currentUser.role === "admin" || this.currentUser.role === "csg_officer") {
      document.getElementById("adminNavItem").style.display = "block"
    }
  }

  updateUIForLoggedOutUser() {
    console.log("Updating UI for logged out user")
    document.getElementById("authButtons").style.display = "block"
    document.getElementById("userMenu").style.display = "none"
    document.getElementById("createPostSection").style.display = "none"

    const addCommentSection = document.getElementById("addCommentSection")
    if (addCommentSection) {
      addCommentSection.style.display = "none"
    }

    document.getElementById("adminNavItem").style.display = "none"
  }

  // Posts Methods
  async loadPosts(reset = false) {
    console.log("Loading posts, reset:", reset)

    if (reset) {
      this.currentPage = 0
      this.posts = []
    }

    const searchTerm = document.getElementById("searchInput").value
    const categoryId = document.getElementById("categoryFilter").value

    try {
      const params = new URLSearchParams({
        limit: this.postsPerPage,
        offset: this.currentPage * this.postsPerPage,
        ...(searchTerm && { search: searchTerm }),
        ...(categoryId && { category_id: categoryId }),
      })

      const url = `api/posts.php?action=list&${params}`
      console.log("Fetching posts from:", url)

      const response = await fetch(url)

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data = await response.json()
      console.log("Posts response:", data)

      if (data.success) {
        if (reset) {
          this.posts = data.posts
        } else {
          this.posts = [...this.posts, ...data.posts]
        }

        this.renderPosts()

        // Show/hide load more button
        const loadMoreBtn = document.getElementById("loadMoreBtn")
        if (data.posts.length < this.postsPerPage) {
          loadMoreBtn.style.display = "none"
        } else {
          loadMoreBtn.style.display = "block"
        }
      } else {
        throw new Error(data.error || "Failed to load posts")
      }
    } catch (error) {
      console.error("Failed to load posts:", error)
      this.showAlert(`Failed to load posts: ${error.message}`, "danger")

      // Show error state in posts container
      const container = document.getElementById("postsContainer")
      container.innerHTML = `
        <div class="text-center py-5">
          <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
          <h5 class="text-danger">Failed to Load Posts</h5>
          <p class="text-muted">Error: ${error.message}</p>
          <button class="btn btn-primary" onclick="app.loadPosts(true)">Try Again</button>
        </div>
      `
    }
  }

  renderPosts() {
    const container = document.getElementById("postsContainer")

    if (this.posts.length === 0) {
      container.innerHTML = `
        <div class="text-center py-5">
          <i class="fas fa-comments fa-3x text-muted mb-3"></i>
          <h5 class="text-muted">No posts found</h5>
          <p class="text-muted">Be the first to share your feedback!</p>
        </div>
      `
      return
    }

    container.innerHTML = this.posts.map((post) => this.renderPostCard(post)).join("")
  }

  renderPostCard(post) {
    const timeAgo = this.timeAgo(new Date(post.created_at))
    const isOwner = this.currentUser && this.currentUser.id == post.user_id

    return `
      <div class="post-card fade-in">
        <div class="post-header">
          <h3 class="post-title" onclick="app.showPostDetail(${post.post_id})">${post.title}</h3>
          <div class="post-meta">
            <span><i class="fas fa-user me-1"></i>${post.author_name}</span>
            <span><i class="fas fa-clock me-1"></i>${timeAgo}</span>
            ${post.category_name ? `<span class="post-category" style="background-color: ${post.category_color}">${post.category_name}</span>` : ""}
            <span class="status-badge status-${post.status}">${post.status.charAt(0).toUpperCase() + post.status.slice(1)}</span>
          </div>
        </div>
        <div class="post-content">
          <p>${this.truncateText(post.content, 200)}</p>
        </div>
        <div class="post-actions">
          <div class="vote-buttons">
            <button class="vote-btn upvote" onclick="app.votePost(${post.post_id}, 'upvote')" ${!this.currentUser ? "disabled" : ""}>
              <i class="fas fa-thumbs-up"></i>
              <span>${post.upvotes}</span>
            </button>
            <button class="vote-btn downvote" onclick="app.votePost(${post.post_id}, 'downvote')" ${!this.currentUser ? "disabled" : ""}>
              <i class="fas fa-thumbs-down"></i>
              <span>${post.downvotes}</span>
            </button>
          </div>
          <button class="comment-btn" onclick="app.showPostDetail(${post.post_id})">
            <i class="fas fa-comment"></i>
            <span>${post.comment_count} Comments</span>
          </button>
          ${
            isOwner
              ? `
              <div class="ms-auto">
                <button class="btn btn-sm btn-outline-primary me-2" onclick="app.editPost(${post.post_id})">
                  <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="app.deletePost(${post.post_id})">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            `
              : ""
          }
        </div>
      </div>
    `
  }

  // Categories Methods
  async loadCategories() {
    try {
      console.log("Loading categories...")
      const response = await fetch("api/posts.php?action=categories")

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data = await response.json()
      console.log("Categories response:", data)

      if (data.success) {
        this.categories = data.categories
        this.populateCategorySelects()
      } else {
        throw new Error(data.error || "Failed to load categories")
      }
    } catch (error) {
      console.error("Failed to load categories:", error)
      this.showAlert(`Failed to load categories: ${error.message}`, "warning")
    }
  }

  populateCategorySelects() {
    const selects = ["categoryFilter", "postCategory"]

    selects.forEach((selectId) => {
      const select = document.getElementById(selectId)
      if (select) {
        // Keep the first option (All Categories or Select a category)
        const firstOption = select.children[0]
        select.innerHTML = ""
        select.appendChild(firstOption)

        this.categories.forEach((category) => {
          const option = document.createElement("option")
          option.value = category.category_id
          option.textContent = category.name
          select.appendChild(option)
        })
      }
    })
  }

  // UI Methods
  showSection(sectionName) {
    console.log("Showing section:", sectionName)

    // Hide all sections
    document.querySelectorAll(".content-section").forEach((section) => {
      section.style.display = "none"
    })

    // Update navigation
    document.querySelectorAll(".nav-link").forEach((link) => {
      link.classList.remove("active")
    })

    // Show selected section
    const sectionElement = document.getElementById(sectionName + "Section")
    if (sectionElement) {
      sectionElement.style.display = "block"
    }

    const navLink = document.querySelector(`[onclick="showSection('${sectionName}')"]`)
    if (navLink) {
      navLink.classList.add("active")
    }

    this.currentSection = sectionName

    // Load section-specific data
    switch (sectionName) {
      case "home":
        this.loadPosts(true)
        break
      case "suggestions":
        this.loadSuggestions()
        break
      case "surveys":
        this.loadSurveys()
        break
      case "admin":
        this.loadAnalytics()
        break
    }
  }

  async loadSuggestions() {
    // For now, just load all posts and filter suggestions
    await this.loadPosts(true)
  }

  async loadSurveys() {
    console.log("Loading surveys...")
    const container = document.getElementById("surveysContainer")
    container.innerHTML = `
      <div class="text-center py-5">
        <i class="fas fa-poll fa-3x text-muted mb-3"></i>
        <h5 class="text-muted">Surveys feature coming soon</h5>
        <p class="text-muted">Survey functionality will be available in the next update!</p>
      </div>
    `
  }

  async loadAnalytics() {
    console.log("Loading analytics...")
    const statsContainer = document.getElementById("statsCards")
    if (statsContainer) {
      statsContainer.innerHTML = `
        <div class="col-12">
          <div class="text-center py-5">
            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Analytics dashboard coming soon</h5>
            <p class="text-muted">Analytics features will be available in the next update!</p>
          </div>
        </div>
      `
    }
  }

  showAlert(message, type = "info") {
    console.log("Showing alert:", message, type)

    const alertContainer = document.createElement("div")
    alertContainer.className = `alert alert-${type} alert-dismissible fade show position-fixed`
    alertContainer.style.cssText = "top: 20px; right: 20px; z-index: 9999; min-width: 300px;"
    alertContainer.innerHTML = `
      ${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `

    document.body.appendChild(alertContainer)

    // Auto-remove after 5 seconds
    setTimeout(() => {
      if (alertContainer.parentNode) {
        alertContainer.remove()
      }
    }, 5000)
  }

  // Event Listeners
  setupEventListeners() {
    console.log("Setting up event listeners...")

    // Search input
    const searchInput = document.getElementById("searchInput")
    if (searchInput) {
      searchInput.addEventListener("keypress", (e) => {
        if (e.key === "Enter") {
          this.searchPosts()
        }
      })
    }
  }

  // Utility Methods
  timeAgo(date) {
    const now = new Date()
    const diffInSeconds = Math.floor((now - date) / 1000)

    if (diffInSeconds < 60) return "Just now"
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`
    if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)}d ago`

    return date.toLocaleDateString()
  }

  truncateText(text, maxLength) {
    if (text.length <= maxLength) return text
    return text.substr(0, maxLength) + "..."
  }

  searchPosts() {
    this.loadPosts(true)
  }

  filterByCategory() {
    this.loadPosts(true)
  }

  loadMorePosts() {
    this.currentPage++
    this.loadPosts(false)
  }

  logout() {
    // Placeholder for logout functionality
    console.log("Logging out...")
    this.currentUser = null
    this.updateUIForLoggedOutUser()
  }
}

// Global functions for onclick handlers
function showSection(section) {
  app.showSection(section)
}

function showLoginModal() {
  const modal = new window.bootstrap.Modal(document.getElementById("loginModal"))
  modal.show()
}

function showRegisterModal() {
  const modal = new window.bootstrap.Modal(document.getElementById("registerModal"))
  modal.show()
}

function showCreatePostModal() {
  if (!app.currentUser) {
    app.showAlert("Please login to create a post", "warning")
    return
  }
  const modal = new window.bootstrap.Modal(document.getElementById("createPostModal"))
  modal.show()
}

function logout() {
  app.logout()
}

document.getElementById("loginForm").addEventListener("submit", async function (e) {
    e.preventDefault();
    const email = document.getElementById("loginEmail").value;
    const password = document.getElementById("loginPassword").value;
    const role = document.getElementById("loginRole").value;

    const response = await fetch("auth.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, password, role })
    });

    const result = await response.json();

    if (result.success) {
        // Show admin dashboard link if role is admin
        if (result.role === "admin") {
            document.getElementById("adminNavItem").style.display = "block";
        } else {
            document.getElementById("adminNavItem").style.display = "none";
        }

        // Show user menu
        document.getElementById("userName").textContent = result.name;
        document.getElementById("authButtons").style.display = "none";
        document.getElementById("userMenu").style.display = "block";
        bootstrap.Modal.getInstance(document.getElementById('loginModal')).hide();
    } else {
        alert(result.message || "Login failed.");
    }
});


// Initialize the application
console.log("Starting SEFS Application...")
const app = new SEFSApp()
