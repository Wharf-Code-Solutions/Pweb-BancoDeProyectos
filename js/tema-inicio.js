// Aplica el tema (claro/oscuro) y el tamaño de letra antes de pintar la página,
// para que no parpadee. Se carga en el <head> sin defer. Lo que el usuario elige
// se guarda en este navegador (localStorage); la primera vez se sigue el tema
// del sistema operativo.
(function () {
  var d = document.documentElement;
  var tema = null;
  var letra = null;
  try {
    tema = localStorage.getItem('bp-tema');
    letra = localStorage.getItem('bp-letra');
  } catch (e) { /* almacenamiento bloqueado: se usan los valores por omisión */ }
  if (tema !== 'claro' && tema !== 'oscuro') {
    tema = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'oscuro' : 'claro';
  }
  d.setAttribute('data-tema', tema);
  d.setAttribute('data-bs-theme', tema === 'oscuro' ? 'dark' : 'light');
  if (letra === 'grande' || letra === 'mayor') d.setAttribute('data-letra', letra);
})();
