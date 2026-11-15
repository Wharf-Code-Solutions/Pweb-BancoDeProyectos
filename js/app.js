// Comportamiento común de la aplicación.
document.addEventListener('DOMContentLoaded', () => {
  // Botones con data-confirmar="mensaje" piden confirmación antes de enviar.
  document.querySelectorAll('[data-confirmar]').forEach((el) => {
    el.addEventListener('click', (ev) => {
      if (!window.confirm(el.dataset.confirmar)) ev.preventDefault();
    });
  });

  // Botón de copiar folio.
  document.querySelectorAll('[data-copiar]').forEach((b) => {
    b.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(b.dataset.copiar);
        const i = b.querySelector('.bi');
        if (i) { i.className = 'bi bi-check-lg'; setTimeout(() => { i.className = 'bi bi-copy'; }, 1500); }
      } catch (e) { /* sin permiso de portapapeles: no pasa nada */ }
    });
  });
});
