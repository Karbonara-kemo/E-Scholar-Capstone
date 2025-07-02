  function cleanBack() {
    window.history.back();
  }

 let slideIndex = 0;
 const slides = document.getElementsByClassName("slide");
 const dots = document.getElementsByClassName("dot");
 
 function showSlides() {

     for (let i = 0; i < slides.length; i++) {
         slides[i].classList.remove("active");
         dots[i].classList.remove("active");
     }
     
     slideIndex++;
     if (slideIndex >= slides.length) {
         slideIndex = 0;
     }

     slides[slideIndex].classList.add("active");
     dots[slideIndex].classList.add("active");
     
     setTimeout(showSlides, 5000);
 }
 
 function currentSlide(n) {
     slideIndex = n - 1;
     showSlides();
 }

 window.onload = function() {
     showSlides();
 };

 function toggleMenu() {
    var menu = document.getElementById("dropdownMenu");
    menu.style.display = (menu.style.display === "block") ? "none" : "block";
}

window.onclick = function(event) {
    if (!event.target.matches('.user-icon')) {
        var dropdowns = document.getElementsByClassName("dropdown-menu");
        for (var i = 0; i < dropdowns.length; i++) {
            dropdowns[i].style.display = "none";
        }
    }
}


let users = [];

function switchTab(tab) {
    document.querySelectorAll('.form').forEach(form => form.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));

    document.getElementById(tab + '-form').classList.add('active');
    document.getElementById(tab + '-tab').classList.add('active');
    
    document.querySelectorAll('.success-message, .error').forEach(msg => msg.style.display = 'none');
}

function validateSignupForm() {
    let isValid = true;

    document.querySelectorAll('.error').forEach(error => error.style.display = 'none');

    const email = document.getElementById('signup-email').value;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        document.getElementById('email-error').style.display = 'block';
        isValid = false;
    }
    
    const password = document.getElementById('signup-password').value;
    if (password.length < 8 || !/\d/.test(password)) {
        document.getElementById('password-error').style.display = 'block';
        isValid = false;
    }

    const confirmPassword = document.getElementById('confirm-password').value;
    if (password !== confirmPassword) {
        document.getElementById('confirm-password-error').style.display = 'block';
        isValid = false;
    }
    
    if (isValid) {
        const user = {
            firstName: document.getElementById('name').value,
            middleName: document.getElementById('middle-name').value,
            lastName: document.getElementById('last-name').value,
            age: document.getElementById('age').value,
            gender: document.getElementById('gender').value,
            birthdate: document.getElementById('birthdate').value,
            email: email,
            password: password
        };
        
        users.push(user);
        
        document.getElementById('signup-success').style.display = 'block';

        document.getElementById('signup-form').reset();
        
        console.log("User registered:", user);
    }
    
    return false;
}

function validateSigninForm() {
    const email = document.getElementById('signin-email').value;
    const password = document.getElementById('signin-password').value;

    document.getElementById('signin-error').style.display = 'none';
    document.getElementById('signin-success').style.display = 'none';

    const user = users.find(u => u.email === email && u.password === password);
    
    if (user) {
        document.getElementById('signin-success').style.display = 'block';
        document.getElementById('signin-form').reset();
        console.log("User signed in:", user);
    } else {
        document.getElementById('signin-error').style.display = 'block';
    }
    
    return false;
}