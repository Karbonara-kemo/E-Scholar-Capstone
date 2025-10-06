    document.addEventListener('DOMContentLoaded', function() {
        const codeInputs = document.querySelectorAll('.code-box');
        
        codeInputs[0].focus();
        
        codeInputs.forEach((input, index) => {
            input.addEventListener('input', function() {
                if (this.value.length === 1) {
                    if (index < codeInputs.length - 1) {
                        codeInputs[index + 1].focus();
                    }
                }
            });
            
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && this.value.length === 0) {
                    if (index > 0) {
                        codeInputs[index - 1].focus();
                    }
                }
            });

            input.addEventListener('keypress', function(e) {
                if (!/[0-9]/.test(e.key)) {
                    e.preventDefault();
                }
            });

            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text').slice(0, 1);
                if (/[0-9]/.test(pastedData)) {
                    this.value = pastedData;

                    const event = new Event('input');
                    this.dispatchEvent(event);
                }
            });
        });
    });