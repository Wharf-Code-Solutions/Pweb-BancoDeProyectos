// Comportamiento común de la aplicación.
document.addEventListener('DOMContentLoaded', () => {
  // Botones con data-confirmar="mensaje" piden confirmación antes de enviar.
  document.querySelectorAll('[data-confirmar]').forEach((el) => {
    el.addEventListener('click', (ev) => {
      if (!window.confirm(el.dataset.confirmar)) ev.preventDefault();
    });
  });
});
