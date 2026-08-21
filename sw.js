const CACHE='clevedon-brewery-v4'
const APP_SHELL=['/','/index.html','/manifest.webmanifest']
async function buildIndex(response){
  const html=await response.text()
  const [ipa,bs21,logo]=await Promise.all([fetch('/assets/ipa.b64').then(r=>r.text()),fetch('/assets/bs21.b64').then(r=>r.text()),fetch('/assets/logo.b64').then(r=>r.text())])
  const out=html
    .replace('<div class="beer-art" aria-label="IPA artwork"></div>','<img class="beer-art" alt="IPA artwork" src="data:image/jpeg;base64,'+ipa+'">')
    .replace('<div class="beer-art" aria-label="BS21 artwork"></div>','<img class="beer-art" alt="BS21 artwork" src="data:image/jpeg;base64,'+bs21+'">')
    .replace('<div class="badge">CLEVEDON</div>','<div class="badge" style="padding:6px 12px"><img alt="Clevedon Brewery" src="data:image/jpeg;base64,'+logo+'" style="display:block;width:150px;height:auto;max-width:60vw;border-radius:6px"></div>')
  return new Response(out,{headers:{'Content-Type':'text/html; charset=utf-8'}})
}
self.addEventListener('install',event=>{event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(APP_SHELL)));self.skipWaiting()})
self.addEventListener('activate',event=>{event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(key=>key!==CACHE).map(key=>caches.delete(key)))).then(()=>self.clients.claim()))})
self.addEventListener('fetch',event=>{if(event.request.method!=='GET')return;event.respondWith((async()=>{const url=new URL(event.request.url);if(url.pathname==='/'||url.pathname==='/index.html'){try{const response=await fetch(event.request);const transformed=await buildIndex(response);const cache=await caches.open(CACHE);await cache.put(event.request,transformed.clone());return transformed}catch(e){return caches.match(event.request)}}const cached=await caches.match(event.request);if(cached)return cached;try{const response=await fetch(event.request);const copy=response.clone();caches.open(CACHE).then(cache=>cache.put(event.request,copy));return response}catch(e){return caches.match('/')}})())})
