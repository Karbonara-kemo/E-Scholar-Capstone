    let slideIndex = 0;
    const slides = document.getElementsByClassName("slide");

    function showSlides() {
        for (let i = 0; i < slides.length; i++) {
            slides[i].classList.remove("active");
        }
        slides[slideIndex].classList.add("active");
        slideIndex++;
        if (slideIndex >= slides.length) {
            slideIndex = 0;
        }
        setTimeout(showSlides, 5000);
    }

    window.onload = function() {
        showSlides();
    };

    document.querySelectorAll('.right-nav-text').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href.startsWith('#')) {
                    e.preventDefault();
                    const targetElement = document.querySelector(href);
                    if (targetElement) {
                        targetElement.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }
            });
        });