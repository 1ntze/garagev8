// Header muda de cor ao descer a página

const header = document.getElementById("header");

window.addEventListener("scroll", () => {

    if(window.scrollY > 50){

        header.classList.add("scrolled");

    }else{

        header.classList.remove("scrolled");

    }

});

// Trocar Slider
const slides = document.querySelectorAll(".slide");

let current = 0;

function trocarSlide(){

    slides[current].classList.remove("active");

    current++;

    if(current >= slides.length){
        current = 0;
    }

    slides[current].classList.add("active");

}

setInterval(trocarSlide, 5000);


