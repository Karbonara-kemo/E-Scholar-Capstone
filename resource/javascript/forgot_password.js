    document.querySelector('.forgot-password-form').addEventListener('submit', function(e) {
        const emailInput = document.getElementById('email');
        const emailError = document.getElementById('email-error');
        if (!emailInput.value.trim()) {
            emailError.textContent = "Email is required.";
            emailError.style.display = "block";
            e.preventDefault();
        } else {
            emailError.textContent = "";
            emailError.style.display = "none";
        }
    });