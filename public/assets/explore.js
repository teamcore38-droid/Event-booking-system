const controls = document.querySelector('.slide-controls');
if (controls) {
    const slides = [...document.querySelectorAll('[data-slide]')];
    const current = controls.querySelector('[data-current]');
    let index = 0;
    controls.hidden = false;
    controls.addEventListener('click', event => {
        const button = event.target.closest('[data-step]');
        if (!button) return;
        slides[index].hidden = true;
        index = (index + Number(button.dataset.step) + slides.length) % slides.length;
        slides[index].hidden = false;
        current.textContent = String(index + 1).padStart(2, '0');
        current.parentElement.setAttribute('aria-label', slides[index].querySelector('h1').textContent);
    });
}
const sorting = document.querySelector('.sort');
sorting.querySelector('button').hidden = true;
sorting.querySelector('select').addEventListener('change', () => sorting.requestSubmit());