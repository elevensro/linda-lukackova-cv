(() => {
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  if (motion.matches) return;

  const portrait = document.querySelector('.portrait');
  const surname = document.querySelector('.last-name');
  const firstName = document.querySelector('.first-name');
  let portraitMoves = [];
  function positionPortrait() {
    const gap = parseFloat(getComputedStyle(portrait.parentElement).gap) || 0;
    portrait.style.transform = `translateX(-${firstName.offsetWidth + surname.offsetWidth + 2 * gap}px)`;
    firstName.style.transform = `translateX(${portrait.offsetWidth + gap}px)`;
    surname.style.transform = `translateX(${portrait.offsetWidth + gap}px)`;
  }
  positionPortrait();
  const photo = portrait.querySelector('img');
  const showPortrait = () => {
    if (!motion.matches) portrait.classList.add('portrait-enter');
  };
  if (photo.complete) showPortrait();
  else photo.addEventListener('load', showPortrait, { once: true });

  const headings = [...document.querySelectorAll('.first-name, .last-name, .profession, .tagline')];
  const sequences = headings.map((heading) => {
    const original = heading.textContent;
    heading.setAttribute('aria-label', original);
    const characters = Array.from(original, (character) => {
      const span = document.createElement('span');
      span.className = 'typing-character';
      span.setAttribute('aria-hidden', 'true');
      span.textContent = character;
      return span;
    });
    heading.replaceChildren(...characters);
    return characters;
  });

  const sections = [...document.querySelectorAll('.columns > section, .columns > .right > section')];
  let observer;
  const reveal = (section) => {
    section.classList.add('is-visible');
    observer?.unobserve(section); // Once revealed, never hide on upward scrolling.
  };
  if ('IntersectionObserver' in window) {
    observer = new IntersectionObserver((entries) => {
      for (const entry of entries) if (entry.isIntersecting) reveal(entry.target);
    }, { threshold: 0.08, rootMargin: '0px 0px -35px 0px' });
    sections.forEach((section) => {
      section.classList.add('reveal', section.classList.contains('left') ? 'reveal-left' : 'reveal-right');
      observer.observe(section);
      section.addEventListener('focusin', () => reveal(section));
    });
  }

  let skip = false;
  const finish = () => {
    skip = true;
    portrait.classList.remove('portrait-enter');
    portraitMoves.forEach(animation => animation.cancel());
    portrait.style.transform = '';
    firstName.style.transform = '';
    surname.style.transform = '';
    sequences.flat().forEach((span) => span.classList.add('is-typed'));
    sections.forEach(reveal);
    observer?.disconnect();
  };
  motion.addEventListener('change', (event) => { if (event.matches) finish(); });
  window.addEventListener('beforeprint', finish);
  const pause = (duration) => new Promise((resolve) => setTimeout(resolve, duration));
  async function type() {
    await Promise.race([document.fonts.ready, pause(800)]);
    positionPortrait();
    await pause(180);
    for (const [index, characters] of sequences.entries()) {
      for (const character of characters) {
        if (skip) return;
        character.classList.add('is-typed');
        await pause(character.textContent === ' ' ? 30 : 55);
      }
      await pause(170);
      if (index === 1 && !skip) {
        portraitMoves = [portrait, firstName, surname].map(element => element.animate(
          [{transform: element.style.transform}, {transform: 'translateX(0)'}],
          {duration: 1100, easing: 'cubic-bezier(.22,1,.36,1)', fill: 'forwards'}
        ));
        await Promise.all(portraitMoves.map(animation => animation.finished.catch(() => {})));
        portrait.style.transform = '';
        firstName.style.transform = '';
        surname.style.transform = '';
        portraitMoves.forEach(animation => animation.cancel());
      }
    }
  }
  type().catch(finish);
})();
