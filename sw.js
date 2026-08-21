const CACHE='clevedon-brewery-v7'
const APP_SHELL=['/','/index.html','/manifest.webmanifest']

async function base64Image(path,mime){
  const text=await fetch(path+'?v=7',{cache:'no-store'}).then(r=>r.text())
  const clean=text.trim()
  const binary=atob(clean)
  const bytes=new Uint8Array(binary.length)
  for(let i=0;i<binary.length;i++) bytes[i]=binary.charCodeAt(i)
  return new Response(bytes,{headers:{'Content-Type':mime,'Cache-Control':'no-store'}})
}

async function buildIndex(response){
  const html=await response.text()
  const [ipa,logo]=await Promise.all([
    fetch('/assets/ipa.b64?v=7',{cache:'no-store'}).then(r=>r.text()),
    fetch('/assets/logo.b64?v=7',{cache:'no-store'}).then(r=>r.text())
  ])
  const out=html
    .replace(/<img class="beer-art" alt="Clevedon Brewery IPA artwork"[^>]*>/,'<img class="beer-art" alt="Clevedon Brewery IPA artwork" src="data:image/webp;base64,'+ipa.trim()+'">')
    .replace(/<img class="beer-art" alt="Clevedon Brewery BS21 artwork"[^>]*>/,'<img class="beer-art" alt="Clevedon Brewery BS21 artwork" src="/assets/bs21.jpg?v=7">')
    .replace('<div class="badge">CLEVEDON</div>','<div class="badge" style="padding:6px 12px"><img alt="Clevedon Brewery" src="data:image/jpeg;base64,'+logo.trim()+'" style="display:block;width:150px;height:auto;max-width:60vw;border-radius:6px"></div>')
  return new Response(out,{headers:{'Content-Type':'text/html; charset=utf-8'}})
}

self.addEventListener('install',event=>{event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(APP_SHELL)).then(()=>self.skipWaiting()))})
self.addEventListener('activate',event=>{event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(key=>key!==CACHE).map(key=>caches.delete(key)))).then(()=>self.clients.claim()))})
self.addEventListener('fetch',event=>{
  if(event.request.method!=='GET')return
  event.respondWith((async()=>{
    const url=new URL(event.request.url)
    if(url.pathname==='/assets/bs21.jpg'){
      try{return await base64Image('/assets/bs21.b64','image/jpeg')}catch(e){return new Response('',{status:404})}
    }
    if(url.pathname==='/'||url.pathname==='/index.html'){
      try{
        const response=await fetch(event.request)
        const transformed=await buildIndex(response)
        const cache=await caches.open(CACHE)
        await cache.put(event.request,transformed.clone())
        return transformed
      }catch(e){return caches.match(event.request)}
    }
    const cached=await caches.match(event.request)
    if(cached)return cached
    try{
      const response=await fetch(event.request)
      const copy=response.clone()
      caches.open(CACHE).then(cache=>cache.put(event.request,copy))
      return response
    }catch(e){return caches.match('/')}
  })())
})
