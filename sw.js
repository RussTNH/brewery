const CACHE='clevedon-brewery-v10'
const APP_SHELL=['/','/index.html','/manifest.webmanifest']

async function buildIndex(response){
  const html=await response.text()
  const [ipa,bs21,logo]=await Promise.all([
    fetch('/assets/ipa.b64?v=10',{cache:'no-store'}).then(r=>r.text()),
    fetch('/assets/bs21.b64?v=10',{cache:'no-store'}).then(r=>r.text()),
    fetch('/assets/logo.b64?v=10',{cache:'no-store'}).then(r=>r.text())
  ])
  const out=html
    .replace(/<img class="beer-art" alt="Clevedon Brewery IPA artwork"[^>]*>/,'<img class="beer-art" alt="Clevedon Brewery IPA artwork" src="data:image/webp;base64,'+ipa.trim()+'">')
    .replace(/<img class="beer-art" alt="Clevedon Brewery BS21 artwork"[^>]*>/,'<img class="beer-art" alt="Clevedon Brewery BS21 artwork" src="data:image/jpeg;base64,'+bs21.trim()+'">')
    .replace('<div class="badge">CLEVEDON</div>','<div class="badge" style="padding:6px 12px"><img alt="Clevedon Brewery" src="data:image/jpeg;base64,'+logo.trim()+'" style="display:block;width:150px;height:auto;max-width:60vw;border-radius:6px"></div>')
  return new Response(out,{headers:{'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-store'}})
}

self.addEventListener('install',event=>{event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(APP_SHELL)).then(()=>self.skipWaiting()))})
self.addEventListener('activate',event=>{event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(key=>key!==CACHE).map(key=>caches.delete(key)))).then(()=>self.clients.claim()))})
self.addEventListener('fetch',event=>{
  if(event.request.method!=='GET')return
  const url=new URL(event.request.url)
  if(url.pathname==='/'||url.pathname==='/index.html'){
    event.respondWith((async()=>{
      try{
        const response=await fetch(event.request)
        const transformed=await buildIndex(response)
        const cache=await caches.open(CACHE)
        await cache.put(new Request('/index.html'),transformed.clone())
        return transformed
      }catch(e){return caches.match('/index.html')}
    })())
  }
})
