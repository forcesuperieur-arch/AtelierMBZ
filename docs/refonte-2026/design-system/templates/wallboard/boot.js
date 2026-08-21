/* Charge le design system Paddock puis les écrans de ce template.
   Un template est copié tel quel dans un projet consommateur : la seule ligne à
   changer est `base`, qui doit pointer sur la racine du design system (ici la
   racine du projet ; dans un projet consommateur, `_ds/<dossier>`). */
(() => {
  if (window.__paddockBooting) return;
  window.__paddockBooting = true;
  const base = '../..';
  const add = (tag, attrs) => { const el = document.createElement(tag); Object.assign(el, attrs); document.head.appendChild(el); return el; };

  for (const p of ['styles.css']) add('link', { rel: 'stylesheet', href: base + '/' + p });
  add('link', { rel: 'stylesheet', href: 'https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css' });

  const load = (src, extra) => new Promise((ok, ko) => {
    const s = add('script', Object.assign({ src: src, onload: ok, onerror: () => ko(new Error(src)) }, extra || {}));
    return s;
  });

  const SCREENS = [
    "WallboardScreen.jsx"
  ];

  (async () => {
    try {
      if (!window.React) {
        await load('https://unpkg.com/react@18.3.1/umd/react.development.js');
        await load('https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js');
      }
      await load(base + '/_ds_bundle.js').catch(() => console.error('ds-base : _ds_bundle.js introuvable sous ' + base + ' — dans un projet consommateur, faites pointer `base` sur _ds/<dossier>'));
      if (!window.Babel) await load('https://unpkg.com/@babel/standalone@7.29.0/babel.min.js');
      const sources = await Promise.all(SCREENS.map((f) => fetch(new URL(f, document.baseURI)).then((r) => r.text())));
      for (const src of sources) (0, eval)(window.Babel.transform(src, { presets: ['react'] }).code);
      window.__paddockReady = true;
      document.dispatchEvent(new CustomEvent('paddock-ready'));
    } catch (e) { console.error('boot.js : ' + e.message); }
  })();
})();
