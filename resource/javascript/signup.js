    document.addEventListener('DOMContentLoaded', function () {
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#signup-password');

        togglePassword.addEventListener('click', function (e) {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });

        const toggleConfirmPassword = document.querySelector('#toggleConfirmPassword');
        const confirmPassword = document.querySelector('#confirm-password');

        toggleConfirmPassword.addEventListener('click', function (e) {
            const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPassword.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });

        const signupForm = document.getElementById('signup-form');
        if (signupForm) {
            signupForm.addEventListener('submit', function(event) {
                const barangaySelect = document.getElementById('barangay');
                const fullAddressInput = document.getElementById('signup-address');
                const selectedBarangay = barangaySelect.value;
                
                if (selectedBarangay) {
                    fullAddressInput.value = selectedBarangay + ', San Julian, Eastern Samar';
                }
            });
        }
        
        const validIdInput = document.getElementById('valid-id');
        const clearValidIdButton = document.getElementById('clear-valid-id');

        validIdInput.addEventListener('change', function() {
            if (validIdInput.files.length > 0) {
                clearValidIdButton.style.display = 'block';
            } else {
                clearValidIdButton.style.display = 'none';
            }
        });

        clearValidIdButton.addEventListener('click', function() {
            validIdInput.value = ''; 
            clearValidIdButton.style.display = 'none';
        });
    });

function validateSignupForm() {
    const barangaySelect = document.getElementById('barangay');
    const fullAddressInput = document.getElementById('signup-address');
    const selectedBarangay = barangaySelect.value;
    
    if (selectedBarangay) {
        fullAddressInput.value = selectedBarangay + ', San Julian, Eastern Samar';
    }

    const requiredFields = [
        { id: "name", errorId: "fname-error", errorMessage: "First Name is required." },
        { id: "last-name", errorId: "lname-error", errorMessage: "Last Name is required." },
        { id: "age", errorId: "age-error", errorMessage: "Age is required." },
        { id: "gender", errorId: "gender-error", errorMessage: "Gender is required." },
        { id: "birthdate", errorId: "birthdate-error", errorMessage: "Birthdate is required." },
        { id: "barangay", errorId: "address-error", errorMessage: "Please select a Barangay from the list." },
        { id: "contact-number", errorId: "contact-error", errorMessage: "Contact Number is required." },
        { id: "signup-email", errorId: "email-error", errorMessage: "Email is required." },
        { id: "signup-password", errorId: "password-error", errorMessage: "Password is required." },
        { id: "confirm-password", errorId: "confirm-password-error", errorMessage: "Confirm Password is required." }
    ];

    let isValid = true;

    requiredFields.forEach(field => {
        const errorElement = document.getElementById(field.errorId);
        if (errorElement) {
            errorElement.style.display = 'none';
        }
    });
    document.getElementById("valid-id-error").style.display = 'none';

    requiredFields.forEach(field => {
        const input = document.getElementById(field.id);
        if (!input.value.trim()) {
            const errorElement = document.getElementById(field.errorId);
            errorElement.style.display = 'block';
            errorElement.textContent = field.errorMessage;
            isValid = false;
        }
    });

    const birthdateField = document.getElementById("birthdate");
    const birthdateValue = birthdateField.value;
    const birthdateError = document.getElementById("birthdate-error");

    if (birthdateValue) { 
        const birthYear = new Date(birthdateValue).getFullYear();
        if (birthYear > 2009) {
            birthdateError.style.display = 'block';
            birthdateError.textContent = "Birth year cannot be after 2009.";
            isValid = false;
        }
    }

    const emailField = document.getElementById("signup-email");
    const emailValue = emailField.value.trim();
    const emailError = document.getElementById("email-error");

    if (emailValue && !emailValue.endsWith('@gmail.com')) {
        emailError.style.display = 'block';
        emailError.textContent = "Invalid email format. Please use a valid @gmail.com address.";
        isValid = false;
    }

    const validIdInput = document.getElementById("valid-id");
    const validIdError = document.getElementById("valid-id-error");
    validIdError.style.display = 'none';
    if (!validIdInput.files || validIdInput.files.length < 2) {
        validIdError.style.display = 'block';
        validIdError.textContent = "Please upload both front and back images of your valid ID.";
        isValid = false;
    }

    const passwordField = document.getElementById("signup-password");
    const confirmPasswordField = document.getElementById("confirm-password");
    const password = passwordField.value;
    const confirmPassword = confirmPasswordField.value;

    if (password && confirmPassword) {
        const passwordRegex = /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&#.])[A-Za-z\d@$!%*?&#.]{8,}$/;
        if (!passwordRegex.test(password)) {
            const passwordError = document.getElementById("password-error");
            passwordError.style.display = 'block';
            passwordError.textContent = "Password must be at least 8 characters long, contain at least one uppercase letter, one lowercase letter, one special character, and one number.";
            isValid = false;
        }
        if (password !== confirmPassword) {
            const confirmPasswordError = document.getElementById("confirm-password-error");
            confirmPasswordError.style.display = 'block';
            confirmPasswordError.textContent = "Passwords do not match.";
            isValid = false;
        }
    }
    return isValid;
}

    function checkSuccessMessage() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) {
            const toast = document.getElementById('toast-message');
            const toastText = document.getElementById('toast-text');
            const toastIcon = document.getElementById('toast-icon');
            
            toastText.textContent = 'Request submitted successfully! Redirecting to login...';
            toastIcon.className = 'fas fa-check-circle';
            toast.style.background = '#28a745';
            
            toast.classList.add('show');

            setTimeout(function() {
                window.location.href = 'signin.php';
            }, 3000); 

            const newUrl = window.location.pathname;
            window.history.replaceState({}, document.title, newUrl);
        }
    }

    function checkErrorMessage() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('error')) {
            const errorType = urlParams.get('error');
            if (errorType === 'email_taken') {
                const emailError = document.getElementById('email-error');
                emailError.style.display = 'block';
                emailError.textContent = 'Email is already taken. Please use a different email.';
            }
        }
    }

    window.onload = () => {
        checkSuccessMessage();
        checkErrorMessage();
    };