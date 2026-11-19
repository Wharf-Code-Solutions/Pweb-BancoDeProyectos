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

// Apariencia: modo oscuro y tamaño de letra (js/tema-inicio.js ya aplicó lo guardado al cargar).
(() => {
  const d = document.documentElement;
  const guardar = (clave, valor) => { try { localStorage.setItem(clave, valor); } catch (e) { /* sin almacenamiento */ } };

  const pintarTema = () => {
    const oscuro = d.dataset.tema === 'oscuro';
    document.querySelectorAll('[data-tema-interruptor]').forEach((c) => { c.checked = oscuro; });
    document.querySelectorAll('[data-tema-alternar]').forEach((b) => {
      b.setAttribute('aria-pressed', oscuro ? 'true' : 'false');
      b.setAttribute('aria-label', oscuro ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
      b.title = b.getAttribute('aria-label');
      const i = b.querySelector('.bi');
      if (i) i.className = oscuro ? 'bi bi-sun' : 'bi bi-moon';
    });
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.content = oscuro ? '#161c28' : '#1b396a';
  };
  const ponerTema = (tema) => {
    d.dataset.tema = tema;
    d.setAttribute('data-bs-theme', tema === 'oscuro' ? 'dark' : 'light');
    guardar('bp-tema', tema);
    pintarTema();
  };

  const pintarLetra = () => {
    const actual = d.dataset.letra || 'normal';
    document.querySelectorAll('.selector-letra [data-letra]').forEach((b) => {
      b.setAttribute('aria-pressed', b.dataset.letra === actual ? 'true' : 'false');
    });
  };
  const ponerLetra = (letra) => {
    if (letra === 'normal') delete d.dataset.letra; else d.dataset.letra = letra;
    guardar('bp-letra', letra);
    pintarLetra();
  };

  document.addEventListener('DOMContentLoaded', () => {
    pintarTema();
    pintarLetra();
    document.querySelectorAll('.selector-letra [data-letra]').forEach((b) => {
      b.addEventListener('click', () => ponerLetra(b.dataset.letra));
    });
    document.querySelectorAll('[data-tema-interruptor]').forEach((c) => {
      c.addEventListener('change', () => ponerTema(c.checked ? 'oscuro' : 'claro'));
    });
    document.querySelectorAll('[data-tema-alternar]').forEach((b) => {
      b.addEventListener('click', () => ponerTema(d.dataset.tema === 'oscuro' ? 'claro' : 'oscuro'));
    });
  });
})();
