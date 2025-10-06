    function previewProfilePic(event) {
        const input = event.target;
        const preview = document.getElementById('profilePic');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }