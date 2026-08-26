document.addEventListener("DOMContentLoaded",()=>{
    if("serviceWorker" in navigator){
        window.addEventListener("load",()=>navigator.serviceWorker.register("sw.js").catch(error=>console.error("Brewery PWA service worker registration failed:",error)));
    }
});
