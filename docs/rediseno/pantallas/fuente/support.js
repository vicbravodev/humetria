// Minimal preview shim for .dc.html boards (local QA only)
window.DCLogic = class { constructor(props){ this.props = props || {}; this.state = {}; } setState(p){ Object.assign(this.state, p); window.__rerender && window.__rerender(); } };
(async function(){
  const src = await (await fetch(location.pathname)).text();
  const tpl = src.match(/<x-dc>([\s\S]*)<\/x-dc>/)[1];
  const scr = src.match(/<script type="text\/x-dc"[^>]*data-props='([^']*)'[^>]*>([\s\S]*?)<\/script>/);
  const propsDef = JSON.parse(scr[1].replace(/&#39;/g, "'"));
  const q = new URLSearchParams(location.search);
  const props = {};
  for (const k in propsDef) if (k[0] !== '$') props[k] = q.has(k) ? q.get(k) : propsDef[k].default;
  const Component = new Function('DCLogic', scr[2] + '\nreturn Component;')(window.DCLogic);
  const comp = new Component(props);
  const ev = (expr, scope) => { try { return new Function('s', 'with(s){return (' + expr + ')}')(scope); } catch(e) { console.log('ERR', expr, e.message); return ''; } };
  const sub = (str, scope) => str.replace(/\{\{([\s\S]+?)\}\}/g, (_, e) => { const v = ev(e.trim(), scope); return v == null ? '' : String(v); });
  function walk(node, scope, out) {
    for (const ch of Array.from(node.childNodes)) {
      if (ch.nodeType === 3) { out.appendChild(document.createTextNode(sub(ch.textContent, scope))); continue; }
      if (ch.nodeType !== 1) continue;
      const tag = ch.tagName.toLowerCase();
      if (tag === 'helmet') { const h = document.createElement('div'); h.innerHTML = ch.innerHTML; for (const x of Array.from(h.childNodes)) if (!document.head.querySelector('[data-hm]')) {} ; if (!window.__h) { document.head.insertAdjacentHTML('beforeend', ch.innerHTML); window.__h = 1; } continue; }
      if (tag === 'sc-if') { const m = (ch.getAttribute('value')||'').match(/\{\{([\s\S]+?)\}\}/); if (m && ev(m[1].trim(), scope)) walk(ch, scope, out); continue; }
      if (tag === 'sc-for') { const m = ch.getAttribute('list').match(/\{\{([\s\S]+?)\}\}/); const as = ch.getAttribute('as'); const arr = ev(m[1].trim(), scope) || []; arr.forEach((it) => { const sc = Object.create(scope); sc[as] = it; walk(ch, sc, out); }); continue; }
      const el = document.createElementNS(ch.namespaceURI, ch.localName);
      for (const a of Array.from(ch.attributes)) {
        const m = a.value.match(/^\{\{([\s\S]+?)\}\}$/);
        if (/^on/i.test(a.name) && m) { const fn = ev(m[1].trim(), scope); if (typeof fn === 'function') el.addEventListener(a.name.slice(2).toLowerCase(), fn); continue; }
        const v = sub(a.value, scope);
        if (['disabled','checked'].includes(a.name) && (v === 'false' || v === '')) continue;
        el.setAttribute(a.name, v);
      }
      walk(ch, scope, el); out.appendChild(el);
    }
  }
  const parsed = new DOMParser().parseFromString('<body>' + tpl.replace(/<(sc-for|sc-if)/g, '<template data-x="$1"').replace(/<\/(sc-for|sc-if)>/g, '</template>') + '</body>', 'text/html');
  // convert templates back to elements so table content survives
  function untemplate(n) { for (const t of Array.from(n.querySelectorAll('template[data-x]'))) {} }
  const root = document.createElement('div');
  function conv(node) { for (const ch of Array.from(node.childNodes)) { if (ch.nodeType===1 && ch.tagName==='TEMPLATE' && ch.dataset.x) { const e = document.createElement(ch.dataset.x); for (const a of Array.from(ch.attributes)) if (a.name!=='data-x') e.setAttribute(a.name,a.value); e.appendChild(ch.content.cloneNode(true)); conv(e); node.replaceChild(e, ch); } else if (ch.nodeType===1) { if (ch.content) conv(ch.content); conv(ch); } } }
  conv(parsed.body);
  window.__rerender = () => { const vals = comp.renderVals(); const scope = Object.assign(Object.create(null), vals); document.body.innerHTML = ''; const frag = document.createElement('div'); walk(parsed.body, scope, frag); while (frag.firstChild) document.body.appendChild(frag.firstChild); };
  window.__rerender(); window.__ready = 1;
})();
