// Offerings carousel (landing page, part details): arrows scroll one "page" of items
// and hide when everything already fits. Call updateOfferingArrows() after showing a hidden carousel.
const offeringCarousels = document.querySelectorAll('.offerings-carousel');

function updateOfferingArrows() {
    offeringCarousels.forEach((carousel) => {
        const track = carousel.querySelector('.offerings-track');
        const maxScroll = track.scrollWidth - track.clientWidth;
        const scrollable = maxScroll > 1;

        carousel.classList.toggle('is-scrollable', scrollable);
        carousel.querySelector('.offerings-arrow-prev').disabled = track.scrollLeft <= 1;
        carousel.querySelector('.offerings-arrow-next').disabled = track.scrollLeft >= maxScroll - 1;
    });
}

offeringCarousels.forEach((carousel) => {
    const track = carousel.querySelector('.offerings-track');

    carousel.querySelectorAll('[data-scroll]').forEach((arrow) => {
        arrow.addEventListener('click', () => {
            const direction = Number(arrow.getAttribute('data-scroll'));
            track.scrollBy({ left: direction * track.clientWidth, behavior: 'smooth' });
        });
    });

    track.addEventListener('scroll', updateOfferingArrows, { passive: true });
});

window.addEventListener('resize', updateOfferingArrows);
window.addEventListener('load', updateOfferingArrows);
updateOfferingArrows();
