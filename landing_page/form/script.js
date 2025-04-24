  function cleanBack() {
    window.history.back();
  }

 // Automatic slideshow functionality
 let slideIndex = 0;
 const slides = document.getElementsByClassName("slide");
 const dots = document.getElementsByClassName("dot");
 
 function showSlides() {
     // Hide all slides
     for (let i = 0; i < slides.length; i++) {
         slides[i].classList.remove("active");
         dots[i].classList.remove("active");
     }
     
     // Move to next slide
     slideIndex++;
     if (slideIndex >= slides.length) {
         slideIndex = 0;
     }
     
     // Show current slide
     slides[slideIndex].classList.add("active");
     dots[slideIndex].classList.add("active");
     
     // Call again after 5 seconds
     setTimeout(showSlides, 5000);
 }
 
 // Manual slide control
 function currentSlide(n) {
     slideIndex = n - 1;
     showSlides();
 }
 
 // Start slideshow when page loads
 window.onload = function() {
     showSlides();
 };


 // Your original navbar script
 function toggleMenu() {
    var menu = document.getElementById("dropdownMenu");
    menu.style.display = (menu.style.display === "block") ? "none" : "block";
}

// Optional: Close the menu when clicking outside
window.onclick = function(event) {
    if (!event.target.matches('.user-icon')) {
        var dropdowns = document.getElementsByClassName("dropdown-menu");
        for (var i = 0; i < dropdowns.length; i++) {
            dropdowns[i].style.display = "none";
        }
    }
}


// Stored user data (in a real application, this would be a database)
let users = [];

function switchTab(tab) {
    // Hide all forms and deactivate all tabs
    document.querySelectorAll('.form').forEach(form => form.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
    
    // Show the selected form and activate the selected tab
    document.getElementById(tab + '-form').classList.add('active');
    document.getElementById(tab + '-tab').classList.add('active');
    
    // Hide success and error messages when switching tabs
    document.querySelectorAll('.success-message, .error').forEach(msg => msg.style.display = 'none');
}

function validateSignupForm() {
    let isValid = true;
    
    // Reset error displays
    document.querySelectorAll('.error').forEach(error => error.style.display = 'none');
    
    // Validate email format
    const email = document.getElementById('signup-email').value;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        document.getElementById('email-error').style.display = 'block';
        isValid = false;
    }
    
    // Validate password
    const password = document.getElementById('signup-password').value;
    if (password.length < 8 || !/\d/.test(password)) {
        document.getElementById('password-error').style.display = 'block';
        isValid = false;
    }
    
    // Confirm password matches
    const confirmPassword = document.getElementById('confirm-password').value;
    if (password !== confirmPassword) {
        document.getElementById('confirm-password-error').style.display = 'block';
        isValid = false;
    }
    
    if (isValid) {
        // Create user object
        const user = {
            firstName: document.getElementById('name').value,
            middleName: document.getElementById('middle-name').value,
            lastName: document.getElementById('last-name').value,
            age: document.getElementById('age').value,
            gender: document.getElementById('gender').value,
            birthdate: document.getElementById('birthdate').value,
            email: email,
            password: password  // In a real app, this would be hashed
        };
        
        // Add user to our "database"
        users.push(user);
        
        // Show success message
        document.getElementById('signup-success').style.display = 'block';
        
        // Reset form
        document.getElementById('signup-form').reset();
        
        // Prevent actual form submission
        console.log("User registered:", user);
    }
    
    return false; // Prevent form submission
}

function validateSigninForm() {
    const email = document.getElementById('signin-email').value;
    const password = document.getElementById('signin-password').value;
    
    // Reset error/success displays
    document.getElementById('signin-error').style.display = 'none';
    document.getElementById('signin-success').style.display = 'none';
    
    // Check if user exists and password matches
    const user = users.find(u => u.email === email && u.password === password);
    
    if (user) {
        // Show success message
        document.getElementById('signin-success').style.display = 'block';
        document.getElementById('signin-form').reset();
        console.log("User signed in:", user);
    } else {
        // Show error message
        document.getElementById('signin-error').style.display = 'block';
    }
    
    return false; // Prevent form submission
}