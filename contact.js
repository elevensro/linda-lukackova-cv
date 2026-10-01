(() => {
  const form = document.querySelector('#contact-form');
  const status = document.querySelector('#form-status');
  const button = form.querySelector('button');
  let challenge;
  let pending;
  const inform = (message, state = '') => {
    status.textContent = message;
    status.dataset.state = state;
  };
  async function prepare() {
    if (challenge) return challenge;
    if (!pending) pending = fetch('contact.php', {credentials: 'same-origin', cache: 'no-store'})
      .then(async response => {
        if (!response.ok) throw new Error('The contact form is temporarily unavailable. Please email linda@e11.consulting.');
        const data = await response.json();
        if (!data.token || !data.challenge) throw new Error('The contact form is unavailable in this static preview.');
        challenge = data;
        challenge.notBefore = Date.now() + 2100;
        return data;
      }).finally(() => { pending = null; });
    return pending;
  }
  form.addEventListener('focusin', () => prepare().catch(() => {}), {once: true});
  // A small, invisible computational challenge adds friction to automated spam.
  async function proof(value) {
    if (!window.crypto?.subtle) throw new Error('Please use an up-to-date browser or email linda@e11.consulting.');
    const encoder = new TextEncoder();
    for (let nonce = 0; nonce < 1000000; nonce++) {
      const digest = new Uint8Array(await crypto.subtle.digest('SHA-256', encoder.encode(value + ':' + nonce)));
      if (digest[0] === 0 && digest[1] < 16) return String(nonce);
    }
    throw new Error('Security verification failed. Please try again.');
  }
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    button.disabled = true;
    inform('Verifying and sending…');
    try {
      const data = await prepare();
      const payload = new FormData(form);
      payload.set('token', data.token);
      payload.set('nonce', await proof(data.challenge));
      const remaining = data.notBefore - Date.now();
      if (remaining > 0) await new Promise(resolve => setTimeout(resolve, remaining));
      const response = await fetch('contact.php', {method: 'POST', body: payload, credentials: 'same-origin'});
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.message || 'Your message could not be sent. Please email linda@e11.consulting.');
      form.reset();
      inform(result.message, 'success');
    } catch (error) {
      inform(error.message || 'Your message could not be sent. Please try again.', 'error');
    } finally {
      challenge = null;
      button.disabled = false;
    }
  });
  document.querySelectorAll('a[href="#privacy"]').forEach(link => link.addEventListener('click', () => {
    document.querySelector('#privacy').open = true;
  }));
})();
